<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planogram - peta rak toko.
 *
 * Tiap rak adalah kisi baris x kolom, dan tiap kotak bisa diisi satu produk. Bentuk kisi
 * dipilih supaya peta di layar bisa mirip rak sungguhan; daftar biasa tidak akan bisa
 * menunjukkan "barang ini di baris paling bawah, ujung kanan".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('rows')->default(4);
            $table->unsignedSmallInteger('cols')->default(6);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('rack_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rack_id')->constrained('racks')->cascadeOnDelete();
            $table->unsignedSmallInteger('row');
            $table->unsignedSmallInteger('col');
            // Produk dihapus -> slotnya ikut hilang, bukan menyisakan kotak berisi produk hantu.
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Jumlah muka: berapa baris produk yang menghadap ke depan di kotak itu.
            $table->unsignedSmallInteger('facings')->default(1);
            $table->timestamps();

            // Satu kotak hanya boleh berisi satu produk. Dijaga di tingkat database, bukan
            // cuma di aplikasi - dua tab yang terbuka bersamaan bisa menaruh dua produk di
            // kotak yang sama kalau hanya mengandalkan pemeriksaan di PHP.
            $table->unique(['rack_id', 'row', 'col']);
            // Satu produk hanya boleh menempati satu kotak, supaya pertanyaan "ada di mana?"
            // punya satu jawaban.
            $table->unique('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_slots');
        Schema::dropIfExists('racks');
    }
};
