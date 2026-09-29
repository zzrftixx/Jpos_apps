<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use Tests\JposTestCase;

class PembelianStandardPOSTest extends JposTestCase
{
    public function test_halaman_index_pembelian_menampilkan_tab_pos_dan_komponen_lengkap(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Maju Jaya', 'phone' => '08123456789']);
        $produk = $this->makeProduct(['name' => 'Kopi Robusta', 'stock' => 5, 'cost_price' => 15000, 'sell_price' => 20000]);

        $response = $this->actingAs($this->admin)->get('/pembelian');

        $response->assertOk();
        $response->assertSee('Riwayat Faktur Pembelian');
        $response->assertSee('Tagihan &amp; Hutang Tempo', false);
        $response->assertSee('+ Catat Pembelian Baru');
        $response->assertSee('Scan barcode scanner');
        $response->assertSee('Supplier Maju Jaya');
        $response->assertSee('Kopi Robusta');
    }

    public function test_filter_periode_dan_supplier_pada_riwayat_pembelian(): void
    {
        $s1 = Supplier::create(['name' => 'Supplier Alfa']);
        $s2 = Supplier::create(['name' => 'Supplier Beta']);
        $produk = $this->makeProduct(['stock' => 0]);

        // Transaksi hari ini dengan s1
        $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $s1->id,
            'supplier_invoice_no' => 'INV-S1-001',
            'purchase_date' => now()->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'unit_type' => 'base', 'price' => 10000]],
            'bayar' => 'tunai',
        ]);

        // Transaksi 10 hari lalu dengan s2
        $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $s2->id,
            'supplier_invoice_no' => 'INV-S2-002',
            'purchase_date' => now()->subDays(10)->toDateString(),
            'items' => [['product_id' => $produk->id, 'qty' => 5, 'unit_type' => 'base', 'price' => 10000]],
            'bayar' => 'tunai',
        ]);

        // Filter supplier 1
        $resSupplier = $this->actingAs($this->admin)->get('/pembelian?supplier_id=' . $s1->id);
        $resSupplier->assertOk();
        $resSupplier->assertSee('INV-S1-001');
        $resSupplier->assertDontSee('INV-S2-002');

        // Filter periode 7 hari terakhir
        $resPeriode = $this->actingAs($this->admin)->get('/pembelian?periode=7_hari');
        $resPeriode->assertOk();
        $resPeriode->assertSee('INV-S1-001');
        $resPeriode->assertDontSee('INV-S2-002');

        // Filter status tempo
        $resTempo = $this->actingAs($this->admin)->get('/pembelian?status=tempo');
        $resTempo->assertOk();
    }

    public function test_pembelian_berhasil_menyimpan_flash_last_purchase_id_dan_bisa_dicetak(): void
    {
        $supplier = Supplier::create(['name' => 'Distributor Sentosa', 'phone' => '0899887766']);
        $produk = $this->makeProduct(['name' => 'Beras Pandan Wangi 5kg', 'stock' => 2, 'cost_price' => 60000, 'sell_price' => 75000]);

        $postRes = $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'SJ-DS-999',
            'purchase_date' => now()->toDateString(),
            'other_cost' => 15000,
            'items' => [[
                'product_id' => $produk->id,
                'qty' => 10,
                'unit_type' => 'base',
                'price' => 65000,
            ]],
            'bayar' => 'hutang',
            'paid_amount' => 200000,
            'due_date' => now()->addDays(14)->toDateString(),
            'note' => 'Pengiriman truk 1',
        ]);

        $postRes->assertSessionHasNoErrors();
        $postRes->assertSessionHas('success');
        $postRes->assertSessionHas('last_purchase_id');
        $postRes->assertSessionHas('last_purchase_no');

        $purchase = Purchase::where('supplier_invoice_no', 'SJ-DS-999')->firstOrFail();

        // Uji cetak faktur pembelian
        $cetakRes = $this->actingAs($this->admin)->get(route('pembelian.cetak', $purchase));
        $cetakRes->assertOk();
        $cetakRes->assertSee('FAKTUR PEMBELIAN');
        $cetakRes->assertSee($purchase->purchase_no);
        $cetakRes->assertSee('Distributor Sentosa');
        $cetakRes->assertSee('Beras Pandan Wangi 5kg');
        $cetakRes->assertSee('Pengiriman truk 1');
        $cetakRes->assertSee('Cetak Faktur (Print / PDF)');
    }

    public function test_catat_pembelian_bisa_memperbarui_harga_jual_produk_dan_multi_satuan(): void
    {
        $supplier = Supplier::create(['name' => 'Grosir Sumber Rejeki']);
        $dusUnit = Unit::firstOrCreate(['name' => 'Dus']);

        $produk = $this->makeProduct([
            'name' => 'Minyak Goreng 2L',
            'stock' => 5,
            'cost_price' => 28000,
            'sell_price' => 32000,
            'multi_unit_enabled' => true,
        ]);

        $pu = $produk->units()->create([
            'unit_id' => $dusUnit->id,
            'conversion' => 6,
            'cost_price' => 165000,
            'price' => 190000,
        ]);

        // Catat pembelian dengan harga beli baru dan harga jual baru
        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'INV-MG-01',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $produk->id,
                    'qty' => 10,
                    'unit_type' => 'base',
                    'price' => 30000,
                    'sell_price' => 35000,
                ],
                [
                    'product_id' => $produk->id,
                    'qty' => 2,
                    'unit_type' => 'unit_' . $pu->id,
                    'price' => 175000,
                    'sell_price' => 205000,
                ],
            ],
            'bayar' => 'tunai',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        // Pastikan harga jual master produk dan satuan tambahannya diperbarui
        $produk->refresh();
        $pu->refresh();

        $this->assertEquals(35000, (float) $produk->sell_price);
        $this->assertEquals(205000, (float) $pu->price);
        $this->assertEquals(175000, (float) $pu->cost_price);
    }

    public function test_halaman_pembelian_menggunakan_nama_barang_fiks_tanpa_dropdown_di_baris(): void
    {
        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertOk();

        // Header kolom tabel spreadsheet pembelian
        $response->assertSee('Nama Barang / Produk');
        $response->assertSee('Satuan Beli');
        $response->assertSee('Qty Masuk');
        $response->assertSee('Harga Beli (Rp)');
        $response->assertSee('Harga Jual (Rp)');
        $response->assertSee('Margin (%)');

        // Baris tabel tidak lagi memakai <select> untuk memilih produk
        $response->assertDontSee("<select :name=\"'items[' + row._key + '][product_id]'\"", false);
        // Memakai input hidden product_id
        $response->assertSee(":name=\"'items[' + row._key + '][product_id]'\"", false);
        $response->assertSee(":name=\"'items[' + row._key + '][sell_price]'\"", false);
        $response->assertSee('Multi-Satuan', false);
    }

    public function test_catat_pembelian_memperbarui_harga_satuan_lain_tanpa_beli_qty(): void
    {
        $supplier = Supplier::create(['name' => 'Grosir Multi Satuan']);
        $dusUnit = Unit::firstOrCreate(['name' => 'Dus']);

        $produk = $this->makeProduct([
            'name' => 'Kopi Kapal Api Spesial',
            'stock' => 10,
            'cost_price' => 1500,
            'sell_price' => 2000,
            'multi_unit_enabled' => true,
        ]);

        $pu = $produk->units()->create([
            'unit_id' => $dusUnit->id,
            'conversion' => 24,
            'cost_price' => 36000,
            'price' => 45000,
        ]);

        // Pembelian hanya beli satuan dasar (Pcs), tapi update harga jual satuan Dus
        $response = $this->actingAs($this->admin)->post('/pembelian', [
            'supplier_id' => $supplier->id,
            'supplier_invoice_no' => 'INV-KKA-01',
            'purchase_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $produk->id,
                    'qty' => 50,
                    'unit_type' => 'base',
                    'price' => 1600,
                    'sell_price' => 2200,
                ],
            ],
            'other_unit_prices' => [
                [
                    'product_id' => $produk->id,
                    'unit_type' => 'unit_' . $pu->id,
                    'sell_price' => 50000,
                    'cost_price' => 38400,
                ],
            ],
            'bayar' => 'tunai',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $produk->refresh();
        $pu->refresh();

        $this->assertEquals(2200, (float) $produk->sell_price);
        $this->assertEquals(50000, (float) $pu->price);
        $this->assertEquals(38400, (float) $pu->cost_price);
        // Stok produk bertambah 50, item pembelian hanya 1
        $this->assertEquals(60, (float) $produk->stock);
        $purchase = Purchase::where('supplier_invoice_no', 'INV-KKA-01')->firstOrFail();
        $this->assertCount(1, $purchase->items);
    }

    public function test_modal_katalog_pembelian_menggunakan_tabel_kompak_standar_pos(): void
    {
        $response = $this->actingAs($this->admin)->get('/pembelian');
        $response->assertOk();

        // Modal Katalog Header
        $response->assertSee('Katalog &amp; Pencarian Barang', false);

        // Header kolom tabel modal
        $response->assertSee('Stok Gudang');
        $response->assertSee('Modal Lama');
        $response->assertSee('Harga Jual');
        $response->assertSee('Aksi Faktur');

        // Filter status cepat
        $response->assertSee('Multi-Satuan');
        $response->assertSee('Stok &le; 10', false);
        $response->assertSee('Sudah di Faktur');
    }
}
