<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_installment')->default(false)->after('recurrence');
            $table->unsignedInteger('installment_number')->nullable()->after('is_installment');
            $table->unsignedInteger('installment_total')->nullable()->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['is_installment', 'installment_number', 'installment_total']);
        });
    }
};
