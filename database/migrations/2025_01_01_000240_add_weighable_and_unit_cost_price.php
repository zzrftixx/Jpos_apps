<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satuan timbangan dan harga modal per satuan tambahan.
 *
 * Catatan untuk yang membaca versi asal fitur ini: di sana migrasi yang setara juga
 * menjalankan `$table->decimal('qty', 15, 4)->change()` pada sale_items. Itu sengaja TIDAK
 * dibawa ke sini. Kolom integer di SQLite memakai INTEGER affinity yang sudah menyimpan
 * pecahan apa adanya sebagai REAL, sedangkan ->change() memaksa Laravel membangun ulang
 * tabel di database toko yang sedang berjalan - berisiko menghilangkan indeks performa dan
 * CHECK constraint, demi hasil yang tidak berbeda sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        // IDEMPOTEN - lihat alasan di migrasi 000230. Database dari baseline petshop/vitur lama
        // sudah punya kolom-kolom ini dari migrasi bernama beda.
        if (! Schema::hasColumn('units', 'is_weighable')) {
            Schema::table('units', function (Blueprint $table) {
                // Satuan timbangan (Kg, Gram, Liter) boleh dijual dengan qty pecahan. Satuan
                // hitung (Pcs, Dus, Box) tetap wajib bilangan bulat - setengah dus tidak ada.
                $table->boolean('is_weighable')->default(false)->after('name');
            });
        }

        if (! Schema::hasColumn('product_units', 'cost_price')) {
            Schema::table('product_units', function (Blueprint $table) {
                // Harga modal khusus untuk satuan ini, diisi manual dan berdiri sendiri dari
                // harga modal satuan dasar. Murni pembanding margin di form produk; nilai stok
                // (HPP) tetap dihitung dari harga modal satuan dasar produk.
                $table->decimal('cost_price', 15, 2)->nullable()->after('conversion');
            });
        }
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('is_weighable');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
