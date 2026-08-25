<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Penyimpanan gambar upload yang tidak pernah menjatuhkan server.
 *
 * Versi sebelumnya memanggil imagecreatefromstring() langsung terhadap file apa pun
 * yang lolos validasi 'image|max:25600'. GD mendekode gambar menjadi bitmap mentah
 * seukuran lebar x tinggi x 4 byte, jadi satu foto beresolusi sangat tinggi bisa minta
 * ratusan MB sekaligus dan menembus memory_limit. Itu menghasilkan FATAL ERROR (bukan
 * exception), sehingga try/catch di sekitarnya tidak berguna dan request berakhir 500.
 *
 * Pertahanannya berlapis:
 *   1. imageRules() menolak dimensi ekstrem lewat validasi (pakai getimagesize, tanpa dekode).
 *   2. Anggaran memori dihitung dulu sebelum GD dipanggil; kalau tidak muat, file disimpan
 *      apa adanya tanpa optimasi - jelek sedikit, tapi tidak pernah mati.
 *   3. Resize memakai imagecopyresampled dan membebaskan bitmap sumber sesegera mungkin.
 *   4. Apa pun yang masih lolos ditangkap Throwable dan jatuh ke penyimpanan biasa.
 */
trait OptimizesUploadedImages
{
    /** Sisi terpanjang gambar setelah dioptimasi (piksel). */
    protected int $maxImageDimension = 1000;

    /**
     * Aturan validasi untuk field gambar.
     *
     * Batas ukuran file sengaja dipertahankan di 25 MB seperti sebelumnya supaya tidak ada
     * file yang tadinya diterima jadi ditolak. Yang baru hanya batas dimensi: itulah kelas
     * gambar yang bikin server mati, dan sekarang ditolak dengan pesan yang bisa dimengerti
     * kasir alih-alih menghasilkan HTTP 500.
     */
    protected function imageRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:25600',
            'dimensions:max_width=8000,max_height=8000',
        ];
    }

    /**
     * Simpan gambar dalam bentuk teroptimasi. Mengembalikan path relatif terhadap disk 'public'.
     */
    protected function saveOptimizedImage(UploadedFile $file, string $folder): string
    {
        try {
            $path = $this->encodeOptimizedImage($file, $folder);

            if ($path !== null) {
                return $path;
            }
        } catch (\Throwable) {
            // Sengaja ditelan: optimasi gambar tidak pernah boleh menggagalkan simpan produk.
        }

        return $file->store($folder, 'public');
    }

    /**
     * Mengembalikan null kalau gambar tidak bisa/tidak aman dioptimasi, supaya pemanggil
     * jatuh ke penyimpanan file apa adanya.
     */
    private function encodeOptimizedImage(UploadedFile $file, string $folder): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = $file->getRealPath();

        if ($source === false || ! is_readable($source)) {
            return null;
        }

        $info = @getimagesize($source);

        if ($info === false || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return null;
        }

        [$width, $height] = $info;

        // Naikkan plafon memori sementara khusus untuk proses dekode. Ini aplikasi desktop
        // satu pemakai, bukan web server bersama, jadi lonjakan sesaat aman - dan plafon
        // dikembalikan lagi di finally apa pun yang terjadi.
        $previousLimit = ini_get('memory_limit');
        @ini_set('memory_limit', '1024M');

        try {
            if (! $this->hasMemoryForImage($width, $height)) {
                return null;
            }

            $binary = @file_get_contents($source);

            if ($binary === false) {
                return null;
            }

            $image = @imagecreatefromstring($binary);
            unset($binary);

            if ($image === false) {
                return null;
            }

            try {
                $extension = $this->targetExtension($file);
                $image = $this->applyExifOrientation($image, $source, $info[2] ?? 0);
                $image = $this->resizeWithinBounds($image, $extension);

                return $this->writeImage($image, $extension, $folder);
            } finally {
                if ($image instanceof \GdImage) {
                    imagedestroy($image);
                }
            }
        } finally {
            @ini_set('memory_limit', $previousLimit);
        }
    }

    /**
     * Apakah sisa memori cukup untuk menampung bitmap sumber sekaligus bitmap hasil resize?
     */
    private function hasMemoryForImage(int $width, int $height): bool
    {
        $limit = $this->memoryLimitInBytes();

        if ($limit <= 0) {
            return true;
        }

        // 4 byte per piksel untuk truecolor, dikali 2 karena bitmap sumber dan hasil resize
        // sesaat hidup bersamaan, ditambah 25% kelonggaran untuk overhead internal GD.
        $needed = (int) ($width * $height * 4 * 2 * 1.25);
        $available = $limit - memory_get_usage(true);

        return $needed < $available;
    }

    private function memoryLimitInBytes(): int
    {
        $raw = trim((string) ini_get('memory_limit'));

        if ($raw === '' || $raw === '-1') {
            return 0;
        }

        $value = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Format keluaran mengikuti ekstensi asli bila didukung GD, selain itu jadi jpg.
     * Versi lama meloloskan 'webp' di sini tapi tetap memanggil imagejpeg(), jadi file
     * berekstensi .webp sebenarnya berisi data JPEG.
     */
    private function targetExtension(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'png') {
            return 'png';
        }

        if ($extension === 'webp' && function_exists('imagewebp')) {
            return 'webp';
        }

        return 'jpg';
    }

    private function applyExifOrientation(\GdImage $image, string $source, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        $degrees = match ((int) ($exif['Orientation'] ?? 0)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = @imagerotate($image, $degrees, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    private function resizeWithinBounds(\GdImage $image, string $extension): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $max = $this->maxImageDimension;

        if ($width <= $max && $height <= $max) {
            return $image;
        }

        $ratio = min($max / $width, $max / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $canvas = @imagecreatetruecolor($newWidth, $newHeight);

        if ($canvas === false) {
            return $image;
        }

        $this->prepareCanvas($canvas, $newWidth, $newHeight, $extension);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $canvas;
    }

    /**
     * PNG/WebP mempertahankan transparansi; JPEG tidak punya alpha, jadi dialasi putih
     * supaya area transparan tidak berubah jadi hitam.
     */
    private function prepareCanvas(\GdImage $canvas, int $width, int $height, string $extension): void
    {
        if ($extension === 'jpg') {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);

            return;
        }

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
    }

    private function writeImage(\GdImage $image, string $extension, string $folder): ?string
    {
        $path = $folder . '/' . \Illuminate\Support\Str::random(40) . '.' . $extension;
        $fullPath = storage_path('app/public/' . $path);
        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return null;
        }

        $written = match ($extension) {
            'png' => @imagepng($image, $fullPath, 7),
            'webp' => @imagewebp($image, $fullPath, 85),
            default => @imagejpeg($image, $fullPath, 85),
        };

        if ($written !== true || ! is_file($fullPath) || filesize($fullPath) === 0) {
            @unlink($fullPath);

            return null;
        }

        return $path;
    }
}
