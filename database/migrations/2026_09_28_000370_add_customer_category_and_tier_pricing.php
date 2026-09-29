<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan Kategori Pelanggan (UMUM, Reseller, Grosir) serta
 * kolom harga bertingkat (tier pricing) khusus untuk Reseller dan Grosir
 * pada tabel products dan product_units.
 *
 * Sesuai keputusan bisnis:
 * 1. Setiap pelanggan diklasifikasikan ke dalam tipe: UMUM (default), Reseller, atau Grosir.
 * 2. Produk memiliki input harga mandiri untuk tiap kategori pelanggan:
 *    - UMUM: memakai sell_price (harga jual dasar)
 *    - Reseller: memakai reseller_price (opsional, fallback ke sell_price jika kosong)
 *    - Grosir: memakai grosir_price (opsional, fallback ke sell_price jika kosong)
 * 3. Fitur diskon grosir per kuantiti (wholesale_price & wholesale_min_qty) tetap dipertahankan
 *    sebagai fasilitas potongan volume untuk pelanggan Umum.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah customer_type ke tabel customers
        if (Schema::hasTable('customers') && !Schema::hasColumn('customers', 'customer_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('customer_type', 30)->default('UMUM')->after('name');
            });
        }

        // 2. Tambah reseller_price & grosir_price ke tabel products
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'reseller_price')) {
                    $table->decimal('reseller_price', 15, 2)->nullable()->after('sell_price');
                }
                if (!Schema::hasColumn('products', 'grosir_price')) {
                    $table->decimal('grosir_price', 15, 2)->nullable()->after('reseller_price');
                }
            });
        }

        // 3. Tambah reseller_price & grosir_price ke tabel product_units (Multi Satuan)
        if (Schema::hasTable('product_units')) {
            Schema::table('product_units', function (Blueprint $table) {
                if (!Schema::hasColumn('product_units', 'reseller_price')) {
                    $table->decimal('reseller_price', 15, 2)->nullable()->after('price');
                }
                if (!Schema::hasColumn('product_units', 'grosir_price')) {
                    $table->decimal('grosir_price', 15, 2)->nullable()->after('reseller_price');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'customer_type')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('customer_type');
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                $drop = [];
                if (Schema::hasColumn('products', 'reseller_price')) {
                    $drop[] = 'reseller_price';
                }
                if (Schema::hasColumn('products', 'grosir_price')) {
                    $drop[] = 'grosir_price';
                }
                if (!empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('product_units')) {
            Schema::table('product_units', function (Blueprint $table) {
                $drop = [];
                if (Schema::hasColumn('product_units', 'reseller_price')) {
                    $drop[] = 'reseller_price';
                }
                if (Schema::hasColumn('product_units', 'grosir_price')) {
                    $drop[] = 'grosir_price';
                }
                if (!empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
