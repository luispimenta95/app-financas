<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investment_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->date('achieved_on');
            $table->timestamps();

            $table->index(['user_id', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_milestones');
    }
};
