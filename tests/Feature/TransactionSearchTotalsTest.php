<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource\Widgets\TransactionsOverview;
use App\Filament\Widgets\TopCategoriesWidget;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-08-15 12:00:00');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = $this->user->accounts()->firstOrFail();
    $this->cardCategory = $this->user->categories()
        ->where('slug', 'cartao-de-credito')
        ->firstOrFail();
    $this->otherCategory = $this->user->categories()
        ->where('slug', 'outros')
        ->firstOrFail();

    createCardBillPayments();
});

afterEach(function () {
    Carbon::setTestNow();
});

function createCardBillPayments(): void
{
    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->cardCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 100000,
        'description' => 'Adiantamento fatura cartão',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-07-14',
        'due_date' => '2026-08-17',
        'payment_date' => '2026-07-14',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->cardCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 125000,
        'description' => 'Pagamento restante fatura cartão',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-08-05',
        'due_date' => '2026-08-17',
        'payment_date' => '2026-08-05',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->otherCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 50000,
        'description' => 'Mercado',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-08-10',
        'due_date' => '2026-08-10',
        'payment_date' => '2026-08-10',
    ]);
}

function createSuperBetTransactions(): void
{
    $jogos = test()->user->categories()->create([
        'name' => 'Jogos',
        'slug' => 'jogos',
        'icon' => 'fas-gamepad',
        'color' => '#22c55e',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => $jogos->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 21077,
        'description' => 'SuperBet (Carteira)',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-03-06',
        'due_date' => '2026-03-06',
        'payment_date' => '2026-03-06',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => $jogos->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 3400,
        'description' => 'SuperBet (Carteira)',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-03-11',
        'due_date' => '2026-03-11',
        'payment_date' => '2026-03-11',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => $jogos->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 2837,
        'description' => 'SuperBet (Carteira)',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-03-11',
        'due_date' => '2026-03-11',
        'payment_date' => '2026-03-11',
    ]);

    Transaction::query()->create([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->otherCategory->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 15405,
        'description' => 'Salário',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-08-01',
        'due_date' => '2026-08-01',
        'payment_date' => '2026-08-01',
    ]);
}

function overviewStatValues(object $component): array
{
    $method = new ReflectionMethod($component->instance(), 'getStats');
    $method->setAccessible(true);

    return collect($method->invoke($component->instance()))
        ->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

test('widget de receitas e despesas em agosto usa a data de pagamento', function () {
    $component = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-08'],
        ],
        'tableSearch' => 'cartão',
    ]);

    $stats = overviewStatValues($component);

    expect($stats['Receitas'])->toBe('R$ 0,00')
        ->and($stats['Despesas'])->toBe('R$ 1.250,00')
        ->and($stats['Saldo'])->toBe('R$ -1.250,00');
});

test('widget em julho debita o adiantamento no mes do pagamento', function () {
    $july = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-07'],
        ],
        'tableSearch' => 'cartão',
    ]);

    $julyStats = overviewStatValues($july);

    expect($julyStats['Despesas'])->toBe('R$ 1.000,00')
        ->and($julyStats['Saldo'])->toBe('R$ -1.000,00');
});

test('lista de agosto soma os dois pagamentos da fatura pelo vencimento', function () {
    $component = Livewire::test(ListTransactions::class)
        ->set('tableSearch', 'cartão')
        ->set('tableFilters.date.monthReference', '2026-08')
        ->assertSuccessful();

    $query = $component->instance()->getFilteredTableQuery();
    $descriptions = (clone $query)->pluck('description')->all();

    expect($descriptions)->toContain('Adiantamento fatura cartão', 'Pagamento restante fatura cartão')
        ->and($descriptions)->not->toContain('Mercado')
        ->and((int) $query->sum('amount'))->toBe(225000);
});

