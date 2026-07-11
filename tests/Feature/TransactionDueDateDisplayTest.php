<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
        'icon' => 'home',
        'color' => '#3b82f6',
    ]);
});

function createDueDateTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 10000,
        'description' => 'Condominio',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-06-19',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-06-19',
    ], $overrides));
}

test('displayDueDate usa due_date e nao a data legada', function () {
    $transaction = createDueDateTransaction();

    expect($transaction->displayDueDate()->toDateString())->toBe('2026-07-10')
        ->and($transaction->date->toDateString())->toBe('2026-06-19')
        ->and($transaction->payment_date->toDateString())->toBe('2026-06-19');
});

test('displayDueDate faz fallback para date quando due_date e nulo', function () {
    $transaction = createDueDateTransaction([
        'due_date' => null,
        'date' => '2026-06-19',
    ]);

    expect($transaction->displayDueDate()->toDateString())->toBe('2026-06-19');
});

test('lista ordena por data de vencimento em ordem crescente', function () {
    createDueDateTransaction([
        'description' => 'Mais tarde',
        'date' => '2026-06-01',
        'due_date' => '2026-07-20',
        'payment_date' => '2026-06-01',
    ]);

    createDueDateTransaction([
        'description' => 'Mais cedo',
        'date' => '2026-07-01',
        'due_date' => '2026-07-05',
        'payment_date' => '2026-07-01',
    ]);

    createDueDateTransaction([
        'description' => 'Meio',
        'date' => '2026-05-01',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-05-01',
    ]);

    $ordered = Transaction::query()
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->pluck('description')
        ->all();

    expect($ordered)->toBe(['Mais cedo', 'Meio', 'Mais tarde']);
});

test('backfill preenche due_date a partir de date quando estiver nulo', function () {
    $id = (string) str()->uuid();

    DB::table('transactions')->insert([
        'id' => $id,
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'transaction_type' => TransactionType::Expense->value,
        'amount' => 2500,
        'description' => 'Legado',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-03-15',
        'due_date' => null,
        'payment_date' => '2026-03-15',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Mesma lógica da migration 2026_07_11_150000_backfill_due_date_on_transactions_table.
    DB::statement('UPDATE transactions SET due_date = date WHERE due_date IS NULL AND date IS NOT NULL');

    $transaction = Transaction::query()->findOrFail($id);

    expect($transaction->due_date?->toDateString())->toBe('2026-03-15');
});
