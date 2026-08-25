<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * UAT penyajian aset statis.
 *
 * Server bawaan PHP tidak mengirim satu pun header cache, jadi peramban mengunduh ulang
 * seluruh aset di SETIAP perpindahan halaman. Diukur pada paket 2.2.0:
 *
 *     jpos.css + alpine + jpos-number + live-search + sesi  =  110 KB, 5 permintaan
 *     11,5 ms dari 51 ms satu perpindahan halaman - 22%-nya habis di situ
 *
 * Dan itu bukan cuma soal 11 ms: server bawaan melayani SATU permintaan pada satu waktu,
 * jadi lima permintaan aset itu antre di depan halaman berikutnya yang sedang dibuka kasir.
 *
 * server.php di akar proyek melayaninya sendiri beserta header cache. Yang dijaga di sini
 * adalah SYARAT AMANNYA: alamat aset harus membawa penanda versi, karena tanpa itu cache
 * panjang berarti kasir melihat tampilan versi lama setelah aplikasi diperbarui - jauh
 * lebih buruk daripada lambat.
 *
 * Perilaku server.php sendiri diuji di dalam paket hasil build (build-exe.ps1 tahap 9),
 * karena ia hanya dipakai oleh server bawaan - bukan oleh lapisan HTTP test Laravel.
 */
class AsetStatisTest extends JposTestCase
{
    /** Halaman yang memuat aset dan bisa dibuka tanpa data tambahan. */
    private const HALAMAN = [
        '/dashboard',
        '/kasir',
        '/master/produk',
        '/pengaturan/tentang',
        '/pengaturan/template-barcode',
    ];

    public function test_setiap_alamat_aset_membawa_penanda_versi(): void
    {
        $versi = config('jpos.version');

        $this->assertNotSame('', $versi);

        foreach (self::HALAMAN as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            preg_match_all('#(?:src|href)="([^"]*/vendor/[^"]+)"#', $html, $cocok);

            $this->assertNotEmpty($cocok[1], "Halaman {$url} tidak memuat aset apa pun - pemeriksaan ini jadi tidak berarti.");

            foreach ($cocok[1] as $alamat) {
                $this->assertStringContainsString(
                    '?v=' . $versi,
                    $alamat,
                    "Aset {$alamat} di {$url} tidak berpenanda versi. Dengan cache setahun, "
                    . 'kasir akan melihat versi lamanya selamanya setelah aplikasi diperbarui.'
                );
            }
        }
    }

    /**
     * Penanda versinya harus IKUT BERUBAH saat versi naik - itu seluruh gunanya.
     */
    public function test_penanda_versi_mengikuti_versi_aplikasi(): void
    {
        config(['jpos.version' => '9.9.9']);

        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('?v=9.9.9', $html);
    }

    /** Berkas asetnya harus benar-benar ada; alamat berpenanda versi tetap salah kalau kosong. */
    public function test_berkas_aset_yang_dirujuk_benar_benar_ada(): void
    {
        foreach (self::HALAMAN as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            preg_match_all('#(?:src|href)="[^"]*/(vendor/[^"?]+)#', $html, $cocok);

            foreach (array_unique($cocok[1]) as $relatif) {
                $this->assertFileExists(
                    public_path($relatif),
                    "Halaman {$url} merujuk aset yang tidak ada: {$relatif}"
                );
            }
        }
    }

    /**
     * Seluruh berkas yang DIMUAT halaman wajib lokal, supaya aplikasi tetap jalan tanpa
     * internet. Pelengkap penjaga CDN yang sudah ada.
     *
     * Yang diperiksa hanya src= dan stylesheet - BUKAN href pada tautan biasa. Halaman
     * Tentang memang memuat tautan ke Instagram dan WhatsApp JaylaTech, dan itu tautan yang
     * diklik pemilik toko, bukan berkas yang harus terunduh supaya halamannya tampil.
     */
    public function test_tidak_ada_berkas_yang_dimuat_dari_luar(): void
    {
        foreach (self::HALAMAN as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            preg_match_all('#\bsrc="(https?://[^"]+)"#', $html, $skrip);
            preg_match_all('#<link[^>]+rel="stylesheet"[^>]+href="(https?://[^"]+)"#', $html, $gaya);

            foreach (array_merge($skrip[1], $gaya[1]) as $alamat) {
                $this->assertStringStartsWith(
                    config('app.url'),
                    $alamat,
                    "Halaman {$url} memuat {$alamat} dari luar - aplikasi akan rusak saat komputer kasir offline."
                );
            }
        }
    }
}
