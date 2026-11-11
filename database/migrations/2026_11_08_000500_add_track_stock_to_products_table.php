<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'track_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_stock')->default(true)->after('stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'track_stock')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('track_stock');
            });
        }
    }
};
