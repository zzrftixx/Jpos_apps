<?php

namespace Tests\Feature;

use App\Http\Controllers\BarcodePrintController;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * UAT halaman Cetak Barcode.
 *
 * Dua keluhan nyata dari toko, dan keduanya ternyata cacat sungguhan:
 *
 *   1. "Select all-nya tidak memilih semua." Benar - token 'pilih semua' dibangun dari
 *      koleksi hasil PAGINASI, jadi hanya mencakup 20 baris yang sedang tampil. Pemilik
 *      toko yang mencentangnya lalu mencetak mengira seluruh katalognya sudah dilabeli.
 *
 *   2. "Kadang hasilnya kacau, ngeprint banyak sampai kertas printernya habis." Tiga sebab
 *      yang menumpuk:
 *        a. @page bermargin 2mm menyisakan area cetak 36x21mm, tapi labelnya dipaksa
 *           40x25mm - SETIAP label meluap ke halaman kedua. Terukur di peramban: 6 label
 *           menjadi 12 halaman.
 *        b. `page-break-after: always` di setiap label menyisakan satu halaman kosong di
 *           akhir cetakan.
 *        c. Jumlah cetak sama sekali tidak dibatasi. Satu angka salah ketik pada 20 baris
 *           terpilih langsung jadi ribuan label, dan tidak ada apa pun yang menahannya.
 *
 *      Ditambah satu hal yang membuat semuanya lebih parah: sebagian toko menyambungkan
 *      menu ini ke printer STRUK 58/80mm, yang tidak mengenal ukuran lembar 40x25mm sama
 *      sekali - tiap pemisah halaman jadi satu umpan kertas penuh.
 */
