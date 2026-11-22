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
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->unsignedInteger('price_monthly')->default(0);
            $table->string('price_label')->nullable();
            $table->string('period')->nullable();
            $table->text('description')->nullable();
            $table->string('cta')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('highlights')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('feature_plan', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_feature_id')->constrained('plan_features')->cascadeOnDelete();
            $table->primary(['plan_id', 'plan_feature_id']);
        });

        $now = now();

        $featureDefs = [
            PlanFeature::TRANSACTIONS => [
                'label' => 'Lançamentos, parcelas e recorrência',
                'description' => 'Controle de entradas e saídas no dia a dia.',
            ],
            PlanFeature::EXPORT_TRANSACTIONS => [
                'label' => 'Exportação de transações',
                'description' => 'Exportar lançamentos pela listagem.',
            ],
            PlanFeature::DASHBOARD_PROJECTION => [
                'label' => 'Dashboard com projeção do mês',
                'description' => 'Widgets de projeção e visão do período.',
            ],
            PlanFeature::CREDIT_CARD_PDF => [
                'label' => 'Importação de fatura (PDF)',
                'description' => 'Ler lançamentos da fatura do cartão.',
            ],
            PlanFeature::PAYSLIP_PDF => [
                'label' => 'Importação de contracheque (PDF)',
                'description' => 'Ler holerite e preencher o salário.',
            ],
            PlanFeature::INVESTMENTS => [
                'label' => 'Carteira de investimentos',
                'description' => 'Renda fixa, variável e exterior.',
            ],
            PlanFeature::INVESTMENT_MILESTONES => [
                'label' => 'Marcos de investimentos',
                'description' => 'Acompanhar metas de patrimônio.',
            ],
        ];

        $featureIds = [];
        $sort = 0;

        foreach ($featureDefs as $key => $feature) {
            $featureIds[$key] = DB::table('plan_features')->insertGetId([
                'key' => $key,
                'label' => $feature['label'],
                'description' => $feature['description'],
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $plans = [
            'free' => [
                'name' => 'Gratuito',
                'price_monthly' => 0,
                'price_label' => 'R$ 0',
                'period' => 'para sempre',
                'description' => 'Organize o mês a mês com o essencial.',
                'cta' => 'Começar grátis',
                'featured' => false,
                'sort_order' => 0,
                'highlights' => [
                    'Contas e categorias ilimitadas',
                    'Lançamentos, parcelas e recorrência',
                    'Dashboard com projeção do mês',
                    'Exportação de transações',
                ],
                'feature_keys' => [
                    PlanFeature::TRANSACTIONS,
                    PlanFeature::EXPORT_TRANSACTIONS,
                    PlanFeature::DASHBOARD_PROJECTION,
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'price_monthly' => 2990,
                'price_label' => 'R$ 29,90',
                'period' => '/mês',
                'description' => 'Leitura de PDF, investimentos e visão completa.',
                'cta' => null,
                'featured' => true,
                'sort_order' => 1,
                'highlights' => [
                    'Tudo do plano Gratuito',
                    'Importação de fatura e contracheque (PDF)',
                    'Carteira de investimentos e marcos',
                    'Prioridade em novos bancos e relatórios',
                    'Suporte prioritário',
                ],
                'feature_keys' => [
                    PlanFeature::TRANSACTIONS,
                    PlanFeature::EXPORT_TRANSACTIONS,
                    PlanFeature::DASHBOARD_PROJECTION,
                    PlanFeature::CREDIT_CARD_PDF,
                    PlanFeature::PAYSLIP_PDF,
                    PlanFeature::INVESTMENTS,
                    PlanFeature::INVESTMENT_MILESTONES,
                ],
            ],
        ];

        foreach ($plans as $key => $plan) {
            $featureKeys = $plan['feature_keys'];
            unset($plan['feature_keys']);

            $planId = DB::table('plans')->insertGetId([
                'key' => $key,
                'name' => $plan['name'],
                'price_monthly' => $plan['price_monthly'],
                'price_label' => $plan['price_label'],
                'period' => $plan['period'],
                'description' => $plan['description'],
                'cta' => $plan['cta'],
                'featured' => $plan['featured'],
                'is_active' => true,
                'highlights' => json_encode($plan['highlights'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $plan['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($featureKeys as $featureKey) {
                if (! isset($featureIds[$featureKey])) {
                    continue;
                }

                DB::table('feature_plan')->insert([
                    'plan_id' => $planId,
                    'plan_feature_id' => $featureIds[$featureKey],
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('plan_features');
    }
};
