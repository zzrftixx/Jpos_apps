<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            // Berapa satuan dasar produk (kolom `unit` di tabel products) setara dengan 1 satuan ini.
            $table->decimal('conversion', 15, 4);
            $table->decimal('price', 15, 2);
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->integer('wholesale_min_qty')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
