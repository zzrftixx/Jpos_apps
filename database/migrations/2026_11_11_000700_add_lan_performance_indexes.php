<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasIndex('products', 'products_active_name_idx')) {
                    $table->index(['is_active', 'name'], 'products_active_name_idx');
                }
                if (! Schema::hasIndex('products', 'products_name_idx')) {
                    $table->index('name', 'products_name_idx');
                }
                if (! Schema::hasIndex('products', 'products_category_id_idx')) {
                    $table->index('category_id', 'products_category_id_idx');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (! Schema::hasIndex('sales', 'sales_parked_at_order_status_idx')) {
                    $table->index(['parked_at', 'order_status'], 'sales_parked_at_order_status_idx');
                }
                if (! Schema::hasIndex('sales', 'sales_table_order_status_idx')) {
                    $table->index(['table_no', 'order_status'], 'sales_table_order_status_idx');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (! Schema::hasIndex('purchases', 'purchases_sisa_hutang_idx')) {
                    $table->index('sisa_hutang', 'purchases_sisa_hutang_idx');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasIndex('products', 'products_active_name_idx')) {
                    $table->dropIndex('products_active_name_idx');
                }
                if (Schema::hasIndex('products', 'products_name_idx')) {
                    $table->dropIndex('products_name_idx');
                }
                if (Schema::hasIndex('products', 'products_category_id_idx')) {
                    $table->dropIndex('products_category_id_idx');
                }
            });
        }

        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (Schema::hasIndex('sales', 'sales_parked_at_order_status_idx')) {
                    $table->dropIndex('sales_parked_at_order_status_idx');
                }
                if (Schema::hasIndex('sales', 'sales_table_order_status_idx')) {
                    $table->dropIndex('sales_table_order_status_idx');
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (Schema::hasIndex('purchases', 'purchases_sisa_hutang_idx')) {
                    $table->dropIndex('purchases_sisa_hutang_idx');
                }
            });
        }
    }
};
