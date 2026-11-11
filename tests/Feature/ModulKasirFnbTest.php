<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Tests\JposTestCase;

class ModulKasirFnbTest extends JposTestCase
{
    private Product $makanan;
    private Product $minuman;

    protected function setUp(): void
    {
        parent::setUp();

        $catFood = $this->makeCategory('Makanan');
        $catDrink = $this->makeCategory('Minuman');

        $this->makanan = $this->makeProduct([
            'name' => 'Nasi Goreng Spesial',
            'sku' => 'FNB01',
            'sell_price' => 25000,
            'cost_price' => 15000,
            'stock' => 50,
            'unit' => 'Porsi',
            'category_id' => $catFood->id,
        ]);

        $this->minuman = $this->makeProduct([
            'name' => 'Es Teh Manis',
            'sku' => 'FNB02',
            'sell_price' => 6000,
            'cost_price' => 2000,
            'stock' => 100,
            'unit' => 'Gelas',
            'category_id' => $catDrink->id,
        ]);
    }

    public function test_halaman_kasir_fnb_dapat_diakses_via_parameter_layout(): void
    {
        $res = $this->actingAs($this->kasir)->get(route('kasir.index', ['layout' => 'fnb']));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('KASIR F&amp;B', $html);
        $this->assertStringContainsString('PESANAN MEJA:', $html);
        $this->assertStringContainsString('Nasi Goreng Spesial', $html);
        $this->assertStringContainsString('Es Teh Manis', $html);
        $this->assertStringContainsString('Simpan / Open Bill', $html);
        $this->assertStringContainsString('Bayar &amp; Cetak [F8]', $html);

        // Pastikan kelas-kelas elemen visual penting hadir di markup
        $this->assertStringContainsString('fnb-card-img-wrap', $html);
        $this->assertStringContainsString('fnb-card-img', $html);
        $this->assertStringContainsString('fnb-card-qty-badge', $html);
        $this->assertStringContainsString('fnb-card-add-btn', $html);
        $this->assertStringContainsString('fnb-btn-minus', $html);
        $this->assertStringContainsString('fnb-qty-box', $html);
        $this->assertStringContainsString('fnb-btn-plus', $html);
        $this->assertStringContainsString('fnb-btn-delete', $html);
        $this->assertStringContainsString('fnb-cart-item-card', $html);
        $this->assertStringContainsString('fnb-item-note-input', $html);
        $this->assertStringContainsString('fnb-total-card', $html);
        $this->assertStringContainsString('fnb-btn-openbill', $html);
        $this->assertStringContainsString('fnb-btn-pay', $html);
        $this->assertStringContainsString('fnb-cat-pill', $html);

        // Pastikan button switcher mode kasir (Modern / Klasik / F&B) di pojok kanan atas telah dihilangkan
        $this->assertStringNotContainsString('Modern</a>', $html);
        $this->assertStringNotContainsString('Klasik</a>', $html);
    }

