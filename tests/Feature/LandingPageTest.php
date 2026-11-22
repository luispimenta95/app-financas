<?php

use App\Http\Controllers\LandingController;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

test('com LANDING_ENABLED desligado a landing não existe e o painel permanece na raiz', function () {
    config(['financas.landing_enabled' => false]);

    expect(financas_landing_enabled())->toBeFalse()
        ->and(financas_panel_path())->toBe('')
        ->and(financas_panel_url('login'))->toEndWith('/login');

    expect(fn () => route('landing'))
        ->toThrow(\Symfony\Component\Routing\Exception\RouteNotFoundException::class);

    // Estrutura atual: visitante na raiz vai para o login do Filament.
    $this->get('/')->assertRedirect();
});

test('helpers apontam o painel para /app quando a landing está ligada', function () {
    config(['financas.landing_enabled' => true]);

    expect(financas_panel_path())->toBe('app')
        ->and(financas_panel_url())->toEndWith('/app')
        ->and(financas_panel_url('login'))->toEndWith('/app/login')
        ->and(financas_panel_url('register'))->toEndWith('/app/register');
});

test('controller da landing renderiza planos e trial quando habilitada', function () {
    config([
        'financas.landing_enabled' => true,
        'financas.trial_days' => 14,
        'financas.brand' => 'Finanças',
    ]);

    Route::get('/__landing', LandingController::class);

    $this->get('/__landing')
        ->assertOk()
        ->assertSee('Finanças', false)
        ->assertSee('Ver planos', false)
        ->assertSee('Gratuito', false)
        ->assertSee('Pro', false)
        ->assertSee('R$ 29,90', false)
        ->assertSee('14 dias', false)
        ->assertSee('Começar grátis', false)
        ->assertSee('Testar 14 dias grátis', false);
});

test('controller da landing responde 404 quando desabilitada', function () {
    config(['financas.landing_enabled' => false]);

    Route::get('/__landing', LandingController::class);

    $this->get('/__landing')->assertNotFound();
});

test('usuário autenticado é redirecionado da landing para o painel', function () {
    config(['financas.landing_enabled' => true]);

    Route::get('/__landing', LandingController::class);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/__landing')
        ->assertRedirect(financas_panel_url());
});

test('invoke da landing retorna View com catálogo de planos', function () {
    config([
        'financas.landing_enabled' => true,
        'financas.trial_days' => 7,
    ]);

    $result = app(LandingController::class)();

    expect($result)->toBeInstanceOf(View::class)
        ->and($result->getData()['trialDays'])->toBe(7)
        ->and($result->getData()['plans'])->toHaveCount(2);
});
