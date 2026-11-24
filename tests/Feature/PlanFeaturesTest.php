<?php

use App\Models\User;
use App\Support\PlanCatalog;
use App\Support\PlanFeature;

beforeEach(function () {
    config([
        'financas.features_enforced' => true,
        'financas.default_plan' => 'free',
        'financas.trial_plan' => 'pro',
        'financas.trial_days' => 14,
    ]);
});

test('plano free libera só as features básicas', function () {
    $user = User::factory()->create(['plan' => 'free', 'trial_ends_at' => null]);

    expect($user->hasFeature(PlanFeature::TRANSACTIONS))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::EXPORT_TRANSACTIONS))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::DASHBOARD_PROJECTION))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::CREDIT_CARD_PDF))->toBeFalse()
        ->and($user->hasFeature(PlanFeature::INVESTMENTS))->toBeFalse();
});

test('plano pro libera pdf e investimentos', function () {
    $user = User::factory()->pro()->create();

    expect($user->hasFeature(PlanFeature::CREDIT_CARD_PDF))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::PAYSLIP_PDF))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::INVESTMENTS))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::INVESTMENT_MILESTONES))->toBeTrue();
});

test('trial ativo concede features do plano de trial', function () {
    $user = User::factory()->onTrial(7)->create();

    expect($user->onTrial())->toBeTrue()
        ->and($user->effectivePlanKey())->toBe('pro')
        ->and($user->hasFeature(PlanFeature::INVESTMENTS))->toBeTrue();
});

test('FEATURES_ENFORCED false libera tudo', function () {
    config(['financas.features_enforced' => false]);

    $user = User::factory()->create(['plan' => 'free']);

    expect($user->hasFeature(PlanFeature::INVESTMENTS))->toBeTrue()
        ->and($user->hasFeature(PlanFeature::CREDIT_CARD_PDF))->toBeTrue();
});

test('registro com plan=pro inicia trial quando o default é free', function () {
    config(['financas.default_plan' => 'free']);

    $attrs = app(PlanCatalog::class)->attributesForRegistration('pro');

    expect($attrs['plan'])->toBe('free')
        ->and($attrs['trial_ends_at'])->not->toBeNull()
        ->and($attrs['trial_ends_at']->isFuture())->toBeTrue();
});

test('com DEFAULT_PLAN=pro o registro Pro fica ilimitado sem trial', function () {
    config(['financas.default_plan' => 'pro']);

    $attrs = app(PlanCatalog::class)->attributesForRegistration('pro');

    expect($attrs['plan'])->toBe('pro')
        ->and($attrs['trial_ends_at'])->toBeNull();
});

test('registro ignora plano arbitrário pedido pelo cliente', function () {
    config(['financas.default_plan' => 'free', 'financas.trial_plan' => 'pro']);

    $attrs = app(PlanCatalog::class)->attributesForRegistration('enterprise');

    expect($attrs['plan'])->toBe('free')
        ->and($attrs['trial_ends_at'])->toBeNull();
});


test('usuário com plan=pro ignora trial expirado e permanece Pro', function () {
    $user = User::factory()->create([
        'plan' => 'pro',
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->effectivePlanKey())->toBe('pro')
        ->and($user->hasFeature(PlanFeature::INVESTMENTS))->toBeTrue();
});

test('comando financas:set-plan atualiza o usuário', function () {
    $user = User::factory()->create(['email' => 'plano@example.com', 'plan' => 'free']);

    $this->artisan('financas:set-plan', [
        'email' => 'plano@example.com',
        'plan' => 'pro',
        '--clear-trial' => true,
    ])->assertSuccessful();

    expect($user->fresh()->plan)->toBe('pro')
        ->and($user->fresh()->trial_ends_at)->toBeNull()
        ->and($user->fresh()->hasFeature(PlanFeature::INVESTMENTS))->toBeTrue();
});
