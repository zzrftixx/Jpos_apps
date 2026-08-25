<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian pembentuk harga modal per satuan, supaya pemilik toko tidak perlu menghitung HPP.
 *
 * Alur yang dilayani: pemilik toko membeli satu Dus seharga 100.000 ditambah ongkir 10.000.
 * Yang dia tahu cuma dua angka itu. Berapa harga modal per Pcs adalah pekerjaan aplikasi,
 * bukan pekerjaannya.
 *
 * Kedua kolom ini disimpan - bukan cuma dihitung di layar - karena tanpa itu angka yang dia
 * ketik lenyap begitu form ditutup. Saat produknya dibuka lagi untuk dikoreksi, dia hanya
 * melihat hasil akhirnya dan harus menghitung mundur sendiri untuk tahu mana yang harga beli
 * dan mana yang ongkir. Justru itu yang mau dihindari.
 *
 * products.hpp_calc_enabled mengingat apakah pemilik toko memakai cara ini, supaya form
 * terbuka dalam keadaan yang sama seperti saat dia meninggalkannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            // Harga beli satu satuan ini, apa adanya seperti yang tertulis di nota.
            $table->decimal('modal_total', 15, 2)->nullable()->after('cost_price');
            // Ongkir, bongkar muat, dan biaya lain yang menempel pada pembelian itu.
            $table->decimal('biaya_lain', 15, 2)->nullable()->after('modal_total');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('hpp_calc_enabled')->default(false)->after('multi_unit_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn(['modal_total', 'biaya_lain']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('hpp_calc_enabled');
        });
    }
};
