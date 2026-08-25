<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Support\Struk;
use Tests\JposTestCase;

/**
 * UAT tata letak struk termal — khususnya kertas 58 mm.
 *
 * Keluhan yang memicu perbaikan ini: struk 80 mm rapi, tapi struk client yang memakai 58 mm
 * "miring dan tidak center", padahal pratinjau di layar sudah rata tengah.
 *
 * Dua sebab, dan keduanya hanya kelihatan di kertas sempit:
 *
 *   1. Struk ditata selebar KERTAS, padahal kepala cetak printer termal lebih sempit dari
 *      rolnya (58 -> 48 mm, 80 -> 72 mm). Isi yang lebih lebar dari area cetak membuat
 *      pengandar printer mengecilkan seluruh halaman - itulah "miring dan tidak center".
 *      Kertas 80 mm lolos karena selisihnya cuma 10%; di 58 mm selisihnya 17% pada kertas
 *      yang sudah sempit.
 *
 *   2. Lebar kolom ditulis dalam PERSEN yang disetel untuk 80 mm. Terukur di peramban
 *      sebelum perbaikan: kolom BARANG hanya 13,1 mm pada kertas 58 mm - sekitar tujuh
 *      karakter - sehingga satu nama produk pecah jadi enam baris.
 */
class StrukLebarCetakTest extends JposTestCase
{
    // -----------------------------------------------------------------
    // Lebar cetak vs lebar kertas
    // -----------------------------------------------------------------

    /** INI INTINYA: yang menentukan tata letak adalah lebar cetak, bukan lebar kertas. */
    public function test_profil_printer_dipetakan_ke_lebar_cetak_bukan_lebar_kertas(): void
    {
        $this->assertSame(48.0, Struk::lebarCetak(['profile' => 'pos58', 'paper_size' => 58]),
            'Rol 58mm hanya mencetak selebar 48mm.');

        $this->assertSame(72.0, Struk::lebarCetak(['profile' => 'pos80', 'paper_size' => 80]),
            'Rol 80mm hanya mencetak selebar 72mm.');
    }

    /**
     * Toko yang sudah menyetel angkanya sendiri sampai hasilnya pas TIDAK boleh diubah.
     *
     * Angka custom itu sudah lebar cetak - hasil coba-coba di printer sungguhan. Menguranginya
     * lagi berarti merusak hasil cetak yang selama ini sudah benar.
     */
    public function test_lebar_yang_sudah_disetel_sendiri_tidak_diubah(): void
    {
        $this->assertSame(72.0, Struk::lebarCetak(['profile' => 'custom', 'paper_size' => 72]));
        $this->assertSame(50.0, Struk::lebarCetak(['profile' => 'pos58', 'print_width' => 50]),
            'print_width yang sudah tersimpan harus menang atas bawaan profil.');
    }

    // -----------------------------------------------------------------
    // Lebar kolom
    // -----------------------------------------------------------------

    /**
     * Kolom angka harus benar-benar memuat nominalnya, di lebar kertas mana pun.
     *
     * Nominal yang membungkus ke baris berikutnya membuat struk salah dibaca - dan struk
     * yang salah dibaca adalah selisih uang di meja kasir.
     */
    public function test_kolom_angka_muat_di_kedua_lebar_kertas(): void
    {
        foreach ([48.0, 72.0] as $lebar) {
            foreach ([6, 7, 9] as $karakterAngka) {
                $font = Struk::fontNota($lebar, $karakterAngka);
                $kolom = Struk::kolomNota($lebar, $font, $karakterAngka);

                $muatKarakter = Struk::karakterPerBaris($lebar * $kolom['harga'] / 100, $font);

                $this->assertGreaterThanOrEqual($karakterAngka, $muatKarakter,
                    "Kolom HARGA di {$lebar}mm hanya memuat {$muatKarakter} karakter, "
                    . "padahal nominalnya {$karakterAngka} karakter.");
            }
        }
    }

