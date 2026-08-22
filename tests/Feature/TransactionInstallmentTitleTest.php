<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Services\Transactions\InstallmentBackfillService;
use App\Services\Transactions\RecurringTransactionService;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = Account::query()->where('user_id', $this->user->id)->firstOrFail();
    $this->category = Category::query()->where('user_id', $this->user->id)->firstOrFail();
});

function createInstallmentTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 15000,
        'description' => 'Geladeira',
        'finished' => false,
        'recurrence' => true,
        'is_installment' => true,
        'date' => '2026-08-10',
        'due_date' => '2026-08-10',
        'payment_date' => null,
    ], $overrides));
}

test('parcela de varios meses numera o titulo como transacao x de y', function () {
    $transaction = createInstallmentTransaction();

    app(RecurringTransactionService::class)->createFutureWithZero($transaction, 3, true);

    $titles = Transaction::query()
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->pluck('description')
        ->all();

    expect($titles)->toBe([
        'Geladeira - Transação 1 de 3',
        'Geladeira - Transação 2 de 3',
        'Geladeira - Transação 3 de 3',
    ]);

    $first = Transaction::query()->where('description', 'Geladeira - Transação 1 de 3')->first();

    expect($first)->not->toBeNull()
        ->and($first->is_installment)->toBeTrue()
        ->and($first->installment_number)->toBe(1)
        ->and($first->installment_total)->toBe(3)
        ->and($first->displayTitle())->toBe('Geladeira - Transação 1 de 3')
        ->and($first->installmentLabel())->toBe('Transação 1 de 3');
});

test('recorrencia comum nao recebe titulo de parcela', function () {
    $transaction = createInstallmentTransaction([
        'description' => 'Aluguel',
        'is_installment' => false,
    ]);

    app(RecurringTransactionService::class)->createFutureWithZero($transaction, 3, true);

    $titles = Transaction::query()
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->pluck('description')
        ->all();

    expect($titles)->toBe(['Aluguel', 'Aluguel', 'Aluguel'])
        ->and(Transaction::query()->where('is_installment', true)->count())->toBe(0)
        ->and($transaction->fresh()->installment_number)->toBeNull()
        ->and($transaction->fresh()->isInstallment())->toBeFalse();
});

test('recorrencia de um mes nao adiciona titulo de parcela', function () {
    $transaction = createInstallmentTransaction([
        'description' => 'Aluguel',
    ]);

    app(RecurringTransactionService::class)->createFutureWithZero($transaction, 1, true);

    expect(Transaction::query()->count())->toBe(1)
        ->and($transaction->fresh()->description)->toBe('Aluguel')
        ->and($transaction->fresh()->installment_number)->toBeNull()
        ->and($transaction->fresh()->isInstallment())->toBeFalse();
});

test('comando atualiza apenas parcelas ja salvas', function () {
    createInstallmentTransaction([
        'description' => 'Notebook',
        'due_date' => '2026-06-05',
        'date' => '2026-06-05',
    ]);
    createInstallmentTransaction([
        'description' => 'Notebook',
        'due_date' => '2026-07-05',
        'date' => '2026-07-05',
        'amount' => 0,
    ]);
    createInstallmentTransaction([
        'description' => 'Notebook',
        'due_date' => '2026-08-05',
        'date' => '2026-08-05',
        'amount' => 0,
    ]);

    createInstallmentTransaction([
        'description' => 'Spotify',
        'is_installment' => false,
        'due_date' => '2026-06-01',
        'date' => '2026-06-01',
    ]);
    createInstallmentTransaction([
        'description' => 'Spotify',
        'is_installment' => false,
        'due_date' => '2026-07-01',
        'date' => '2026-07-01',
    ]);
    createInstallmentTransaction([
        'description' => 'Spotify',
        'is_installment' => false,
        'due_date' => '2026-08-01',
        'date' => '2026-08-01',
    ]);

    Artisan::call('transactions:backfill-installments');

    $notebook = Transaction::query()
        ->where('description', 'like', 'Notebook%')
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->get();

    expect($notebook)->toHaveCount(3)
        ->and($notebook->pluck('description')->all())->toBe([
            'Notebook - Transação 1 de 3',
            'Notebook - Transação 2 de 3',
            'Notebook - Transação 3 de 3',
        ])
        ->and($notebook->pluck('installment_number')->all())->toBe([1, 2, 3])
        ->and($notebook->pluck('installment_total')->unique()->all())->toBe([3]);

    $spotify = Transaction::query()
        ->where('description', 'Spotify')
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->get();

    expect($spotify)->toHaveCount(3)
        ->and($spotify->pluck('description')->unique()->all())->toBe(['Spotify'])
        ->and($spotify->every(fn (Transaction $transaction): bool => $transaction->installment_number === null))->toBeTrue();
});

test('backfill e idempotente e nao duplica o sufixo', function () {
    createInstallmentTransaction([
        'description' => 'TV',
        'due_date' => '2026-01-10',
        'date' => '2026-01-10',
    ]);
    createInstallmentTransaction([
        'description' => 'TV',
        'due_date' => '2026-02-10',
        'date' => '2026-02-10',
    ]);

    $service = app(InstallmentBackfillService::class);

    expect($service->backfill())->toBe(2)
        ->and($service->backfill())->toBe(0);

    expect(Transaction::query()->orderByRaw(Transaction::dueDateExpression() . ' ASC')->pluck('description')->all())
        ->toBe([
            'TV - Transação 1 de 2',
            'TV - Transação 2 de 2',
        ]);
});

test('comando com description atualiza so o grupo informado', function () {
    createInstallmentTransaction([
        'description' => 'Celular Sabrina',
        'is_installment' => false,
        'due_date' => '2026-07-17',
        'date' => '2026-07-01',
    ]);
    createInstallmentTransaction([
        'description' => 'Celular Sabrina',
        'is_installment' => false,
        'due_date' => '2026-08-17',
        'date' => '2026-08-17',
    ]);
    createInstallmentTransaction([
        'description' => 'Notebook',
        'is_installment' => false,
        'due_date' => '2026-07-05',
        'date' => '2026-07-05',
    ]);
    createInstallmentTransaction([
        'description' => 'Notebook',
        'is_installment' => false,
        'due_date' => '2026-08-05',
        'date' => '2026-08-05',
    ]);

    Artisan::call('transactions:backfill-installments', ['--description' => 'Celular Sabrina']);

    $celular = Transaction::query()
        ->where('description', 'like', '%Celular Sabrina%')
        ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
        ->get();

    expect($celular)->toHaveCount(2)
        ->and($celular->pluck('description')->all())->toBe([
            'Celular Sabrina - Transação 1 de 2',
            'Celular Sabrina - Transação 2 de 2',
        ])
        ->and($celular->every(fn (Transaction $transaction): bool => $transaction->is_installment))->toBeTrue();

    $notebook = Transaction::query()
        ->where('description', 'Notebook')
        ->get();

    expect($notebook)->toHaveCount(2)
        ->and($notebook->every(fn (Transaction $transaction): bool => $transaction->description === 'Notebook'))->toBeTrue()
        ->and($notebook->every(fn (Transaction $transaction): bool => $transaction->installment_number === null))->toBeTrue();
});