    public function test_setting_tampilan_kasir_dapat_diatur_ke_mode_fnb(): void
    {
        $res = $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'layout' => 'fnb',
            'default_view' => 'gambar',
        ]);
        $res->assertRedirect();

        $this->assertEquals('fnb', Setting::kasirLayout());

        // Setelah diset ke FNB, membuka /kasir tanpa query layout langsung menampilkan FNB
        $kasirRes = $this->actingAs($this->kasir)->get(route('kasir.index'));
        $kasirRes->assertStatus(200);
        $this->assertStringContainsString('KASIR F&amp;B', $kasirRes->getContent());
    }

    public function test_transaksi_fnb_dapat_menyimpan_nomor_meja_dan_catatan_item(): void
    {
        $payload = [
            'customer_id' => null,
            'items' => [
                [
                    'product_id' => $this->makanan->id,
                    'qty' => 2,
                    'note' => 'Pedas level 3, telur ceplok setengah matang',
                ],
                [
                    'product_id' => $this->minuman->id,
                    'qty' => 2,
                    'note' => 'Es dipisah, gula sedikit',
                ],
            ],
            'discount' => 0,
            'paid_amount' => 100000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 08',
            'note' => '[Dine In] Tamu: Bpk. Bambang',
        ];

        $res = $this->actingAs($this->kasir)->postJson(route('kasir.store'), $payload);
        $res->assertStatus(200);

        $json = $res->json();
        $this->assertTrue($json['success']);
        $this->assertEquals('Meja 08', $json['table_no']);
        $this->assertArrayHasKey('receipt_url', $json);
        $this->assertArrayHasKey('kitchen_receipt_url', $json);

        // Verifikasi di database
        $sale = Sale::with('items')->find($json['sale_id']);
        $this->assertNotNull($sale);
        $this->assertEquals('Meja 08', $sale->table_no);
        $this->assertEquals('[Dine In] Tamu: Bpk. Bambang', $sale->note);
        $this->assertCount(2, $sale->items);

        $foodItem = $sale->items->where('product_id', $this->makanan->id)->first();
        $this->assertEquals('Pedas level 3, telur ceplok setengah matang', $foodItem->note);

        $drinkItem = $sale->items->where('product_id', $this->minuman->id)->first();
        $this->assertEquals('Es dipisah, gula sedikit', $drinkItem->note);
    }

    public function test_struk_kitchen_menampilkan_nomor_meja_dan_rincian_pesanan(): void
    {
        $sale = Sale::create([
            'invoice_no' => 'TRX-FNB-TEST-001',
            'user_id' => $this->kasir->id,
            'subtotal' => 62000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 62000,
            'paid_amount' => 62000,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'table_no' => 'Meja VIP 02',
            'note' => '[Dine In] Segera antarkan',
        ]);

        $sale->items()->create([
            'product_id' => $this->makanan->id,
            'product_name' => $this->makanan->name,
            'price' => 25000,
            'qty' => 2,
            'subtotal' => 50000,
            'note' => 'Jangan pakai seledri',
        ]);

        $res = $this->actingAs($this->kasir)->get(route('kasir.receipt.kitchen', $sale->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('*** PESANAN DAPUR / BAR ***', $html);
        $this->assertStringContainsString('Meja VIP 02', $html);
        $this->assertStringContainsString('Nasi Goreng Spesial', $html);
        $this->assertStringContainsString('Jangan pakai seledri', $html);
        $this->assertStringContainsString('Segera antarkan', $html);
    }

    public function test_struk_pelanggan_menampilkan_nomor_meja(): void
    {
        $sale = Sale::create([
            'invoice_no' => 'TRX-FNB-TEST-002',
            'user_id' => $this->kasir->id,
            'subtotal' => 25000,
            'discount' => 0,
            'tax_amount' => 0,
            'total' => 25000,
            'paid_amount' => 30000,
            'change_amount' => 5000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 05',
        ]);

        $sale->items()->create([
            'product_id' => $this->makanan->id,
            'product_name' => $this->makanan->name,
            'price' => 25000,
            'qty' => 1,
            'subtotal' => 25000,
            'note' => 'Pedas sedang',
        ]);

        $res = $this->actingAs($this->kasir)->get(route('kasir.receipt', $sale->id));
        $res->assertStatus(200);

        $html = $res->getContent();
        $this->assertStringContainsString('Meja', $html);
        $this->assertStringContainsString('Meja 05', $html);
        $this->assertStringContainsString('Pedas sedang', $html);
    }

    public function test_pengaturan_mode_produk_dapat_menyimpan_fnb_stock_mode(): void
    {
        $viewRes = $this->actingAs($this->admin)->get(route('pengaturan.mode-produk'));
        $viewRes->assertStatus(200);
        $this->assertStringContainsString('Modul Kasir F&B', $viewRes->getContent());
        $this->assertStringContainsString('fnb_stock_mode', $viewRes->getContent());

        $res = $this->actingAs($this->admin)->post(route('pengaturan.mode-produk.update'), [
            'mode' => 'sederhana',
            'fnb_stock_mode' => 'all_nostock',
        ]);
        $res->assertRedirect();

        $this->assertEquals('all_nostock', Setting::fnbStockMode());

        // Kembali ke per_product
        $this->actingAs($this->admin)->post(route('pengaturan.mode-produk.update'), [
            'mode' => 'sederhana',
            'fnb_stock_mode' => 'per_product',
        ]);
        $this->assertEquals('per_product', Setting::fnbStockMode());
    }

    public function test_pengaturan_tampilan_kasir_dapat_menyimpan_fnb_stock_mode(): void
    {
        $viewRes = $this->actingAs($this->admin)->get(route('pengaturan.tampilan-kasir'));
        $viewRes->assertStatus(200);
        $this->assertStringContainsString('fnb_stock_mode', $viewRes->getContent());

        $res = $this->actingAs($this->admin)->post(route('pengaturan.tampilan-kasir.update'), [
            'layout' => 'fnb',
            'fnb_stock_mode' => 'all_nostock',
        ]);
        $res->assertRedirect();

        $this->assertEquals('all_nostock', Setting::fnbStockMode());
    }

    public function test_master_produk_dapat_menyimpan_produk_tanpa_stok_fnb(): void
    {
        // 1. Tambah produk tanpa stok (track_stock = 0)
        $res = $this->actingAs($this->admin)->post(route('produk.store'), [
            'name' => 'Kopi Tubruk Dapur',
            'type' => 'barang',
            'track_stock' => 0,
            'sell_price' => 8000,
            'cost_price' => 3000,
            'unit' => 'Cangkir',
        ]);
        $res->assertRedirect();

        $kopi = Product::where('name', 'Kopi Tubruk Dapur')->first();
        $this->assertNotNull($kopi);
        $this->assertFalse((bool) $kopi->track_stock);
        $this->assertEquals(0, $kopi->stock);
        $this->assertFalse($kopi->tracksStock('fnb'));
        $this->assertTrue($kopi->isTanpaStok('fnb'));
    }

    public function test_produk_fnb_tanpa_stok_dapat_dijual_meski_stok_nol_tanpa_memotong_stok(): void
    {
        // Atur layout ke fnb dan mode stok ke per_product
        Setting::set('kasir_display', ['layout' => 'fnb', 'fnb_stock_mode' => 'per_product']);
        Setting::set('produk_mode', ['mode' => 'sederhana', 'fnb_stock_mode' => 'per_product']);

        // Buat produk tanpa stok (mis. masakan olahan dapur) dengan stok 0
        $menuDapur = $this->makeProduct([
            'name' => 'Nasi Rawon Spesial',
            'sku' => 'RAWON01',
            'sell_price' => 30000,
            'cost_price' => 15000,
            'stock' => 0,
            'track_stock' => false,
            'unit' => 'Porsi',
        ]);

        $payload = [
            'customer_id' => null,
            'items' => [
                [
                    'product_id' => $menuDapur->id,
                    'qty' => 5,
                    'note' => 'Sambal banyak',
                ],
            ],
            'discount' => 0,
            'paid_amount' => 150000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 12',
            'layout' => 'fnb',
        ];

        // Seharusnya berhasil disimpan dan tidak ditolak dengan galat stok tidak cukup
        $res = $this->actingAs($this->kasir)->postJson(route('kasir.store'), $payload);
        $res->assertStatus(200);

        // Verifikasi stok tidak berkurang dan tetap 0
        $menuDapur->refresh();
        $this->assertEquals(0, $menuDapur->stock);

        // Verifikasi tidak ada pergerakan stok penjualan yang tercatat
        $this->assertDatabaseMissing('stock_movements', [
            'product_id' => $menuDapur->id,
            'type' => 'sale',
        ]);
    }

    public function test_produk_fnb_dengan_stok_divalidasi_dan_memotong_stok_saat_transaksi(): void
    {
        Setting::set('kasir_display', ['layout' => 'fnb', 'fnb_stock_mode' => 'per_product']);

        // Produk dengan stok (track_stock = true) stok = 2
        $minumanKemasan = $this->makeProduct([
            'name' => 'Coca Cola Kaleng',
            'sku' => 'COLA01',
            'sell_price' => 10000,
            'cost_price' => 7000,
            'stock' => 2,
            'track_stock' => true,
            'unit' => 'Kaleng',
        ]);

        // Coba beli 3 (melebihi stok 2) -> harus 422
        $payloadGagal = [
            'items' => [
                ['product_id' => $minumanKemasan->id, 'qty' => 3],
            ],
            'paid_amount' => 30000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 01',
            'layout' => 'fnb',
        ];
        $resGagal = $this->actingAs($this->kasir)->postJson(route('kasir.store'), $payloadGagal);
        $resGagal->assertStatus(422);

        // Beli 2 (pas) -> harus berhasil dan stok menjadi 0
        $payloadSukses = [
            'items' => [
                ['product_id' => $minumanKemasan->id, 'qty' => 2],
            ],
            'paid_amount' => 20000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 01',
            'layout' => 'fnb',
        ];
        $resSukses = $this->actingAs($this->kasir)->postJson(route('kasir.store'), $payloadSukses);
        $resSukses->assertStatus(200);

        $minumanKemasan->refresh();
        $this->assertEquals(0, $minumanKemasan->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $minumanKemasan->id,
            'type' => 'sale',
            'qty' => -2,
        ]);
    }

    public function test_mode_fnb_all_nostock_mengizinkan_semua_menu_dijual_tanpa_cek_stok(): void
    {
        Setting::set('kasir_display', ['layout' => 'fnb', 'fnb_stock_mode' => 'all_nostock']);
        Setting::set('produk_mode', ['mode' => 'sederhana', 'fnb_stock_mode' => 'all_nostock']);

        // Produk dengan track_stock = true tetapi stok = 0
        $snack = $this->makeProduct([
            'name' => 'Kentang Goreng',
            'sku' => 'FRIES01',
            'sell_price' => 15000,
            'cost_price' => 8000,
            'stock' => 0,
            'track_stock' => true,
            'unit' => 'Porsi',
        ]);

        $payload = [
            'items' => [
                ['product_id' => $snack->id, 'qty' => 3],
            ],
            'paid_amount' => 45000,
            'payment_method' => 'tunai',
            'table_no' => 'Meja 02',
            'layout' => 'fnb',
        ];

        // Karena mode F&B diatur ke all_nostock, pesanan harus berhasil tanpa ditolak stok habis
        $res = $this->actingAs($this->kasir)->postJson(route('kasir.store'), $payload);
        $res->assertStatus(200);

        // Dan stok tidak dipotong
        $snack->refresh();
        $this->assertEquals(0, $snack->stock);
    }
}
