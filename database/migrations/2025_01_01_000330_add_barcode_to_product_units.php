<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barcode per satuan.
 *
 * Menu Cetak Barcode sudah lama bisa mencetak label PER SATUAN - label rak untuk harga per
 * Kg tidak boleh menempel di kemasan yang dijual per Gram. Tapi seluruh label itu membawa
 * kode yang sama, yaitu barcode produknya, karena satuan tidak punya kode sendiri.
 *
 * Akibatnya di kasir: memindai label "Karung" dan label "Kg" menghasilkan hal yang persis
 * sama, dan kasir tetap harus memilih satuannya sendiri di layar. Pada jam ramai itulah
 * langkah yang paling sering terlewat - dan barang berharga Rp 250.000 per karung tercatat
 * terjual Rp 12.000 per kilo.
 *
 * Kolomnya nullable: satuan yang tidak diberi kode sendiri tetap ikut barcode produknya,
 * jadi tidak ada satu pun toko yang perlu mengisi apa pun setelah pembaruan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('product_units', 'barcode')) {
            return;
        }

        Schema::table('product_units', function (Blueprint $table) {
            $table->string('barcode')->nullable()->after('unit_id');

            // Dicari di setiap pindaian, jadi indeksnya wajib - tanpa itu tiap pindaian
            // memindai seluruh tabel, dan di kasir yang sibuk itu langsung terasa.
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('product_units', 'barcode')) {
            return;
        }

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
