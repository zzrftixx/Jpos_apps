<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('order_status', ['completed', 'waiting', 'cancelled'])->default('completed')->after('status');
            $table->timestamp('due_date')->nullable()->after('order_status');
            $table->text('note')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['order_status', 'due_date', 'note']);
        });
    }
};
