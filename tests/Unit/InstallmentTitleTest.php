<?php

use App\Services\Transactions\InstallmentTitle;

test('formata o titulo em portugues como transacao x de y', function () {
    expect(InstallmentTitle::label(2, 12))->toBe('Transação 2 de 12')
        ->and(InstallmentTitle::format('Geladeira', 1, 10))->toBe('Geladeira - Transação 1 de 10');
});

test('nao adiciona sufixo quando ha apenas um mes', function () {
    expect(InstallmentTitle::format('Aluguel', 1, 1))->toBe('Aluguel');
});

test('remove o sufixo de parcela da descricao', function () {
    expect(InstallmentTitle::baseDescription('Geladeira - Transação 3 de 10'))->toBe('Geladeira')
        ->and(InstallmentTitle::baseDescription('Aluguel'))->toBe('Aluguel');
});

test('formatar de novo nao duplica o sufixo', function () {
    expect(InstallmentTitle::format('Geladeira - Transação 1 de 10', 2, 10))
        ->toBe('Geladeira - Transação 2 de 10');
});
