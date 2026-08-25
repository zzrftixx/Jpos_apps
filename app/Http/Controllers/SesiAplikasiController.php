<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Penanda "jendela aplikasi masih terbuka", dipakai JPOS.exe untuk tahu kapan server
 * boleh dimatikan.
 *
 * Jendela kasir dibuka di browser sebagai proses TERPISAH dari JPOS.exe, jadi launcher
 * tidak punya cara langsung untuk tahu jendelanya sudah ditutup. Halaman karena itu
 * mengirim detak berkala; begitu detaknya berhenti, berarti tidak ada lagi jendela yang
 * terbuka dan server bisa dimatikan.
 *
 * Sengaja TIDAK memerlukan login: halaman login pun harus bisa menahan server tetap
 * hidup selagi kasir mengetik kata sandi. Yang dicatat hanya waktu, tidak ada data apa
 * pun yang dibaca atau ditulis ke database.
 */
class SesiAplikasiController extends Controller
{
    public const BERKAS_DETAK = 'jpos-detak';
    public const BERKAS_TUTUP = 'jpos-tutup';

    /** Dipanggil berkala selama jendela aplikasi terbuka. */
    public function detak(): Response
    {
        $this->sentuh(self::BERKAS_DETAK);

        // Sinyal tutup sebelumnya dibatalkan: halaman baru saja hidup lagi. Ini yang
        // membuat berpindah halaman (yang juga memicu sinyal tutup) tidak ikut mematikan
        // server.
        $this->hapus(self::BERKAS_TUTUP);

        return response('ok')->header('Cache-Control', 'no-store');
    }

    /**
     * Dipanggil saat jendela ditutup ATAU saat berpindah halaman.
     *
     * Keduanya tidak bisa dibedakan dari sisi browser, jadi ini hanya "kemungkinan
     * ditutup". Launcher menunggu sebentar; kalau detak berikutnya datang berarti tadi
     * cuma pindah halaman dan server tetap hidup.
     */
    public function tutup(): Response
    {
        $this->sentuh(self::BERKAS_TUTUP);

        return response('ok')->header('Cache-Control', 'no-store');
    }

    private function berkas(string $nama): string
    {
        return storage_path('framework/' . $nama);
    }

    private function sentuh(string $nama): void
    {
        $path = $this->berkas($nama);

        try {
            if (! is_dir(dirname($path))) {
                @mkdir(dirname($path), 0755, true);
            }

            // Ditulis, bukan touch(), supaya mtime pasti berubah di semua sistem berkas.
            @file_put_contents($path, (string) time());
        } catch (\Throwable) {
            // Penanda ini hanya alat bantu; kegagalannya tidak boleh mengganggu aplikasi.
        }
    }

    private function hapus(string $nama): void
    {
        $path = $this->berkas($nama);

        if (is_file($path)) {
            @unlink($path);
        }
    }
}
