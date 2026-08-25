<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan baris kas dengan aset tetap yang dibelinya.
 *
 * KENAPA PERLU. Mendaftarkan peralatan menggerakkan DUA hal sekaligus: sisi aset bertambah
 * (fixed_assets) dan kas berkurang (cash_transactions kategori `aset_tetap`). Keduanya harus
 * hidup dan mati bersama - kalau salah satu dihapus sendirian, neraca langsung timpang persis
 * sebesar peralatan itu.
 *
 * Sebelumnya pasangannya dicari dengan mencocokkan catatan kas "Beli <nama aset>" beserta
 * nominalnya. Itu berfungsi selama tidak ada dua aset bernama sama berharga sama, dan selama
 * catatannya tidak pernah diedit - dua syarat yang tidak bisa dijamin. Sekarang hubungannya
 * eksplisit.
 *
 * BACKFILL. Instalasi yang sudah berjalan mungkin punya aset tetap yang dicatat lewat halaman
 * Neraca. Baris kasnya dicocokkan sekali di sini memakai pola lama, supaya penghapusan tetap
 * membereskan keduanya. Yang tidak ketemu dibiarkan - lebih baik tidak terhubung daripada
 * terhubung ke baris yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->after('category')
                ->constrained('fixed_assets')->nullOnDelete();
        });

        if (! Schema::hasTable('fixed_assets')) {
            return;
        }

        foreach (DB::table('fixed_assets')->get() as $aset) {
            $kas = DB::table('cash_transactions')
                ->where('type', 'out')
                ->where('category', 'aset_tetap')
                ->whereNull('fixed_asset_id')
                ->where('note', 'Beli ' . $aset->name)
                ->where('amount', $aset->acquisition_cost)
                ->orderBy('id')
                ->first();

            if ($kas) {
                DB::table('cash_transactions')->where('id', $kas->id)
                    ->update(['fixed_asset_id' => $aset->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_asset_id');
        });
    }
};
