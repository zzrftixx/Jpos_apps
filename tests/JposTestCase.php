<?php

namespace Tests;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * Basis test untuk seluruh UAT JPOS.
 *
 * Memakai SQLite in-memory (lihat phpunit.xml) sehingga tidak ada satu pun test yang
 * menyentuh database dev maupun database client.
 */
abstract class JposTestCase extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $kasirRole;
    protected User $admin;
    protected User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrator',
            'slug' => 'admin',
            'permissions' => array_keys(Role::allMenuKeys()),
            'is_system' => true,
        ]);

        $this->kasirRole = Role::create([
            'name' => 'Kasir',
            'slug' => 'kasir',
            'permissions' => ['dashboard', 'kasir', 'retur', 'pelanggan'],
            'is_system' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@jpos.local',
            'password' => Hash::make('rahasia123'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->kasir = User::create([
            'name' => 'Kasir Satu',
            'username' => 'kasir1',
            'email' => 'kasir1@jpos.local',
            'password' => Hash::make('rahasia123'),
            'role_id' => $this->kasirRole->id,
            'is_active' => true,
        ]);
    }

    protected function makeCategory(string $name = 'Umum'): Category
    {
        return Category::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)]);
    }

    protected function makeCustomer(string $name = 'Pelanggan Umum'): Customer
    {
        return Customer::create(['name' => $name, 'phone' => '-']);
    }

    /**
     * Produk barang dengan nilai default yang masuk akal; override lewat $attributes.
     */
    protected function makeProduct(array $attributes = []): Product
    {
        static $counter = 0;
        $counter++;

        return Product::create(array_merge([
            'name' => 'Produk ' . $counter,
            'type' => 'barang',
            'sku' => 'SKU-TEST' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
            'unit' => 'pcs',
            'cost_price' => 6000,
            'sell_price' => 10000,
            'stock' => 100,
            'min_stock' => 5,
            'is_taxable' => true,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Satuan besar (mis. DUS berisi 12 pcs) untuk menguji konversi stok.
     *
     * conversion selalu diukur terhadap satuan dasar produk dan tetap menjadi sumber
     * kebenaran untuk seluruh perhitungan. ratio_to_previous diturunkan otomatis dari
     * satuan yang sudah ada supaya barisnya membentuk rantai yang sah - kalau tidak, form
     * produk menolaknya saat disimpan ulang dan test jadi gagal karena alasan yang tidak
     * ada hubungannya dengan apa yang sedang diuji.
     */
    protected function makeProductUnit(Product $product, string $unitName, float $conversion, float $price, array $attributes = []): ProductUnit
    {
        $unit = Unit::firstOrCreate(['name' => $unitName]);

        $sebelumnya = ProductUnit::where('product_id', $product->id)
            ->orderByDesc('conversion')->value('conversion');

        return ProductUnit::create(array_merge([
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion' => $conversion,
            'ratio_to_previous' => $sebelumnya ? round($conversion / (float) $sebelumnya, 4) : $conversion,
            'sort_order' => ProductUnit::where('product_id', $product->id)->count(),
            'price' => $price,
        ], $attributes));
    }

    /**
     * PNG dengan header dimensi raksasa tapi isi file hanya puluhan byte.
     *
     * UploadedFile::fake()->image(12000, 12000) tidak bisa dipakai untuk kasus ini:
     * helper itu benar-benar mengalokasikan bitmapnya lewat GD, jadi proses test-nya
     * sendiri yang mati kehabisan memori. Yang perlu diuji adalah bahwa validasi membaca
     * dimensi lewat getimagesize() (header saja) dan menolak SEBELUM GD dipanggil.
     */
    protected function hugeDimensionPng(int $width, int $height, string $name = 'raksasa.png'): \Illuminate\Http\UploadedFile
    {
        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        $ihdr = pack('N', $width) . pack('N', $height) . chr(8) . chr(2) . chr(0) . chr(0) . chr(0);
        $png = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', $ihdr) . $chunk('IDAT', gzcompress('')) . $chunk('IEND', '');

        $path = tempnam(sys_get_temp_dir(), 'jpos-img') . '.png';
        file_put_contents($path, $png);

        return new \Illuminate\Http\UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function enableTax(float $percent = 11.0): void
    {
        Setting::set('tax', [
            'enabled' => true,
            'name' => 'PPN',
            'percent' => $percent,
            'include_in_price' => false,
        ]);
    }
}
