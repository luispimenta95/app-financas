<?php

use App\Enums\TransactionType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\MonthProjectionWidget;
use App\Filament\Widgets\TransactionsOverview;
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
        ]))->toBeTrue()
        ->and(MonthProjectionWidget::shouldDisplay([
            'startDate' => '',
            'endDate' => '',
        ]))->toBeTrue();
});

test('shouldDisplay retorna false quando alguma data esta preenchida', function () {
    expect(MonthProjectionWidget::shouldDisplay([
        'startDate' => '2026-07-01',
        'endDate' => null,
    ]))->toBeFalse()
        ->and(MonthProjectionWidget::shouldDisplay([
            'startDate' => null,
            'endDate' => '2026-07-31',
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

test('apagar a data volta o dashboard ao estado sem filtro', function () {
    $component = Livewire::test(Dashboard::class)
        ->set('filters.startDate', '2026-07-01')
        ->set('filters.startDate', '');

    expect($component->get('filters'))->toBe(['startDate' => null, 'endDate' => null])
        ->and($component->instance()->getVisibleWidgets())->toContain(MonthProjectionWidget::class);
});

test('data final anterior a inicial e recusada', function () {
    $component = Livewire::test(Dashboard::class)
        ->set('filters.startDate', '2026-07-10')
        ->set('filters.endDate', '2026-07-05');

    $component->assertHasErrors('filters.endDate');
    expect($component->get('filters'))->toBe(['startDate' => '2026-07-10', 'endDate' => null]);
});

test('data inicial posterior a final e recusada', function () {
    $component = Livewire::test(Dashboard::class)
        ->set('filters.endDate', '2026-07-05')
        ->set('filters.startDate', '2026-07-10');

    $component->assertHasErrors('filters.startDate');
    expect($component->get('filters'))->toBe(['startDate' => null, 'endDate' => '2026-07-05']);
});

test('datas iguais sao aceitas', function () {
    Livewire::test(Dashboard::class)
        ->set('filters.startDate', '2026-07-10')
        ->set('filters.endDate', '2026-07-10')
        ->assertHasNoErrors()
        ->assertSet('filters', ['startDate' => '2026-07-10', 'endDate' => '2026-07-10']);
});

test('limpar filtros zera as datas', function () {
    $component = Livewire::test(Dashboard::class)
        ->set('filters.startDate', '2026-07-01')
        ->set('filters.endDate', '2026-07-15')
        ->call('clearFilters');

    expect($component->get('filters'))->toBe(['startDate' => null, 'endDate' => null])
        ->and($component->instance()->getVisibleWidgets())->toContain(MonthProjectionWidget::class);
});

test('widgets voltam ao mes atual quando a data e apagada', function () {
    createProjectionTransaction([
        'amount' => 12345,
        'description' => 'Despesa de julho',
    ]);
    createProjectionTransaction([
        'amount' => 99999,
        'description' => 'Despesa antiga',
        'date' => '2026-01-10',
        'due_date' => '2026-01-10',
        'payment_date' => '2026-01-10',
    ]);

    Livewire::test(TransactionsOverview::class, ['filters' => ['startDate' => '', 'endDate' => '']])
        ->assertSee('R$ 123,45')
        ->assertDontSee('R$ 999,99');

    Livewire::test(TransactionsOverview::class, ['filters' => ['startDate' => '2026-01-01', 'endDate' => '2026-01-31']])
        ->assertSee('R$ 999,99');
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

test('saldo positivo do mes anterior entra na projecao do mes atual', function () {
    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 50000,
        'finished' => true,
        'description' => 'Sobra de junho',
        'date' => '2026-06-20',
        'due_date' => '2026-06-20',
        'payment_date' => '2026-06-20',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 20000,
        'finished' => true,
        'description' => 'Gasto de junho',
        'date' => '2026-06-25',
        'due_date' => '2026-06-25',
        'payment_date' => '2026-06-25',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 10000,
        'finished' => true,
        'description' => 'Receita de julho',
        'date' => '2026-07-05',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-05',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('Saldo ao fim do mês')
        ->assertSee('Todas as transações do mês foram finalizadas')
        ->assertSee('R$ 400,00');
});

test('em 01/11 a projecao usa novembro e inclui sobra de outubro', function () {
    Carbon::setTestNow('2026-11-01 12:00:00');

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 26630,
        'finished' => true,
        'description' => 'Sobra de outubro',
        'date' => '2026-10-30',
        'due_date' => '2026-10-30',
        'payment_date' => '2026-10-30',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 914345,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Receitas de novembro',
        'date' => '2026-11-15',
        'due_date' => '2026-11-15',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 220760,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Despesas de novembro',
        'date' => '2026-11-20',
        'due_date' => '2026-11-20',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('R$ 9.143,45')
        ->assertSee('R$ 2.207,60')
        ->assertSee('Saldo projetado')
        ->assertSee('R$ 7.202,15')
        ->assertSee('Saldo atual de R$ 266,30');
});

test('divida do mes anterior nao entra na projecao do mes atual', function () {
    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 80000,
        'finished' => true,
        'description' => 'Estouro de junho',
        'date' => '2026-06-10',
        'due_date' => '2026-06-10',
        'payment_date' => '2026-06-10',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 25000,
        'finished' => true,
        'description' => 'Receita de julho',
        'date' => '2026-07-05',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-05',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('Saldo ao fim do mês')
        ->assertSee('R$ 250,00')
        ->assertDontSee('R$ -550,00');
});

test('saldo ao fim do mes desconta aportes finalizados', function () {
    $investmentCategory = Category::query()
        ->where('user_id', $this->user->id)
        ->where('slug', Category::INVESTMENTS_SLUG)
        ->firstOrFail();

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 517548,
        'finished' => true,
        'description' => 'Salario',
        'date' => '2026-07-05',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-05',
    ]);

    createProjectionTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 427363,
        'finished' => true,
        'description' => 'Despesas do mes',
        'date' => '2026-07-10',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-07-10',
    ]);

    createProjectionTransaction([
        'category_id' => $investmentCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 202185,
        'finished' => true,
        'description' => 'Aporte',
        'date' => '2026-07-12',
        'due_date' => '2026-07-12',
        'payment_date' => '2026-07-12',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('R$ 5.175,48')
        ->assertSee('R$ 4.273,63')
        ->assertSee('Saldo ao fim do mês')
        ->assertSee('R$ -1.120,00');
});

test('saldo projetado soma ao saldo atual as pendentes do mes, inclusive aportes', function () {
    $investmentCategory = Category::query()
        ->where('user_id', $this->user->id)
        ->where('slug', Category::INVESTMENTS_SLUG)
        ->firstOrFail();

    createProjectionTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 100000,
        'description' => 'Salario',
    ]);

    createProjectionTransaction([
        'amount' => 20000,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Conta pendente',
        'date' => '2026-07-20',
        'due_date' => '2026-07-20',
    ]);

    createProjectionTransaction([
        'category_id' => $investmentCategory->id,
        'amount' => 30000,
        'finished' => false,
        'payment_date' => null,
        'description' => 'Aporte pendente',
        'date' => '2026-07-25',
        'due_date' => '2026-07-25',
    ]);

    Livewire::test(MonthProjectionWidget::class)
        ->assertSee('Saldo projetado')
        ->assertSee('Saldo atual de R$ 1.000,00')
        ->assertSee('R$ 500,00');
});
