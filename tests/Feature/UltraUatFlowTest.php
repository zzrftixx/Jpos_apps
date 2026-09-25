<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\Akuntansi;
use App\Support\Angka;
use App\Support\MetodeBayar;
use App\Support\ProductCatalog;
use Tests\JposTestCase;

/**
 * UAT (User Acceptance Testing) End-to-End Alur Operasional Toko JPos.
 *
 * Menguji simulasi nyata satu shift penuh di toko retail:
 * 1. Setup Master Data (Produk Multi-Satuan & Satuan Timbangan Pecahan)
 * 2. Kasir: Pindai Barcode, Keranjang, Multi-Satuan & Checkout Tunai
 * 3. Alur Pesanan DP / Waiting List & Pelunasan Bertahap
 * 4. Alur Retur & Tukar-Tambah (Barang kembali + Tambah item baru + Selisih uang)
 * 5. Pembatalan Transaksi & Proteksi Stok Anti-Drift Float (H1, H2, H4)
 * 6. Rekonsiliasi Finansial & Persamaan Neraca Seimbang: ASET = KEWAJIBAN + MODAL (H7)
 */
class UltraUatFlowTest extends JposTestCase
{
    private Product $minyak;
    private Product $telur;
    private Customer $pelanggan;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Master Satuan
        Unit::updateOrCreate(['name' => 'Pcs'], ['is_weighable' => false]);
        $this->dusUnit = Unit::updateOrCreate(['name' => 'Dus'], ['is_weighable' => false]);
        Unit::updateOrCreate(['name' => 'Kg'], ['is_weighable' => true]);

        // 2. Setup Master Pelanggan & Supplier
        $this->pelanggan = Customer::create([
            'name' => 'Ibu Siti',
            'phone' => '081234567890',
            'address' => 'Jl. Mawar No. 10',
        ]);

        $this->supplier = Supplier::create([
            'name' => 'CV Sumber Makmur',
            'phone' => '089988776655',
            'address' => 'Kawasan Industri',
        ]);

        // 3. Produk 1: Minyak Goreng (Satuan Pcs, Multi-satuan Dus isi 12)
        $this->minyak = Product::create([
            'sku' => 'MYK001',
            'barcode' => '899123456001',
            'name' => 'Minyak Goreng 1L',
            'type' => 'barang',
            'cost_price' => 12000,
            'sell_price' => 15000,
            'stock' => 100,
            'unit' => 'Pcs',
            'min_stock' => 10,
            'is_active' => true,
            'is_taxable' => false,
        ]);

        $this->dusUnit = Unit::firstOrCreate(['name' => 'Dus'], ['is_weighable' => false]);
        $this->dusProductUnit = ProductUnit::create([
            'product_id' => $this->minyak->id,
            'unit_id' => $this->dusUnit->id,
            'conversion' => 12,
            'price' => 168000, // Rp 14.000 / pcs (harga grosir dus)
            'allow_decimal' => false,
        ]);

