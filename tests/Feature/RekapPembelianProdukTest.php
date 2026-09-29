<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\ProductUnit;
use Tests\JposTestCase;

/**
 * UAT Rekap Akumulasi Pembelian per Produk (Rekap Kulakan Barang Masuk).
 *
 * Menguji fitur khusus pemilik toko untuk memantau akumulasi barang yang dibeli
 * dari faktur supplier dalam rentang periode tertentu.
 * Kuantitas ini TIDAK BERKURANG oleh penjualan, karena berfungsi sebagai catatan
 * historis murni barang belanja modal/kulakan.
 */
class RekapPembelianProdukTest extends JposTestCase
{
    public function test_rekap_pembelian_mengakumulasikan_kuantitas_dan_nominal_produk(): void
    {
        $produk = $this->makeProduct(['name' => 'Whiskas Tuna 1kg', 'cost_price' => 50000, 'stock' => 0]);
        $supplier = Supplier::create(['name' => 'Distributor Petshop']);

        // Faktur 1: Beli 10 pcs @ 50.000 = 500.000
        $p1 = Purchase::create([
            'purchase_no' => 'PB-TEST-001',
            'supplier_invoice_no' => 'INV-SUP-101',
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'total' => 500000,
            'paid_amount' => 500000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $p1->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 10,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 50000,
            'subtotal' => 500000,
        ]);

        // Faktur 2: Beli 5 pcs @ 52.000 = 260.000
        $p2 = Purchase::create([
            'purchase_no' => 'PB-TEST-002',
            'supplier_invoice_no' => 'INV-SUP-102',
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'total' => 260000,
            'paid_amount' => 260000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $p2->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 5,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 52000,
            'subtotal' => 260000,
        ]);

        $response = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk');
        $response->assertOk();
        $response->assertSee('Whiskas Tuna 1kg');
        $response->assertSee('15'); // Akumulasi total qty (10 + 5)
        $response->assertSee('760.000'); // Total rupiah kulakan (500.000 + 260.000)
    }

