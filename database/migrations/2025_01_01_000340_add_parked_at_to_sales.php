<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda transaksi yang DITAHAN di meja kasir.
 *
 * Bedanya dengan pesanan DP - dan inilah kenapa keduanya perlu jalur terpisah:
 *
 *   PESANAN DP        pelanggan memesan, mengambil beberapa HARI lagi, biasanya bayar muka.
 *                     Punya tanggal jatuh tempo. Dipantau pemilik toko.
 *
 *   TRANSAKSI TERTAHAN  pelanggan sedang berdiri di depan kasir, tapi mau ambil barang lain
 *                     dulu atau harus mengurus sesuatu. Keranjangnya ditahan beberapa MENIT
 *                     supaya antrean di belakangnya bisa dilayani. Tidak ada uang sama sekali.
 *
 * Secara mesin keduanya sama persis: `order_status = 'waiting'` dengan stok direservasi.
 * Yang membedakan cuma NIAT-nya, dan niat itu tidak boleh ditebak dari nominal atau dari
 * ada-tidaknya tanggal - itu aturan tersembunyi yang selalu berakhir jadi pertanyaan.
 *
 * KENAPA KOLOM BARU, BUKAN `order_status` BARU. Menambah status berarti setiap query
 * `order_status = 'waiting'` di seluruh aplikasi harus ikut diubah - laporan, neraca,
 * dashboard, katalog. Yang terlewat akan diam-diam menghilangkan transaksi dari pembukuan.
 * Kolom penanda terpisah membuat SELURUH pembukuan yang sudah benar tetap benar tanpa
 * disentuh sama sekali.
 *
 * KENAPA timestamp, BUKAN boolean. Kasir perlu tahu sudah berapa lama sebuah keranjang
 * ditahan - yang tertahan sejak dua jam lalu hampir pasti ditinggal pelanggannya, dan
 * stoknya perlu dilepas. Boolean tidak bisa menjawab itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'parked_at')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->timestamp('parked_at')->nullable()->after('due_date');
            $table->index('parked_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sales', 'parked_at')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['parked_at']);
            $table->dropColumn('parked_at');
        });
    }
};
