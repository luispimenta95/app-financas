<?php

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Models\Investment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('usuario pode criar investimento de renda fixa com liquidez diaria', function () {
    Livewire::test(ManageInvestments::class)
        ->callAction('create', data: [
            'type' => InvestmentType::FixedIncome->value,
            'name' => 'CDB Liquidez Diária',
            'institution' => 'Nubank',
            'amount' => '1.500,00',
            'application_date' => '2026-07-01',
            'cdi_rate' => 110,
            'daily_liquidity' => true,
            'maturity_date' => null,
        ])
        ->assertHasNoActionErrors();

    $investment = Investment::query()->first();

    expect($investment)->not->toBeNull()
        ->and($investment->type)->toBe(InvestmentType::FixedIncome)
        ->and($investment->name)->toBe('CDB Liquidez Diária')
        ->and($investment->institution)->toBe('Nubank')
        ->and($investment->amount)->toBe(150000)
        ->and($investment->application_date->toDateString())->toBe('2026-07-01')
        ->and((float) $investment->cdi_rate)->toBe(110.0)
        ->and($investment->daily_liquidity)->toBeTrue()
        ->and($investment->maturity_date)->toBeNull();
});

test('investimento sem liquidez diaria exige data de vencimento', function () {
    Livewire::test(ManageInvestments::class)
        ->callAction('create', data: [
            'type' => InvestmentType::FixedIncome->value,
            'name' => 'CDB 2 anos',
            'institution' => 'XP',
            'amount' => '2.000,00',
            'application_date' => '2026-07-01',
            'cdi_rate' => 115,
            'daily_liquidity' => false,
            'maturity_date' => null,
        ])
        ->assertHasActionErrors(['maturity_date']);
});

test('usuario pode criar investimento sem liquidez diaria com vencimento', function () {
    Livewire::test(ManageInvestments::class)
        ->callAction('create', data: [
            'type' => InvestmentType::FixedIncome->value,
            'name' => 'LCI Prefixo',
            'institution' => 'BTG',
            'amount' => '3.000,00',
            'application_date' => '2026-07-01',
            'cdi_rate' => 95,
            'daily_liquidity' => false,
            'maturity_date' => '2028-07-01',
        ])
        ->assertHasNoActionErrors();

    $investment = Investment::query()->first();

    expect($investment)->not->toBeNull()
        ->and($investment->daily_liquidity)->toBeFalse()
        ->and($investment->maturity_date->toDateString())->toBe('2028-07-01');
});

test('soma dos valores aplicados considera todos os investimentos do usuario', function () {
    Investment::factory()->for($this->user)->create(['amount' => 100000]);
    Investment::factory()->for($this->user)->create(['amount' => 250000]);

    $total = (int) Investment::query()->sum('amount');

    expect($total)->toBe(350000);
});

test('aba de renda fixa filtra apenas investimentos de renda fixa', function () {
    Investment::factory()->for($this->user)->fixedIncome()->create(['name' => 'CDB']);
    Investment::factory()->for($this->user)->variableIncome()->create(['name' => 'Ações PETR4']);

    Livewire::test(ManageInvestments::class)
        ->assertCanSeeTableRecords(Investment::query()->get())
        ->set('activeTab', InvestmentType::FixedIncome->value)
        ->assertCanSeeTableRecords(Investment::query()->fixedIncome()->get())
        ->assertCanNotSeeTableRecords(Investment::query()->variableIncome()->get());
});
