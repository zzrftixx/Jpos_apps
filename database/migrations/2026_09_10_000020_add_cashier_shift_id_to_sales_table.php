<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sales', 'cashier_shift_id')) {
            return;
        }

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('cashier_shift_id')->nullable()->after('user_id')->constrained('cashier_shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales', 'cashier_shift_id')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->dropForeign(['cashier_shift_id']);
                $table->dropColumn('cashier_shift_id');
            });
        }
    }
};
