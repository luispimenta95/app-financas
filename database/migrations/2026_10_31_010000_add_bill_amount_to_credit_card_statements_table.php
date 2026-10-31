<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->integer('bill_amount')->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('credit_card_statements', function (Blueprint $table) {
            $table->dropColumn('bill_amount');
        });
    }
};
