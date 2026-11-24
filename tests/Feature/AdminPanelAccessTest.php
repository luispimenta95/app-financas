<?php

use App\Models\User;
use App\Support\AppAdmin;
use Filament\Facades\Filament;

beforeEach(function () {
    config(['financas.admin_email' => 'admin@example.com']);
});

test('apenas o admin acessa o painel admin', function () {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $user = User::factory()->create(['email' => 'user@example.com']);

    expect(AppAdmin::check($admin))->toBeTrue()
        ->and(AppAdmin::check($user))->toBeFalse()
        ->and($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse()
        ->and($user->canAccessPanel(Filament::getPanel('dash')))->toBeTrue();
});

test('e-mail do admin é reservado e não pode ser reivindicado', function () {
    expect(AppAdmin::isReservedEmail('admin@example.com'))->toBeTrue()
        ->and(AppAdmin::isReservedEmail('ADMIN@example.com'))->toBeTrue()
        ->and(AppAdmin::isReservedEmail('user@example.com'))->toBeFalse();
});


test('catálogo no banco sobrescreve o config quando existe', function () {
    $feature = \App\Models\Feature::query()->create([
        'key' => 'custom_feature',
        'label' => 'Feature custom',
        'description' => 'Teste',
        'sort_order' => 99,
    ]);

    $plan = \App\Models\Plan::query()->create([
        'key' => 'custom',
        'name' => 'Custom',
        'price_monthly' => 100,
        'price_label' => 'R$ 1',
        'period' => '/mês',
        'description' => 'Plano teste',
        'featured' => false,
        'is_active' => true,
        'highlights' => ['A'],
        'sort_order' => 99,
    ]);

    $plan->features()->attach($feature);

    $catalog = app(\App\Support\PlanCatalog::class);
    $catalog->forgetCache();

    expect($catalog->planExists('custom'))->toBeTrue()
        ->and($catalog->featureKeysForPlan('custom'))->toBe(['custom_feature']);
});
