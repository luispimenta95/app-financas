<?php

use App\Models\Transactions\Payslip;
use App\Services\Transactions\PayslipParser;

const MONTHLY_PAYSLIP = <<<'TXT'
                                                                                    Mês / Ano          Folha Nº
                                          DEMONSTRATIVO DE                              10/2026         1/ 1
                                             PAGAMENTO
Empresa                                                                          CNPJ
EMPRESA EXEMPLO S.A.                                                              00.000.000/0001-00
Nome do Funcionário                                          Cargo
FULANO DE TAL                                                 Analista
Matrícula             Data de Admissão                                Salário               Dep IR    Dep SF
000001                01/01/2020                                                8.241,13      00         00

  Cod.                        Descrição                      Qtde.    Vencimentos            Descontos
   0004      Salário                                          30,00              8.241,13
   0034      Triênio                                           2,00                494,46
   0247      Hora Extra Noturna 100%                          17,00              1.485,05
   0344      DSR sobre Horas Extras Noturnas                                        55,23
   0346      DSR sobre Horas Extras                                                285,59
   8774      Adicional Noturno Horas Extras 25%               26,30                287,18
   2075      Desc Vale-Alimentação                                                                        1,00
   2103      INSS Normal                                                                                988,07
   2125      Imposto de Renda Normal                                                                  1.802,92
   5562      Desc Adiantamento Quinzenal                                                              3.296,45
   8030      PP. BÁSICA - VGBL ITAU                                                                     659,29




                               TOTAIS                                       10.848,64                6.747,73
Banco       Agência      Conta Corrente    Data de Crédito
000         0.000        00000-0           30/10/2026                LÍQUIDO:                      4.100,91

Base de FGTS Normal                       10848.64
Valor FGTS Normal                           867.89
TXT;

const ADVANCE_PAYSLIP = <<<'TXT'
                                                                                   Mês / Ano        Folha Nº
                                          DEMONSTRATIVO DE                             10/2026       1/ 1
                                             PAGAMENTO
Matrícula             Data de Admissão                               Salário               Dep IR   Dep SF
000001                01/01/2020                                               8.241,13      00       00

  Cod.                        Descrição                     Qtde.    Vencimentos            Descontos
   5840      Adiantamento Quinzenal                           0,40              3.296,45




                               TOTAIS                                          3.296,45               0,00
Banco       Agência      Conta Corrente   Data de Crédito
000         0.000        00000-0          15/10/2026                LÍQUIDO:                      3.296,45
TXT;

test('lê contracheque mensal separando vencimentos e descontos pela coluna', function () {
    $payslip = (new PayslipParser)->parseText(MONTHLY_PAYSLIP);

    expect($payslip['kind'])->toBe(Payslip::KIND_MONTHLY)
        ->and($payslip['reference_month'])->toBe('2026-10')
        ->and($payslip['credit_date'])->toBe('2026-10-30')
        ->and($payslip['base_salary'])->toBe(824113)
        ->and($payslip['gross_amount'])->toBe(1084864)
        ->and($payslip['deductions_amount'])->toBe(674773)
        ->and($payslip['net_amount'])->toBe(410091)
        ->and($payslip['items'])->toHaveCount(11)
        ->and(array_sum(array_column($payslip['items'], 'earnings')))->toBe(1084864)
        ->and(array_sum(array_column($payslip['items'], 'deductions')))->toBe(674773);

    expect($payslip['items'][0])->toMatchArray([
        'code' => '0004',
        'description' => 'Salário',
        'quantity' => '30,00',
        'earnings' => 824113,
        'deductions' => 0,
    ])->and($payslip['items'][3])->toMatchArray([
        'description' => 'DSR sobre Horas Extras Noturnas',
        'quantity' => null,
        'earnings' => 5523,
    ])->and($payslip['items'][9])->toMatchArray([
        'description' => 'Desc Adiantamento Quinzenal',
        'earnings' => 0,
        'deductions' => 329645,
    ]);
});

test('lê adiantamento quinzenal', function () {
    $payslip = (new PayslipParser)->parseText(ADVANCE_PAYSLIP);

    expect($payslip['kind'])->toBe(Payslip::KIND_ADVANCE)
        ->and($payslip['credit_date'])->toBe('2026-10-15')
        ->and($payslip['gross_amount'])->toBe(329645)
        ->and($payslip['deductions_amount'])->toBe(0)
        ->and($payslip['net_amount'])->toBe(329645)
        ->and($payslip['items'])->toHaveCount(1)
        ->and($payslip['items'][0])->toMatchArray([
            'code' => '5840',
            'quantity' => '0,40',
            'earnings' => 329645,
        ]);
});

test('retorna nulo para pdf que não é contracheque', function () {
    expect((new PayslipParser)->parseText("17/08 SUPERMERCADO BH 189,90\n"))->toBeNull();
});
