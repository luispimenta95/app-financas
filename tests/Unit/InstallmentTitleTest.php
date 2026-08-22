<?php

use App\Services\Transactions\InstallmentTitle;

test('formata o titulo em portugues como parcela x de y', function () {
    expect(InstallmentTitle::label(2, 12))->toBe('Parcela 2 de 12')
        ->and(InstallmentTitle::format('Geladeira', 1, 10))->toBe('Geladeira - Parcela 1 de 10');
});

test('nao adiciona sufixo quando ha apenas um mes', function () {
    expect(InstallmentTitle::format('Aluguel', 1, 1))->toBe('Aluguel');
});

test('remove o sufixo de parcela da descricao', function () {
    expect(InstallmentTitle::baseDescription('Geladeira - Parcela 3 de 10'))->toBe('Geladeira')
        ->and(InstallmentTitle::baseDescription('Geladeira - Transação 3 de 10'))->toBe('Geladeira')
        ->and(InstallmentTitle::baseDescription('Aluguel'))->toBe('Aluguel');
});

test('formatar de novo nao duplica o sufixo', function () {
    expect(InstallmentTitle::format('Geladeira - Parcela 1 de 10', 2, 10))
        ->toBe('Geladeira - Parcela 2 de 10')
        ->and(InstallmentTitle::format('Geladeira - Transação 1 de 10', 2, 10))
        ->toBe('Geladeira - Parcela 2 de 10')
        ->and(InstallmentTitle::format('Celular Sabrina - Parcela 2 de 10 - Transação 2 de 10', 2, 10))
        ->toBe('Celular Sabrina - Parcela 2 de 10');
});

test('listagem nao mostra transacao x de y junto da parcela', function () {
    expect(InstallmentTitle::withoutLegacyTransactionSuffix(
        'Celular Sabrina - Parcela 2 de 10 - Transação 2 de 10'
    ))->toBe('Celular Sabrina - Parcela 2 de 10');
});
