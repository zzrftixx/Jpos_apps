<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\Unit;
use Tests\JposTestCase;

/**
 * Template struk "Invoice (Dot Matrix)".
 *
 * Layout keempat, untuk toko yang mencetak nota di printer dot matrix dengan kertas
 * continuous form 22 x 16 cm - bukan struk roll termal. Ukurannya TETAP dan sengaja tidak
 * mengikuti pengaturan Printer Struk: yang diatur di sana adalah lebar kertas roll, dan
 * memakainya di sini akan memotong tabel invoice.
 */
class StrukInvoiceTest extends JposTestCase
{
    private function buatTransaksi(float $qty = 2, ?string $satuanDasar = null): Sale
    {
        if ($satuanDasar) {
            Unit::firstOrCreate(['name' => $satuanDasar])->update(['is_weighable' => true]);
        }

        $produk = $this->makeProduct([
            'name' => 'Beras Premium',
            'unit' => $satuanDasar ?? 'Pcs',
            'stock' => 100,
            'sell_price' => 15000,
        ]);

        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => $qty]],
            'paid_amount' => 999999,
            'payment_method' => 'cash',
        ])->assertOk();

        return Sale::firstOrFail();
    }

    private function pakaiLayout(string $layout): void
    {
        Setting::set('template_struk', [
            'layout' => $layout,
            'show_logo' => false,
            'show_address' => true,
            'show_phone' => true,
            'show_cashier' => true,
            'show_customer' => true,
            'header_note' => '',
            'footer_note' => 'Terima kasih telah berbelanja!',
        ]);
    }

    public function test_layout_invoice_bisa_disimpan(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'invoice',
            'footer_note' => 'Terima kasih',
        ])->assertSessionHasNoErrors();

        $this->assertSame('invoice', Setting::get('template_struk')['layout']);
    }

    public function test_layout_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/pengaturan/template-struk', [
            'layout' => 'entah-apa',
            'footer_note' => '-',
        ])->assertSessionHasErrors('layout');
    }

    public function test_struk_invoice_memakai_kertas_tetap_dan_punya_kolom_tanda_tangan(): void
    {
        $sale = $this->buatTransaksi();
        $this->pakaiLayout('invoice');

        $isi = $this->actingAs($this->admin)->get("/kasir/receipt/{$sale->id}")
            ->assertOk()->getContent();

        $this->assertStringContainsString('size: 220mm 160mm', $isi, 'Kertas invoice harus tetap 22 x 16 cm.');
        $this->assertStringContainsString('INVOICE', $isi);
        $this->assertStringContainsString('Kepada Yth', $isi);
        $this->assertStringContainsString('Harga Satuan', $isi);
        $this->assertStringContainsString('Penerima,', $isi);
        $this->assertStringContainsString('Hormat Kami,', $isi);
    }

    /**
     * Kertas continuous form punya tinggi tetap, jadi invoice dengan satu barang tetap harus
     * mengisi kotak tabelnya - kalau tidak, garisnya berhenti di tengah halaman.
     */
    public function test_tabel_invoice_diganjal_sampai_lima_baris(): void
    {
        $sale = $this->buatTransaksi();
        $this->pakaiLayout('invoice');

        $isi = $this->actingAs($this->admin)->get("/kasir/receipt/{$sale->id}")
            ->assertOk()->getContent();

        // 1 baris isi + 4 baris pengganjal.
        $this->assertSame(4, substr_count($isi, '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>'));
    }

    /**
     * INI YANG PALING MUDAH TERLEWAT. Qty sekarang boleh pecahan, dan seluruh layout struk
     * harus mencetaknya apa adanya. Kalau qty diformat dengan 0 desimal, penjualan 0,5 Kg
     * tercetak "1 x 15.000" di struk PELANGGAN sementara subtotalnya 7.500.
     */
    public function test_semua_layout_mencetak_qty_pecahan_apa_adanya(): void
    {
        $sale = $this->buatTransaksi(0.5, 'Kg');

        foreach (['simple', 'normal', 'tabel', 'invoice'] as $layout) {
            $this->pakaiLayout($layout);

            $isi = $this->actingAs($this->admin)->get("/kasir/receipt/{$sale->id}")
                ->assertOk()->getContent();

            $this->assertStringContainsString('0,5', $isi, "Layout {$layout} tidak mencetak qty 0,5.");
            $this->assertStringNotContainsString('0.5000', $isi, "Layout {$layout} mencetak angka mentah dari database.");
        }
    }

    public function test_pratinjau_pengaturan_menampilkan_invoice(): void
    {
        $this->buatTransaksi();
        $this->pakaiLayout('invoice');

        $this->actingAs($this->admin)->get('/pengaturan/template-struk')
            ->assertOk()
            ->assertSee('Invoice (Dot Matrix)', false)
            ->assertSee('Kepada Yth', false)
            ->assertSee('22 x 16 cm', false);
    }

    /** Logo harus lewat MediaController, bukan symlink storage yang tidak ada di paket .exe. */
    public function test_logo_invoice_dilayani_lewat_media(): void
    {
        $sale = $this->buatTransaksi();
        Setting::set('store_profile', ['name' => 'Toko Uji', 'logo' => 'logo/toko.png']);
        $this->pakaiLayout('invoice');
        Setting::set('template_struk', array_merge(Setting::get('template_struk'), ['show_logo' => true]));

        $isi = $this->actingAs($this->admin)->get("/kasir/receipt/{$sale->id}")
            ->assertOk()->getContent();

        $this->assertStringContainsString('/media/logo/toko.png', $isi);
        $this->assertStringNotContainsString('/storage/logo/toko.png', $isi);
    }
}
