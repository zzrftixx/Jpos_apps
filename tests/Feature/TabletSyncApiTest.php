<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\CashTransaction;
use App\Http\Middleware\VerifyTabletSyncToken;
use Tests\JposTestCase;

class TabletSyncApiTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $token = VerifyTabletSyncToken::getOrGenerateToken();
        $this->withHeader('X-JPOS-TABLET-TOKEN', $token);
    }

    public function test_api_status_mengembalikan_informasi_server_aktif(): void
    {
        $response = $this->getJson('/api/tablet/status');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'server_name',
                'server_time',
                'version',
                'total_products',
            ])
            ->assertJson(['status' => 'online']);
    }

    public function test_api_katalog_menghasilkan_daftar_produk_lengkap(): void
    {
        $cat = Category::firstOrCreate(['slug' => 'minuman'], ['name' => 'Minuman', 'slug' => 'minuman']);
        $prod = Product::create([
            'name' => 'Kopi Tablet Test',
            'barcode' => '899123456789',
            'sku' => 'KOP-TAB-01',
            'sell_price' => 15000,
            'cost_price' => 10000,
            'stock' => 50,
            'unit' => 'Cup',
            'category_id' => $cat->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/tablet/katalog');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'synced_at',
                'store',
                'products',
                'categories',
            ]);

        $this->assertTrue(collect($response->json('products'))->contains('name', 'Kopi Tablet Test'));
    }

    public function test_api_sync_transaksi_berhasil_menyimpan_transaksi_offline_dan_mencatat_sale_payments(): void
    {
        $prod = Product::create([
            'name' => 'Snack Tablet Test',
            'barcode' => '999123456789',
            'sku' => 'SNK-TAB-01',
            'sell_price' => 20000,
            'cost_price' => 15000,
            'stock' => 10,
            'unit' => 'Bks',
            'is_active' => true,
        ]);

        $offlineId = 'TAB-TEST-' . uniqid();
        $payload = [
            'sales' => [
                [
                    'offline_id' => $offlineId,
                    'created_at' => now()->toIso8601String(),
                    'cashier_name' => 'Kasir Tablet 1',
                    'subtotal' => 40000,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'total' => 40000,
                    'paid_amount' => 50000,
                    'change_amount' => 10000,
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'product_id' => $prod->id,
                            'qty' => 2,
                            'price' => 20000,
                            'subtotal' => 40000,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/tablet/sync-transaksi', $payload);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'synced_count' => 1,
            ]);

        // Cek stok terpotong: dari 10 berkurang 2 = 8
        $prod->refresh();
        $this->assertEquals(8, $prod->stock);

        // Cek data transaksi tersimpan
        $sale = Sale::where('note', 'like', "%[OFFLINE_ID:{$offlineId}]%")->first();
        $this->assertNotNull($sale);
        $this->assertEquals(40000, $sale->total);

        // Integritas Akuntansi: SalePayment wajib tercatat (amount = paid - change = 40.000)
        $payment = SalePayment::where('sale_id', $sale->id)->first();
        $this->assertNotNull($payment, 'SalePayment wajib tercatat untuk transaksi tablet');
        $this->assertEquals(40000.0, (float) $payment->amount);
        $this->assertEquals('tunai', $payment->method);
        $this->assertEquals('bayar', $payment->kind);

        // Tidak boleh membuat CashTransaction (karena kas penjualan dihitung dari Sale / SalePayment)
        $cashTx = CashTransaction::where('description', 'like', "%{$sale->invoice_no}%")->first();
        $this->assertNull($cashTx, 'Penjualan tablet tidak boleh membuat CashTransaction agar tidak terjadi double-count kas');

        // Uji idempoten / cegah duplikasi: jika dikirim ulang, tidak boleh menambah transaksi lagi
        $responseRetry = $this->postJson('/api/tablet/sync-transaksi', $payload);
        $responseRetry->assertOk();
        $this->assertEquals(1, Sale::where('note', 'like', "%[OFFLINE_ID:{$offlineId}]%")->count());
        $this->assertEquals(1, SalePayment::where('sale_id', $sale->id)->count());
    }

    public function test_api_sync_transaksi_qris_tercatat_di_sale_payments(): void
    {
        $prod = Product::create([
            'name' => 'Minuman Tablet QRIS',
            'barcode' => '888123456789',
            'sku' => 'MNM-TAB-02',
            'sell_price' => 25000,
            'cost_price' => 15000,
            'stock' => 15,
            'unit' => 'Btl',
            'is_active' => true,
        ]);

        $offlineId = 'TAB-QRIS-' . uniqid();
        $payload = [
            'sales' => [
                [
                    'offline_id' => $offlineId,
                    'created_at' => now()->toIso8601String(),
                    'cashier_name' => 'Kasir Tablet 2',
                    'subtotal' => 25000,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'total' => 25000,
                    'paid_amount' => 25000,
                    'change_amount' => 0,
                    'payment_method' => 'qris',
                    'items' => [
                        [
                            'product_id' => $prod->id,
                            'qty' => 1,
                            'price' => 25000,
                            'subtotal' => 25000,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/tablet/sync-transaksi', $payload);
        $response->assertOk();

        $sale = Sale::where('note', 'like', "%[OFFLINE_ID:{$offlineId}]%")->first();
        $this->assertNotNull($sale);

        $payment = SalePayment::where('sale_id', $sale->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(25000.0, (float) $payment->amount);
        $this->assertEquals('qris', $payment->method);
    }

    public function test_api_tablet_tanpa_token_ditolak_401(): void
    {
        $response = $this->withHeaders(['X-JPOS-TABLET-TOKEN' => ''])->getJson('/api/tablet/status');
        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_api_tablet_dengan_token_salah_ditolak_403(): void
    {
        $response = $this->withHeaders(['X-JPOS-TABLET-TOKEN' => 'TOKEN_PALSU_12345678901234567890'])->getJson('/api/tablet/status');
        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    public function test_api_katalog_mendukung_etag_dan_304_not_modified(): void
    {
        $response1 = $this->getJson('/api/tablet/katalog');
        $response1->assertOk();
        $this->assertTrue($response1->headers->has('ETag'));

        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag);

        $response2 = $this->withHeader('If-None-Match', $etag)->getJson('/api/tablet/katalog');
        $response2->assertStatus(304);
    }
}

