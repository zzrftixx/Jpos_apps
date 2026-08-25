<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            // Boleh dijual dengan qty pecahan, ditentukan PER PRODUK dan bukan per satuan
            // global: "Kg" boleh pecahan pada Gula yang ditimbang, tapi tetap bulat pada
            // produk lain yang kebetulan juga memakai satuan Kg tapi dijual per karung.
            $table->boolean('allow_decimal')->default(false)->after('ratio_to_previous');
        });
    }

    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn('allow_decimal');
        });
    }
};
