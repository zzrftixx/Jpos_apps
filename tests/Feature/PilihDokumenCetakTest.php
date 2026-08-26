<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * Pilihan bentuk dokumen: Struk atau Invoice.
 *
 * Invoice sendiri sudah ada sejak 2.1.0, tapi sebagai SATU pilihan global - toko harus
 * memilih satu bentuk untuk semua transaksi. Yang ditambahkan di sini adalah jalur
 * memilihnya per transaksi, karena keputusannya baru bisa diambil saat pelanggan sudah di
 * depan meja: yang belanja tiga barang cukup struk, yang belanja sekeranjang minta rincian.
 *
 * Yang paling dijaga di berkas ini adalah hal yang paling mudah rusak diam-diam: toko yang
 * TIDAK menyalakan fitur ini tidak boleh merasakan satu perbedaan pun.
 */
class PilihDokumenCetakTest extends JposTestCase
{
    private function transaksi(): Sale
    {
        $produk = $this->makeProduct(['name' => 'Beras Premium', 'stock' => 50, 'sell_price' => 15000]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    private function aturTemplate(array $ubah = []): void
    {
        Setting::set('template_struk', array_merge([
            'layout' => 'tabel',
            'show_logo' => false,
            'show_address' => true,
            'show_phone' => true,
            'show_cashier' => true,
            'show_customer' => true,
            'header_note' => '',
            'footer_note' => 'Terima kasih',
        ], $ubah));
    }

    // -----------------------------------------------------------------
    // Perilaku lama tidak boleh bergeser
    // -----------------------------------------------------------------

    /**
     * PALING MENENTUKAN: fitur ini mati secara bawaan.
     *
     * Toko yang sedang berjalan tidak boleh menemukan pilihan baru di meja kasirnya hanya
     * karena aplikasinya diperbarui - kasir yang bingung di depan antrean jauh lebih mahal
     * daripada fitur yang datang terlambat.
     */
    public function test_mati_secara_bawaan_dan_meja_kasir_tidak_berubah(): void
    {
        $this->aturTemplate();

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('dokumen=', $html);
        // Label tombolnya dibangun Alpine lewat x-text, jadi yang dicari adalah sumbernya -
        // bukan teks 'Invoice' yang memang tidak pernah ada sebagai HTML di sini.
        $this->assertStringNotContainsString("label: 'Invoice'", $html);
        // Tanpa fitur ini, struk dibuka apa adanya - persis seperti sebelumnya.
        $this->assertStringContainsString("window.open(alamatStruk, '_blank')", $html);
    }

    /** Tautan struk yang sudah ada di seluruh aplikasi tetap menghasilkan bentuk tersimpan. */
    public function test_tanpa_parameter_memakai_layout_tersimpan(): void
    {
        $this->aturTemplate(['layout' => 'invoice']);
        $t = $this->transaksi();

        $this->actingAs($this->kasir)
            ->get(route('kasir.receipt', $t))
            ->assertOk()
            ->assertSee('Cetak Invoice', false);
    }

    // -----------------------------------------------------------------
    // Dua bentuk yang benar-benar berbeda
    // -----------------------------------------------------------------

    public function test_dokumen_invoice_memakai_kertas_dot_matrix(): void
    {
        $this->aturTemplate(['layout' => 'tabel']);
        $t = $this->transaksi();

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.receipt', $t) . '?dokumen=invoice')
            ->assertOk()->getContent();

        // 22 x 16 cm - ukuran tetap, tidak mengikuti pengaturan kertas roll.
        $this->assertStringContainsString('220mm 160mm', $html);
        $this->assertStringContainsString('Cetak Invoice', $html);
    }

    public function test_dokumen_struk_memakai_kertas_roll_termal(): void
    {
        $this->aturTemplate(['layout' => 'tabel']);
        $t = $this->transaksi();

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.receipt', $t) . '?dokumen=struk')
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('220mm 160mm', $html);
        $this->assertStringContainsString('Cetak Struk', $html);
    }

    /**
     * Kasus yang paling mudah terlewat.
     *
     * Toko boleh memilih Invoice sebagai bentuk struknya - itu sah dan sudah berjalan. Tapi
     * begitu pilihan dokumen dinyalakan, tombol "Struk" dan "Invoice" akan mencetak berkas
     * yang sama persis, dan kasir tidak punya cara menebak kenapa. Permintaan `struk` yang
     * jatuh ke layout invoice karena itu dialihkan ke Tabel (Nota).
     */
    public function test_struk_tidak_pernah_mencetak_invoice_walau_layoutnya_invoice(): void
    {
        $this->aturTemplate(['layout' => 'invoice']);
        $t = $this->transaksi();

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.receipt', $t) . '?dokumen=struk')
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('220mm 160mm', $html,
            'Tombol Struk mencetak invoice - kedua tombol jadi tidak ada bedanya.');
        $this->assertStringContainsString('Cetak Struk', $html);
    }

    // -----------------------------------------------------------------
    // Pengaturan
    // -----------------------------------------------------------------

    public function test_pengaturan_bisa_disimpan_dan_terbaca_di_meja_kasir(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'tabel',
            'footer_note' => 'Terima kasih',
            'pilih_dokumen' => '1',
            'dokumen_default' => 'invoice',
        ])->assertSessionHasNoErrors();

        $tersimpan = Setting::get('template_struk', []);

        $this->assertTrue($tersimpan['pilih_dokumen']);
        $this->assertSame('invoice', $tersimpan['dokumen_default']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString("label: 'Invoice'", $html);
        // Pilihan yang tersorot saat layar dibuka mengikuti pengaturan, bukan selalu Struk.
        $this->assertStringContainsString('dokumenCetak: "invoice"', $html);
    }

    /** Centang dilepas -> tersimpan mati, bukan diabaikan. */
    public function test_pilihan_dokumen_bisa_dimatikan_kembali(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true, 'dokumen_default' => 'invoice']);

        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'tabel',
            'footer_note' => 'Terima kasih',
            // pilih_dokumen sengaja tidak dikirim - itulah yang dilakukan checkbox yang dilepas
        ])->assertSessionHasNoErrors();

        $this->assertFalse(Setting::get('template_struk', [])['pilih_dokumen']);
    }

    public function test_bentuk_dokumen_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'tabel',
            'footer_note' => 'Terima kasih',
            'pilih_dokumen' => '1',
            'dokumen_default' => 'faktur-pajak',
        ])->assertSessionHasErrors('dokumen_default');
    }

    /**
     * "Keduanya" dicabut di 2.9.1 - jendela keduanya diblokir peramban dan tidak pernah
     * benar-benar terbuka di komputer client. Nilai lama yang mungkin sudah tersimpan tidak
     * boleh membuat halaman kasir menampilkan pilihan yang sudah tidak ada.
     */
    public function test_nilai_keduanya_yang_sudah_dicabut_jatuh_kembali_ke_struk(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true, 'dokumen_default' => 'keduanya']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString('dokumenCetak: "struk"', $html);
        $this->assertStringNotContainsString("label: 'Keduanya'", $html);

        // Disimpan ulang lewat form pun ditolak, bukan diterima diam-diam.
        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'tabel',
            'footer_note' => 'Terima kasih',
            'pilih_dokumen' => '1',
            'dokumen_default' => 'keduanya',
        ])->assertSessionHasErrors('dokumen_default');
    }

    // -----------------------------------------------------------------
    // Meja kasir
    // -----------------------------------------------------------------

    public function test_meja_kasir_menawarkan_tiga_pilihan(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true, 'dokumen_default' => 'struk']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        foreach (["nilai: 'struk'", "nilai: 'invoice'"] as $pilihan) {
            $this->assertStringContainsString($pilihan, $html);
        }

        $this->assertStringNotContainsString("label: 'Keduanya'", $html);

        // SATU jendela, selalu. Dua jendela sekaligus pernah dicoba dan yang kedua diblokir.
        $this->assertStringContainsString(
            "window.open(alamatStruk + '?dokumen=' + this.dokumenCetak, '_blank')", $html);
        $this->assertStringNotContainsString('dokumenTertahan', $html);
    }

    // -----------------------------------------------------------------
    // Pesanan / Waiting List - cetak ulang
    // -----------------------------------------------------------------

    private function pesananDp(): Sale
    {
        $produk = $this->makeProduct(['name' => 'Semen Gresik', 'stock' => 50, 'sell_price' => 60000]);

        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 5]],
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
        ])->assertOk();

        return Sale::latest('id')->firstOrFail();
    }

    /**
     * Pesanan yang MASIH MENUNGGU DP bisa dicetak dalam dua bentuk.
     *
     * Justru di sini invoice paling sering dibutuhkan: pesanan besar yang belum lunas adalah
     * yang paling perlu rincian tertulis - pembelinya membawa pulang bukti pesanan, tokonya
     * menyimpan lampiran penagihan.
     */
    public function test_pesanan_menunggu_dp_bisa_dicetak_struk_atau_invoice(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true]);
        $p = $this->pesananDp();

        $this->assertSame('waiting', $p->order_status);

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.waiting-list'))->assertOk()->getContent();

        $this->assertStringContainsString(route('kasir.receipt', $p) . '?dokumen=struk', $html);
        $this->assertStringContainsString(route('kasir.receipt', $p) . '?dokumen=invoice', $html);
    }

    /** Yang sudah LUNAS juga - pelanggan sering kembali meminta invoicenya belakangan. */
    public function test_pesanan_lunas_bisa_dicetak_struk_atau_invoice(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true]);
        $t = $this->transaksi();

        $this->assertSame('completed', $t->order_status);

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.waiting-list', ['status' => 'completed']))->assertOk()->getContent();

        $this->assertStringContainsString(route('kasir.receipt', $t) . '?dokumen=struk', $html);
        $this->assertStringContainsString(route('kasir.receipt', $t) . '?dokumen=invoice', $html);
    }

    /** Fitur mati -> satu tombol Struk apa adanya, persis seperti sebelumnya. */
    public function test_pesanan_hanya_punya_satu_tombol_saat_fitur_mati(): void
    {
        $this->aturTemplate();
        $this->pesananDp();

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.waiting-list'))->assertOk()->getContent();

        $this->assertStringNotContainsString('?dokumen=', $html);
        $this->assertStringContainsString('Struk', $html);
    }
}