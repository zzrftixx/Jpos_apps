<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua hal yang dibutuhkan Neraca dan belum ada datanya di mana pun.
 *
 * ASET TETAP - etalase, komputer, motor, timbangan. Barang yang dipakai untuk berjualan,
 * bukan untuk dijual. Nilainya termasuk kekayaan toko, jadi tanpa ini neraca menyembunyikan
 * sebagian aset yang sesungguhnya ada.
 *
 * SNAPSHOT NERACA - neraca hanya bisa dihitung untuk HARI INI, karena nilai persediaan
 * berasal dari stok saat ini dikali harga modal saat ini, dan stock_movements hanya menyimpan
 * kuantitas tanpa nilai. Untuk bisa membandingkan posisi keuangan antar bulan, angkanya harus
 * dibekukan saat tutup buku. Tanpa ini, pertanyaan "bulan lalu modal saya berapa?" tidak akan
 * pernah bisa dijawab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('acquired_at');
            $table->decimal('acquisition_cost', 15, 2);
            // Umur manfaat dalam bulan. Dikosongkan berarti tidak disusutkan - wajar untuk
            // barang yang nilainya tidak menyusut berarti, mis. etalase kaca.
            $table->integer('useful_life_months')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('acquired_at');
        });

        Schema::create('neraca_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();

            $table->decimal('kas', 15, 2)->default(0);
            $table->decimal('piutang', 15, 2)->default(0);
            $table->decimal('persediaan', 15, 2)->default(0);
            $table->decimal('aset_tetap', 15, 2)->default(0);
            $table->decimal('total_aset', 15, 2)->default(0);

            $table->decimal('hutang_usaha', 15, 2)->default(0);
            $table->decimal('total_kewajiban', 15, 2)->default(0);

            $table->decimal('modal_awal', 15, 2)->default(0);
            $table->decimal('tambahan_modal', 15, 2)->default(0);
            $table->decimal('prive', 15, 2)->default(0);
            $table->decimal('laba_ditahan', 15, 2)->default(0);
            $table->decimal('total_modal', 15, 2)->default(0);

            $table->decimal('selisih', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neraca_snapshots');
        Schema::dropIfExists('fixed_assets');
    }
};
