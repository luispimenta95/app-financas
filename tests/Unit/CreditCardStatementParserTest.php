<?php

use App\Services\Transactions\CreditCardStatementParser;
use Illuminate\Support\Carbon;

test('lê lançamentos no formato itau e mastercard', function () {
    $text = <<<'TXT'
MASTERCARD BLACK
17/08 SUPERMERCADO BH 189,90
18/08 UBER *TRIP 23,50
19/08 NETFLIX.COM 03/12 55,90
20/08 PAGAMENTO RECEBIDO 1.200,00
21/08 AMAZON MARKETPLACE 1.234,56
22/08 CREDITO LOJA 50,00 CR
TOTAL DA FATURA 1.503,86
TXT;

    $items = (new CreditCardStatementParser())->parseText($text, Carbon::parse('2026-09-17'));

    expect($items)->toHaveCount(5)
        ->and($items[0]['description'])->toBe('SUPERMERCADO BH')
        ->and($items[0]['amount'])->toBe(18990)
        ->and($items[0]['occurred_on'])->toBe('2026-08-17')
        ->and($items[2]['installment_label'])->toBe('03/12')
        ->and($items[2]['amount'])->toBe(5590)
        ->and($items[3]['amount'])->toBe(123456)
        ->and($items[4]['amount'])->toBe(-5000);
});

test('lê lançamentos no formato nubank', function () {
    $text = <<<'TXT'
08 AGO IFOOD 42,00
09 AGO POSTO SHELL 150,15
TXT;

    $items = (new CreditCardStatementParser())->parseText($text, Carbon::parse('2026-09-17'));

    expect($items)->toHaveCount(2)
        ->and($items[0]['occurred_on'])->toBe('2026-08-08')
        ->and($items[0]['description'])->toBe('IFOOD')
        ->and($items[1]['amount'])->toBe(15015);
});

test('lê fatura itau em duas colunas e ignora limite e proximas faturas', function () {
    $text = <<<'TXT'
10/09 Pagamento via conta -1.770,63 18/09 SPOLETOBRASILIABRA 45,90
18/09 ROUND COMERCIO 01/06 76,69
21/09 Desconto em Fatura -27,28
10/06 AUTO ENERGIABR 04/05 158,00
12/08 PIX VICTOR HUG 02/03 1.868,22
L Total dos lançamentos atuais 5.317,31
Limite de crédito 46.167,00
Compras parceladas - próximas faturas
10/06 AUTO ENERGIABR 05/05 158,00
Próxima fatura 4.202,08
Total para próximas faturas 16.604,24
TXT;

    $items = (new CreditCardStatementParser())->parseText($text, Carbon::parse('2026-10-19'));
    $sum = array_sum(array_column($items, 'amount'));

    expect($items)->toHaveCount(5)
        ->and($items[0]['description'])->toBe('SPOLETOBRASILIABRA')
        ->and($items[0]['amount'])->toBe(4590)
        ->and($items[1]['installment_label'])->toBe('01/06')
        ->and($items[2]['amount'])->toBe(-2728)
        ->and($sum)->toBe(4590 + 7669 - 2728 + 15800 + 186822);
});

test('ignora adiantamento e totais', function () {
    $text = <<<'TXT'
10/08 MERCADO 10,00
SALDO ANTERIOR 200,00
TXT;

    $items = (new CreditCardStatementParser())->parseText($text, Carbon::parse('2026-09-17'));

    expect($items)->toHaveCount(1)
        ->and($items[0]['description'])->toBe('MERCADO');
});

test('lê o total desta fatura mesmo com dígitos quebrados por espaço', function () {
    $parser = new CreditCardStatementParser;

    expect($parser->parseBillTotal("Total da fatura anterior   2.02 4,57\nVencimento: 17/09/2026   = Total desta fatura      1.77 0,63\n"))->toBe(177063)
        ->and($parser->parseBillTotal("O total da sua fatura é:        Com vencimento em:\n   R$ 4.001,39          19/10/2026\n"))->toBe(400139)
        ->and($parser->parseBillTotal("Valor do Documento                  R$ 4.001,39\n"))->toBe(400139)
        ->and($parser->parseBillTotal("17/08 SUPERMERCADO BH 189,90\n"))->toBeNull();
});

test('não encerra a leitura em títulos da coluna da direita e usa o ano anterior para datas após o vencimento', function () {
    $text = implode("\n", [
        '                 DATA                                                 VALOR EM R$',
        '                 01/10    Pagamento via conta                              -377,09',
        '                                                                                             Encargos cobrados nesta fatura',
        '                 Lançamentos: compras e saques                                               Multa por atraso                                   2,00 %       0,00',
        '                 DATA     ESTABELECIMENTO                             VALOR EM R$',
        '                 23/10    LOJA VIAGEM 12/12                                 982,41',
        '                                                                                             Novo teto de juros do cartão de crédito',
        '                 11/11    ESCRITORIO 11/12                                  188,00',
        '                 12/09    MERCADO EXEMPLO 01/06                              33,20       Juros e encargos financeiros até o momento                      0,00',
        '                 Compras parceladas - próximas faturas                                       Fique atento aos encargos para o próximo',
        '                 11/11    ESCRITORIO 12/12                                 188,00',
    ]);

    $items = (new CreditCardStatementParser)->parseText($text, Carbon::parse('2026-10-19'));

    expect($items)->toHaveCount(3)
        ->and(array_column($items, 'description'))->toBe(['LOJA VIAGEM', 'ESCRITORIO', 'MERCADO EXEMPLO'])
        ->and(array_column($items, 'occurred_on'))->toBe(['2025-10-23', '2025-11-11', '2026-09-12'])
        ->and(array_sum(array_column($items, 'amount')))->toBe(120361);
});
