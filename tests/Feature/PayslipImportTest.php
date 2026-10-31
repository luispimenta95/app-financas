<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Transactions\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource\Pages\UploadPayslip;
use App\Models\Transactions\Category;
use App\Models\Transactions\Payslip;
use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Services\Transactions\CreditCardStatementTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = $this->user->accounts()->firstOrFail();
    $this->salaryCategory = $this->user->categories()
        ->where('slug', Category::SALARY_SLUG)
        ->firstOrFail();
});

function createSalaryTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->salaryCategory->id,
        'transaction_type' => TransactionType::Income,
        'amount' => 0,
        'description' => 'Salário Serasa mensal',
        'finished' => false,
        'recurrence' => false,
        'date' => '2026-10-30',
        'due_date' => '2026-10-30',
        'payment_date' => null,
    ], $overrides));
}

function fakePayslipText(): string
{
    return <<<'TXT'
                                                                                    Mês / Ano          Folha Nº
                                          DEMONSTRATIVO DE                              10/2026         1/ 1
  Cod.                        Descrição                      Qtde.    Vencimentos            Descontos
   0004      Salário                                          30,00              8.241,13
   2103      INSS Normal                                                                                988,07
                               TOTAIS                                        8.241,13                  988,07
Banco       Agência      Conta Corrente    Data de Crédito
000         0.000        00000-0           30/10/2026                LÍQUIDO:                      7.253,06
TXT;
}

test('envia contracheque, salva itens e usa o líquido como valor da transação', function () {
    Storage::fake('public');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->once()
        ->andReturn(fakePayslipText());

    $salary = createSalaryTransaction();

    Livewire::test(UploadPayslip::class, ['record' => $salary->getKey()])
        ->set('pdf', UploadedFile::fake()->create('51594_20261030.pdf', 120, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(TransactionResource::getUrl('index'));

    $payslip = $salary->fresh()->payslip;

    expect($payslip)->not->toBeNull()
        ->and($payslip->kind)->toBe(Payslip::KIND_MONTHLY)
        ->and($payslip->original_name)->toBe('51594_20261030.pdf')
        ->and($payslip->net_amount)->toBe(725306)
        ->and($payslip->items)->toHaveCount(2)
        ->and(Storage::disk('public')->exists($payslip->file_path))->toBeTrue()
        ->and($salary->fresh()->amount)->toBe(725306);
});

test('substitui o valor já lançado pelo líquido do pdf', function () {
    Storage::fake('public');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->andReturn(fakePayslipText());

    $salary = createSalaryTransaction(['amount' => 700000]);

    Livewire::test(UploadPayslip::class, ['record' => $salary->getKey()])
        ->set('pdf', UploadedFile::fake()->create('contracheque.pdf', 120, 'application/pdf'))
        ->call('submit');

    expect($salary->fresh()->amount)->toBe(725306)
        ->and($salary->fresh()->payslip->net_amount)->toBe(725306);
});

test('lista mostra enviar contracheque e abre o detalhamento ao clicar na linha', function () {
    Carbon::setTestNow('2026-10-30 12:00:00');
    Storage::fake('public');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->andReturn(fakePayslipText());

    $pending = createSalaryTransaction(['description' => 'Salário Serasa quinzenal']);
    $salary = createSalaryTransaction();

    Livewire::test(UploadPayslip::class, ['record' => $salary->getKey()])
        ->set('pdf', UploadedFile::fake()->create('contracheque.pdf', 120, 'application/pdf'))
        ->call('submit');

    session()->forget('expand_credit_card_statement');

    Livewire::test(ListTransactions::class)
        ->assertTableActionVisible('uploadPayslip', $pending)
        ->assertTableActionHidden('uploadPayslip', $salary)
        ->assertSeeHtml("toggleCreditCardStatement(&#039;{$salary->getKey()}&#039;)")
        ->assertDontSee('INSS Normal')
        ->call('toggleCreditCardStatement', $salary->getKey())
        ->assertSee('INSS Normal')
        ->assertSee('Vencimentos')
        ->assertSee('R$ 7.253,06');
});

test('pagina de contracheque bloqueia transação que não é salário', function () {
    $card = createSalaryTransaction([
        'category_id' => $this->user->categories()->where('slug', Category::CREDIT_CARD_SLUG)->value('id'),
    ]);

    $this->get(TransactionResource::getUrl('uploadPayslip', ['record' => $card]))->assertForbidden();
});
