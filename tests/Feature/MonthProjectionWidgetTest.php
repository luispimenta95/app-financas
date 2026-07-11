<?php

use App\Enums\TransactionType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\MonthProjectionWidget;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = Account::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Conta Teste',
        'slug' => 'conta-teste',
    ]);

    $this->category = Category::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Moradia',
        'slug' => 'moradia',
        'icon' => 'home',
        'color' => '#3b82f6',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function createProjectionTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 10000,
        'description' => 'Conta de luz',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-07-10',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-07-10',
    ], $overrides));
}

test('shouldDisplay retorna true quando nao ha filtros', function () {
    expect(MonthProjectionWidget::shouldDisplay(null))->toBeTrue()
        ->and(MonthProjectionWidget::shouldDisplay([]))->toBeTrue()
        ->and(MonthProjectionWidget::shouldDisplay([
            'startDate' => null,
            'endDate' => null,
            'accountId' => null,
            'preview' => null,
        ]))->toBeTrue();
});

test('shouldDisplay retorna false quando algum filtro esta preenchido', function () {
    expect(MonthProjectionWidget::shouldDisplay([
        'startDate' => '2026-07-01',
        'endDate' => null,
        'accountId' => null,
        'preview' => null,
    ]))->toBeFalse()
        ->and(MonthProjectionWidget::shouldDisplay([
            'startDate' => null,
            'endDate' => null,
            'accountId' => null,
            'preview' => true,
        ]))->toBeFalse()
        ->and(MonthProjectionWidget::shouldDisplay([
            'startDate' => null,
            'endDate' => null,
            'accountId' => 'abc',
            'preview' => null,
        ]))->toBeFalse();
});

test('widget aparece no dashboard sem filtros e some com filtros', function () {
    $component = Livewire::test(Dashboard::class);

    expect($component->instance()->getVisibleWidgets())
        ->toContain(MonthProjectionWidget::class);

    $component->set('filters.startDate', '2026-07-01');

    expect($component->instance()->getVisibleWidgets())
        ->not->toContain(MonthProjectionWidget::class);
});

test('projecao inclui pendentes do mes atual no saldo', function () {
    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 500000,
        'finished' => true,
        'description' => 'Salario',
        'date' => '2026-07-05',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-05',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 150000,
        'finished' => true,
        'description' => 'Aluguel pago',
        'date' => '2026-07-01',
        'due_date' => '2026-07-01',
        'payment_date' => '2026-07-01',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 80000,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Conta pendente',
        'date' => '2026-07-20',
        'due_date' => '2026-07-20',
    ]);

    $start = now()->startOfMonth()->toDateString();
    $end = now()->endOfMonth()->toDateString();

    $income = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Income)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end, preview: true)
        ->sum('amount');

    $expense = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Expense)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end, preview: true)
        ->sum('amount');

    expect($income)->toBe(500000)
        ->and($expense)->toBe(230000)
        ->and($income - $expense)->toBe(270000);
});

test('widget renderiza valores projetados do mes atual', function () {
    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 300000,
        'finished' => true,
        'description' => 'Salario',
        'date' => '2026-07-05',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-05',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 100000,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Pendente',
        'date' => '2026-07-25',
        'due_date' => '2026-07-25',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('Projeção do mês')
        ->assertSee('Saldo projetado')
        ->assertSee('R$ 3.000,00')
        ->assertSee('R$ 1.000,00')
        ->assertSee('R$ 2.000,00');
});
