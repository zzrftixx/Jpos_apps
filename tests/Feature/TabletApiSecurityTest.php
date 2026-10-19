<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyTabletSyncToken;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\NetworkInfo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabletApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin',
            'permissions' => ['all'],
            'is_system' => true,
        ]);

        User::create([
            'name' => 'Admin Test',
            'username' => 'admin',
            'email' => 'admin@test.local',
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_api_tablet_ditolak_jika_tanpa_token(): void
    {
        $response = $this->getJson('/api/tablet/status');
        $response->assertStatus(401);
        $response->assertJson(['success' => false]);

        $responseKat = $this->getJson('/api/tablet/katalog');
        $responseKat->assertStatus(401);

        $responseSync = $this->postJson('/api/tablet/sync-transaksi', ['sales' => []]);
        $responseSync->assertStatus(401);
    }

    public function test_api_tablet_ditolak_jika_token_salah(): void
    {
        VerifyTabletSyncToken::getOrGenerateToken();

        $response = $this->withHeader('X-JPOS-TABLET-TOKEN', 'TOKEN_PALSU_12345')
            ->getJson('/api/tablet/status');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_api_tablet_berhasil_dengan_token_valid_dan_tidak_membocorkan_hpp(): void
    {
        $token = VerifyTabletSyncToken::getOrGenerateToken();

        $category = Category::create(['name' => 'Makanan', 'slug' => 'makanan']);
        Product::create([
            'name' => 'Kopi Susu',
            'sku' => 'KOPI-01',
            'category_id' => $category->id,
            'sell_price' => 15000,
            'cost_price' => 8000, // HPP rahasia toko
            'stock' => 50,
            'unit' => 'Cup',
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-JPOS-TABLET-TOKEN', $token)
            ->getJson('/api/tablet/katalog');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Pastikan harga jual ada, tapi cost_price (HPP) tidak dibocorkan
        $data = $response->json('products.0');
        $this->assertEquals('Kopi Susu', $data['name']);
        $this->assertEquals(15000, $data['price']);
        $this->assertArrayNotHasKey('cost_price', $data);
    }

    public function test_sync_transaksi_tablet_memotong_stok_dan_mencatat_pembayaran_secara_atomic(): void
    {
        $token = VerifyTabletSyncToken::getOrGenerateToken();

        $category = Category::create(['name' => 'Minuman', 'slug' => 'minuman']);
        $product = Product::create([
            'name' => 'Teh Botol',
            'sku' => 'TEH-01',
            'category_id' => $category->id,
            'sell_price' => 5000,
            'cost_price' => 3000,
            'stock' => 20,
            'unit' => 'Btl',
            'is_active' => true,
        ]);

        $payload = [
            'sales' => [
                [
                    'offline_id' => 'TAB-TEST-001',
                    'subtotal' => 10000,
                    'total' => 10000,
                    'paid_amount' => 10000,
                    'change_amount' => 0,
                    'payment_method' => 'cash',
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'qty' => 2,
                            'price' => 5000,
                            'subtotal' => 10000,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->withHeader('X-JPOS-TABLET-TOKEN', $token)
            ->postJson('/api/tablet/sync-transaksi', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'synced_count' => 1]);

        // Verifikasi stok berkurang 2 (20 -> 18)
        $this->assertEquals(18, $product->fresh()->stock);

        // Verifikasi mutasi stok tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'sale',
            'qty' => -2,
            'stock_after' => 18,
        ]);

        // Verifikasi sale_payments tercatat
        $this->assertDatabaseHas('sale_payments', [
            'amount' => 10000,
            'kind' => 'bayar',
        ]);
    }

    public function test_sanitasi_clean_domain_anti_ssrf_dan_loopback_hijacking(): void
    {
        // 1. Dilarang IP address loopback / lan
        $this->assertNull(NetworkInfo::cleanDomain('127.0.0.1'));
        $this->assertNull(NetworkInfo::cleanDomain('192.168.1.10'));
        $this->assertNull(NetworkInfo::cleanDomain('0.0.0.0'));

        // 2. Dilarang reserved / local loopback hostnames
        $this->assertNull(NetworkInfo::cleanDomain('localhost'));
        $this->assertNull(NetworkInfo::cleanDomain('loopback'));
        $this->assertNull(NetworkInfo::cleanDomain('broadcasthost'));
        $this->assertNull(NetworkInfo::cleanDomain('sub.localhost'));
        $this->assertNull(NetworkInfo::cleanDomain('metadata.google.internal'));

        // 3. Dilarang kata tunggal tanpa TLD / dot
        $this->assertNull(NetworkInfo::cleanDomain('kasir'));
        $this->assertNull(NetworkInfo::cleanDomain('pos'));

        // 4. Dilarang karakter injeksi shell atau metakarakter
        $this->assertNull(NetworkInfo::cleanDomain('kasir.toko;calc.exe'));
        $this->assertNull(NetworkInfo::cleanDomain('kasir.toko|whoami'));
        $this->assertNull(NetworkInfo::cleanDomain('`id`.kasir.toko'));
        $this->assertNull(NetworkInfo::cleanDomain('kasir"toko'));

        // 5. Valid local domains harus diterima dan dibersihkan
        $this->assertEquals('kasir.toko', NetworkInfo::cleanDomain('kasir.toko'));
        $this->assertEquals('pos.lan', NetworkInfo::cleanDomain('http://pos.lan:8000/path'));
        $this->assertEquals('toko.local', NetworkInfo::cleanDomain('https://TOKO.LOCAL/'));
        $this->assertEquals('kasir-dua.pos.lan', NetworkInfo::cleanDomain('kasir-dua.pos.lan'));
    }
}
