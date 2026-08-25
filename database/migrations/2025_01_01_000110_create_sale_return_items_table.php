<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            // decimal, bukan integer: kuantitas boleh pecahan sejak barang timbangan bisa
            // dijual per Kg atau per Gram.
            //
            // JANGAN menambahkan migrasi ->change() untuk "membetulkan" instalasi lama.
            // Kolom integer di SQLite memakai INTEGER affinity, yang menyimpan nilai
            // pecahan apa adanya sebagai REAL - jadi instalasi lama sudah berperilaku
            // persis sama tanpa perlu disentuh. Sebaliknya, ->change() memaksa Laravel
            // MEMBANGUN ULANG tabel di database client: berisiko menghilangkan indeks
            // performa dan CHECK constraint, demi hasil yang tidak berbeda sama sekali.
            $table->decimal('qty', 15, 4);
            $table->decimal('price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
