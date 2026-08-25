<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('unit')->default('pcs');
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            // decimal, bukan integer: kuantitas boleh pecahan sejak barang timbangan bisa
            // dijual per Kg atau per Gram.
            //
            // JANGAN menambahkan migrasi ->change() untuk "membetulkan" instalasi lama.
            // Kolom integer di SQLite memakai INTEGER affinity, yang menyimpan nilai
            // pecahan apa adanya sebagai REAL - jadi instalasi lama sudah berperilaku
            // persis sama tanpa perlu disentuh. Sebaliknya, ->change() memaksa Laravel
            // MEMBANGUN ULANG tabel di database client: berisiko menghilangkan indeks
            // performa dan CHECK constraint, demi hasil yang tidak berbeda sama sekali.
            $table->decimal('stock', 15, 4)->default(0);
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
