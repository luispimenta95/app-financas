<?php

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
    | Trial e planos (catálogo comercial)
    |--------------------------------------------------------------------------
    |
    | Valores em centavos onde aplicável. A landing usa este catálogo; a
    | integração Stripe/Paddle pode reutilizar as mesmas chaves depois.
    |
    */

    'trial_days' => (int) env('TRIAL_DAYS', 14),

    'plans' => [
        'free' => [
            'key' => 'free',
            'name' => 'Gratuito',
            'price_monthly' => 0,
            'price_label' => 'R$ 0',
            'period' => 'para sempre',
            'description' => 'Organize o mês a mês com o essencial.',
            'cta' => 'Começar grátis',
            'featured' => false,
            'features' => [
                'Contas e categorias ilimitadas',
                'Lançamentos, parcelas e recorrência',
                'Dashboard com projeção do mês',
                'Exportação de transações',
            ],
        ],
        'pro' => [
            'key' => 'pro',
            'name' => 'Pro',
            'price_monthly' => 2990,
            'price_label' => 'R$ 29,90',
            'period' => '/mês',
            'description' => 'Leitura de PDF, investimentos e visão completa.',
            'cta' => null, // preenchido com trial_days na view
            'featured' => true,
            'features' => [
                'Tudo do plano Gratuito',
                'Importação de fatura e contracheque (PDF)',
                'Carteira de investimentos e marcos',
                'Prioridade em novos bancos e relatórios',
                'Suporte prioritário',
            ],
        ],
    ],

];
