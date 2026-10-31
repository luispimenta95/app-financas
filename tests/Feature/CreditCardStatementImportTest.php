<?php

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Transactions\TransactionResource\Pages\ListCreditCardPurchases;
use App\Filament\Resources\Transactions\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource\Pages\UploadCreditCardStatement;
use App\Models\Transactions\Category;
use App\Models\Transactions\CreditCardPurchase;
use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Services\Transactions\CreditCardStatementImporter;
use App\Services\Transactions\CreditCardStatementTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->account = $this->user->accounts()->firstOrFail();
    $this->cardCategory = $this->user->categories()
        ->where('slug', Category::CREDIT_CARD_SLUG)
        ->firstOrFail();
    $this->otherCategory = $this->user->categories()
        ->where('slug', 'outros')
        ->firstOrFail();
});

function createCardTransaction(array $overrides = []): Transaction
{
    return Transaction::query()->create(array_merge([
        'user_id' => test()->user->id,
        'account_id' => test()->account->id,
        'category_id' => test()->cardCategory->id,
        'transaction_type' => TransactionType::Expense,
        'amount' => 52160,
        'description' => 'Cartão itau Uniclass',
        'finished' => false,
        'recurrence' => false,
        'date' => '2026-11-17',
        'due_date' => '2026-11-17',
        'payment_date' => null,
        'attachment' => 'transactions/fatura.pdf',
    ], $overrides));
}

test('nao importa fatura de adiantamento', function () {
    $transaction = createCardTransaction([
        'description' => 'Adiantamento fatura cartão',
    ]);

    $result = app(CreditCardStatementImporter::class)->import($transaction);

    expect($result['imported'])->toBeFalse()
        ->and($transaction->isCreditCardBill())->toBeFalse();
});

test('nao importa fatura de outra categoria', function () {
    $transaction = createCardTransaction([
        'category_id' => test()->otherCategory->id,
        'description' => 'Conta de Luz',
    ]);

    expect($transaction->isCreditCardBill())->toBeFalse()
        ->and(app(CreditCardStatementImporter::class)->import($transaction)['imported'])->toBeFalse();
});

test('salva os gastos da fatura e abre a listagem', function () {
    Storage::fake('public');
    Storage::disk('public')->put('transactions/fatura.pdf', 'fake-pdf');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->once()
        ->andReturn("17/08 SUPERMERCADO BH 189,90\n18/08 UBER *TRIP 23,50\n");

    $transaction = createCardTransaction();

    $result = app(CreditCardStatementImporter::class)->import($transaction);

    expect($result['imported'])->toBeTrue()
        ->and($result['count'])->toBe(2)
        ->and(CreditCardPurchase::query()->where('transaction_id', $transaction->id)->count())->toBe(2)
        ->and($transaction->fresh()->hasCreditCardStatement())->toBeTrue();

    Livewire::test(ListCreditCardPurchases::class, ['record' => $transaction->getKey()])
        ->assertSuccessful()
        ->assertSee('SUPERMERCADO BH')
        ->assertSee('UBER *TRIP')
        ->assertSee('R$ 189,90');
});

test('lista mostra enviar fatura ao lado de visualizar e editar', function () {
    Carbon::setTestNow('2026-11-17 12:00:00');

    $card = createCardTransaction();
    $advance = createCardTransaction([
        'description' => 'Adiantamento fatura cartão',
    ]);

    Livewire::test(ListTransactions::class)
        ->assertTableActionExists('uploadStatement')
        ->assertTableActionVisible('uploadStatement', $card)
        ->assertTableActionHidden('uploadStatement', $advance)
        ->assertTableActionExists('view')
        ->assertTableActionExists('edit');

    Livewire::test(UploadCreditCardStatement::class, ['record' => $card->getKey()])
        ->assertSuccessful()
        ->assertSee('PDF da fatura')
        ->assertSee('Senha do PDF')
        ->assertSee('Ler fatura');
});