test('filtro de categoria cartao soma a fatura na listagem e o pagamento no widget', function () {
    $list = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', '2026-08')
        ->set('tableFilters.category_id.values', [$this->cardCategory->id])
        ->assertSuccessful();

    expect((int) $list->instance()->getFilteredTableQuery()->sum('amount'))->toBe(225000);

    $widget = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-08'],
            'category_id' => ['values' => [$this->cardCategory->id]],
        ],
    ]);

    $stats = overviewStatValues($widget);

    expect($stats['Despesas'])->toBe('R$ 1.250,00')
        ->and($stats['Saldo'])->toBe('R$ -1.250,00');
});

test('widget com pesquisa e sem mes soma transacoes de qualquer periodo', function () {
    createSuperBetTransactions();

    $widget = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => 'all'],
        ],
        'tableSearch' => 'sup',
    ]);

    $stats = overviewStatValues($widget);

    expect($stats['Receitas'])->toBe('R$ 244,77')
        ->and($stats['Despesas'])->toBe('R$ 28,37')
        ->and($stats['Saldo'])->toBe('R$ 216,40');
});

test('widget com pesquisa por texto e mes em branco nao restringe ao mes atual', function () {
    createSuperBetTransactions();

    $blankMonth = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => null],
        ],
        'tableSearch' => 'sup',
    ]);

    $noDateFilter = Livewire::test(TransactionsOverview::class, [
        'tableSearch' => 'sup',
    ]);

    expect(overviewStatValues($blankMonth)['Receitas'])->toBe('R$ 244,77')
        ->and(overviewStatValues($blankMonth)['Despesas'])->toBe('R$ 28,37')
        ->and(overviewStatValues($noDateFilter)['Receitas'])->toBe('R$ 244,77')
        ->and(overviewStatValues($noDateFilter)['Despesas'])->toBe('R$ 28,37');
});

test('lista com pesquisa e mes todos inclui transacoes de outro mes', function () {
    createSuperBetTransactions();

    $component = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', 'all')
        ->set('tableSearch', 'sup')
        ->assertSuccessful();

    $query = $component->instance()->getFilteredTableQuery();
    $descriptions = (clone $query)->pluck('description')->all();

    expect($descriptions)->toContain('SuperBet (Carteira)')
        ->and($descriptions)->not->toContain('Mercado')
        ->and((int) $query->sum('amount'))->toBe(27314);
});

test('lista com pesquisa por texto sem escolher mes inclui outro periodo', function () {
    createSuperBetTransactions();

    $component = Livewire::test(ListTransactions::class)
        ->set('tableSearch', 'sup')
        ->assertSuccessful();

    $monthReference = data_get($component->instance()->tableFilters, 'date.monthReference');
    $descriptions = $component->instance()->getFilteredTableQuery()->pluck('description')->all();

    expect($descriptions)->toContain('SuperBet (Carteira)')
        ->and($monthReference)->toBe('all');
});

test('lista com pesquisa mantem mes diferente do atual', function () {
    createSuperBetTransactions();

    $component = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', '2026-03')
        ->set('tableSearch', 'sup')
        ->assertSuccessful();

    $monthReference = data_get($component->instance()->tableFilters, 'date.monthReference');
    $query = $component->instance()->getFilteredTableQuery();

    expect($monthReference)->toBe('2026-03')
        ->and((int) $query->sum('amount'))->toBe(27314);
});

test('widget com pesquisa e mes especifico continua limitado aquele mes', function () {
    createSuperBetTransactions();

    $widget = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-08'],
        ],
        'tableSearch' => 'sup',
    ]);

    $stats = overviewStatValues($widget);

    expect($stats['Receitas'])->toBe('R$ 0,00')
        ->and($stats['Despesas'])->toBe('R$ 0,00')
        ->and($stats['Saldo'])->toBe('R$ 0,00');
});

test('principais categorias em agosto consideram a fatura pelo vencimento', function () {
    Livewire::test(TopCategoriesWidget::class, [
        'filters' => [
            'startDate' => '2026-08-01',
            'endDate' => '2026-08-31',
            'accountId' => null,
            'preview' => false,
        ],
    ])
        ->assertSee('Cartão de Crédito')
        ->assertSee('R$ 2.250,00');
});
