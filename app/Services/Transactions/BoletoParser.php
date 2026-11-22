<?php

namespace App\Services\Transactions;

use Illuminate\Support\Carbon;

class BoletoParser
{
    /**
     * @return array{
     *     digitable_line: string,
     *     barcode: string|null,
     *     bank_code: string|null,
     *     beneficiary: string|null,
     *     payer: string|null,
     *     document_number: string|null,
     *     our_number: string|null,
     *     amount: int,
     *     due_date: Carbon|null,
     *     description: string
     * }|null
     */
    public function parseText(string $text): ?array
    {
        $normalized = $this->normalizeText($text);

        $amountFromText = $this->extractAmountFromText($normalized);
        $dueFromText = $this->extractDueDateFromText($normalized);
        $digitable = $this->extractDigitableLine($normalized, $amountFromText);

        if ($digitable === null) {
            $digitable = $this->extractReferenceKey($normalized, $dueFromText);
        }

        if ($digitable === null) {
            return null;
        }

        $barcode = $this->digitableToBarcode($digitable);
        $amount = ctype_digit($digitable) ? $this->amountFromDigitable($digitable) : 0;
        $dueDate = ctype_digit($digitable) ? $this->dueDateFromDigitable($digitable) : null;
        $bankCode = ctype_digit($digitable) ? $this->bankOrSegmentCode($digitable) : null;

        // Texto impresso (TOTAL A PAGAR etc.) tem prioridade sobre valor “fantasma” da linha.
        if ($amountFromText !== null && $amountFromText > 0) {
            $amount = $amountFromText;
        } elseif ($amount <= 0) {
            $amount = 0;
        }

        if ($dueDate === null) {
            $dueDate = $dueFromText;
        }

        $beneficiary = $this->extractBeneficiary($normalized);
        $payer = $this->extractLabeledValue($normalized, [
            'pagador',
            'sacado',
            'nome do pagador',
            'cliente',
        ]);
        $documentNumber = $this->extractLabeledValue($normalized, [
            'numero do documento',
            'nº do documento',
            'no do documento',
            'documento',
            'referencia',
            'referência',
            'inscrição',
            'inscricao',
        ], maxLength: 40);
        $ourNumber = $this->extractLabeledValue($normalized, [
            'nosso numero',
            'nosso nº',
            'nosso no',
            'identificador',
        ], maxLength: 40);

        if ($amount <= 0) {
            return null;
        }

        $description = $beneficiary
            ? 'Boleto · '.$beneficiary
            : (str_starts_with($digitable, 'ref:')
                ? 'Boleto'
                : (strlen($digitable) === 48 ? 'Boleto concessionária' : 'Boleto '.$bankCode));

        return [
            'digitable_line' => $digitable,
            'barcode' => $barcode,
            'bank_code' => $bankCode,
            'beneficiary' => $beneficiary,
            'payer' => $payer,
            'document_number' => $documentNumber,
            'our_number' => $ourNumber,
            'amount' => $amount,
            'due_date' => $dueDate,
            'description' => $description,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function extractDigitableLine(string $text, ?int $amountFromText = null): ?string
    {
        $priority = [];

        // 1) Linha bancária formatada.
        if (preg_match(
            '/\b(\d{5}\s*\.\s*\d{5}\s+\d{5}\s*\.\s*\d{6}\s+\d{5}\s*\.\s*\d{6}\s+\d\s+\d{14})\b/u',
            $text,
            $match,
        )) {
            $digits = preg_replace('/\D+/', '', $match[1]);

            if (is_string($digits) && strlen($digits) === 47) {
                $priority[] = $digits;
            }
        }

        // 2) Concessionária formatada.
        if (preg_match(
            '/\b((?:\d{11}\s*[-\.]?\s*\d[\s]+){3}\d{11}\s*[-\.]?\s*\d)\b/u',
            $text,
            $match,
        )) {
            $digits = preg_replace('/\D+/', '', $match[1]);

            if (is_string($digits) && strlen($digits) === 48 && str_starts_with($digits, '8')) {
                $priority[] = $digits;
            }
        }

        $compact = preg_replace('/\s+/', '', $text) ?? $text;

        // 3) Sequências contínuas delimitadas (não coladas a outros dígitos).
        if (preg_match_all('/(?<!\d)(8\d{47})(?!\d)/', $compact, $matches)) {
            foreach ($matches[1] as $candidate) {
                $priority[] = $candidate;
            }
        }

        if (preg_match_all('/(?<!\d)(\d{47})(?!\d)/', $compact, $matches)) {
            foreach ($matches[1] as $candidate) {
                if ($this->looksLikeBankDigitable($candidate)) {
                    $priority[] = $candidate;
                }
            }
        }

        foreach (array_values(array_unique($priority)) as $candidate) {
            if ($this->isPlausibleDigitable($candidate, $amountFromText)) {
                return $candidate;
            }
        }

        // 4) Janela em dígitos: só se bater com o valor impresso no PDF.
        if ($amountFromText !== null && $amountFromText > 0) {
            $onlyDigits = preg_replace('/\D+/', '', $text) ?? '';

            if (strlen($onlyDigits) >= 48) {
                for ($i = 0; $i <= strlen($onlyDigits) - 48; $i++) {
                    $candidate = substr($onlyDigits, $i, 48);

                    if (str_starts_with($candidate, '8') && $this->amountFromDigitable($candidate) === $amountFromText) {
                        return $candidate;
                    }
                }
            }

            if (strlen($onlyDigits) >= 47) {
                for ($i = 0; $i <= strlen($onlyDigits) - 47; $i++) {
                    $candidate = substr($onlyDigits, $i, 47);

                    if ($this->looksLikeBankDigitable($candidate) && $this->amountFromDigitable($candidate) === $amountFromText) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function isPlausibleDigitable(string $candidate, ?int $amountFromText): bool
    {
        $amount = $this->amountFromDigitable($candidate);

        if (strlen($candidate) === 47 && $this->looksLikeBankDigitable($candidate)) {
            if ($amountFromText !== null && $amount > 0 && $amount !== $amountFromText) {
                return false;
            }

            return $amount >= 0 && $amount < 100_000_000;
        }

        if (strlen($candidate) === 48 && str_starts_with($candidate, '8')) {
            if ($amount <= 0 || $amount >= 100_000_000) {
                return false;
            }

            if ($amountFromText !== null && $amount !== $amountFromText) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function extractReferenceKey(string $text, ?Carbon $dueDate): ?string
    {
        $inscription = null;

        if (preg_match('/inscri[cç][aã]o\s*[:\-]?\s*(\d{5,8}-?\d)/iu', $text, $match)) {
            $inscription = preg_replace('/\D+/', '', $match[1]);
        } elseif (preg_match('/\b(\d{5,7}-\d)\b/', $text, $match)) {
            $inscription = preg_replace('/\D+/', '', $match[1]);
        }

        $monthYear = null;

        if (preg_match('/\b(0?[1-9]|1[0-2])\/(20\d{2})\b/', $text, $match)) {
            $monthYear = sprintf('%04d-%02d', (int) $match[2], (int) $match[1]);
        } elseif ($dueDate !== null) {
            $monthYear = $dueDate->format('Y-m');
        }

        $issuer = null;

        if (preg_match('/\b(CAESB|SABESP|COPASA|SANEAGO|CEDAE|EMBASA|COMPESA|SANEPAR|CORSAN)\b/iu', $text, $match)) {
            $issuer = strtolower($match[1]);
        } elseif (preg_match('/COMPANHIA DE SANEAMENTO/iu', $text)) {
            $issuer = 'saneamento';
        }

        if ($inscription === null && $issuer === null) {
            return null;
        }

        return 'ref:'.($issuer ?? 'boleto').':'.($inscription ?? 'sem-inscricao').':'.($monthYear ?? 'sem-mes');
    }

    private function extractBeneficiary(string $text): ?string
    {
        if (preg_match('/\b(CAESB|SABESP|COPASA|SANEAGO|CEDAE|EMBASA|COMPESA|SANEPAR|CORSAN)\b/iu', $text, $match)) {
            return strtoupper($match[1]);
        }

        if (preg_match('/COMPANHIA DE SANEAMENTO AMBIENTAL DO DISTRITO FEDERAL/iu', $text)) {
            return 'CAESB';
        }

        if (preg_match('/COMPANHIA DE SANEAMENTO[^\n]{0,60}/iu', $text, $match)) {
            return trim(preg_replace('/\s+/', ' ', $match[0]) ?? $match[0]);
        }

        return $this->extractLabeledValue($text, [
            'beneficiario',
            'cedente',
            'beneficiario final',
            'nome do beneficiario',
            'empresa',
            'orgao',
            'órgão',
        ]);
    }

    private function looksLikeBankDigitable(string $digits): bool
    {
        if (strlen($digits) !== 47) {
            return false;
        }

        $bank = (int) substr($digits, 0, 3);

        return $bank >= 1 && $bank <= 999 && ! str_starts_with($digits, '8');
    }

    private function digitableToBarcode(string $digitable): ?string
    {
        if (! ctype_digit($digitable)) {
            return null;
        }

        if (strlen($digitable) === 47) {
            $barcode = substr($digitable, 0, 4)
                .substr($digitable, 32, 1)
                .substr($digitable, 33, 14)
                .substr($digitable, 4, 5)
                .substr($digitable, 10, 10)
                .substr($digitable, 21, 10);

            return strlen($barcode) === 44 ? $barcode : null;
        }

        if (strlen($digitable) === 48) {
            $barcode = substr($digitable, 0, 11)
                .substr($digitable, 12, 11)
                .substr($digitable, 24, 11)
                .substr($digitable, 36, 11);

            return strlen($barcode) === 44 ? $barcode : null;
        }

        return null;
    }

    private function amountFromDigitable(string $digitable): int
    {
        if (! ctype_digit($digitable)) {
            return 0;
        }

        if (strlen($digitable) === 47) {
            return (int) substr($digitable, 37, 10);
        }

        if (strlen($digitable) === 48) {
            $barcode = $this->digitableToBarcode($digitable);

            if ($barcode === null) {
                return 0;
            }

            $valueId = (int) substr($barcode, 2, 1);

            if (in_array($valueId, [6, 7], true)) {
                return (int) substr($barcode, 4, 11);
            }
        }

        return 0;
    }

    private function dueDateFromDigitable(string $digitable): ?Carbon
    {
        if (! ctype_digit($digitable) || strlen($digitable) !== 47) {
            return null;
        }

        $factor = (int) substr($digitable, 33, 4);

        if ($factor === 0) {
            return null;
        }

        $oldBase = Carbon::create(1997, 10, 7)->startOfDay();
        $dueOld = $oldBase->copy()->addDays($factor);

        $newBase = Carbon::create(2025, 2, 22)->startOfDay();
        $dueNew = $newBase->copy()->addDays($factor - 1000);

        $today = now()->startOfDay();

        if ($factor >= 1000 && $dueOld->lt($newBase) && $dueNew->gte($newBase->copy()->subYear()) && $dueNew->lte($today->copy()->addYears(5))) {
            return $dueNew;
        }

        if ($dueOld->between($today->copy()->subYears(5), $today->copy()->addYears(5))) {
            return $dueOld;
        }

        if ($factor >= 1000 && $dueNew->between($today->copy()->subYears(2), $today->copy()->addYears(5))) {
            return $dueNew;
        }

        return $dueOld;
    }

    private function bankOrSegmentCode(string $digitable): string
    {
        if (strlen($digitable) === 48) {
            return substr($digitable, 0, 2);
        }

        return substr($digitable, 0, 3);
    }

    /**
     * @param  list<string>  $labels
     */
    private function extractLabeledValue(string $text, array $labels, int $maxLength = 120): ?string
    {
        foreach ($labels as $label) {
            $pattern = '/'.$this->accentInsensitivePattern($label).'\s*[:\-]?\s*([^\n]{2,'.$maxLength.'})/iu';

            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            $value = trim($match[1]);
            $value = preg_replace('/\s{2,}/', ' ', $value) ?? $value;
            $value = preg_replace('/\b(cpf|cnpj|ag[eê]ncia|conta|carteira|vencimento|valor)\b.*$/iu', '', $value) ?? $value;
            $value = trim($value, " \t-:|");

            if ($value === '') {
                continue;
            }

            // Evita capturar lixo tipo "no CF/DF: 07.324..."
            if (preg_match('/\b(cnpj|cpf|cf\/df|cep)\b/iu', $value)) {
                continue;
            }

            $allowNumeric = in_array($label, [
                'numero do documento',
                'nº do documento',
                'no do documento',
                'documento',
                'nosso numero',
                'nosso nº',
                'nosso no',
                'identificador',
                'referencia',
                'referência',
                'inscrição',
                'inscricao',
            ], true);

            if ($allowNumeric || ! preg_match('/^\d+$/', $value)) {
                return mb_substr($value, 0, $maxLength);
            }
        }

        return null;
    }

    private function accentInsensitivePattern(string $label): string
    {
        $map = [
            'a' => '[aáàãâ]',
            'e' => '[eéê]',
            'i' => '[ií]',
            'o' => '[oóôõ]',
            'u' => '[uúü]',
            'c' => '[cç]',
        ];

        $chars = preg_split('//u', mb_strtolower($label), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pattern = '';

        foreach ($chars as $char) {
            $pattern .= $map[$char] ?? preg_quote($char, '/');
        }

        return $pattern;
    }

    private function extractAmountFromText(string $text): ?int
    {
        $patterns = [
            '/TOTAL\s+A\s+PAGAR\s*[:\-]?\s*R?\$?\s*([\d.]+,\d{2})/iu',
            '/VALOR\s+TOTAL\s*(?:\(R\$\))?\s*[:\-]?\s*R?\$?\s*([\d.]+,\d{2})/iu',
            '/valor\s*(?:do\s*documento|cobrado|a\s*pagar|total)?\s*[:\-]?\s*R\$?\s*([\d.]+,\d{2})/iu',
            // Caesb e similares: "40,4427/11/2026"
            '/(?<![\d.])([\d.]+,\d{2})(?=\d{2}\/\d{2}\/\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                $cents = (int) str($match[1])->replace(['.', ','], '')->toInteger();

                if ($cents > 0) {
                    return $cents;
                }
            }
        }

        return null;
    }

    private function extractDueDateFromText(string $text): ?Carbon
    {
        $patterns = [
            '/DATA\s+DE\s+VENCIMENTO[\s\S]{0,120}?(\d{2}\/\d{2}\/\d{4})/iu',
            '/VENCIMENTO\s*[:\-]?\s*(\d{2}\/\d{2}\/\d{4})/iu',
            '/(?<![\d.])[\d.]+,\d{2}(\d{2}\/\d{2}\/\d{4})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                try {
                    return Carbon::createFromFormat('d/m/Y', $match[1])->startOfDay();
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }
}
