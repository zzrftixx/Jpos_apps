<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use Tests\JposTestCase;

/**
 * Pilihan bentuk dokumen saat bayar: Struk / Invoice / Keduanya.
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

        $this->assertStringNotContainsString('dokumen=invoice', $html);
        // Label tombolnya dibangun Alpine lewat x-text, jadi yang dicari adalah sumbernya -
        // bukan teks 'Keduanya' yang memang tidak pernah ada sebagai HTML.
        $this->assertStringNotContainsString("label: 'Keduanya'", $html);
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
            'dokumen_default' => 'keduanya',
        ])->assertSessionHasNoErrors();

        $tersimpan = Setting::get('template_struk', []);

        $this->assertTrue($tersimpan['pilih_dokumen']);
        $this->assertSame('keduanya', $tersimpan['dokumen_default']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString("label: 'Keduanya'", $html);
        $this->assertStringContainsString('dokumenCetak', $html);
        // Pilihan yang tersorot saat layar dibuka mengikuti pengaturan, bukan selalu Struk.
        $this->assertStringContainsString('dokumenCetak: "keduanya"', $html);
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

    // -----------------------------------------------------------------
    // Meja kasir
    // -----------------------------------------------------------------

    public function test_meja_kasir_menawarkan_tiga_pilihan(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true, 'dokumen_default' => 'struk']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        foreach (["nilai: 'struk'", "nilai: 'invoice'", "nilai: 'keduanya'"] as $pilihan) {
            $this->assertStringContainsString($pilihan, $html);
        }

        // "Keduanya" membuka dua jendela - struk dulu, lalu invoice.
        $this->assertStringContainsString("['struk', 'invoice']", $html);
    }

    /**
     * Jendela yang diblokir peramban tidak boleh berakhir sebagai kebingungan diam.
     *
     * Transaksinya sudah tersimpan di server saat itu terjadi; yang gagal hanya membuka
     * halaman cetaknya. Halaman sengaja TIDAK dimuat ulang supaya tautan penggantinya tidak
     * ikut hilang bersamanya.
     */
    public function test_jendela_yang_diblokir_ditawarkan_sebagai_tautan(): void
    {
        $this->aturTemplate(['pilih_dokumen' => true, 'dokumen_default' => 'keduanya']);

        $html = $this->actingAs($this->kasir)->get(route('kasir.index'))->assertOk()->getContent();

        $this->assertStringContainsString('dokumenTertahan', $html);
        $this->assertStringContainsString('jendela cetak diblokir peramban', $html);
        $this->assertStringContainsString('if (semuaTerbuka) window.location.reload();', $html,
            'Halaman dimuat ulang tanpa syarat - tautan dokumen yang tertahan akan ikut hilang.');
    }
}
