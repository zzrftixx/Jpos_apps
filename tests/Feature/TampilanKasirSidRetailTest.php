<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Tests\JposTestCase;

class TampilanKasirSidRetailTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session()->forget('pos_layout');
    }

    /**
     * Pengaturan tampilan kasir dapat menyimpan pilihan mode tampilan produk ('gambar', 'list', 'both').
     */
    public function test_pengaturan_tampilan_kasir_dapat_menyimpan_pilihan_mode_tampilan(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'default_view' => 'list',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['view' => 'list', 'toggle' => false], Setting::kasirDisplayMode());

        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'default_view' => 'both',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['view' => 'gambar', 'toggle' => true], Setting::kasirDisplayMode());

        // Ubah kembali ke gambar
        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'default_view' => 'gambar',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(['view' => 'gambar', 'toggle' => false], Setting::kasirDisplayMode());
    }

    /**
     * Validasi default_view hanya menerima 'gambar', 'list', atau 'both'.
     */
    public function test_pengaturan_tampilan_kasir_validasi_mode(): void
    {
        $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'default_view' => 'invalid_view',
        ])->assertSessionHasErrors(['default_view']);
    }

    /**
     * Halaman kasir menampilkan katalog produk dan keranjang secara langsung berdampingan,
     * tanpa tombol atau shortcut buka katalog F2 dan tanpa switcher layout.
     */
    public function test_halaman_kasir_menampilkan_katalog_dan_keranjang_tanpa_f2(): void
    {
        $this->makeProduct(['name' => 'Susu Kotak UHT', 'stock' => 20, 'sell_price' => 7000]);

        $html = $this->actingAs($this->kasir)
            ->get(route('kasir.index'))
            ->assertOk()
            ->getContent();

        // 1. Katalog Produk dan Kolom Pencarian langsung tampil
        $this->assertStringContainsString('x-ref="searchInput"', $html);
        $this->assertStringContainsString('Cari nama produk, SKU, atau scan barcode...', $html);
        $this->assertStringContainsString('Semua Kategori', $html);

        // 2. Panel Keranjang transaksi langsung tampil
        $this->assertStringContainsString('x-ref="daftarKeranjang"', $html);
        $this->assertStringContainsString('Keranjang', $html);
        $this->assertStringContainsString('Bayar &amp; Cetak Struk', $html);
        $this->assertStringContainsString('Tahan Transaksi', $html);

        // 3. Tidak ada tombol atau shortcut F2 untuk buka/sembunyikan katalog
        $this->assertStringNotContainsString('Buka Katalog Produk', $html);
        $this->assertStringNotContainsString('Sembunyikan Produk', $html);
        $this->assertStringNotContainsString("e.key === 'F2'", $html);
        $this->assertStringNotContainsString('showCatalog', $html);

        // 4. Tidak ada tombol switcher layout (Modern vs Sederhana/SID Retail)
        $this->assertStringNotContainsString('Layout Modern', $html);
        $this->assertStringNotContainsString('Layout Sederhana', $html);
        $this->assertStringNotContainsString('Layout SID Retail', $html);
        $this->assertStringNotContainsString('TAMPILAN SEDERHANA', $html);
        $this->assertStringNotContainsString('SID RETAIL DISPLAY', $html);
    }
}
