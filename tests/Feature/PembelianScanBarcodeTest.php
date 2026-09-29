<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Unit;
use Tests\JposTestCase;

class PembelianScanBarcodeTest extends JposTestCase
{
    public function test_halaman_pembelian_memuat_data_barcode_modal_dan_jual_produk(): void
    {
        $dusUnit = Unit::firstOrCreate(['name' => 'Dus']);

        $product = $this->makeProduct([
            'name' => 'Kopi Kapal Api',
            'sku' => 'KPA-001',
            'barcode' => '8991234567890',
            'unit' => 'Pcs',
            'cost_price' => 1500,
            'sell_price' => 2000,
            'stock' => 50,
            'multi_unit_enabled' => true,
        ]);

        $product->units()->create([
            'unit_id' => $dusUnit->id,
            'conversion' => 24,
            'barcode' => '8991234567899',
            'cost_price' => 36000,
            'price' => 48000,
        ]);

        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertOk();

        // Memastikan HTML memuat atribut barcode, harga modal, harga jual, dan listener scanner
        $response->assertSee('8991234567890');
        $response->assertSee('8991234567899');
        $response->assertSee('@jpos:barcode-dipindai.window', false);
        $response->assertSee('placeholder="Scan barcode scanner atau ketik nama produk / SKU... (tekan Enter)"', false);
        $response->assertSee('Modal Lama:', false);
        $response->assertSee('Harga Jual:', false);
    }

    public function test_halaman_pembelian_memuat_ringkasan_dan_modal_detail_faktur(): void
    {
        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertOk();

        // 4 Kartu Metrik Ringkasan
        $response->assertSee('Pembelian Bulan Ini');
        $response->assertSee('Sisa Hutang Pemasok');
        $response->assertSee('Nota Belum Lunas');
        $response->assertSee('Lewat Jatuh Tempo');

        // Modal Detail Nota & Riwayat Cicilan
        $response->assertSee('showDetail');
        $response->assertSee('Daftar Barang Diterima');
        $response->assertSee('Riwayat Pembayaran / Cicilan');

        // Modal Bayar Hutang Baru
        $response->assertSee('Sisa Hutang Aktif:');
        $response->assertSee('Bayar Semua (Lunas)');
    }
}
