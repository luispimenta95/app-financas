<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Services\Transactions\MonthlyBalanceService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = Account::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Conta Teste',
        'slug' => 'conta-teste-carryover',
    ]);

    $this->category = Category::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Moradia',
        'slug' => 'moradia-carryover',
        'icon' => 'home',
        'color' => '#3b82f6',
    ]);
});

function createCarryoverTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 10000,
        'description' => 'Receita',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-06-10',
        'due_date' => '2026-06-10',
        'payment_date' => '2026-06-10',
    ], $overrides));
}

test('transfere apenas a sobra positiva do mes anterior', function () {
    createCarryoverTransaction([
        'amount' => 50000,
        'date' => '2026-06-05',
        'due_date' => '2026-06-05',
        'payment_date' => '2026-06-05',
    ]);

    createCarryoverTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 20000,
        'description' => 'Despesa',
        'date' => '2026-06-20',
        'due_date' => '2026-06-20',
        'payment_date' => '2026-06-20',
    ]);

    $service = app(MonthlyBalanceService::class);

    expect($service->carriedInto('2026-07-01'))->toBe(30000)
        ->and($service->carriedInto('2026-06-01'))->toBe(0);
});

test('nao transfere saldo negativo para o mes seguinte', function () {
    createCarryoverTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 40000,
        'description' => 'Estouro',
        'date' => '2026-06-10',
        'due_date' => '2026-06-10',
        'payment_date' => '2026-06-10',
    ]);

    expect(app(MonthlyBalanceService::class)->carriedInto('2026-07-01'))->toBe(0);
});

test('encadeia a sobra pelos meses seguintes sem carregar divida', function () {
    createCarryoverTransaction([
        'amount' => 40000,
        'date' => '2026-05-10',
        'due_date' => '2026-05-10',
        'payment_date' => '2026-05-10',
    ]);

    createCarryoverTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 10000,
        'description' => 'Gasto junho',
        'date' => '2026-06-10',
        'due_date' => '2026-06-10',
        'payment_date' => '2026-06-10',
    ]);

    createCarryoverTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 50000,
        'description' => 'Estouro julho',
        'date' => '2026-07-10',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-07-10',
    ]);

    $service = app(MonthlyBalanceService::class);

    expect($service->carriedInto('2026-06-01'))->toBe(40000)
        ->and($service->carriedInto('2026-07-01'))->toBe(30000)
        ->and($service->carriedInto('2026-08-01'))->toBe(0);
});
