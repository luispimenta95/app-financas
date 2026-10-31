<?php

namespace App\Services\Transactions;

use App\Models\Transactions\Payslip;
use Illuminate\Support\Carbon;

/**
 * Lê o texto (pdftotext -layout) do demonstrativo de pagamento da folha (Apdata).
 *
 * Quantidade, vencimento e desconto usam o mesmo formato numérico (0,00);
 * a coluna de cada valor é decidida pela posição em relação ao cabeçalho.
 */
class PayslipParser
{
    private const MONEY = '\d{1,3}(?:\.\d{3})*,\d{2}';

    /**
     * @return array{
     *     kind: string,
     *     reference_month: ?string,
     *     credit_date: ?string,
     *     base_salary: ?int,
     *     gross_amount: int,
     *     deductions_amount: int,
     *     net_amount: int,
     *     items: list<array{code: ?string, description: string, quantity: ?string, earnings: int, deductions: int, sort_order: int}>
     * }|null
     */
    public function parseText(string $text): ?array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $columns = null;
        $items = [];
        $gross = null;
        $deductions = null;
        $net = null;

        foreach ($lines as $line) {
            if ($columns === null) {
                $columns = $this->detectColumns($line);

                continue;
            }

            if (preg_match('/^\s*TOTAIS\b/u', $line)) {
                $values = $this->moneyTokens($line);
                $gross = isset($values[0]) ? $this->toCents($values[0]['value']) : null;
                $deductions = isset($values[1]) ? $this->toCents($values[1]['value']) : 0;

                continue;
            }

            if (preg_match('/L[ÍI]QUIDO:\s*('.self::MONEY.')/u', $line, $match)) {
                $net = $this->toCents($match[1]);

                break;
            }

            if ($gross === null && ($item = $this->parseItem($line, $columns, count($items))) !== null) {
                $items[] = $item;
            }
        }

        if ($items === [] || $net === null) {
            return null;
        }

        $gross ??= array_sum(array_column($items, 'earnings'));
        $deductions ??= array_sum(array_column($items, 'deductions'));

        return [
            'kind' => $this->detectKind($items),
            'reference_month' => $this->referenceMonth($text),
            'credit_date' => $this->creditDate($text),
            'base_salary' => $this->baseSalary($lines),
            'gross_amount' => $gross,
            'deductions_amount' => $deductions,
            'net_amount' => $net,
            'items' => $items,
        ];
    }

    /**
     * @return array{quantity: int, earnings: int, deductions: int}|null
     */
    private function detectColumns(string $line): ?array
    {
        if (!preg_match('/Cod\./u', $line) || !str_contains($line, 'Vencimentos') || !str_contains($line, 'Descontos')) {
            return null;
        }

        return [
            'quantity' => $this->columnEnd($line, 'Qtde.'),
            'earnings' => $this->columnEnd($line, 'Vencimentos'),
            'deductions' => $this->columnEnd($line, 'Descontos'),
        ];
    }

    private function columnEnd(string $line, string $label): int
    {
        $position = mb_strpos($line, $label);

        return $position === false ? -1000 : $position + mb_strlen($label);
    }

    /**
     * @param  array{quantity: int, earnings: int, deductions: int}  $columns
     * @return array{code: ?string, description: string, quantity: ?string, earnings: int, deductions: int, sort_order: int}|null
     */
    private function parseItem(string $line, array $columns, int $sortOrder): ?array
    {
        if (!preg_match('/^\s*(\d{3,5})\s+(\S.*)$/u', $line, $match)) {
            return null;
        }

        $tokens = $this->moneyTokens($line);

        if ($tokens === []) {
            return null;
        }

        $description = trim(mb_substr($line, 0, $tokens[0]['start']));
        $description = trim((string) preg_replace('/^\s*'.preg_quote($match[1], '/').'\s+/u', '', $description));

        if ($description === '') {
            return null;
        }

        $item = [
            'code' => $match[1],
            'description' => $description,
            'quantity' => null,
            'earnings' => 0,
            'deductions' => 0,
            'sort_order' => $sortOrder,
        ];

        foreach ($tokens as $token) {
            $column = $this->nearestColumn($token['end'], $columns);

            if ($column === 'quantity') {
                $item['quantity'] = $token['value'];
            } else {
                $item[$column] += $this->toCents($token['value']);
            }
        }

        if ($item['earnings'] === 0 && $item['deductions'] === 0) {
            return null;
        }

        return $item;
    }

    /**
     * @param  array{quantity: int, earnings: int, deductions: int}  $columns
     */
    private function nearestColumn(int $end, array $columns): string
    {
        $best = 'earnings';
        $bestDistance = PHP_INT_MAX;

        foreach ($columns as $name => $columnEnd) {
            $distance = abs($end - $columnEnd);

            if ($distance < $bestDistance) {
                $best = $name;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @return list<array{value: string, start: int, end: int}>
     */
    private function moneyTokens(string $line): array
    {
        preg_match_all('/(?<![\d.,])'.self::MONEY.'(?![\d,])/u', $line, $matches, PREG_OFFSET_CAPTURE);

        return array_map(function (array $match) use ($line): array {
            $start = mb_strlen(substr($line, 0, $match[1]));

            return [
                'value' => $match[0],
                'start' => $start,
                'end' => $start + mb_strlen($match[0]),
            ];
        }, $matches[0]);
    }

    /**
     * @param  list<array{description: string, earnings: int}>  $items
     */
    private function detectKind(array $items): string
    {
        foreach ($items as $item) {
            if ($item['earnings'] > 0 && str($item['description'])->lower()->contains('adiantamento')) {
                return Payslip::KIND_ADVANCE;
            }
        }

        return Payslip::KIND_MONTHLY;
    }

    private function referenceMonth(string $text): ?string
    {
        if (!preg_match('/M[êe]s\s*\/\s*Ano.*\R.*?\b(\d{2})\/(\d{4})\b/u', $text, $match)) {
            return null;
        }

        return $match[2].'-'.$match[1];
    }

    private function creditDate(string $text): ?string
    {
        if (!preg_match('/Data de Cr[ée]dito.*\R.*?\b(\d{2}\/\d{2}\/\d{4})\b/u', $text, $match)) {
            return null;
        }

        return Carbon::createFromFormat('d/m/Y', $match[1])->toDateString();
    }

    /**
     * @param  list<string>  $lines
     */
    private function baseSalary(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (str_contains($line, 'Matrícula') && str_contains($line, 'Salário')) {
                $tokens = $this->moneyTokens($lines[$index + 1] ?? '');

                return isset($tokens[0]) ? $this->toCents($tokens[0]['value']) : null;
            }
        }

        return null;
    }

    private function toCents(string $value): int
    {
        return (int) round(((float) str_replace(['.', ','], ['', '.'], $value)) * 100);
    }
}
