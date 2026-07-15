<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource\Pages\ListTransactions;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
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
        'icon' => 'fas-home',
        'color' => '#3b82f6',
    ]);
});

function createFocusTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 10000,
        'description' => 'Transacao',
        'finished' => false,
        'recurrence' => false,
        'date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'payment_date' => null,
    ], $overrides));
}

test('resolveFocusPage calcula a pagina da primeira transacao a partir de hoje', function () {
    expect(ListTransactions::resolveFocusPage(0, 25, 40))->toBe(1)
        ->and(ListTransactions::resolveFocusPage(24, 25, 40))->toBe(1)
        ->and(ListTransactions::resolveFocusPage(25, 25, 40))->toBe(2)
        ->and(ListTransactions::resolveFocusPage(30, 25, 40))->toBe(2)
        ->and(ListTransactions::resolveFocusPage(40, 25, 40))->toBe(2)
        ->and(ListTransactions::resolveFocusPage(10, 'all', 40))->toBe(1)
        ->and(ListTransactions::resolveFocusPage(0, 25, 0))->toBe(1);
});

test('lista de transacoes abre na pagina com a data atual para frente', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'America/Sao_Paulo'));

    foreach (range(1, 25) as $index) {
        createFocusTransaction([
            'description' => "Antes {$index}",
            'date' => '2026-07-01',
            'due_date' => '2026-07-01',
        ]);
    }

    createFocusTransaction([
        'description' => 'Hoje',
        'date' => '2026-07-14',
        'due_date' => '2026-07-14',
    ]);

    createFocusTransaction([
        'description' => 'Depois',
        'date' => '2026-07-20',
        'due_date' => '2026-07-20',
    ]);

    $component = Livewire::test(ListTransactions::class)
        ->assertSuccessful();

    expect($component->instance()->hasFocusedOnCurrentDate)->toBeTrue()
        ->and($component->instance()->getTablePage())->toBe(2);

    $descriptions = $component->instance()
        ->getTableRecords()
        ->pluck('description')
        ->all();

    expect($descriptions)->toContain('Hoje', 'Depois')
        ->and($descriptions)->not->toContain('Antes 1');

    Carbon::setTestNow();
});

test('lista nao força pagina quando o mes filtrado nao e o atual', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'America/Sao_Paulo'));

    foreach (range(1, 30) as $day) {
        createFocusTransaction([
            'description' => "Junho {$day}",
            'date' => sprintf('2026-06-%02d', min($day, 30)),
            'due_date' => sprintf('2026-06-%02d', min($day, 30)),
        ]);
    }

    $component = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', '2026-06')
        ->assertSuccessful();

    // Após trocar o filtro, o foco é recalculado; mês passado não deve pular de página.
    expect($component->instance()->getTablePage())->toBe(1);

    Carbon::setTestNow();
});
