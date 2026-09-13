<?php

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Filament\Resources\Investments\Pages\ManageMilestones;
use App\Models\InvestmentMilestone;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('usuario pode registrar um marco com valor e data', function () {
    Livewire::test(ManageMilestones::class)
        ->callAction('create', data: [
            'amount' => '5.000,00',
            'achieved_on' => '2025-10-10',
        ])
        ->assertHasNoActionErrors();

    $milestone = InvestmentMilestone::query()->first();

    expect($milestone)->not->toBeNull()
        ->and($milestone->amount)->toBe(500000)
        ->and($milestone->achieved_on->toDateString())->toBe('2025-10-10')
        ->and($milestone->user_id)->toBe($this->user->id);
});

test('usuario pode atualizar um marco', function () {
    $milestone = InvestmentMilestone::factory()->for($this->user)->create([
        'amount' => 500000,
        'achieved_on' => '2025-10-10',
    ]);

    Livewire::test(ManageMilestones::class)
        ->callTableAction('edit', $milestone, data: [
            'amount' => '10.000,00',
            'achieved_on' => '2025-11-15',
        ])
        ->assertHasNoTableActionErrors();

    $milestone->refresh();

    expect($milestone->amount)->toBe(1000000)
        ->and($milestone->achieved_on->toDateString())->toBe('2025-11-15');
});

test('usuario pode excluir um marco', function () {
    $milestone = InvestmentMilestone::factory()->for($this->user)->create();

    Livewire::test(ManageMilestones::class)
        ->callTableAction('delete', $milestone)
        ->assertHasNoTableActionErrors();

    expect(InvestmentMilestone::query()->count())->toBe(0);
});

test('lista de marcos mostra registros do usuario ordenados pela data', function () {
    $older = InvestmentMilestone::factory()->for($this->user)->create([
        'amount' => 500000,
        'achieved_on' => '2025-10-10',
    ]);
    $newer = InvestmentMilestone::factory()->for($this->user)->create([
        'amount' => 1000000,
        'achieved_on' => '2025-11-15',
    ]);

    Livewire::test(ManageMilestones::class)
        ->assertCanSeeTableRecords([$newer, $older])
        ->assertSee('5.000,00')
        ->assertSee('10/10/2025')
        ->assertSee('10.000,00')
        ->assertSee('15/11/2025');
});

test('marcos de outro usuario nao aparecem', function () {
    auth()->logout();

    $otherUser = User::factory()->create();
    $otherMilestone = InvestmentMilestone::factory()->for($otherUser)->create([
        'amount' => 999000,
        'achieved_on' => '2025-01-01',
    ]);

    $this->actingAs($this->user);

    $ownMilestone = InvestmentMilestone::factory()->for($this->user)->create([
        'amount' => 500000,
        'achieved_on' => '2025-10-10',
    ]);

    Livewire::test(ManageMilestones::class)
        ->assertCanSeeTableRecords([$ownMilestone])
        ->assertCanNotSeeTableRecords([$otherMilestone]);
});

test('aba marcos redireciona da listagem de investimentos', function () {
    Livewire::test(ManageInvestments::class)
        ->set('activeTab', 'marcos')
        ->assertRedirect();
});

test('outras abas redirecionam de marcos para investimentos', function () {
    Livewire::test(ManageMilestones::class)
        ->set('activeTab', InvestmentType::VariableIncome->value)
        ->assertRedirect();
});
