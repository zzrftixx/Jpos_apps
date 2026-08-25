<?php

namespace App\Support;

/**
 * Memutuskan apakah cache config/route/view/event masih sesuai, atau perlu dibangun ulang.
 *
 * KENAPA ADA: `jpos:prepare` dijalankan JPOS.exe setiap kali aplikasi dibuka, dan kasir
 * menunggu sepanjang itu sebelum layar pertama muncul. Versi sebelumnya membangun ulang
 * keempat cache di SETIAP start. Diukur pada paket 2.2.0:
 *
 *     selalu bangun ulang        rata-rata 6.086 ms
 *     bangun ulang bila perlu    rata-rata 1.074 ms
 *
 * `view:cache` sendirian menghabiskan 5 detik untuk mengompilasi ulang 86 berkas Blade yang
 * isinya sama persis dengan kompilasi sebelumnya.
 *
 * Alasan asli membangun ulang tiap start tetap sahih, cuma terlalu luas: APP_URL di .env
 * berubah mengikuti port yang tersedia, jadi config cache lama bisa menunjuk port yang
 * salah. Itu alasan untuk membangun ulang KETIKA PORTNYA BERUBAH, bukan setiap kali.
 *
 * MELOMPAT TERLALU RAJIN JAUH LEBIH BERBAHAYA DARIPADA LAMBAT: aplikasi akan berjalan
 * dengan rute atau tampilan versi lama setelah diperbarui, dan pemiliknya akan melaporkan
 * "fiturnya tidak muncul" padahal kodenya sudah ada di sana. Karena itu sidik jarinya
 * memuat semua yang bisa membuat cache basi, dan tiap komponennya diuji terpisah.
 */
class PenandaCache
{
    /** Cache yang keberadaannya wajib diperiksa - penanda yang cocok tidak menjamin berkasnya ada. */
    private const BERKAS_CACHE = ['config.php', 'routes-v7.php', 'events.php'];

    /** Folder yang isinya menentukan sah-tidaknya cache. */
    private const FOLDER_DIPANTAU = ['config', 'routes', 'resources/views', 'app'];

    /**
     * Akar aplikasi yang diperiksa. Bisa diarahkan ke folder lain supaya test menguji
     * keputusannya di atas pohon berkas tiruan - menulis cache Laravel sungguhan dari
     * dalam test akan merusak lingkungan yang sedang dipakai.
     */
    private string $akar;

    public function __construct(?string $akar = null)
    {
        $this->akar = rtrim($akar ?? base_path(), '\/');
    }

    private function path(string $relatif): string
    {
        return $this->akar . DIRECTORY_SEPARATOR . ltrim($relatif, '\/');
    }

    public function berkasPenanda(): string
    {
        return $this->path('bootstrap/cache/jpos-cache-stamp');
    }

    /**
     * Cache boleh dipakai apa adanya?
     *
     * Dua syarat, dan keduanya harus terpenuhi: berkas cachenya benar-benar ada, DAN
     * sidik jarinya sama dengan saat cache itu dibangun.
     */
    public function masihSesuai(): bool
    {
        foreach (self::BERKAS_CACHE as $berkas) {
            if (! is_file($this->path('bootstrap/cache/' . $berkas))) {
                return false;
            }
        }

        $penanda = $this->berkasPenanda();

        if (! is_file($penanda)) {
            return false;
        }

        return trim((string) @file_get_contents($penanda)) === $this->sidikJari();
    }

    public function perbarui(): void
    {
        @file_put_contents($this->berkasPenanda(), $this->sidikJari());
    }

    public function hapus(): void
    {
        @unlink($this->berkasPenanda());
    }

    /**
     * Sidik jari yang berubah persis ketika salah satu cache jadi basi.
     *
     *   - isi berkas VERSION      -> paket update selalu menaikkannya
     *   - APP_URL                 -> port berpindah
     *   - waktu ubah .env         -> pengaturan lain diubah tangan
     *   - waktu ubah terbaru di config/, routes/, resources/views/, app/
     *
     * Yang terakhir membuat ini tetap benar walau versinya lupa dinaikkan. Menelusuri ISI
     * seluruh berkas akan lebih mahal daripada cache yang dihemat, jadi yang dipakai waktu
     * ubahnya - cukup untuk membedakan "kode berubah" dari "kode sama".
     */
    public function sidikJari(): string
    {
        $versi = $this->path('VERSION');
        $env = $this->path('.env');

        $bagian = [
            is_file($versi) ? trim((string) @file_get_contents($versi)) : '',
            (string) config('app.url'),
            is_file($env) ? (string) @filemtime($env) : '',
        ];

        foreach (self::FOLDER_DIPANTAU as $folder) {
            $bagian[] = $folder . ':' . $this->waktuUbahTerbaru($this->path($folder));
        }

        return md5(implode('|', $bagian));
    }

    private function waktuUbahTerbaru(string $folder): int
    {
        if (! is_dir($folder)) {
            return 0;
        }

        $terbaru = 0;

        $isi = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($isi as $satu) {
            if ($satu->isFile()) {
                $terbaru = max($terbaru, (int) $satu->getMTime());
            }
        }

        return $terbaru;
    }
}
