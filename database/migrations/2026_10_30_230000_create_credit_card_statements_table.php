<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->integer('total_amount')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::table('credit_card_purchases', function (Blueprint $table) {
            $table->foreignUuid('credit_card_statement_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $groups = DB::table('credit_card_purchases')
            ->selectRaw('transaction_id, user_id, count(*) as items_count, sum(amount) as total_amount, max(created_at) as imported_at')
            ->groupBy('transaction_id', 'user_id')
            ->get();

        foreach ($groups as $group) {
            $statementId = (string) Str::uuid();
            $attachment = DB::table('transactions')->where('id', $group->transaction_id)->value('attachment');

            DB::table('credit_card_statements')->insert([
                'id' => $statementId,
                'user_id' => $group->user_id,
                'transaction_id' => $group->transaction_id,
                'file_path' => (string) ($attachment ?? ''),
                'original_name' => $attachment ? basename($attachment) : null,
                'items_count' => (int) $group->items_count,
                'total_amount' => (int) $group->total_amount,
                'imported_at' => $group->imported_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('credit_card_purchases')
                ->where('transaction_id', $group->transaction_id)
                ->update(['credit_card_statement_id' => $statementId]);
        }
    }

    public function down(): void
    {
        Schema::table('credit_card_purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_statement_id');
        });

        Schema::dropIfExists('credit_card_statements');
    }
};
