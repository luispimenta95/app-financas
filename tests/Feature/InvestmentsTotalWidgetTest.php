<?php

use App\Enums\InvestmentType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\InvestmentsTotalWidget;
use App\Models\Investment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('widget de total de investimentos aparece no dashboard', function () {
    $component = Livewire::test(Dashboard::class);

    expect($component->instance()->getVisibleWidgets())
        ->toContain(InvestmentsTotalWidget::class);
});

test('widget mostra a soma total da carteira do usuario', function () {
    Investment::factory()->for($this->user)->fixedIncome()->create(['amount' => 150000]);
    Investment::factory()->for($this->user)->fixedIncome()->create(['amount' => 250000]);
    Investment::factory()->for($this->user)->variableIncome()->create(['amount' => 100000]);

    Livewire::test(InvestmentsTotalWidget::class)
        ->assertSee('Total investido')
        ->assertSee('R$ 5.000,00')
        ->assertSee(InvestmentType::FixedIncome->getLabel())
        ->assertSee('R$ 4.000,00')
        ->assertSee(InvestmentType::VariableIncome->getLabel())
        ->assertSee('R$ 1.000,00')
        ->assertSee('3 aplicações');
});
