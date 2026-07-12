<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;

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

    $this->investmentCategory = Category::query()
        ->where('user_id', $this->user->id)
        ->where('slug', Category::INVESTMENTS_SLUG)
        ->firstOrFail();
});

afterEach(function () {
    Carbon::setTestNow();
});

function createBalanceTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 10000,
        'description' => 'Transação teste',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-07-10',
        'due_date' => '2026-07-10',
        'payment_date' => '2026-07-10',
    ], $overrides));
}

test('aportes reduzem o saldo sem entrar em despesas', function () {
    createBalanceTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 500000,
        'description' => 'Salário',
    ]);

    createBalanceTransaction([
        'transaction_type' => TransactionType::Expense,
        'amount' => 150000,
        'description' => 'Aluguel',
    ]);

    createBalanceTransaction([
        'category_id' => test()->investmentCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 100000,
        'description' => 'Aporte em CDB',
    ]);

    $start = now()->startOfMonth()->toDateString();
    $end = now()->endOfMonth()->toDateString();

    $income = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Income)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $expense = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Expense)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $contributions = (int) Transaction::query()
        ->onlyInvestments()
        ->where('transaction_type', TransactionType::Expense)
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $redemptions = (int) Transaction::query()
        ->onlyInvestments()
        ->where('transaction_type', TransactionType::Income)
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $balance = $income - $expense - ($contributions - $redemptions);

    expect($income)->toBe(500000)
        ->and($expense)->toBe(150000)
        ->and($contributions)->toBe(100000)
        ->and($redemptions)->toBe(0)
        ->and($balance)->toBe(250000);
});

test('resgates de investimento aumentam o saldo', function () {
    createBalanceTransaction([
        'transaction_type' => TransactionType::Income,
        'amount' => 300000,
        'description' => 'Salário',
    ]);

    createBalanceTransaction([
        'category_id' => test()->investmentCategory->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 50000,
        'description' => 'Resgate de investimento',
    ]);

    $start = now()->startOfMonth()->toDateString();
    $end = now()->endOfMonth()->toDateString();

    $income = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Income)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $expense = (int) Transaction::query()
        ->where('transaction_type', TransactionType::Expense)
        ->withoutInvestments()
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $contributions = (int) Transaction::query()
        ->onlyInvestments()
        ->where('transaction_type', TransactionType::Expense)
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $redemptions = (int) Transaction::query()
        ->onlyInvestments()
        ->where('transaction_type', TransactionType::Income)
        ->forCashFlowPeriod($start, $end)
        ->sum('amount');

    $balance = $income - $expense - ($contributions - $redemptions);

    expect($balance)->toBe(350000);
});
