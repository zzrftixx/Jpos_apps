<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use Tests\JposTestCase;

class RestaurantTableBarcodeTest extends JposTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->admin;

        Setting::set('kasir_display', ['layout' => 'fnb']);
        Setting::set('fnb_stock_mode', 'per_product');
    }

    public function test_authenticated_user_can_access_table_management_page()
    {
        $response = $this->actingAs($this->user)->get(route('meja.index'));

        $response->assertOk();
        $response->assertSee('Pengelolaan Meja');
        $response->assertSee('Meja 01');
    }

    public function test_user_can_create_new_table()
    {
        $payload = [
            'name' => 'Meja VIP 88',
            'code' => 'VIP88',
            'capacity' => 10,
            'description' => 'Lantai 2 Balkon VIP',
            'status' => 'available',
            'is_active' => true,
            'sort_order' => 50,
        ];

        $response = $this->actingAs($this->user)->post(route('meja.store'), $payload);

        $response->assertRedirect(route('meja.index'));
        $this->assertDatabaseHas('restaurant_tables', [
            'name' => 'Meja VIP 88',
            'code' => 'VIP88',
            'capacity' => 10,
        ]);
    }

    public function test_table_code_must_be_unique()
    {
        RestaurantTable::create([
            'name' => 'Meja Lama',
            'code' => 'M99',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->user)->post(route('meja.store'), [
            'name' => 'Meja Baru Bentrok',
            'code' => 'M99',
            'status' => 'available',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_user_can_update_table()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja Awal',
            'code' => 'MA01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->user)->put(route('meja.update', $table), [
            'name' => 'Meja Terupdate',
            'code' => 'MA01',
            'capacity' => 6,
            'status' => 'reserved',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('meja.index'));
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'name' => 'Meja Terupdate',
            'capacity' => 6,
            'status' => 'reserved',
        ]);
    }

    public function test_user_can_delete_table_without_active_orders()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja Hapus',
            'code' => 'DEL01',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->user)->delete(route('meja.destroy', $table));

        $response->assertRedirect(route('meja.index'));
        $this->assertDatabaseMissing('restaurant_tables', [
            'id' => $table->id,
        ]);
    }

    public function test_user_can_quick_generate_multiple_tables()
    {
        $response = $this->actingAs($this->user)->post(route('meja.quick-generate'), [
            'prefix' => 'Saung',
            'start_number' => 1,
            'count' => 5,
            'capacity' => 6,
            'description' => 'Area Saung Taman',
        ]);

        $response->assertRedirect(route('meja.index'));
        $this->assertDatabaseHas('restaurant_tables', [
            'name' => 'Saung 01',
            'capacity' => 6,
        ]);
        $this->assertDatabaseHas('restaurant_tables', [
            'name' => 'Saung 05',
            'capacity' => 6,
        ]);
    }

    public function test_print_barcodes_view_renders_table_qr_and_barcodes()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja Tes Cetak',
            'code' => 'TC01',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->user)->get(route('meja.cetak-barcode', ['ids' => $table->id, 'layout' => 'tent_card']));

        $response->assertOk();
        $response->assertSee('Meja Tes Cetak');
        $response->assertSee('TC01');
        $response->assertSee($table->order_url);
    }

    public function test_customer_can_open_public_self_order_menu_page()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 07',
            'code' => 'M07',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Makanan Utama', 'slug' => 'makanan-utama']);
        $product = $this->makeProduct([
            'name' => 'Nasi Goreng Spesial',
            'sell_price' => 25000,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        // Akses publik tanpa login
        $response = $this->get(route('meja.order', $table->code));

        $response->assertOk();
        $response->assertSee('Meja 07');
        $response->assertSee('Nasi Goreng Spesial');
        $response->assertSee('25000');
    }

    public function test_customer_can_submit_self_order_and_creates_waiting_sale_in_kasir()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 05',
            'code' => 'M05',
            'status' => 'available',
            'is_active' => true,
        ]);

        $productWithStock = $this->makeProduct([
            'name' => 'Es Jeruk Nipis',
            'sell_price' => 10000,
            'stock' => 20,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $productNoStock = $this->makeProduct([
            'name' => 'Mie Goreng Dapur',
            'sell_price' => 18000,
            'stock' => 0,
            'track_stock' => false,
            'is_active' => true,
        ]);

        $payload = [
            'customer_name' => 'Budi Susanto',
            'customer_note' => 'Jangan pakai seledri ya',
            'items' => [
                [
                    'product_id' => $productWithStock->id,
                    'qty' => 2,
                    'note' => 'Es sedikit',
                ],
                [
                    'product_id' => $productNoStock->id,
                    'qty' => 1,
                    'note' => 'Pedas level 3',
                ],
            ],
        ];

        $response = $this->postJson(route('meja.order.submit', $table->code), $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        // Verifikasi Sale tercatat di kasir dengan nomor meja dan status waiting
        $this->assertDatabaseHas('sales', [
            'table_no' => 'Meja 05',
            'order_status' => 'waiting',
            'total' => 38000, // (10000 * 2) + (18000 * 1)
            'paid_amount' => 0,
        ]);

        $sale = Sale::where('table_no', 'Meja 05')->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertStringContainsString('Budi Susanto', $sale->note);
        $this->assertStringContainsString('Jangan pakai seledri', $sale->note);

        // Verifikasi SaleItems tercatat dengan catatan masing-masing
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productWithStock->id,
            'qty' => 2,
            'note' => 'Es sedikit',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productNoStock->id,
            'qty' => 1,
            'note' => 'Pedas level 3',
        ]);

        // Verifikasi pengurangan stok untuk produk yang melacak stok
        $this->assertEquals(18, $productWithStock->fresh()->stock);

        // Verifikasi status meja diperbarui menjadi occupied
        $this->assertEquals('occupied', $table->fresh()->status);
    }

    public function test_customer_order_validates_insufficient_stock_when_tracking_stock()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 02',
            'code' => 'M02',
            'status' => 'available',
            'is_active' => true,
        ]);

        $product = $this->makeProduct([
            'name' => 'Kopi Arabika Premium',
            'sell_price' => 30000,
            'stock' => 1,
            'track_stock' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson(route('meja.order.submit', $table->code), [
            'customer_name' => 'Andi',
            'items' => [
                [
                    'product_id' => $product->id,
                    'qty' => 5, // melebihi stok 1
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('tidak mencukupi', $response->json('message'));
    }

    public function test_customer_order_status_page_displays_order_confirmation()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 03',
            'code' => 'M03',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-0001',
            'table_no' => $table->name,
            'order_status' => 'waiting',
            'subtotal' => 20000,
            'total' => 20000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'menunggu_kasir',
            'status' => 'pending',
        ]);

        $response = $this->get(route('meja.order.status', ['code' => $table->code, 'sale' => $sale->id]));

        $response->assertOk();
        $response->assertSee('Meja 03');
        $response->assertSee('INV-TEST-0001');
        $response->assertSee('Pesanan Berhasil Dikirim');
    }

    public function test_load_table_order_to_cashier_loads_cart_and_redirects()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 08',
            'code' => 'M08',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $product = $this->makeProduct([
            'name' => 'Ayam Bakar Madu',
            'sell_price' => 35000,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-LOAD-01',
            'table_no' => $table->name,
            'order_status' => 'waiting',
            'subtotal' => 35000,
            'total' => 35000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'status' => 'pending',
            'payment_method' => 'menunggu_kasir',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'qty' => 1,
            'price' => 35000,
            'subtotal' => 35000,
            'unit_conversion' => 1,
            'cost_price_snapshot' => 20000,
            'note' => 'Pedas manis',
        ]);

        $response = $this->actingAs($this->user)->get(route('meja.buka-kasir', $table));

        $response->assertRedirect(route('kasir.index'));
        $response->assertSessionHas('kasir_table_no', 'Meja 08');
        $response->assertSessionHas('kasir_keranjang_diambil');
    }

    public function test_active_orders_api_returns_json_with_order_counts()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 04',
            'code' => 'M04',
            'status' => 'available',
            'is_active' => true,
        ]);

        Sale::create([
            'invoice_no' => 'INV-API-01',
            'table_no' => $table->name,
            'order_status' => 'waiting',
            'total' => 50000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->user)->get(route('meja.active-orders'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'total_waiting' => 1,
        ]);

        $this->assertNotEmpty($response->json('recent_orders'));
        $this->assertEquals('INV-API-01', $response->json('recent_orders.0.invoice_no'));
        $this->assertEquals('Meja 04', $response->json('recent_orders.0.table_no'));
        $this->assertNotNull($response->json('latest_order_id'));

        $tables = collect($response->json('tables'));
        $targetTable = $tables->firstWhere('name', 'Meja 04');
        $this->assertNotNull($targetTable);
        $this->assertEquals(1, $targetTable['order_count']);
    }

    public function test_fnb_cashier_receives_dynamic_tables_and_orders()
    {
        RestaurantTable::create([
            'name' => 'Meja Khusus FnB',
            'code' => 'MK01',
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('kasir.index', ['layout' => 'fnb']));

        $response->assertOk();
        $response->assertSee('Meja Khusus FnB');
        $response->assertSee('MK01');
        $response->assertSee('notifikasiPesananMeja');
    }

    public function test_cashier_can_load_specific_waiting_order_via_buka_order_route()
    {
        $table = RestaurantTable::create([
            'name' => 'Meja 09',
            'code' => 'M09',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Kopi Latte',
            'sku' => 'LATTE-01',
            'sell_price' => 25000,
            'cost_price' => 10000,
            'stock' => 10,
            'type' => 'barang',
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_no' => 'INV-NOTIF-01',
            'table_no' => $table->name,
            'order_status' => 'waiting',
            'total' => 25000,
            'paid_amount' => 0,
            'change_amount' => 0,
            'payment_method' => 'cash',
        ]);

        \App\Models\SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 25000,
            'qty' => 1,
            'subtotal' => 25000,
        ]);

        $response = $this->actingAs($this->user)->get(route('meja.buka-order', $sale));

        $response->assertRedirect(route('kasir.index'));
        $response->assertSessionHas('kasir_keranjang_diambil');
        $response->assertSessionHas('kasir_table_no', 'Meja 09');
    }

    public function test_cashier_page_renders_prominent_table_order_popup_modal()
    {
        $response = $this->actingAs($this->user)->get(route('kasir.index'));

        $response->assertOk();
        $response->assertSee('Pesanan Meja Baru Masuk!');
        $response->assertSee('z-index: 9999999');
        $response->assertSee('Muat ke Kasir');
        $response->assertSee('Langsung Lunasi');
    }
}
