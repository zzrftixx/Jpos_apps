<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('wholesale_price', 15, 2)->nullable()->after('sell_price');
            $table->integer('wholesale_min_qty')->nullable()->after('wholesale_price');
            $table->string('large_unit_name')->nullable()->after('unit');
            $table->decimal('large_unit_conversion', 15, 4)->nullable()->after('large_unit_name');
            $table->decimal('large_unit_price', 15, 2)->nullable()->after('large_unit_conversion');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'wholesale_min_qty', 'large_unit_name', 'large_unit_conversion', 'large_unit_price']);
        });
    }
};
