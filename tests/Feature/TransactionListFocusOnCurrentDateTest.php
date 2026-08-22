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

test('lista de transacoes exibe todos os registros do filtro sem paginar', function () {
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

    $descriptions = $component->instance()
        ->getTableRecords()
        ->pluck('description')
        ->all();

    expect($component->instance()->hasFocusedOnCurrentDate)->toBeTrue()
        ->and($component->instance()->getTable()->isPaginated())->toBeFalse()
        ->and($descriptions)->toContain('Hoje', 'Depois', 'Antes 1')
        ->and($descriptions)->toHaveCount(27);

    Carbon::setTestNow();
});

test('lista aplica o filtro de mes sem paginar', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00', 'America/Sao_Paulo'));

    foreach (range(1, 5) as $day) {
        createFocusTransaction([
            'description' => "Junho {$day}",
            'date' => sprintf('2026-06-%02d', $day),
            'due_date' => sprintf('2026-06-%02d', $day),
        ]);
    }

    $component = Livewire::test(ListTransactions::class)
        ->set('tableFilters.date.monthReference', '2026-06')
        ->assertSuccessful();

    expect($component->instance()->getTable()->isPaginated())->toBeFalse()
        ->and($component->instance()->getTableRecords())->toHaveCount(5);

    Carbon::setTestNow();
});
