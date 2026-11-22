<?php

use App\Support\PlanFeature;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boletos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('digitable_line', 54)->nullable()->index();
            $table->string('barcode', 48)->nullable();
            $table->string('bank_code', 3)->nullable();
            $table->string('beneficiary')->nullable();
            $table->string('payer')->nullable();
            $table->string('document_number')->nullable();
            $table->string('our_number')->nullable();
            $table->integer('amount')->default(0);
            $table->date('due_date')->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->longText('raw_text')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'digitable_line']);
        });

        if (! Schema::hasTable('plan_features')) {
            return;
        }

        $now = now();
        $featureId = DB::table('plan_features')->where('key', PlanFeature::BOLETO_PDF)->value('id');

        if ($featureId === null) {
            $maxSort = (int) DB::table('plan_features')->max('sort_order');
            $featureId = DB::table('plan_features')->insertGetId([
                'key' => PlanFeature::BOLETO_PDF,
                'label' => 'Importação de boleto (PDF)',
                'description' => 'Ler boleto e cadastrar/atualizar o lançamento automaticamente.',
                'sort_order' => $maxSort + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $proPlanId = DB::table('plans')->where('key', 'pro')->value('id');

        if ($proPlanId !== null) {
            $exists = DB::table('feature_plan')
                ->where('plan_id', $proPlanId)
                ->where('plan_feature_id', $featureId)
                ->exists();

            if (! $exists) {
                DB::table('feature_plan')->insert([
                    'plan_id' => $proPlanId,
                    'plan_feature_id' => $featureId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_features')) {
            $featureId = DB::table('plan_features')->where('key', PlanFeature::BOLETO_PDF)->value('id');

            if ($featureId !== null) {
                DB::table('feature_plan')->where('plan_feature_id', $featureId)->delete();
                DB::table('plan_features')->where('id', $featureId)->delete();
            }
        }

        Schema::dropIfExists('boletos');
    }
};