class CetakBarcodeTest extends JposTestCase
{
    private function buatProduk(int $jumlah): void
    {
        for ($i = 1; $i <= $jumlah; $i++) {
            $this->makeProduct([
                'name' => sprintf('Produk %03d', $i),
                'barcode' => '899000000' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Pilih semua
    // -----------------------------------------------------------------

    /** INI CACAT YANG DIPERBAIKI: pilih semua harus mencakup seluruh katalog, bukan satu halaman. */
    public function test_pilih_semua_mencakup_seluruh_katalog_bukan_satu_halaman(): void
    {
        $this->buatProduk(25); // paginasi 20 per halaman

        $token = $this->actingAs($this->admin)
            ->get(route('barcode.index'))
            ->assertOk()
            ->viewData('semuaToken');

        $this->assertCount(25, $token,
            'Pilih semua hanya mencakup halaman yang tampil, bukan seluruh katalog.');
    }

    /** Kalau kasir sedang menyaring, pilih semua mengikuti hasil saringan - bukan seluruh katalog. */
    public function test_pilih_semua_mengikuti_hasil_pencarian(): void
    {
        $this->buatProduk(25);
        $this->makeProduct(['name' => 'Barang Khusus Rak Depan', 'barcode' => '8991234567890']);

        $token = $this->actingAs($this->admin)
            ->get(route('barcode.index', ['q' => 'Khusus']))
            ->assertOk()
            ->viewData('semuaToken');

        $this->assertCount(1, $token, 'Pilih semua tidak mengikuti hasil pencarian.');
    }

    /** Satuan tambahan punya barisnya sendiri, jadi tokennya juga harus ikut terpilih. */
    public function test_pilih_semua_ikut_menyertakan_satuan_tambahan(): void
    {
        $produk = $this->makeProduct(['name' => 'Beras', 'barcode' => '8990000000001']);
        $this->makeProductUnit($produk, 'Karung', 25, 250000);

        $token = $this->actingAs($this->admin)
            ->get(route('barcode.index'))
            ->assertOk()
            ->viewData('semuaToken');

        $this->assertCount(2, $token, 'Satuan tambahan tidak ikut terpilih.');
    }

    /**
     * Daftar token harus ada DI DALAM blok yang ditukar pencarian langsung. Kalau di luar,
     * setelah kasir mencari, pilih semua akan memilih token dari daftar SEBELUM pencarian -
     * dan mencetak label produk yang tidak ada di layar.
     */
    public function test_daftar_token_ikut_tertukar_saat_pencarian_langsung(): void
    {
        $this->buatProduk(3);

        $isi = $this->actingAs($this->admin)->get(route('barcode.index'))->assertOk()->getContent();

        $posisiBlok = strpos($isi, 'data-live-results');
        // Dicari tag skripnya, bukan sekadar nama atributnya: nama itu juga muncul lebih
        // awal di dalam x-data (pada bacaSemua()), dan itu bukan letak datanya.
        $posisiToken = strpos($isi, '<script type="application/json" data-semua-token>');

        $this->assertNotFalse($posisiToken, 'Daftar token tidak ditemukan.');
        $this->assertGreaterThan($posisiBlok, $posisiToken,
            'Daftar token berada di luar blok yang ditukar pencarian langsung.');
    }

    // -----------------------------------------------------------------
    // Rem cetak
    // -----------------------------------------------------------------

    /**
     * PALING MENENTUKAN: kertas yang sudah keluar tidak bisa ditarik kembali, jadi batasnya
     * harus berlaku SEBELUM dicetak - bukan berupa peringatan yang bisa dilewati.
     */
    public function test_jumlah_label_dibatasi_sebelum_dicetak(): void
    {
        $this->buatProduk(20);

        $ids = \App\Models\Product::pluck('id')->implode(',');

        $response = $this->actingAs($this->admin)
            ->get(route('barcode.print', ['ids' => $ids, 'qty' => 1000]))
            ->assertOk();

        $total = $response->viewData('totalLabel');

        $this->assertLessThanOrEqual(BarcodePrintController::MAKS_LABEL_SEKALI_CETAK, $total,
            "Cetak menghasilkan {$total} label - jauh melebihi batas.");
        $this->assertTrue($response->viewData('dipangkas'));
        $response->assertSee('dibatasi', false);
    }

    /** Pekerjaan sungguhan tidak boleh ikut terhalang oleh rem itu. */
    public function test_cetak_dalam_jumlah_wajar_tidak_dipangkas(): void
    {
        $this->buatProduk(10);
        $ids = \App\Models\Product::pluck('id')->implode(',');

        $response = $this->actingAs($this->admin)
            ->get(route('barcode.print', ['ids' => $ids, 'qty' => 5]))
            ->assertOk();

        $this->assertSame(50, $response->viewData('totalLabel'));
        $this->assertFalse($response->viewData('dipangkas'));
    }

    // -----------------------------------------------------------------
    // Tata letak cetak
    // -----------------------------------------------------------------

    private function halamanCetak(string $mode): string
    {
        $produk = $this->makeProduct(['name' => 'Produk Uji', 'barcode' => '8990000000009']);

        Setting::set('printer_barcode', [
            'label_width' => 40, 'label_height' => 25, 'mode' => $mode, 'printer_name' => '',
        ]);
        Setting::set('printer_struk', ['profile' => 'pos58', 'paper_size' => 58, 'margin' => 0]);

        return $this->actingAs($this->admin)
            ->get(route('barcode.print', ['ids' => $produk->id, 'qty' => 3]))
            ->assertOk()
            ->getContent();
    }

    /**
     * INI SEBAB UTAMA KERTAS HABIS: label tidak boleh lebih besar dari kotak halamannya.
     * Margin @page 2mm menyisakan 36x21mm sementara labelnya 40x25mm - setiap label meluap
     * ke halaman kedua, dan cetakan langsung berlipat dua.
     */
    public function test_mode_label_tidak_meluap_ke_halaman_kedua(): void
    {
        $isi = $this->halamanCetak('label');

        $this->assertStringContainsString('@page { size: 40mm 25mm; margin: 0; }', $isi,
            'Margin @page masih mempersempit kotak halaman sehingga label meluap.');
    }

    /** Pemisah halaman dipasang sebelum label BERIKUTNYA, supaya tidak ada halaman kosong menggantung. */
    public function test_mode_label_tidak_menyisakan_halaman_kosong_di_akhir(): void
    {
        $isi = $this->halamanCetak('label');

        $this->assertStringContainsString('.label + .label { page-break-before: always;', $isi);
        $this->assertStringNotContainsString('page-break-after: always', $isi,
            'Masih memakai page-break-after di setiap label - menyisakan halaman kosong.');
    }

    /**
     * Printer struk tidak mengenal lembar 40x25mm: setiap pemisah halaman jadi satu umpan
     * kertas penuh. Di mode rol karena itu TIDAK boleh ada satu pun pemisah halaman.
     */
    public function test_mode_rol_tidak_memakai_pemisah_halaman_sama_sekali(): void
    {
        $isi = $this->halamanCetak('roll');

        $this->assertStringNotContainsString('page-break-before: always', $isi,
            'Mode rol masih memisah halaman - tiap label akan memakan satu umpan kertas penuh.');
        $this->assertStringNotContainsString('page-break-after: always', $isi);

        // Lebarnya mengikuti lebar CETAK printer struk (rol 58mm -> 48mm), bukan ukuran label.
        $this->assertStringContainsString('@page { size: 48mm auto; margin: 0; }', $isi);
    }

    /** Mode rol memberi tahu kasir apa yang sedang terjadi, bukan diam-diam berbeda. */
    public function test_mode_rol_menjelaskan_dirinya(): void
    {
        $isi = $this->halamanCetak('roll');

        $this->assertStringContainsString('printer struk', $isi);
        $this->assertStringContainsString('Printer Barcode', $isi);
    }

    /** Jumlah yang akan tercetak terlihat sebelum tombol ditekan. */
    public function test_jumlah_label_terlihat_sebelum_dicetak(): void
    {
        $isi = $this->halamanCetak('label');

        $this->assertStringContainsString('3</strong> label akan dicetak', $isi);
    }
}
