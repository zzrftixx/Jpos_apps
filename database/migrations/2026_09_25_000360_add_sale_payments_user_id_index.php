<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sale_payments')) {
            Schema::table('sale_payments', function (Blueprint $table) {
                // Indeks komposit user_id + created_at untuk kalkulasi ringkasan shift kasir instan
                $table->index(['user_id', 'created_at'], 'sale_payments_user_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sale_payments')) {
            Schema::table('sale_payments', function (Blueprint $table) {
                $table->dropIndex('sale_payments_user_created_idx');
            });
        }
    }
};