    /**
     * INI CACAT YANG DIPERBAIKI: kolom nama tidak boleh menyempit hanya karena kertasnya
     * lebih sempit. Sebelum perbaikan, BARANG dapat 22,6% di kertas 58mm dan 40% di 80mm.
     */
    public function test_kolom_nama_tidak_kehilangan_porsinya_di_kertas_sempit(): void
    {
        $sempit = Struk::kolomNota(48.0, Struk::fontNota(48.0, 7), 7);
        $lebar = Struk::kolomNota(72.0, Struk::fontNota(72.0, 7), 7);

        $this->assertGreaterThan(25, $sempit['nama'],
            'Kolom BARANG di kertas sempit tersisa terlalu sedikit.');

        // Porsinya boleh berbeda, tapi tidak boleh anjlok - itu gejala persen yang disetel
        // untuk satu lebar kertas lalu dipakai di lebar lain.
        $this->assertGreaterThan($lebar['nama'] * 0.6, $sempit['nama'],
            'Porsi kolom BARANG anjlok saat kertas menyempit.');
    }

    public function test_seluruh_kolom_berjumlah_seratus_persen(): void
    {
        foreach ([40.0, 48.0, 58.0, 72.0, 80.0] as $lebar) {
            $kolom = Struk::kolomNota($lebar, Struk::fontNota($lebar, 9), 9);
            $jumlah = array_sum($kolom);

            $this->assertEqualsWithDelta(100, $jumlah, 0.1,
                "Kolom di {$lebar}mm berjumlah {$jumlah}%, bukan 100%.");
        }
    }

    // -----------------------------------------------------------------
    // Font & tata letak yang mengalah
    // -----------------------------------------------------------------

    /** Font mengecil hanya kalau perlu, dan tidak pernah di bawah batas keterbacaan. */
    public function test_font_mengecil_hanya_saat_perlu(): void
    {
        $this->assertSame(Struk::FONT_NOTA_MAX, Struk::fontNota(72.0, 7),
            'Kertas 72mm tidak perlu mengecilkan font.');

        $this->assertLessThan(Struk::FONT_NOTA_MAX, Struk::fontNota(48.0, 9),
            'Kertas 48mm dengan nominal panjang seharusnya mengecilkan font.');

        foreach ([30.0, 40.0, 48.0] as $lebar) {
            $this->assertGreaterThanOrEqual(Struk::FONT_NOTA_MIN, Struk::fontNota($lebar, 11),
                'Font turun di bawah batas keterbacaan printer termal.');
        }
    }

    /** Tabel nota 4 kolom pada dasarnya tata letak 80mm; di kertas sangat sempit ia mengalah. */
    public function test_tabel_nota_mengalah_di_kertas_yang_terlalu_sempit(): void
    {
        $this->assertTrue(Struk::muatNota(72.0, 12, 9));
        $this->assertTrue(Struk::muatNota(48.0, Struk::fontNota(48.0, 9), 9),
            'Kertas 48mm seharusnya masih muat setelah fontnya menyesuaikan.');

        $this->assertFalse(Struk::muatNota(28.0, Struk::FONT_NOTA_MIN, 9),
            'Kertas 28mm jelas tidak muat untuk tabel 4 kolom.');
    }

    // -----------------------------------------------------------------
    // Struk sungguhan
    // -----------------------------------------------------------------

    private function strukUntuk(string $profile, int $paper): string
    {
        Setting::set('printer_struk', [
            'profile' => $profile, 'paper_size' => $paper,
            'margin' => 0, 'font_size' => 12, 'auto_print' => false,
        ]);
        Setting::set('template_struk', ['layout' => 'tabel']);

        return $this->actingAs($this->admin)
            ->get(route('kasir.receipt', $this->buatTransaksi()))
            ->assertOk()
            ->getContent();
    }

