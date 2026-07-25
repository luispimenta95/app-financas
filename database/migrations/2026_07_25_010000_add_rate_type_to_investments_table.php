<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->string('rate_type')->default('cdi')->after('application_date');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('cdi_rate', 'interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('interest_rate', 'cdi_rate');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('rate_type');
        });
    }
};
