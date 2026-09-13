<?php

use App\Enums\InvestmentRateType;
use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
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
            'rate_type' => InvestmentRateType::Cdi->value,
            'interest_rate' => 110,
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
        ->and($investment->rate_type)->toBe(InvestmentRateType::Cdi)
        ->and((float) $investment->interest_rate)->toBe(110.0)
        ->and($investment->formattedInterestRate())->toBe('110,00 % CDI')
        ->and($investment->daily_liquidity)->toBeTrue()
        ->and($investment->maturity_date)->toBeNull();
});

test('usuario pode criar investimento com rentabilidade prefixada', function () {
    Livewire::test(ManageInvestments::class)
        ->callAction('create', data: [
            'type' => InvestmentType::FixedIncome->value,
            'name' => 'CDB Prefixo',
            'institution' => 'Itaú',
            'amount' => '5.000,00',
            'application_date' => '2026-07-01',
            'rate_type' => InvestmentRateType::Prefixed->value,
            'interest_rate' => 15,
            'daily_liquidity' => false,
            'maturity_date' => '2027-07-01',
        ])
        ->assertHasNoActionErrors();

    $investment = Investment::query()->first();

    expect($investment)->not->toBeNull()
        ->and($investment->rate_type)->toBe(InvestmentRateType::Prefixed)
        ->and((float) $investment->interest_rate)->toBe(15.0)
        ->and($investment->formattedInterestRate())->toBe('15,00 % a.a.');
});

test('investimento sem liquidez diaria exige data de vencimento', function () {
    Livewire::test(ManageInvestments::class)
        ->callAction('create', data: [
            'type' => InvestmentType::FixedIncome->value,
            'name' => 'CDB 2 anos',
            'institution' => 'XP',
            'amount' => '2.000,00',
            'application_date' => '2026-07-01',
            'rate_type' => InvestmentRateType::Cdi->value,
            'interest_rate' => 115,
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
            'rate_type' => InvestmentRateType::Prefixed->value,
            'interest_rate' => 12.5,
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
    Investment::factory()->for($this->user)->variableIncome()->create();

    Livewire::test(ManageInvestments::class)
        ->assertCanSeeTableRecords(Investment::query()->get())
        ->set('activeTab', InvestmentType::FixedIncome->value)
        ->assertCanSeeTableRecords(Investment::query()->fixedIncome()->get())
        ->assertCanNotSeeTableRecords(Investment::query()->variableIncome()->get());
});

test('renda variavel pode ser definida apenas com valor aplicado', function () {
    Livewire::test(ManageInvestments::class)
        ->set('activeTab', InvestmentType::VariableIncome->value)
        ->callAction('createVariableIncome', data: [
            'amount' => '10.000,00',
        ])
        ->assertHasNoActionErrors();

    $investment = Investment::query()->first();

    expect($investment)->not->toBeNull()
        ->and($investment->type)->toBe(InvestmentType::VariableIncome)
        ->and($investment->name)->toBe(Investment::VARIABLE_INCOME_NAME)
        ->and($investment->amount)->toBe(1000000)
        ->and($investment->institution)->toBeNull()
        ->and($investment->application_date)->toBeNull()
        ->and($investment->interest_rate)->toBeNull()
        ->and($investment->formattedInterestRate())->toBe('—');
});

test('renda variavel e unica e pode ser atualizada', function () {
    $existing = Investment::factory()->for($this->user)->variableIncome()->create([
        'amount' => 100000,
    ]);

    Livewire::test(ManageInvestments::class)
        ->set('activeTab', InvestmentType::VariableIncome->value)
        ->callAction('updateVariableIncome', data: [
            'amount' => '2.500,00',
        ])
        ->assertHasNoActionErrors();

    expect(Investment::query()->variableIncome()->count())->toBe(1)
        ->and($existing->fresh()->amount)->toBe(250000);
});

test('tabela de investimentos exibe updated_at no formato brasileiro', function () {
    $investment = Investment::factory()->for($this->user)->variableIncome()->create([
        'amount' => 100000,
    ]);

    Livewire::test(ManageInvestments::class)
        ->assertCanRenderTableColumn('updated_at')
        ->assertSee($investment->updated_at->format('d/m/Y H:i'));
});

test('nao permite criar segunda renda variavel', function () {
    Investment::factory()->for($this->user)->variableIncome()->create([
        'amount' => 100000,
    ]);

    expect(fn () => Investment::query()->create(Investment::variableIncomeAttributes(200000)))
        ->toThrow(ValidationException::class);
});

test('investimentos fora do brasil podem ser definidos apenas com valor estimado', function () {
    Livewire::test(ManageInvestments::class)
        ->set('activeTab', InvestmentType::Abroad->value)
        ->callAction('createAbroad', data: [
            'amount' => '8.500,00',
        ])
        ->assertHasNoActionErrors();

    $investment = Investment::query()->first();

    expect($investment)->not->toBeNull()
        ->and($investment->type)->toBe(InvestmentType::Abroad)
        ->and($investment->name)->toBe(Investment::ABROAD_NAME)
        ->and($investment->amount)->toBe(850000)
        ->and($investment->institution)->toBeNull()
        ->and($investment->application_date)->toBeNull()
        ->and($investment->interest_rate)->toBeNull()
        ->and($investment->formattedInterestRate())->toBe('—');
});

test('investimentos fora do brasil sao unicos e podem ser atualizados', function () {
    $existing = Investment::factory()->for($this->user)->abroad()->create([
        'amount' => 100000,
    ]);

    Livewire::test(ManageInvestments::class)
        ->set('activeTab', InvestmentType::Abroad->value)
        ->callAction('updateAbroad', data: [
            'amount' => '3.200,00',
        ])
        ->assertHasNoActionErrors();

    expect(Investment::query()->abroad()->count())->toBe(1)
        ->and($existing->fresh()->amount)->toBe(320000);
});

test('nao permite criar segundo controle fora do brasil', function () {
    Investment::factory()->for($this->user)->abroad()->create([
        'amount' => 100000,
    ]);

    expect(fn () => Investment::query()->create(Investment::abroadAttributes(200000)))
        ->toThrow(ValidationException::class);
});

test('renda variavel e fora do brasil podem coexistir', function () {
    Investment::factory()->for($this->user)->variableIncome()->create(['amount' => 100000]);
    Investment::factory()->for($this->user)->abroad()->create(['amount' => 200000]);

    expect(Investment::query()->variableIncome()->count())->toBe(1)
        ->and(Investment::query()->abroad()->count())->toBe(1)
        ->and((int) Investment::query()->sum('amount'))->toBe(300000);
});
