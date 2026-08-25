<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyajikan gambar produk & logo toko dari storage/app/public.
 *
 * Dulu ini dua closure yang isinya sama persis di routes/web.php (/media/{path} dan
 * /storage/{path}).
 *
 * Perubahan yang benar-benar berdampak ada di header cache. Versi lama hanya
 * mengirim "Cache-Control: public" tanpa max-age, sehingga browser wajib bertanya
 * ulang ke server untuk SETIAP gambar di SETIAP kali halaman dibuka. Di server PHP
 * bawaan yang hanya melayani satu request pada satu waktu, halaman Kasir berisi
 * puluhan gambar produk berarti puluhan request yang saling mengantre. Nama file
 * gambar adalah string acak 40 karakter yang isinya tidak pernah berubah, jadi
 * aman di-cache lama dan permintaannya hilang sama sekali.
 *
 * Pemeriksaan path di resolve() bersifat berlapis (defense in depth): lewat server
 * PHP bawaan, permintaan berisi ".." memang sudah ditolak 403/404 sebelum sampai
 * ke sini, tapi itu perlindungan dari lapisan server - bukan dari kode ini sendiri.
 */
class MediaController extends Controller
{
    public function show(string $path): Response
    {
        $fullPath = $this->resolve($path);

        if ($fullPath === null) {
            return $this->placeholder();
        }

        $mime = @mime_content_type($fullPath) ?: 'image/jpeg';

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            // Gambar produk memakai nama file acak 40 karakter, jadi isinya tidak pernah
            // berubah untuk URL yang sama dan aman di-cache lama oleh browser.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Resolusi path yang aman.
     *
     * $path datang dari URL dan digabung apa adanya ke storage_path(). Tanpa
     * pemeriksaan ini, permintaan berisi segmen ".." bisa membaca file di luar folder
     * gambar - termasuk .env dan file database.
     */
    private function resolve(string $path): ?string
    {
        $root = realpath(storage_path('app/public'));

        if ($root === false) {
            return null;
        }

        $candidate = realpath($root . DIRECTORY_SEPARATOR . $path);

        if ($candidate === false || ! is_file($candidate)) {
            return null;
        }

        $normalisedRoot = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (! str_starts_with($candidate, $normalisedRoot)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Gambar pengganti supaya baris produk tanpa gambar tidak menampilkan ikon rusak.
     */
    private function placeholder(): Response
    {
        $noImage = public_path('images/no-image.svg');

        if (is_file($noImage)) {
            return response()->file($noImage, ['Content-Type' => 'image/svg+xml']);
        }

        abort(404);
    }
}
