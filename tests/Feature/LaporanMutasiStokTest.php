<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Tests\JposTestCase;

class LaporanMutasiStokTest extends JposTestCase
{
    public function test_halaman_mutasi_stok_dapat_diakses(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok'));
        $response->assertOk();
        $response->assertSee('Rekap Mutasi Stok');
    }

    public function test_akumulasi_barang_masuk_dan_keluar_terhitung_akurat(): void
    {
        $product = $this->makeProduct([
            'name' => 'Royal Canin Maxi Adult 15kg',
            'stock' => 0,
            'cost_price' => 700000,
            'sell_price' => 850000,
            'unit' => 'Karung',
        ]);

        // 1. Pembelian (Kulakan Masuk) 20 karung
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'qty' => 20, 'unit_type' => 'base', 'price' => 700000]
            ],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        // 2. Penjualan Selesai (Kasir Keluar) 5 karung
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 5, 'unit_type' => 'base']
            ],
            'paid_amount' => 4250000,
            'payment_method' => 'cash',
        ])->assertOk();

        // 3. Akses laporan mutasi stok
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok'));
        $response->assertOk();
        $response->assertSee('Royal Canin Maxi Adult 15kg');
        $response->assertSee('20'); // Total Masuk
        $response->assertSee('5');  // Total Keluar
    }

    public function test_pesanan_belum_selesai_dan_batal_tidak_dihitung_sebagai_barang_keluar(): void
    {
        $product = $this->makeProduct([
            'name' => 'Bolt Tuna 20kg',
            'stock' => 50,
            'cost_price' => 350000,
            'sell_price' => 420000,
            'unit' => 'Sak',
        ]);

        // Pesanan Waiting List (DP/tertahan) - HUKUM 5: belum diserahkan, bukan omset & bukan mutasi keluar selesai
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 3, 'unit_type' => 'base']
            ],
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'is_waiting_list' => true,
            'mode_pesanan' => 'dp',
            'customer_name' => 'Pelanggan DP',
        ])->assertOk();

        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Bolt Tuna']));
        $response->assertOk();
        $response->assertSee('Bolt Tuna 20kg');
        // Total keluar harus tetap 0 karena pesanan belum selesai
        $content = $response->getContent();
        $this->assertStringContainsString('Bolt Tuna 20kg', $content);
    }

    public function test_retur_penjualan_mengurangi_barang_keluar(): void
    {
        $product = $this->makeProduct([
            'name' => 'Whiskas Ocean Fish 1kg',
            'stock' => 10,
            'cost_price' => 50000,
            'sell_price' => 65000,
            'unit' => 'Pcs',
            'type' => 'barang',
        ]);

        // Jual 5
        $res = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 5, 'unit_type' => 'base']
            ],
            'paid_amount' => 325000,
            'payment_method' => 'cash',
        ]);
        $res->assertOk();
        $saleId = $res->json('sale_id');

        $saleItem = \App\Models\SaleItem::where('sale_id', $saleId)->first();

        // Retur 2
        $this->actingAs($this->kasir)->post('/retur', [
            'sale_id' => $saleId,
            'items' => [
                ['sale_item_id' => $saleItem->id, 'qty' => 2]
            ],
            'reason' => 'Salah beli varian rasa',
        ])->assertSessionHasNoErrors();

        // Bersih yang keluar seharusnya 5 - 2 = 3
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Whiskas']));
        $response->assertOk();
        $response->assertSee('Whiskas Ocean Fish 1kg');
        $response->assertSee('3'); // Net keluar = 3
    }

    public function test_produk_jasa_tidak_muncul_di_laporan_mutasi_stok(): void
    {
        $jasa = $this->makeProduct([
            'name' => 'Jasa Grooming Kucing Petshop',
            'stock' => 0,
            'cost_price' => 0,
            'sell_price' => 75000,
            'unit' => 'Sesi',
            'type' => 'jasa',
        ]);

        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Grooming']));
        $response->assertOk();
        $response->assertDontSee('Jasa Grooming Kucing Petshop');
    }

    public function test_laporan_mutasi_stok_dapat_diekspor_ke_pdf_dan_excel(): void
    {
        $product = $this->makeProduct([
            'name' => 'Me-O Cat Treat 50g',
            'stock' => 20,
            'cost_price' => 12000,
            'sell_price' => 18000,
            'unit' => 'Pcs',
            'type' => 'barang',
        ]);

        $pdfResponse = $this->actingAs($this->admin)->get(route('laporan.ekspor', ['jenis' => 'mutasi-stok', 'format' => 'pdf']));
        $pdfResponse->assertOk();
        $this->assertStringStartsWith('%PDF', $pdfResponse->streamedContent());

        $excelResponse = $this->actingAs($this->admin)->get(route('laporan.ekspor', ['jenis' => 'mutasi-stok', 'format' => 'xlsx']));
        $excelResponse->assertOk();
        $this->assertGreaterThan(1000, strlen($excelResponse->streamedContent()));
    }

    public function test_stok_awal_terhitung_akurat_berdasarkan_mutasi(): void
    {
        $product = $this->makeProduct([
            'name' => 'Pro Plan Adult Salmon 2.5kg',
            'stock' => 10,
            'cost_price' => 200000,
            'sell_price' => 260000,
            'unit' => 'Pcs',
        ]);

        // Pembelian (masuk) 5 pcs
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'qty' => 5, 'unit_type' => 'base', 'price' => 200000]
            ],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        // Penjualan (keluar) 2 pcs
        $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 2, 'unit_type' => 'base']
            ],
            'paid_amount' => 520000,
            'payment_method' => 'cash',
        ])->assertOk();

        // Posisi stok akhir produk saat ini = 10 + 5 - 2 = 13.
        // Mutasi periode hari ini: Masuk = 5, Keluar = 2.
        // Stok awal sebelum mutasi: 13 - 5 + 2 = 10.
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Pro Plan']));
        $response->assertOk();
        $response->assertSee('Pro Plan Adult Salmon 2.5kg');
        $response->assertSee('Stok Awal');
        $items = $response->viewData('products');
        $this->assertEquals(10, (float) $items->first()->stok_awal);
        $this->assertEquals(5, (float) $items->first()->total_masuk);
        $this->assertEquals(2, (float) $items->first()->total_keluar);
        $this->assertEquals(13, (float) $items->first()->stock);
    }

    public function test_stok_awal_mendukung_presisi_pecahan_desimal_pakan(): void
    {
        \App\Models\Unit::updateOrCreate(['name' => 'Kg'], ['is_weighable' => true]);

        $product = $this->makeProduct([
            'name' => 'Whiskas Kiloan Repack',
            'stock' => 5.5,
            'cost_price' => 30000,
            'sell_price' => 45000,
            'unit' => 'Kg',
        ]);

        // Pembelian masuk 4.25 kg
        $this->actingAs($this->admin)->post('/pembelian', [
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'qty' => 4.25, 'unit_type' => 'base', 'price' => 30000]
            ],
            'bayar' => 'tunai',
        ])->assertSessionHasNoErrors();

        // Penjualan kasir keluar 1.75 kg (45000 * 1.75 = 78750)
        $kasirRes = $this->actingAs($this->kasir)->postJson('/kasir', [
            'items' => [
                ['product_id' => $product->id, 'qty' => 1.75, 'unit_type' => 'base']
            ],
            'paid_amount' => 100000,
            'payment_method' => 'cash',
        ]);
        $kasirRes->assertOk();

        // Stok akhir = 5.5 + 4.25 - 1.75 = 8.0
        // Stok awal = 8.0 - 4.25 + 1.75 = 5.5
        $response = $this->actingAs($this->admin)->get(route('laporan.mutasi-stok', ['q' => 'Whiskas Kiloan']));
        $response->assertOk();
        $items = $response->viewData('products');
        $this->assertEquals(5.5, (float) $items->first()->stok_awal);
        $this->assertEquals(4.25, (float) $items->first()->total_masuk);
        $this->assertEquals(1.75, (float) $items->first()->total_keluar);
        $this->assertEquals(8.0, (float) $items->first()->stock);
    }
}
