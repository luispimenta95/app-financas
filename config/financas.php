<?php

use App\Support\PlanFeature;

return [

    /*
    |--------------------------------------------------------------------------
    | Landing page de vendas
    |--------------------------------------------------------------------------
    |
    | Quando true, a rota `/` exibe a landing comercial e o painel Filament
    | passa a viver em `/app` (login em `/app/login`, etc.).
    | Quando false, o comportamento atual se mantém: Filament na raiz `/`.
    |
    */

    'landing_enabled' => (bool) env('LANDING_ENABLED', false),

    'brand' => env('FINANCAS_BRAND', 'Finanças'),

    /*
    |--------------------------------------------------------------------------
    | Planos e funcionalidades
    |--------------------------------------------------------------------------
    |
    | FEATURES_ENFORCED=false libera tudo (útil em desenvolvimento).
    | DEFAULT_PLAN é atribuído a novos usuários sem trial.
    | TRIAL_PLAN + TRIAL_DAYS controlam o acesso temporário do Pro.
    |
    | Edite "features" e "plans.*.feature_keys" para montar o catálogo.
    | No perfil do usuário (Filament) você escolhe o plano e o fim do trial.
    |
    */

    'features_enforced' => (bool) env('FEATURES_ENFORCED', true),

    'default_plan' => env('DEFAULT_PLAN', 'free'),

    'trial_plan' => env('TRIAL_PLAN', 'pro'),

    'trial_days' => (int) env('TRIAL_DAYS', 14),

    'features' => [
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
    ],

    'plans' => [
        'free' => [
            'name' => 'Gratuito',
            'price_monthly' => 0,
            'price_label' => 'R$ 0',
            'period' => 'para sempre',
            'description' => 'Organize o mês a mês com o essencial.',
            'cta' => 'Começar grátis',
            'featured' => false,
            'feature_keys' => [
                PlanFeature::TRANSACTIONS,
                PlanFeature::EXPORT_TRANSACTIONS,
                PlanFeature::DASHBOARD_PROJECTION,
            ],
            // Textos extras só para a landing (opcional).
            'highlights' => [
                'Contas e categorias ilimitadas',
                'Lançamentos, parcelas e recorrência',
                'Dashboard com projeção do mês',
                'Exportação de transações',
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
            'feature_keys' => [
                PlanFeature::TRANSACTIONS,
                PlanFeature::EXPORT_TRANSACTIONS,
                PlanFeature::DASHBOARD_PROJECTION,
                PlanFeature::CREDIT_CARD_PDF,
                PlanFeature::PAYSLIP_PDF,
                PlanFeature::INVESTMENTS,
                PlanFeature::INVESTMENT_MILESTONES,
            ],
            'highlights' => [
                'Tudo do plano Gratuito',
                'Importação de fatura e contracheque (PDF)',
                'Carteira de investimentos e marcos',
                'Prioridade em novos bancos e relatórios',
                'Suporte prioritário',
            ],
        ],
    ],

];
