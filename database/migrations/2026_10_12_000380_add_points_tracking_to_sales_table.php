<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan jejak poin loyalitas pada transaksi penjualan.
     *
     * points_earned   : Jumlah poin yang didapatkan pembeli dari transaksi ini (hanya completed).
     * points_redeemed : Jumlah poin yang ditukarkan untuk klaim voucher potongan belanja.
     * points_discount : Nilai rupiah potongan harga dari penukaran poin tersebut.
     *
     * Dipasang langsung di tabel sales supaya pembatalan pesanan (cancelOrder) bisa
     * menarik kembali poin yang didapat dan mengembalikan poin yang ditukar secara transaksional (H3 & H4).
     */
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'points_earned')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->integer('points_earned')->default(0);
            $table->integer('points_redeemed')->default(0);
            $table->decimal('points_discount', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'points_earned')) {
                $table->dropColumn('points_earned');
            }
            if (Schema::hasColumn('sales', 'points_redeemed')) {
                $table->dropColumn('points_redeemed');
            }
            if (Schema::hasColumn('sales', 'points_discount')) {
                $table->dropColumn('points_discount');
            }
        });
    }
};
