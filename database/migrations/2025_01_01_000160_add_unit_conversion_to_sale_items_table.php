<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('unit_label')->nullable()->after('qty');
            // Berapa satuan dasar (mis. pcs) yang diwakili tiap 1 qty baris ini. Dipakai untuk
            // pengurangan/pengembalian stok yang benar saat retur, karena stok selalu disimpan
            // dalam satuan dasar meskipun baris ini dijual dalam satuan besar (mis. dus/kg).
            $table->decimal('unit_conversion', 15, 4)->default(1)->after('unit_label');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['unit_label', 'unit_conversion']);
        });
    }
};
