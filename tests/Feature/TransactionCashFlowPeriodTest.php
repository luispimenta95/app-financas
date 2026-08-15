<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;

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

function createTransaction(array $overrides = []): Transaction
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
        'date' => '2026-04-10',
        'due_date' => '2026-04-10',
        'payment_date' => '2026-04-10',
    ], $overrides));
}

test('lista registro no mes do vencimento mesmo pago em outro mes', function () {
    createTransaction([
        'date' => '2026-04-10',
        'due_date' => '2026-04-10',
        'payment_date' => '2026-05-05',
        'finished' => true,
        'amount' => 15000,
    ]);

    $april = Transaction::query()
        ->forDuePeriod('2026-04-01', '2026-04-30')
        ->sum('amount');

    $may = Transaction::query()
        ->forDuePeriod('2026-05-01', '2026-05-31')
        ->sum('amount');

    expect($april)->toBe(15000)
        ->and($may)->toBe(0);
});

test('totais de entradas e saidas usam o mes do pagamento', function () {
    createTransaction([
        'date' => '2026-04-10',
        'due_date' => '2026-04-10',
        'payment_date' => '2026-05-05',
        'finished' => true,
        'amount' => 15000,
        'transaction_type' => TransactionType::Expense,
    ]);

    createTransaction([
        'date' => '2026-04-15',
        'due_date' => '2026-04-15',
        'payment_date' => '2026-04-20',
        'finished' => true,
        'amount' => 50000,
        'transaction_type' => TransactionType::Income,
        'description' => 'Salario',
    ]);

    $aprilExpenses = Transaction::query()
        ->where('transaction_type', TransactionType::Expense)
        ->forCashFlowPeriod('2026-04-01', '2026-04-30')
        ->sum('amount');

    $mayExpenses = Transaction::query()
        ->where('transaction_type', TransactionType::Expense)
        ->forCashFlowPeriod('2026-05-01', '2026-05-31')
        ->sum('amount');

    $aprilIncomes = Transaction::query()
        ->where('transaction_type', TransactionType::Income)
        ->forCashFlowPeriod('2026-04-01', '2026-04-30')
        ->sum('amount');

    expect($aprilExpenses)->toBe(0)
        ->and($mayExpenses)->toBe(15000)
        ->and($aprilIncomes)->toBe(50000);
});

test('fatura de cartao soma pelo vencimento e debita saldo no mes do pagamento', function () {
    createTransaction([
        'description' => 'Adiantamento fatura cartão',
        'date' => '2026-07-14',
        'due_date' => '2026-08-17',
        'payment_date' => '2026-07-14',
        'finished' => true,
        'amount' => 100000,
    ]);

    createTransaction([
        'description' => 'Pagamento restante fatura cartão',
        'date' => '2026-08-05',
        'due_date' => '2026-08-17',
        'payment_date' => '2026-08-05',
        'finished' => true,
        'amount' => 125000,
    ]);

    $augustDue = Transaction::query()
        ->forDuePeriod('2026-08-01', '2026-08-31')
        ->searchTerm('cartão')
        ->sum('amount');

    $julyDue = Transaction::query()
        ->forDuePeriod('2026-07-01', '2026-07-31')
        ->searchTerm('cartão')
        ->sum('amount');

    $julyCash = Transaction::query()
        ->forCashFlowPeriod('2026-07-01', '2026-07-31')
        ->searchTerm('cartão')
        ->sum('amount');

    $augustCash = Transaction::query()
        ->forCashFlowPeriod('2026-08-01', '2026-08-31')
        ->searchTerm('cartão')
        ->sum('amount');

    expect($augustDue)->toBe(225000)
        ->and($julyDue)->toBe(0)
        ->and($julyCash)->toBe(100000)
        ->and($augustCash)->toBe(125000);
});

test('modo projecao inclui pendentes no mes do vencimento', function () {
    createTransaction([
        'date' => '2026-04-10',
        'due_date' => '2026-04-10',
        'payment_date' => null,
        'finished' => false,
        'amount' => 8000,
    ]);

    $withoutPreview = Transaction::query()
        ->forCashFlowPeriod('2026-04-01', '2026-04-30', preview: false)
        ->sum('amount');

    $withPreview = Transaction::query()
        ->forCashFlowPeriod('2026-04-01', '2026-04-30', preview: true)
        ->sum('amount');

    expect($withoutPreview)->toBe(0)
        ->and($withPreview)->toBe(8000);
});
