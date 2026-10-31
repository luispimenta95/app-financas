<?php

namespace App\Services\Transactions;

use Illuminate\Support\Carbon;

class CreditCardStatementParser
{
    private const LEFT_COLUMN_LIMIT = 60;

    /**
     * Valor cobrado na fatura, em centavos. Alguns PDFs do Itaú quebram os dígitos com espaços ("1.77 0,63").
     */
    public function parseBillTotal(string $text): ?int
    {
        $money = '(?:R\s*\$\s*)?(\d[\d. ]*,\s?\d\s?\d)';
        $patterns = [
            '/total\s+desta\s+fatura\s+'.$money.'/iu',
            '/o\s+total\s+da\s+sua\s+fatura\s+[ée]:.*\R\s*'.$money.'/iu',
            '/valor\s+do\s+documento\s+'.$money.'/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return $this->toCents(str_replace(' ', '', $match[1]));
            }
        }

        return null;
    }

    /**
     * @return list<array{occurred_on: ?string, description: string, amount: int, installment_label: ?string}>
     */
    public function parseText(string $text, ?Carbon $referenceDate = null): array
    {
        $items = [];
        $sort = 0;
        $seen = [];

        foreach (preg_split('/\R/u', $text) as $rawLine) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $rawLine) ?? '');

            if ($line === '') {
                continue;
            }

            if ($this->isStopSection($rawLine)) {
                break;
            }

            foreach ($this->parseLineItems($line, $referenceDate) as $parsed) {
                $fingerprint = implode('|', [
                    $parsed['occurred_on'] ?? '',
                    mb_strtolower($parsed['description']),
                    (string) $parsed['amount'],
                    $parsed['installment_label'] ?? '',
                ]);

                if (isset($seen[$fingerprint])) {
                    continue;
                }

                $seen[$fingerprint] = true;
                $parsed['sort_order'] = $sort++;
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * @return list<array{occurred_on: ?string, description: string, amount: int, installment_label: ?string}>
     */
    private function parseLineItems(string $line, ?Carbon $referenceDate): array
    {
        $matched = preg_match_all(
            '/(\d{2}\/\d{2}(?:\/\d{2,4})?)\s+(.+?)\s+(-)?(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2})(?:\s*(CR|DB))?/u',
            $line,
            $matches,
            PREG_SET_ORDER
        );

        if (!$matched) {
            $fallback = $this->parseFallbackLine($line, $referenceDate);

            return $fallback ? [$fallback] : [];
        }

        $items = [];

        foreach ($matches as $match) {
            $parsed = $this->hydrateItem(
                trim($match[1] ?? ''),
                trim($match[2] ?? ''),
                ($match[3] ?? '') === '-' || strcasecmp((string) ($match[5] ?? ''), 'CR') === 0,
                $this->toCents($match[4]),
                $referenceDate,
            );

            if ($parsed !== null) {
                $items[] = $parsed;
            }
        }

        return $items;
    }

    /**
     * @return array{occurred_on: ?string, description: string, amount: int, installment_label: ?string}|null
     */
    private function parseFallbackLine(string $line, ?Carbon $referenceDate): ?array
    {
        if (!preg_match('/^(.*?)(-)?(?:R\$\s*)?(\d{1,3}(?:\.\d{3})*,\d{2})(?:\s*(CR|DB))?$/iu', $line, $amountMatch)) {
            return null;
        }

        return $this->hydrateItem(
            '',
            trim($amountMatch[1]),
            $amountMatch[2] === '-' || strcasecmp((string) ($amountMatch[4] ?? ''), 'CR') === 0,
            $this->toCents($amountMatch[3]),
            $referenceDate,
        );
    }

    /**
     * @return array{occurred_on: ?string, description: string, amount: int, installment_label: ?string}|null
     */
    private function hydrateItem(string $leadingDate, string $rest, bool $negative, int $amount, ?Carbon $referenceDate): ?array
    {
        if ($amount === 0) {
            return null;
        }

        if ($negative) {
            $amount *= -1;
        }

        $occurredOn = $leadingDate !== ''
            ? $this->normalizeDate($leadingDate, $referenceDate)
            : null;

        if ($occurredOn === null) {
            $monthName = $this->matchLeadingDate($rest, $referenceDate);

            if ($monthName !== null) {
                [$occurredOn, $rest] = $monthName;
            }
        }

        $installment = null;

        if (preg_match('/^(.*?)(?:\s|-)?(\d{1,2}\/\d{1,2})$/u', $rest, $parcelMatch)) {
            $candidate = $parcelMatch[2];
            [$parcelCurrent, $parcelTotal] = array_map('intval', explode('/', $candidate));

            if ($parcelCurrent > 0 && $parcelTotal > 1 && $parcelCurrent <= $parcelTotal && $parcelTotal <= 48) {
                $installment = sprintf('%02d/%02d', $parcelCurrent, $parcelTotal);
                $rest = trim($parcelMatch[1], " \t-");
            }
        }

        $description = trim($rest, " \t-");
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');

        if ($description === '' || mb_strlen($description) < 3 || preg_match('/^R\$$/u', $description)) {
            return null;
        }

        if ($this->isNoise($description) || $this->isCategoryOnly($description)) {
            return null;
        }

        if ($occurredOn === null && $installment === null && !preg_match('/iof/iu', $description)) {
            return null;
        }

        return [
            'occurred_on' => $occurredOn,
            'description' => mb_substr($description, 0, 255),
            'amount' => $amount,
            'installment_label' => $installment,
        ];
    }

    /**
     * @return array{0: ?string, 1: string}|null
     */
    private function matchLeadingDate(string $rest, ?Carbon $referenceDate): ?array
    {
        if (preg_match('/^(\d{2}\/\d{2}(?:\/\d{2,4})?)(?:\s+\d{2}\/\d{2}(?:\/\d{2,4})?)?\s+(.*)$/u', $rest, $match)) {
            return [$this->normalizeDate($match[1], $referenceDate), $match[2]];
        }

        if (preg_match('/^(\d{1,2})\s+(JAN|FEV|MAR|ABR|MAI|JUN|JUL|AGO|SET|OUT|NOV|DEZ)\b\s+(.*)$/iu', $rest, $match)) {
            $monthMap = [
                'JAN' => 1, 'FEV' => 2, 'MAR' => 3, 'ABR' => 4, 'MAI' => 5, 'JUN' => 6,
                'JUL' => 7, 'AGO' => 8, 'SET' => 9, 'OUT' => 10, 'NOV' => 11, 'DEZ' => 12,
            ];
            $day = (int) $match[1];
            $month = $monthMap[mb_strtoupper($match[2])] ?? null;

            if ($month && $day >= 1 && $day <= 31) {
                $year = $this->resolveYear($month, $day, $referenceDate);

                if (checkdate($month, $day, $year)) {
                    return [sprintf('%04d-%02d-%02d', $year, $month, $day), $match[3]];
                }
            }
        }

        return null;
    }

    private function normalizeDate(string $value, ?Carbon $referenceDate): ?string
    {
        if (!preg_match('/^(\d{2})\/(\d{2})(?:\/(\d{2,4}))?$/', $value, $match)) {
            return null;
        }

        $day = (int) $match[1];
        $month = (int) $match[2];
        $year = isset($match[3])
            ? ((int) $match[3] < 100 ? 2000 + (int) $match[3] : (int) $match[3])
            : $this->resolveYear($month, $day, $referenceDate);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Lançamentos sem ano nunca são posteriores ao vencimento da fatura;
     * datas "à frente" (ex.: parcela 12/12 comprada em 23/10 numa fatura de 19/10) são do ano anterior.
     */
    private function resolveYear(int $month, int $day, ?Carbon $referenceDate): int
    {
        $reference = ($referenceDate ?? now())->copy()->startOfDay();
        $year = (int) $reference->year;

        if (!checkdate($month, $day, $year)) {
            return $year;
        }

        return Carbon::create($year, $month, $day)->greaterThan($reference) ? $year - 1 : $year;
    }

    private function toCents(string $amount): int
    {
        return (int) str($amount)->replace(['.', ','], ['', ''])->toInteger();
    }

    /**
     * Em PDFs de duas colunas (pdftotext -layout), "Encargos cobrados" e "Novo teto de juros"
     * podem aparecer na coluna da direita ao lado dos lançamentos; só encerra se o título
     * estiver na coluna da esquerda.
     */
    private function isStopSection(string $rawLine): bool
    {
        $normalized = mb_strtolower(str_replace("\t", '    ', $rawLine));

        if (!preg_match(
            '/compras parceladas|pr[oó]ximas faturas|limites de cr[eé]dito|encargos cobrados nesta fatura|novo teto de juros/u',
            $normalized,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            return false;
        }

        return mb_strlen(substr($normalized, 0, $match[0][1])) < self::LEFT_COLUMN_LIMIT;
    }

    private function isCategoryOnly(string $description): bool
    {
        return (bool) preg_match(
            '/^(restaurante|supermercado|vestu[aá]rio|outros|servi[cç]os|educa[cç][aã]o|eletronicos|automobile)\s+(bras[ií]lia|s[aã]o paulo)$/iu',
            $description
        );
    }

    private function isNoise(string $line): bool
    {
        $normalized = mb_strtolower($line);

        return (bool) preg_match(
            '/pagamento via conta|pagamento recebido|pagamentos? efetuados|pagto efetivado|total dos pagamentos|saldo anterior|saldo atual|saldo financiado|saldo devedor|limite total|limite dispon|limite de cr[eé]dito|vencimento da fatura|total da fatura|total desta fatura|fatura anterior|valor total|valor do documento|nome do pagador|encargos totais|juros de mora|juros m[aá]ximos|iof de financiamento|d[oó]lar de convers[aã]o|total transa[cç][oõ]es|total lan[cç]amentos|lan[cç]amentos atuais|lan[cç]amentos no cart[aã]o|lan[cç]amentos produtos|lan[cç]amentos internacionais|lan[cç]amentos:|pagina\s+\d|mastercard black|itau uniclass|demonstrativo|pagamento m[ií]nimo|total a pagar|valor total financiado|resumo da fatura|previs[aã]o|titular |cart[aã]o \d|banco ita[uú]|nossos canais/u',
            $normalized
        );
    }
}
