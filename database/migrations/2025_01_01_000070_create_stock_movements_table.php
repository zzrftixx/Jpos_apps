<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'sale', 'return']);
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
            $table->decimal('stock_after', 15, 4);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
