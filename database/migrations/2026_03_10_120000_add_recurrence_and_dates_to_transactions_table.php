<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('recurrence')->default(false)->after('finished');
            $table->date('due_date')->nullable()->after('recurrence');
            $table->date('payment_date')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['recurrence', 'due_date', 'payment_date']);
        });
    }
};