        // 4. Produk 2: Telur Ayam (Satuan timbangan Kg, boleh pecahan)
        $this->telur = Product::create([
            'sku' => 'TLR001',
            'barcode' => '899123456002',
            'name' => 'Telur Ayam Ras',
            'type' => 'barang',
            'cost_price' => 24000,
            'sell_price' => 28000,
            'stock' => 50.0,
            'unit' => 'Kg',
            'min_stock' => 5.0,
            'is_active' => true,
            'is_taxable' => false,
        ]);
    }

    /**
     * UAT Skenario 1: Penjualan Kasir Lengkap (Pecahan + Multi-Satuan + Kembalian)
     */
    public function test_uat_alur_kasir_penjualan_tunai_pecahan_dan_multi_satuan(): void
    {
        $stokMinyakAwal = $this->minyak->stock;
        $stokTelurAwal = $this->telur->stock;

        // Kasir memindai barcode minyak goreng
        $scan = $this->actingAs($this->kasir)
            ->getJson('/kasir/scan?barcode=899123456001')
            ->assertOk()
            ->json();
        $this->assertTrue($scan['found']);
        $this->assertSame($this->minyak->id, $scan['product']['id']);

        // Transaksi keranjang:
        // - 1 Dus Minyak Goreng (konversi 12 pcs, harga 168.000)
        // - 2,5 Kg Telur Ayam (2,5 x 28.000 = 70.000)
        // Total belanja: 168.000 + 70.000 = 238.000
        // Uang diserahkan: 250.000 (Kembalian: 12.000)
        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                [
                    'product_id' => $this->minyak->id,
                    'qty' => 1,
                    'unit_type' => 'unit_' . $this->dusProductUnit->id,
                ],
                [
                    'product_id' => $this->telur->id,
                    'qty' => 2.5,
                    'unit_type' => 'base',
                ],
            ],
            'customer_id' => $this->pelanggan->id,
            'payment_method' => 'tunai',
            'paid_amount' => 250000,
            'discount' => 0,
        ])->assertOk();

        $saleId = $response->json('sale_id');
        $this->assertNotNull($saleId);

        $sale = Sale::with('items', 'payments')->findOrFail($saleId);
        $this->assertSame('completed', $sale->order_status);
        $this->assertEqualsWithDelta(238000, (float) $sale->total, 0.01);
        $this->assertEqualsWithDelta(250000, (float) $sale->paid_amount, 0.01);
        $this->assertEqualsWithDelta(12000, (float) $sale->change_amount, 0.01);

        // HUKUM 6: cost_price_snapshot wajib terisi
        foreach ($sale->items as $item) {
            $this->assertNotNull($item->cost_price_snapshot);
            $this->assertGreaterThan(0, (float) $item->cost_price_snapshot);
        }

        // HUKUM 2: StockMovement berpasangan & stok fisik berkurang tepat
        $this->minyak->refresh();
        $this->telur->refresh();
        $this->assertEqualsWithDelta($stokMinyakAwal - 12, $this->minyak->stock, 0.001);
        $this->assertEqualsWithDelta($stokTelurAwal - 2.5, $this->telur->stock, 0.001);

        // Verifikasi SalePayment: amount adalah uang bersih (paid - change = 238.000)
        $this->assertCount(1, $sale->payments);
        $payment = $sale->payments->first();
        $this->assertEqualsWithDelta(238000, (float) $payment->amount, 0.01);
        $this->assertSame('tunai', $payment->method);
    }

    /**
     * UAT Skenario 2: Siklus Pesanan DP / Waiting List sampai Pelunasan
     */
    public function test_uat_alur_pesanan_dp_hingga_pelunasan_lengkap(): void
    {
        $stokTelurAwal = $this->telur->stock;

        // 1. Pembeli pesan 10 Kg telur (Total: 280.000), DP via QRIS 100.000
        $response = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $this->telur->id, 'qty' => 10, 'unit_type' => 'base'],
            ],
            'customer_id' => $this->pelanggan->id,
            'payment_method' => 'qris',
            'paid_amount' => 100000,
            'is_waiting_list' => true,
            'due_date' => now()->addDays(2)->toDateString(),
        ])->assertOk();

        $saleId = $response->json('sale_id');
        $sale = Sale::findOrFail($saleId);

        // Status pesanan masih waiting
        $this->assertSame('waiting', $sale->order_status);
        $this->assertEqualsWithDelta(180000, $sale->remaining, 0.01);

        // Stok sudah dikunci agar tidak terjual ke orang lain
        $this->telur->refresh();
        $this->assertEqualsWithDelta($stokTelurAwal - 10, $this->telur->stock, 0.001);

        // HUKUM 5: Pesanan DP BELUM menjadi omset
        $hariIni = now()->toDateString();
        $omsetHarian = Akuntansi::omsetHarian($hariIni, $hariIni);
        $this->assertEqualsWithDelta(0, $omsetHarian[$hariIni] ?? 0, 0.01);

        // Uang masuk DP sudah diakui di Kas & Uang Muka
        $posisi = Akuntansi::posisiPada($hariIni);
        $this->assertGreaterThanOrEqual(100000, $posisi->kas);
        $this->assertEqualsWithDelta(100000, $posisi->uang_muka, 0.01);

        // 2. Pelunasan sisa 180.000 via Transfer Bank
        $this->actingAs($this->kasir)->post("/kasir/waiting-list/{$sale->id}/pay", [
            'amount' => 180000,
            'method' => 'transfer',
        ])->assertRedirect();

        $sale->refresh();
        $this->assertSame('completed', $sale->order_status);
        $this->assertEqualsWithDelta(0, $sale->remaining, 0.01);

        // Setelah lunas, omset resmi diakui penuh Rp 280.000
        $omsetBaru = Akuntansi::omsetHarian($hariIni, $hariIni);
        $this->assertEqualsWithDelta(280000, $omsetBaru[$hariIni] ?? 0, 0.01);

        // Kewajiban uang muka pelanggan kembali ke 0
        $posisiAkhir = Akuntansi::posisiPada($hariIni);
        $this->assertEqualsWithDelta(0, $posisiAkhir->uang_muka, 0.01);
    }

    /**
     * UAT Skenario 3: Retur & Tukar-Tambah Pasca Penjualan
     */
    public function test_uat_alur_retur_dan_tukar_tambah_selisih(): void
    {
        // 1. Buat penjualan awal: 5 Pcs Minyak Goreng = 75.000
        $saleRes = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $this->minyak->id, 'qty' => 5, 'unit_type' => 'base'],
            ],
            'payment_method' => 'tunai',
            'paid_amount' => 75000,
        ])->assertOk();

        $sale = Sale::with('items')->findOrFail($saleRes->json('sale_id'));
        $saleItemId = $sale->items->first()->id;

        $stokMinyakSebelumRetur = $this->minyak->fresh()->stock;
        $stokTelurSebelumRetur = $this->telur->fresh()->stock;

        // 2. Pelanggan meretur 2 Pcs Minyak (nilai: 30.000) dan TUKAR dengan 2 Kg Telur (nilai: 56.000)
        // Penambahan item baru senilai 56.000 dibayar via additional_payment 56.000
        $returRes = $this->actingAs($this->admin)->postJson('/retur', [
            'sale_id' => $sale->id,
            'reason' => 'Tukar dengan telur',
            'items' => [
                ['sale_item_id' => $saleItemId, 'qty' => 2],
            ],
            'add_items' => [
                ['product_id' => $this->telur->id, 'qty' => 2, 'unit_type' => 'base'],
            ],
            'additional_payment' => 56000,
            'additional_payment_method' => 'tunai',
        ])->assertOk();

        $this->assertTrue($returRes->json('success'));

        // 3. Verifikasi pergerakan stok:
        // Minyak kembali 2 pcs
        $this->assertEqualsWithDelta($stokMinyakSebelumRetur + 2, $this->minyak->fresh()->stock, 0.001);
        // Telur berkurang 2 kg
        $this->assertEqualsWithDelta($stokTelurSebelumRetur - 2, $this->telur->fresh()->stock, 0.001);

        // 4. Verifikasi pencatatan uang tambahan di SalePayment
        $payments = SalePayment::where('sale_id', $sale->id)->get();
        $this->assertCount(2, $payments);
        $tukarTambahPay = $payments->where('kind', 'bayar')->last();
        $this->assertEqualsWithDelta(56000, (float) $tukarTambahPay->amount, 0.01);
    }

    /**
     * UAT Skenario 4: Pembatalan Transaksi & Uji Integritas Persamaan Neraca (H7)
     */
    public function test_uat_pembatalan_transaksi_dan_keseimbangan_neraca(): void
    {
        // Set modal awal pembukuan toko
        Setting::set('pembukuan', [
            'tanggal_mulai' => now()->startOfMonth()->toDateString(),
            'saldo_awal_kas' => 10000000,
            'modal_awal' => 10000000 + ($this->minyak->stock * $this->minyak->cost_price) + ($this->telur->stock * $this->telur->cost_price),
        ]);

        $hariIni = now()->toDateString();

        // Cek posisi awal: Neraca wajib seimbang (selisih == 0)
        $posisiAwal = Akuntansi::posisiPada($hariIni);
        $this->assertEqualsWithDelta(0, $posisiAwal->selisih, 0.01, 'Neraca awal tidak seimbang.');

        // Transaksi 1: Jual 10 Pcs Minyak = 150.000 tunai
        $saleRes = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->minyak->id, 'qty' => 10, 'unit_type' => 'base']],
            'payment_method' => 'tunai',
            'paid_amount' => 150000,
        ])->assertOk();

        $saleId = $saleRes->json('sale_id');
        $sale = Sale::findOrFail($saleId);

        // Neraca setelah penjualan: Tetap seimbang
        $posisiJual = Akuntansi::posisiPada($hariIni);
        $this->assertEqualsWithDelta(0, $posisiJual->selisih, 0.01, 'Neraca timpang setelah penjualan.');

        // Batalkan penjualan melalui ReturnController::cancelSale
        $this->actingAs($this->admin)->post(route('retur.cancel-sale', $sale))->assertRedirect();

        $sale->refresh();
        $this->assertSame('cancelled', $sale->order_status);

        // HUKUM 7: Neraca setelah pembatalan tetap seimbang
        $posisiBatal = Akuntansi::posisiPada($hariIni);
        $this->assertEqualsWithDelta(0, $posisiBatal->selisih, 0.01, 'HUKUM 7 Dilanggar: Neraca tidak seimbang pasca pembatalan!');
    }

    /**
     * UAT Skenario 5: Serialisasi Katalog JSON Kasir & Multi-Satuan
     */
    public function test_uat_katalog_json_dan_struktur_multi_satuan(): void
    {
        $catalog = app(ProductCatalog::class);
        $items = $catalog->forCart();

        $this->assertIsArray($items);
        $this->assertNotEmpty($items);

        $minyakItem = collect($items)->firstWhere('sku', 'MYK001');
        $this->assertNotNull($minyakItem);
        $this->assertSame('Minyak Goreng 1L', $minyakItem['name']);
        $this->assertFalse($minyakItem['is_weighable']);
        $this->assertCount(1, $minyakItem['additional_units']);
        $this->assertSame('Dus', $minyakItem['additional_units'][0]['unit_name']);
        $this->assertEqualsWithDelta(12, $minyakItem['additional_units'][0]['conversion'], 0.001);

        $telurItem = collect($items)->firstWhere('sku', 'TLR001');
        $this->assertNotNull($telurItem);
        $this->assertTrue($telurItem['is_weighable']);
    }

    /**
     * UAT Skenario 6: Laporan Penjualan & Ekspor Tanpa Cacat
     */
    public function test_uat_laporan_penjualan_dan_ekspor(): void
    {
        // 1. Buat penjualan tunai
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [['product_id' => $this->minyak->id, 'qty' => 2, 'unit_type' => 'base']],
            'payment_method' => 'tunai',
            'paid_amount' => 30000,
        ])->assertOk();

        // 2. Akses halaman laporan penjualan
        $res = $this->actingAs($this->admin)->get('/laporan/penjualan')->assertOk();
        $this->assertStringContainsString('Rp 30.000', $res->getContent());
        $this->assertStringContainsString('Uang Masuk per Metode', $res->getContent());

        // 3. Ekspor Laporan Penjualan ke Excel
        $this->actingAs($this->admin)->get('/laporan/ekspor/penjualan/xlsx')->assertOk();
    }
}
