<?php

use App\Enums\TransactionType;
use App\Models\Transactions\Boleto;
use App\Models\User;
use App\Services\Transactions\BoletoImporter;
use App\Services\Transactions\BoletoParser;
use Illuminate\Support\Facades\Storage;

test('parser lê segunda via Caesb sem linha digitável em texto', function () {
    $text = <<<'TXT'
    TOTAL A PAGAR
    40,44
    COMPANHIA DE SANEAMENTO AMBIENTAL DO DISTRITO FEDERAL
    INSCRIÇÃO:
    MÊS/ANO:
    LUIS FELIPE ARAÚJO PIMENTA
    27/11/2026
    928114-2
    DATA DE VENCIMENTO
    928114-2	11/2026	40,4427/11/20261
    2ª VIA - CAESB
    TXT;

    $parsed = app(BoletoParser::class)->parseText($text);

    expect($parsed)->not->toBeNull()
        ->and($parsed['amount'])->toBe(4044)
        ->and($parsed['due_date']->toDateString())->toBe('2026-11-27')
        ->and($parsed['beneficiary'])->toBe('CAESB')
        ->and($parsed['digitable_line'])->toStartWith('ref:caesb:');
});

test('parser extrai boleto de concessionária (48 dígitos)', function () {
    // Produto 8, segmento 1, valor-id 6, valor R$ 49,04 (= 00000004904)
    $barcode = '81600000000490400000000000000000000000000000';
    // Monta linha 48 com DV placeholder (parser não valida DV).
    $digitable = substr($barcode, 0, 11).'0'
        .substr($barcode, 11, 11).'0'
        .substr($barcode, 22, 11).'0'
        .substr($barcode, 33, 11).'0';

    $text = <<<TXT
    Beneficiário: Companhia de Água
    Vencimento: 23/10/2026
    Valor a pagar: R$ 49,04
    {$digitable}
    TXT;

    $parsed = app(BoletoParser::class)->parseText($text);

    expect($parsed)->not->toBeNull()
        ->and(strlen($parsed['digitable_line']))->toBe(48)
        ->and($parsed['amount'])->toBe(4904)
        ->and($parsed['due_date']->toDateString())->toBe('2026-10-23')
        ->and($parsed['beneficiary'])->toContain('Água');
});

test('parser extrai linha digitável, valor e vencimento', function () {
    // Banco 237, fator 1000 (= 22/02/2025 no ciclo novo), valor R$ 150,75
    $digitable = '23793.38128 60000.000003 00000.000400 1 10000000015075';
    $text = <<<TXT
    Beneficiário: ACME Energia LTDA
    Pagador: João da Silva
    Nosso Número: 123456
    Número do Documento: NF-7788
    Vencimento: 22/02/2025
    Valor do Documento: R$ 150,75
    Linha Digitável: {$digitable}
    TXT;

    $parsed = app(BoletoParser::class)->parseText($text);

    expect($parsed)->not->toBeNull()
        ->and($parsed['digitable_line'])->toBe(preg_replace('/\D+/', '', $digitable))
        ->and($parsed['amount'])->toBe(15075)
        ->and($parsed['bank_code'])->toBe('237')
        ->and($parsed['beneficiary'])->toContain('ACME')
        ->and($parsed['due_date']->toDateString())->toBe('2025-02-22');
});

test('importer cria lançamento e atualiza quando a linha digitável se repete', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $account = $user->accounts()->first();
    $category = $user->categories()->where('slug', 'outros')->first();

    $digitable = '2379338128600000000300000000400110000000015075';
    $text = "Beneficiário: Conta de Luz\n{$digitable}\nValor do Documento: R$ 150,75\n";

    Storage::disk('public')->put('boletos/teste.pdf', '%PDF-1.4 fake');

    $parser = Mockery::mock(BoletoParser::class);
    $parser->shouldReceive('parseText')->andReturn([
        'digitable_line' => $digitable,
        'barcode' => null,
        'bank_code' => '237',
        'beneficiary' => 'Conta de Luz',
        'payer' => null,
        'document_number' => null,
        'our_number' => null,
        'amount' => 15075,
        'due_date' => now()->addDays(5)->startOfDay(),
        'description' => 'Boleto · Conta de Luz',
    ]);

    $extractor = Mockery::mock(\App\Services\Transactions\CreditCardStatementTextExtractor::class);
    $extractor->shouldReceive('extract')->andReturn($text);

    $importer = new BoletoImporter($parser, $extractor);

    $first = $importer->import(
        user: $user,
        relativePath: 'boletos/teste.pdf',
        accountId: $account->id,
        categoryId: $category->id,
    );

    expect($first['imported'])->toBeTrue()
        ->and($first['created'])->toBeTrue()
        ->and(Boleto::query()->count())->toBe(1);

    $parser2 = Mockery::mock(BoletoParser::class);
    $parser2->shouldReceive('parseText')->andReturn([
        'digitable_line' => $digitable,
        'barcode' => null,
        'bank_code' => '237',
        'beneficiary' => 'Conta de Luz',
        'payer' => null,
        'document_number' => null,
        'our_number' => null,
        'amount' => 19990,
        'due_date' => now()->addDays(10)->startOfDay(),
        'description' => 'Boleto · Conta de Luz',
    ]);

    $extractor2 = Mockery::mock(\App\Services\Transactions\CreditCardStatementTextExtractor::class);
    $extractor2->shouldReceive('extract')->andReturn($text);

    $second = (new BoletoImporter($parser2, $extractor2))->import(
        user: $user,
        relativePath: 'boletos/teste.pdf',
        accountId: $account->id,
        categoryId: $category->id,
    );

    expect($second['imported'])->toBeTrue()
        ->and($second['created'])->toBeFalse()
        ->and(Boleto::query()->count())->toBe(1)
        ->and($second['amount'])->toBe(19990);

    $transaction = \App\Models\Transactions\Transaction::query()->find($second['transaction_id']);

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(19990)
        ->and($transaction->transaction_type)->toBe(TransactionType::Expense);
});