test('expande os gastos da fatura abaixo do cartao', function () {
    Carbon::setTestNow('2026-11-17 12:00:00');

    $card = createCardTransaction();
    CreditCardPurchase::query()->create([
        'user_id' => $this->user->id,
        'transaction_id' => $card->id,
        'occurred_on' => '2026-08-17',
        'description' => 'SUPERMERCADO BH',
        'amount' => 18990,
        'sort_order' => 0,
    ]);

    $other = createCardTransaction(['description' => 'Outro cartão']);

    Livewire::test(ListTransactions::class)
        ->assertDontSee('SUPERMERCADO BH')
        ->assertDontSee('Soma dos lançamentos')
        ->assertSeeHtml("toggleCreditCardStatement(&#039;{$card->getKey()}&#039;)")
        ->assertDontSeeHtml("toggleCreditCardStatement(&#039;{$other->getKey()}&#039;)")        ->call('toggleCreditCardStatement', $card->getKey())
        ->assertSee('SUPERMERCADO BH')
        ->assertSee('R$ 189,90')
        ->assertSee('Parcela')
        ->assertSee('Soma dos lançamentos')
        ->call('toggleCreditCardStatement', $card->getKey())
        ->assertDontSee('SUPERMERCADO BH');
});

test('envia o pdf da fatura pela tela de upload', function () {
    Storage::fake('public');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->once()
        ->andReturn("17/08 SUPERMERCADO BH 189,90\n");

    $card = createCardTransaction(['attachment' => null]);
    $pdf = UploadedFile::fake()->create('Fatura_Itau_20261030-193001.pdf', 120, 'application/pdf');

    Livewire::test(UploadCreditCardStatement::class, ['record' => $card->getKey()])
        ->set('pdf', $pdf)
        ->set('statement_password', '12345678900')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(TransactionResource::getUrl('index'));

    $statement = $card->fresh()->creditCardStatement;

    expect($card->fresh()->attachment)->not->toBeNull()
        ->and($statement)->not->toBeNull()
        ->and($statement->original_name)->toBe('Fatura_Itau_20261030-193001.pdf')
        ->and($statement->file_path)->toBe($card->fresh()->attachment)
        ->and($statement->items_count)->toBe(1)
        ->and($statement->total_amount)->toBe(18990)
        ->and($statement->imported_at)->not->toBeNull()
        ->and(CreditCardPurchase::query()->where('credit_card_statement_id', $statement->id)->count())->toBe(1)
        ->and($statement->bill_amount)->toBeNull()
        ->and($card->fresh()->amount)->toBe(52160);
});

test('usa o total desta fatura do pdf como valor da transação', function () {
    Storage::fake('public');

    $this->mock(CreditCardStatementTextExtractor::class)
        ->shouldReceive('extract')
        ->once()
        ->andReturn(<<<'TXT'
                     Vencimento: 19/10/2026                    = Total desta fatura                  4.001,39
    17/08 SUPERMERCADO BH 189,90
    TXT);

    $card = createCardTransaction(['attachment' => null]);

    Livewire::test(UploadCreditCardStatement::class, ['record' => $card->getKey()])
        ->set('pdf', UploadedFile::fake()->create('fatura.pdf', 120, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors();

    expect($card->fresh()->amount)->toBe(400139)
        ->and($card->fresh()->creditCardStatement->bill_amount)->toBe(400139)
        ->and($card->fresh()->creditCardStatement->total_amount)->toBe(18990);
});

test('formulario de cartao pede pdf da fatura e adiantamento nao', function () {
    expect(TransactionResource::acceptsCreditCardStatement(test()->cardCategory->id, 'Cartão itau Uniclass'))->toBeTrue()
        ->and(TransactionResource::acceptsCreditCardStatement(test()->cardCategory->id, 'Adiantamento fatura cartão'))->toBeFalse()
        ->and(TransactionResource::acceptsCreditCardStatement(test()->otherCategory->id, 'Cartão itau Uniclass'))->toBeFalse();
});