    public function test_rekap_pembelian_tidak_berkurang_saat_ada_transaksi_penjualan(): void
    {
        $produk = $this->makeProduct([
            'name' => 'Royal Canin Mother & Babycat 2kg',
            'cost_price' => 100000,
            'sell_price' => 150000,
            'stock' => 0,
        ]);

        // Input Pembelian 20 pcs
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $produk->id,
                'qty' => 20,
                'unit_type' => 'base',
                'price' => 100000,
            ]],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        $this->assertSame(20.0, (float) $produk->fresh()->stock);

        // Penjualan kasir 7 pcs
        $this->actingAs($this->admin)->postJson('/kasir', [
            'items' => [['product_id' => $produk->id, 'qty' => 7]],
            'paid_amount' => 1050000,
            'payment_method' => 'cash',
        ])->assertOk();

        // Stok sisa di toko berkurang jadi 13
        $this->assertSame(13.0, (float) $produk->fresh()->stock);

        // Tapi di Rekap Barang Masuk, akumulasi belanja kulakan TETAP 20 pcs!
        $response = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk');
        $response->assertOk();
        $response->assertSee('Royal Canin Mother & Babycat 2kg');
        $response->assertSee('20'); // Tetap 20, tidak berkurang jadi 13
        $response->assertSee('2.000.000'); // Total modal 20 * 100.000
    }

    public function test_filter_periode_tanggal_pada_rekap_pembelian(): void
    {
        $produk = $this->makeProduct(['name' => 'Pasir Kucing Gumpal 10L']);

        // Pembelian bulan lalu (30 hari lalu)
        $tglLalu = now()->subMonth()->startOfMonth()->addDays(2)->toDateString();
        $p1 = Purchase::create([
            'purchase_no' => 'PB-LALU-001',
            'purchase_date' => $tglLalu,
            'total' => 300000,
            'paid_amount' => 300000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $p1->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 10,
            'unit_label' => 'Sak',
            'unit_conversion' => 1,
            'price' => 30000,
            'subtotal' => 300000,
        ]);

        // Pembelian bulan ini
        $p2 = Purchase::create([
            'purchase_no' => 'PB-KINI-001',
            'purchase_date' => now()->toDateString(),
            'total' => 450000,
            'paid_amount' => 450000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $p2->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 15,
            'unit_label' => 'Sak',
            'unit_conversion' => 1,
            'price' => 30000,
            'subtotal' => 450000,
        ]);

        // Filter default (bulan ini): harus hanya muncul 15 Sak
        $resBulanIni = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk&periode=bulan_ini');
        $resBulanIni->assertOk();
        $resBulanIni->assertSee('15');
        $resBulanIni->assertSee('450.000');

        // Filter bulan lalu: harus muncul 10 Sak
        $resBulanLalu = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk&periode=bulan_lalu');
        $resBulanLalu->assertOk();
        $resBulanLalu->assertSee('10');
        $resBulanLalu->assertSee('300.000');
    }

    public function test_filter_supplier_pada_rekap_pembelian(): void
    {
        $pA = Supplier::create(['name' => 'Pemasok Alpha']);
        $pB = Supplier::create(['name' => 'Pemasok Beta']);
        $prodA = $this->makeProduct(['name' => 'Makanan Kucing Alpha']);
        $prodB = $this->makeProduct(['name' => 'Makanan Kucing Beta']);

        // Beli dari Supplier Alpha
        $notaA = Purchase::create([
            'purchase_no' => 'PB-ALPHA-01',
            'supplier_id' => $pA->id,
            'purchase_date' => now()->toDateString(),
            'total' => 100000,
            'paid_amount' => 100000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $notaA->id,
            'product_id' => $prodA->id,
            'product_name' => $prodA->name,
            'qty' => 5,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 20000,
            'subtotal' => 100000,
        ]);

        // Beli dari Supplier Beta
        $notaB = Purchase::create([
            'purchase_no' => 'PB-BETA-01',
            'supplier_id' => $pB->id,
            'purchase_date' => now()->toDateString(),
            'total' => 200000,
            'paid_amount' => 200000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);
        PurchaseItem::create([
            'purchase_id' => $notaB->id,
            'product_id' => $prodB->id,
            'product_name' => $prodB->name,
            'qty' => 10,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 20000,
            'subtotal' => 200000,
        ]);

        // Filter khusus supplier Alpha
        $response = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk&supplier_id=' . $pA->id);
        $response->assertOk();
        $response->assertSee('Makanan Kucing Alpha');

        $rekapItems = $response->viewData('rekapProduk');
        $this->assertTrue(collect($rekapItems->items())->contains('nama_produk', 'Makanan Kucing Alpha'));
        $this->assertFalse(collect($rekapItems->items())->contains('nama_produk', 'Makanan Kucing Beta'));
    }

    public function test_rekap_pembelian_multi_satuan_menghitung_satuan_dasar_dengan_benar(): void
    {
        $produk = $this->makeProduct(['name' => 'Snack Me-O Creamy Treat', 'unit' => 'Pcs']);
        $unitDus = Unit::firstOrCreate(['name' => 'Dus']);
        ProductUnit::firstOrCreate([
            'product_id' => $produk->id,
            'unit_id' => $unitDus->id,
        ], [
            'conversion' => 24, // 1 Dus = 24 Pcs
            'price' => 240000,
        ]);

        $nota = Purchase::create([
            'purchase_no' => 'PB-DUS-01',
            'purchase_date' => now()->toDateString(),
            'total' => 520000,
            'paid_amount' => 520000,
            'sisa_hutang' => 0,
            'user_id' => $this->admin->id,
        ]);

        // Beli 2 Dus (= 48 Pcs) @ 240.000 = 480.000
        PurchaseItem::create([
            'purchase_id' => $nota->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 2,
            'unit_label' => 'Dus',
            'unit_conversion' => 24,
            'price' => 240000,
            'subtotal' => 480000,
        ]);

        // Dan beli ecer 4 Pcs @ 10.000 = 40.000
        PurchaseItem::create([
            'purchase_id' => $nota->id,
            'product_id' => $produk->id,
            'product_name' => $produk->name,
            'qty' => 4,
            'unit_label' => 'Pcs',
            'unit_conversion' => 1,
            'price' => 10000,
            'subtotal' => 40000,
        ]);

        $response = $this->actingAs($this->admin)->get('/pembelian?tab=rekap_produk');
        $response->assertOk();
        $response->assertSee('Snack Me-O Creamy Treat');
        // Total akumulasi satuan dasar = (2 * 24) + 4 = 52 Pcs
        $response->assertSee('52');
        $response->assertSee('520.000');
    }
}
