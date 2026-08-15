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

function overviewStatValues(object $component): array
{
    $method = new ReflectionMethod($component->instance(), 'getStats');
    $method->setAccessible(true);

    return collect($method->invoke($component->instance()))
        ->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

test('pesquisa de gastos com cartao em agosto soma a fatura completa pelo vencimento', function () {
    $component = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-08'],
        ],
        'tableSearch' => 'cartão',
    ]);

    $stats = overviewStatValues($component);

    expect($stats['Receitas'])->toBe('R$ 0,00')
        ->and($stats['Despesas'])->toBe('R$ 2.250,00')
        ->and($stats['Saldo'])->toBe('R$ -1.250,00');
});

test('filtro de categoria cartao em agosto tambem soma os dois pagamentos da fatura', function () {
    $component = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-08'],
            'category_id' => ['values' => [$this->cardCategory->id]],
        ],
    ]);

    $stats = overviewStatValues($component);

    expect($stats['Despesas'])->toBe('R$ 2.250,00')
        ->and($stats['Saldo'])->toBe('R$ -1.250,00');
});

test('pagamento adiantado debita o saldo do mes em que foi feito', function () {
    $july = Livewire::test(TransactionsOverview::class, [
        'tableFilters' => [
            'date' => ['monthReference' => '2026-07'],
        ],
        'tableSearch' => 'cartão',
    ]);

    $julyStats = overviewStatValues($july);

    expect($julyStats['Despesas'])->toBe('R$ 0,00')
        ->and($julyStats['Saldo'])->toBe('R$ -1.000,00');
});

test('lista de agosto encontra os dois pagamentos da fatura ao pesquisar cartao', function () {
    $component = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', '2026-08')
        ->set('tableSearch', 'cartão')
        ->assertSuccessful();

    $descriptions = $component->instance()
        ->getFilteredTableQuery()
        ->pluck('description')
        ->all();

    expect($descriptions)->toContain('Adiantamento fatura cartão', 'Pagamento restante fatura cartão')
        ->and($descriptions)->not->toContain('Mercado');
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
