<?php

use App\Enums\InvestmentRateType;
use App\Enums\InvestmentType;
use App\Enums\TransactionType;
use App\Filament\Resources\Investments\Pages\ManageInvestments;
use App\Filament\Widgets\TransactionsOverview;
use App\Models\Investment;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-07-11 12:00:00');

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = Account::query()->create([
        'user_id' => $this->user->id,
        'name' => 'Conta Teste',
        'slug' => 'conta-teste',
    ]);

    $this->investmentCategory = Category::query()
        ->where('user_id', $this->user->id)
        ->where('slug', Category::INVESTMENTS_SLUG)
        ->firstOrFail();

    $this->investment = Investment::query()->create([
        'type' => InvestmentType::FixedIncome,
        'name' => 'CDB Nubank',
        'institution' => 'Nubank',
        'amount' => 100000,
        'application_date' => '2026-07-01',
        'rate_type' => InvestmentRateType::Cdi,
        'interest_rate' => 100,
        'daily_liquidity' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('resgate parcial baixa o investimento e lanca receita em Investimentos', function () {
    Livewire::test(ManageInvestments::class)
        ->callTableAction('redeem', $this->investment, data: [
            'amount' => '400,00',
            'date' => '2026-07-08',
        ])
        ->assertHasNoTableActionErrors();

    $transaction = Transaction::query()->sole();

    expect($this->investment->fresh()->amount)->toBe(60000)
        ->and($transaction->transaction_type)->toBe(TransactionType::Income)
        ->and($transaction->category_id)->toBe($this->investmentCategory->id)
        ->and($transaction->account_id)->toBe($this->user->accounts()->value('id'))
        ->and($transaction->amount)->toBe(40000)
        ->and($transaction->finished)->toBeTrue()
        ->and($transaction->payment_date->toDateString())->toBe('2026-07-08')
        ->and($transaction->description)->toBe('Resgate CDB Nubank');
});

test('resgate total mantem o investimento zerado e esconde o botao', function () {
    $component = Livewire::test(ManageInvestments::class)
        ->callTableAction('redeem', $this->investment, data: [
            'amount' => '1.000,00',
            'date' => '2026-07-08',
        ])
        ->assertHasNoTableActionErrors();

    expect($this->investment->fresh())->not->toBeNull()
        ->and($this->investment->fresh()->amount)->toBe(0);

    $component->assertTableActionHidden('redeem', $this->investment->fresh());
});

test('resgate maior que o saldo nao altera nada', function () {
    Livewire::test(ManageInvestments::class)
        ->callTableAction('redeem', $this->investment, data: [
            'amount' => '1.500,00',
            'date' => '2026-07-08',
        ])
        ->assertHasTableActionErrors(['amount']);

    expect($this->investment->fresh()->amount)->toBe(100000)
        ->and(Transaction::query()->count())->toBe(0);
});

test('card de aportes mostra o valor liquido dos resgates', function () {
    Transaction::query()->create([
        'user_id' => $this->user->id,
        'account_id' => $this->account->id,
        'category_id' => $this->investmentCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 100000,
        'description' => 'Aporte CDB',
        'finished' => true,
        'recurrence' => false,
        'date' => '2026-07-02',
        'due_date' => '2026-07-02',
        'payment_date' => '2026-07-02',
    ]);

    Livewire::test(ManageInvestments::class)
        ->callTableAction('redeem', $this->investment, data: [
            'amount' => '400,00',
            'date' => '2026-07-08',
        ]);

    Livewire::test(TransactionsOverview::class)
        ->assertSee('R$ 600,00')
        ->assertSee('Líquido de R$ 400,00 em resgates');
});
