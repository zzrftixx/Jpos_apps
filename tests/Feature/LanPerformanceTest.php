<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Support\ProductCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\JposTestCase;

class LanPerformanceTest extends JposTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(ProductCatalog::class)->flush();
    }

    protected function tearDown(): void
    {
        app(ProductCatalog::class)->flush();
        parent::tearDown();
    }

    public function test_katalog_kasir_dan_produk_bebas_url_localhost_saat_dibuka_dari_ip_lan(): void
    {
        $this->makeProduct([
            'name' => 'Kopi Susu Gula Aren',
            'image' => 'products/kopi-lan-test.png',
        ]);
        $this->makeProduct([
            'name' => 'Roti Bakar Keju',
            'image' => null,
        ]);

        // Hangatkan cache katalog seolah dijalankan dari localhost/CLI lebih dulu
        $catalog = app(ProductCatalog::class);
        $warm = $catalog->forCart();
        $this->assertNotEmpty($warm);
        $this->assertStringStartsWith('/media/products/', $warm[0]['image_url']);

        // Buka modul kasir dan master produk menggunakan host IP LAN
        $kasirHtml = $this->actingAs($this->kasir)
            ->get('http://192.168.1.50:8000/kasir')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('http://localhost/media/', $kasirHtml);
        $this->assertStringNotContainsString('http:\/\/localhost\/media\/', $kasirHtml);
        $this->assertStringNotContainsString('http://localhost/images/no-image.svg', $kasirHtml);
        $this->assertStringNotContainsString('http:\/\/localhost\/images\/no-image.svg', $kasirHtml);
        $this->assertStringContainsString('kopi-lan-test.png', $kasirHtml);
        $this->assertStringContainsString('no-image.svg', $kasirHtml);

        $fnbHtml = $this->actingAs($this->kasir)
            ->get('http://192.168.1.50:8000/kasir?layout=fnb')
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('http://localhost/media/', $fnbHtml);
        $this->assertStringNotContainsString('http:\/\/localhost\/media\/', $fnbHtml);

        $produkHtml = $this->actingAs($this->admin)
            ->get('http://192.168.1.50:8000/master/produk')
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('http://localhost/media/', $produkHtml);
        $this->assertStringNotContainsString('http://localhost/images/no-image.svg', $produkHtml);
        $this->assertStringContainsString('/media/products/kopi-lan-test.png', $produkHtml);
    }

    public function test_cache_lama_yang_masih_menyimpan_localhost_otomatis_disegarkan(): void
    {
        $this->makeProduct([
            'name' => 'Teh Tarik',
            'image' => 'products/teh-tarik.png',
        ]);

        $catalog = app(ProductCatalog::class);
        $valid = $catalog->forCart();

        // Simulasikan cache lawas yang sempat menyimpan http://localhost
        $store = Cache::store('file');
        $stale = $valid;
        $stale[0]['image_url'] = 'http://localhost/media/products/teh-tarik.png';
        $store->put('jpos:catalog:cart:v1', $stale, 3600);

        $refreshed = $catalog->forCart();
        $this->assertSame('/media/products/teh-tarik.png', $refreshed[0]['image_url']);
    }

    public function test_kompresi_gzip_aktif_untuk_halaman_kasir_dan_produk(): void
    {
        $this->makeProduct(['name' => 'Produk Gzip Test']);

        foreach (['/kasir', '/master/produk'] as $uri) {
            $raw = $this->actingAs($this->admin)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->get($uri)
                ->assertOk()
                ->getContent();

            $res = $this->actingAs($this->admin)
                ->withHeaders(['Accept-Encoding' => 'gzip, deflate'])
                ->get($uri)
                ->assertOk();

            $this->assertSame('gzip', $res->headers->get('Content-Encoding'));
            $this->assertStringContainsString('Accept-Encoding', (string) $res->headers->get('Vary'));

            $compressed = $res->getContent();
            $this->assertLessThan(strlen($raw), strlen($compressed));

            $decoded = gzdecode($compressed);
            $this->assertNotFalse($decoded);
            $this->assertStringContainsString('Produk Gzip Test', $decoded);
        }
    }

    public function test_rute_media_dan_detak_bebas_session_sqlite_dan_mendukung_etag(): void
    {
        $dir = storage_path('app/public/products');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $filePath = $dir . '/test-lan-img.png';
        // 1x1 PNG valid
        file_put_contents($filePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        ));

        try {
            DB::enableQueryLog();
            DB::flushQueryLog();

            $res = $this->get('/media/products/test-lan-img.png')->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            $sessionQueries = array_filter(
                $queries,
                fn (array $q) => str_contains(strtolower($q['query']), 'sessions')
            );
            $this->assertCount(0, $sessionQueries, 'Rute /media tidak boleh melakukan query ke tabel sessions.');
            $this->assertCount(0, $res->headers->getCookies(), 'Rute /media tidak boleh mengirim cookie session.');

            $etag = $res->headers->get('ETag');
            $this->assertNotEmpty($etag);

            $this->withHeaders(['If-None-Match' => $etag])
                ->get('/media/products/test-lan-img.png')
                ->assertStatus(304);

            DB::enableQueryLog();
            DB::flushQueryLog();
            $detak = $this->get('/jpos-detak');
            $detakQueries = DB::getQueryLog();
            DB::disableQueryLog();

            $detak->assertSuccessful();
            $this->assertCount(0, $detak->headers->getCookies(), 'Rute /jpos-detak tidak boleh membuat cookie session.');
            $this->assertCount(0, $detakQueries, 'Rute /jpos-detak tidak boleh mengakses database.');
        } finally {
            @unlink($filePath);
        }
    }

    public function test_active_orders_mengembalikan_unchanged_bila_sig_sama_tanpa_query_berat(): void
    {
        RestaurantTable::create([
            'name' => 'Meja 1',
            'code' => 'MJ-01',
            'capacity' => 4,
            'status' => 'tersedia',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $first = $this->actingAs($this->kasir)
            ->getJson(route('meja.active-orders'))
            ->assertOk()
            ->json();

        $this->assertTrue($first['success']);
        $this->assertNotEmpty($first['sig']);
        $this->assertArrayHasKey('recent_orders', $first);

        DB::enableQueryLog();
        DB::flushQueryLog();

        $second = $this->actingAs($this->kasir)
            ->getJson(route('meja.active-orders', ['sig' => $first['sig']]))
            ->assertOk()
            ->json();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($second['success']);
        $this->assertTrue($second['unchanged']);
        $this->assertArrayNotHasKey('recent_orders', $second);
        $this->assertLessThanOrEqual(2, count($queries));

        $itemQueries = array_filter(
            $queries,
            fn (array $q) => str_contains(strtolower($q['query']), 'sale_items')
        );
        $this->assertCount(0, $itemQueries, 'Polling unchanged tidak boleh meng-query sale_items.');

        // Begitu ada pesanan waiting baru masuk, sig lama otomatis invalid dan payload penuh dikembalikan
        Sale::create([
            'invoice_no' => 'INV-LAN-001',
            'user_id' => $this->kasir->id,
            'subtotal' => 25000,
            'discount' => 0,
            'tax' => 0,
            'total' => 25000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'tunai',
            'order_status' => 'waiting',
            'table_no' => 'Meja 1',
        ]);

        $third = $this->actingAs($this->kasir)
            ->getJson(route('meja.active-orders', ['sig' => $first['sig']]))
            ->assertOk()
            ->json();

        $this->assertTrue($third['success']);
        $this->assertArrayNotHasKey('unchanged', $third);
        $this->assertNotSame($first['sig'], $third['sig']);
        $this->assertSame(1, $third['total_waiting']);
    }

    public function test_halaman_memakai_favicon_ringan_lazy_image_dan_meta_detak(): void
    {
        $this->assertFileExists(public_path('images/favicon.png'));
        $this->assertLessThan(20000, filesize(public_path('images/favicon.png')));

        $this->makeProduct(['name' => 'Produk Lazy']);

        $kasirHtml = $this->actingAs($this->kasir)->get('/kasir')->assertOk()->getContent();
        $this->assertStringContainsString('favicon.png', $kasirHtml);
        $this->assertStringContainsString('meta name="jpos-detak-url"', $kasirHtml);
        $this->assertStringContainsString('loading="lazy" decoding="async"', $kasirHtml);

        $produkHtml = $this->actingAs($this->admin)->get('/master/produk')->assertOk()->getContent();
        $this->assertStringContainsString('favicon.png', $produkHtml);
        $this->assertStringContainsString('loading="lazy" decoding="async"', $produkHtml);
    }
}
