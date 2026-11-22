<?php

use App\Enums\TransactionType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\UpcomingDueTransactionsWidget;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-11-22 12:00:00');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = $this->user->accounts()->first()
        ?? Account::query()->create([
            'user_id' => $this->user->id,
            'name' => 'Conta Teste',
            'slug' => 'conta-teste',
        ]);

    $this->category = Category::query()->firstOrCreate(
        [
            'user_id' => $this->user->id,
            'slug' => 'moradia',
        ],
        [
            'name' => 'Moradia',
            'icon' => 'home',
            'color' => '#3b82f6',
        ],
    );
});

afterEach(function () {
    Carbon::setTestNow();
});

function createUpcomingTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->category->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 15000,
        'description' => 'Conta de luz',
        'finished' => false,
        'recurrence' => false,
        'date' => '2026-11-25',
        'due_date' => '2026-11-25',
        'payment_date' => null,
    ], $overrides));
}

test('widget aparece no dashboard', function () {
    $component = Livewire::test(Dashboard::class);

    expect($component->instance()->getVisibleWidgets())
        ->toContain(UpcomingDueTransactionsWidget::class);
});

test('lista pendencias que vencem nos proximos 7 dias', function () {
    createUpcomingTransaction([
        'description' => 'Luz',
        'due_date' => '2026-11-22',
        'date' => '2026-11-22',
        'amount' => 10000,
    ]);
    createUpcomingTransaction([
        'description' => 'Internet',
        'due_date' => '2026-11-28',
        'date' => '2026-11-28',
        'amount' => 20000,
    ]);
    createUpcomingTransaction([
        'description' => 'Fora da janela',
        'due_date' => '2026-11-29',
        'date' => '2026-11-29',
        'amount' => 99900,
    ]);
    createUpcomingTransaction([
        'description' => 'Ja paga',
        'due_date' => '2026-11-24',
        'date' => '2026-11-24',
        'finished' => true,
        'payment_date' => '2026-11-22',
        'amount' => 5000,
    ]);
    createUpcomingTransaction([
        'description' => 'Vencida ontem',
        'due_date' => '2026-11-21',
        'date' => '2026-11-21',
        'amount' => 8000,
    ]);

    expect(Transaction::query()->upcomingDue(7)->pluck('description')->all())
        ->toBe(['Luz', 'Internet']);

    Livewire::test(UpcomingDueTransactionsWidget::class)
        ->assertSee('Luz')
        ->assertSee('Internet')
        ->assertSee('Hoje')
        ->assertDontSee('Fora da janela')
        ->assertDontSee('Ja paga')
        ->assertDontSee('Vencida ontem')
        ->assertSee('R$ 300,00');
});

test('scope upcomingDue considera apenas nao finalizadas na janela', function () {
    createUpcomingTransaction(['due_date' => '2026-11-23', 'date' => '2026-11-23']);
    createUpcomingTransaction([
        'due_date' => '2026-11-23',
        'date' => '2026-11-23',
        'finished' => true,
        'payment_date' => '2026-11-22',
    ]);
    createUpcomingTransaction(['due_date' => '2026-12-01', 'date' => '2026-12-01']);

    expect(Transaction::query()->upcomingDue(7)->count())->toBe(1);
});
