<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membekukan harga modal pada saat barang terjual.
 *
 * Selama ini HPP di Laporan Laba Rugi dihitung dengan mengambil products.cost_price SAAT
 * LAPORAN DIBUKA. Akibatnya, begitu pemilik toko memperbarui harga modal karena pemasok
 * menaikkan harga, laba SELURUH BULAN LAMPAU ikut bergerak - dan laporan yang sudah dicetak,
 * ditunjukkan ke pasangan, atau dipakai menghitung bonus karyawan tidak akan pernah bisa
 * direproduksi.
 *
 * Kolom ini menyimpan harga modal yang berlaku ketika transaksi terjadi, sehingga laporan
 * bulan lalu selalu menghasilkan angka yang sama berapa kali pun dibuka.
 *
 * Sengaja nullable: transaksi yang sudah ada tidak punya angka ini dan tidak bisa direka
 * ulang. Untuk baris lama, laporan tetap memakai products.cost_price sebagai cadangan -
 * tidak seakurat aslinya, tapi jauh lebih baik daripada nol, dan tidak ada data yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price_snapshot', 15, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_price_snapshot');
        });
    }
};
