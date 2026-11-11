<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales', 'table_no')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('table_no')->nullable()->after('note');
            });
        }

        if (!Schema::hasColumn('sale_items', 'note')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->string('note')->nullable()->after('product_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'table_no')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropColumn('table_no');
            });
        }

        if (Schema::hasColumn('sale_items', 'note')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->dropColumn('note');
            });
        }
    }
};
