<?php

namespace Tests\Feature;

use Tests\JposTestCase;

/**
 * UAT penanda sesi: dasar dari perilaku "tutup jendela = server ikut mati".
 *
 * Yang diuji di sini adalah kontrak antara halaman (public/vendor/jpos-sesi.js) dan
 * JPOS.exe (AwasiJendela di JPOS_Launcher.cs). Launcher hanya melihat dua berkas penanda;
 * kalau isi kontraknya meleset, aplikasi bisa mati saat kasir masih bekerja - atau justru
 * tidak pernah mati sama sekali.
 */
class SesiAplikasiTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bersihkanPenanda();
    }

    protected function tearDown(): void
    {
        $this->bersihkanPenanda();

        parent::tearDown();
    }

    private function path(string $nama): string
    {
        return storage_path('framework/' . $nama);
    }

    private function bersihkanPenanda(): void
    {
        foreach (['jpos-detak', 'jpos-tutup'] as $nama) {
            if (is_file($this->path($nama))) {
                unlink($this->path($nama));
            }
        }
    }

    public function test_detak_meninggalkan_penanda_yang_dibaca_launcher(): void
    {
        $this->get('/jpos-detak')->assertOk();

        $this->assertFileExists($this->path('jpos-detak'));
    }

    /**
     * Kasir yang belum login pun harus bisa menahan server tetap hidup - kalau tidak,
     * server mati sendiri selagi kata sandi diketik.
     */
    public function test_detak_bisa_dikirim_tanpa_login(): void
    {
        $this->assertGuest();

        $this->get('/jpos-detak')->assertOk();
        $this->get('/jpos-tutup')->assertOk();
    }

    public function test_sinyal_tutup_meninggalkan_penanda_terpisah(): void
    {
        $this->get('/jpos-tutup')->assertOk();

        $this->assertFileExists($this->path('jpos-tutup'));
        $this->assertFileDoesNotExist($this->path('jpos-detak'));
    }

    /**
     * INI YANG PALING PENTING.
     *
     * Berpindah halaman memicu peristiwa yang sama persis dengan menutup jendela, dan
     * browser tidak menyediakan cara untuk membedakannya. Yang membedakan hanyalah: pada
     * perpindahan halaman, detak dari halaman berikutnya datang beberapa saat kemudian.
     * Detak itu WAJIB menghapus sinyal tutup - kalau tidak, JPOS.exe akan mematikan server
     * 12 detik setelah kasir sekadar berpindah dari Dashboard ke Kasir.
     */
    public function test_detak_membatalkan_sinyal_tutup_saat_pindah_halaman(): void
    {
        $this->get('/jpos-tutup')->assertOk();
        $this->assertFileExists($this->path('jpos-tutup'));

        // Halaman berikutnya selesai dimuat.
        $this->get('/jpos-detak')->assertOk();

        $this->assertFileDoesNotExist(
            $this->path('jpos-tutup'),
            'Sinyal tutup harus hilang begitu ada detak, kalau tidak server mati saat kasir cuma berpindah halaman.'
        );
    }

    /**
     * Kebalikannya: menutup jendela sungguhan tidak boleh menyisakan detak yang lebih baru
     * dari sinyal tutupnya, karena launcher memakai keberadaan sinyal tutup sebagai bukti
     * bahwa tidak ada lagi yang membuka aplikasi.
     */
    public function test_sinyal_tutup_bertahan_kalau_tidak_ada_detak_susulan(): void
    {
        $this->get('/jpos-detak')->assertOk();
        $this->get('/jpos-tutup')->assertOk();

        $this->assertFileExists($this->path('jpos-tutup'));
    }

    public function test_penanda_tidak_menyentuh_database(): void
    {
        $produk = $this->makeProduct(['stock' => 42]);

        $this->get('/jpos-detak')->assertOk();
        $this->get('/jpos-tutup')->assertOk();

        $this->assertSame(42.0, (float) $produk->fresh()->stock);
    }

    /**
     * Halaman mengirim detak tiap 10 detik. Ambang batas di launcher (12 detik sebelum
     * percaya pada sinyal tutup, 150 detik sebelum menyerah menunggu detak) harus tetap
     * lebih longgar dari itu. Test ini mengunci angka-angka tersebut supaya perubahan di
     * salah satu sisi tidak diam-diam membuat aplikasi mati saat kasir masih bekerja.
     */
    public function test_ambang_batas_launcher_masih_lebih_longgar_dari_jeda_detak(): void
    {
        $js = file_get_contents(public_path('vendor/jpos-sesi.js'));
        $cs = file_get_contents(base_path('JPOS_Launcher.cs'));

        preg_match('/JEDA_DETAK\s*=\s*(\d+)/', $js, $m);
        $jedaDetakDetik = ((int) $m[1]) / 1000;

        preg_match('/KonfirmasiTutupDetik\s*=\s*(\d+)/', $cs, $m);
        $konfirmasiTutup = (int) $m[1];

        preg_match('/DetakHilangDetik\s*=\s*(\d+)/', $cs, $m);
        $detakHilang = (int) $m[1];

        $this->assertGreaterThan($jedaDetakDetik, $konfirmasiTutup);

        // Browser memperlambat timer di jendela yang lama tidak aktif sampai sekitar satu
        // kali per menit; ambang "detak hilang" harus jauh di atas itu.
        $this->assertGreaterThan(60, $detakHilang);
    }
}
