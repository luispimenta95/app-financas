<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('reference_month', 7)->nullable();
            $table->date('credit_date')->nullable();
            $table->integer('base_salary')->nullable();
            $table->integer('gross_amount')->default(0);
            $table->integer('deductions_amount')->default(0);
            $table->integer('net_amount')->default(0);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslip_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->nullable();
            $table->string('description');
            $table->string('quantity', 20)->nullable();
            $table->integer('earnings')->default(0);
            $table->integer('deductions')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
    }
};
