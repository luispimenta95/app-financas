<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('asset_name', 'name');
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->string('type')->default('fixed_income')->after('user_id');
            $table->string('institution')->nullable()->after('name');
            $table->date('application_date')->nullable()->after('amount');
            $table->decimal('cdi_rate', 8, 2)->nullable()->after('application_date');
            $table->boolean('daily_liquidity')->default(true)->after('cdi_rate');
            $table->date('maturity_date')->nullable()->after('daily_liquidity');
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'institution',
                'application_date',
                'cdi_rate',
                'daily_liquidity',
                'maturity_date',
            ]);
        });

        Schema::table('investments', function (Blueprint $table) {
            $table->renameColumn('name', 'asset_name');
        });
    }
};
