<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $defaults = ['Pcs', 'Kg', 'Gram', 'Liter', 'Ml', 'Dus', 'Lusin', 'Renceng', 'Box', 'Pak', 'Karton', 'Ikat'];
        foreach ($defaults as $name) {
            DB::table('units')->insertOrIgnore(['name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Pindahkan satuan besar lama (kolom large_unit_* di products, fitur single-unit-conversion
        // sebelumnya) ke tabel product_units yang baru, supaya data yang sudah dikonfigurasi tidak
        // hilang setelah pindah ke sistem multi-satuan.
        $products = DB::table('products')->whereNotNull('large_unit_name')->get();

        foreach ($products as $product) {
            $unitName = trim($product->large_unit_name);
            if ($unitName === '') {
                continue;
            }

            $unit = DB::table('units')->whereRaw('LOWER(name) = ?', [strtolower($unitName)])->first();
            if (!$unit) {
                $unitId = DB::table('units')->insertGetId([
                    'name' => $unitName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $unitId = $unit->id;
            }

            DB::table('product_units')->insert([
                'product_id' => $product->id,
                'unit_id' => $unitId,
                'conversion' => $product->large_unit_conversion ?? 1,
                'price' => $product->large_unit_price ?? 0,
                'wholesale_price' => null,
                'wholesale_min_qty' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Tidak perlu rollback data seed/migrasi.
    }
};