    /** Satu transaksi dengan nominal berdigit banyak, supaya kolom angka benar-benar diuji. */
    private function buatTransaksi(): Sale
    {
        $produk = $this->makeProduct([
            'name' => 'Beras Premium Kemasan Karung Besar',
            'unit' => 'Pcs',
            'stock' => 100,
            'sell_price' => 1500000,
        ]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 9999999,
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::firstOrFail();
    }

    /**
     * PALING MENENTUKAN: halaman struk sungguhan harus menyebut lebar CETAK, bukan lebar
     * kertas - baik di @page maupun di lebar badan.
     */
    public function test_halaman_struk_memakai_lebar_cetak(): void
    {
        $isi = $this->strukUntuk('pos58', 58);

        $this->assertStringContainsString('size: 48mm auto', $isi,
            'Ukuran halaman masih memakai lebar kertas, bukan lebar cetak.');
        $this->assertStringContainsString('width: 48mm', $isi,
            'Lebar badan struk masih memakai lebar kertas.');
        $this->assertStringNotContainsString('58mm auto', $isi);
    }

    public function test_struk_delapan_puluh_mm_juga_memakai_lebar_cetak(): void
    {
        $isi = $this->strukUntuk('pos80', 80);

        $this->assertStringContainsString('size: 72mm auto', $isi);
        $this->assertStringNotContainsString('80mm auto', $isi);
    }

    /**
     * Margin cetak dipasang sebagai padding BADAN, bukan margin @page.
     *
     * Margin @page mempersempit kotak halaman sementara lebar badan tetap, sehingga isinya
     * justru meluap ke luar area cetak - persis kebalikan dari yang diinginkan orang yang
     * menaikkan angka margin untuk merapikan hasil cetaknya.
     */
    public function test_margin_tidak_membuat_isi_meluap_dari_area_cetak(): void
    {
        Setting::set('printer_struk', [
            'profile' => 'pos58', 'paper_size' => 58,
            'margin' => 3, 'font_size' => 12, 'auto_print' => false,
        ]);
        Setting::set('template_struk', ['layout' => 'tabel']);

        $isi = $this->actingAs($this->admin)
            ->get(route('kasir.receipt', $this->buatTransaksi()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('@page { size: 48mm auto; margin: 0; }', $isi,
            'Margin masih dipasang di @page sehingga isi struk meluap.');
        $this->assertStringContainsString('3mm', $isi,
            'Margin tidak dipasang sebagai padding badan.');
    }

    /** Lembar uji cetak memakai lebar yang sama persis dengan struk sungguhan. */
    public function test_lembar_uji_cetak_memakai_lebar_yang_sama(): void
    {
        Setting::set('printer_struk', ['profile' => 'pos58', 'paper_size' => 58, 'margin' => 0]);

        $this->actingAs($this->admin)
            ->get(route('pengaturan.printer-struk.uji'))
            ->assertOk()
            ->assertSee('size: 48mm auto', false)
            ->assertSee('Lebar cetak: 48 mm', false);
    }

    /**
     * Pratinjau yang berbohong lebih buruk daripada tidak ada pratinjau: ia membuat orang
     * mencari penyebabnya di tempat yang salah. Ini persis yang terjadi - pratinjau digambar
     * selebar kertas, hasil cetaknya selebar kepala cetak.
     */
    public function test_pratinjau_template_memakai_lebar_cetak(): void
    {
        Setting::set('printer_struk', ['profile' => 'pos58', 'paper_size' => 58, 'margin' => 0]);

        $isi = $this->actingAs($this->admin)
            ->get(route('pengaturan.template-struk'))
            ->assertOk()
            ->getContent();

        // 48mm x 2.75 = 132px. Lebar kertas 58mm akan menghasilkan 159.5px.
        $this->assertStringContainsString('width:132px', str_replace(' ', '', $isi),
            'Pratinjau masih digambar selebar kertas, bukan selebar cetak.');
    }
}
