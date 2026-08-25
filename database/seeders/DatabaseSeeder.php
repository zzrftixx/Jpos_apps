<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $admin = Role::updateOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'permissions' => array_keys(Role::allMenuKeys()),
            'is_system' => true,
        ]);

        $kasirRole = Role::updateOrCreate(['slug' => 'kasir'], [
            'name' => 'Kasir',
            'permissions' => ['dashboard', 'kasir', 'retur', 'pelanggan'],
            'is_system' => true,
        ]);

        // Users
        User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'email' => 'admin@jpos.local',
            'password' => Hash::make('password'),
            'role_id' => $admin->id,
            'is_active' => true,
        ]);

        User::updateOrCreate(['username' => 'kasir1'], [
            'name' => 'Kasir 1',
            'email' => 'kasir1@jpos.local',
            'password' => Hash::make('password'),
            'role_id' => $kasirRole->id,
            'is_active' => true,
        ]);

        // Categories
        $cats = ['Makanan', 'Minuman', 'Snack', 'Rokok', 'Lainnya'];
        foreach ($cats as $c) {
            Category::updateOrCreate(['slug' => Str::slug($c)], ['name' => $c]);
        }

        // Supplier
        $supplier = Supplier::updateOrCreate(['name' => 'Supplier Umum'], [
            'contact_person' => 'Budi',
            'phone' => '081234567890',
            'address' => 'Jl. Contoh No. 1',
        ]);

        // Products sample
        $makanan = Category::where('slug', 'makanan')->first();
        $minuman = Category::where('slug', 'minuman')->first();
        $snack = Category::where('slug', 'snack')->first();

        $products = [
            ['name' => 'Nasi Goreng', 'category_id' => $makanan->id, 'cost_price' => 8000, 'sell_price' => 15000, 'stock' => 50, 'unit' => 'porsi'],
            ['name' => 'Mie Goreng', 'category_id' => $makanan->id, 'cost_price' => 7000, 'sell_price' => 13000, 'stock' => 50, 'unit' => 'porsi'],
            ['name' => 'Es Teh Manis', 'category_id' => $minuman->id, 'cost_price' => 2000, 'sell_price' => 5000, 'stock' => 100, 'unit' => 'gelas'],
            ['name' => 'Kopi Hitam', 'category_id' => $minuman->id, 'cost_price' => 3000, 'sell_price' => 8000, 'stock' => 100, 'unit' => 'gelas'],
            ['name' => 'Keripik Singkong', 'category_id' => $snack->id, 'cost_price' => 4000, 'sell_price' => 7000, 'stock' => 40, 'unit' => 'pcs'],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(['sku' => 'SKU-' . Str::upper(Str::random(6))], array_merge($p, [
                'supplier_id' => $supplier->id,
                'barcode' => (string) random_int(1000000000000, 9999999999999),
                'min_stock' => 5,
                'is_taxable' => true,
                'is_active' => true,
            ]));
        }

        // Customer
        Customer::updateOrCreate(['name' => 'Pelanggan Umum'], ['phone' => '-']);

        // Settings default
        Setting::set('store_profile', [
            'name' => 'JPOS by JaylaTech',
            'address' => 'Jl. Contoh No. 1, Kota Anda',
            'phone' => '0800-0000-0000',
            'email' => 'toko@jpos.test',
            'logo' => null,
            'footer_note' => 'Terima kasih telah berbelanja!',
        ]);

        Setting::set('tax', [
            'enabled' => false,
            'name' => 'PPN',
            'percent' => 11,
            'include_in_price' => false,
        ]);

        Setting::set('printer_struk', [
            'paper_size' => '80', // 58 or 80 mm
            'font_size' => 12,
            'auto_print' => false,
            'printer_name' => '',
        ]);

        Setting::set('printer_barcode', [
            'label_width' => 40,
            'label_height' => 25,
            'printer_name' => '',
        ]);

        Setting::set('template_barcode', [
            'show_name' => true,
            'show_price' => true,
            'show_barcode_text' => true,
            'barcode_type' => 'CODE128',
        ]);

        Setting::set('template_struk', [
            'show_logo' => false,
            'show_address' => true,
            'show_phone' => true,
            'show_cashier' => true,
            'show_customer' => true,
            'header_note' => '',
            'footer_note' => 'Terima kasih telah berbelanja!',
        ]);

        Setting::set('cloud_sync', [
            'enabled' => false,
            'endpoint' => '',
            'api_key' => '',
            'auto_sync' => false,
            'last_synced_at' => null,
        ]);
    }
}
