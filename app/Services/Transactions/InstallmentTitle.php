<?php

namespace App\Services\Transactions;

class InstallmentTitle
{
    public const SUFFIX_PATTERN = '/ - (?:Transação|Parcela) (\d+) de (\d+)$/u';

    public static function label(int $number, int $total): string
    {
        return "Parcela {$number} de {$total}";
    }

    public static function baseDescription(?string $description): string
    {
        $description = trim((string) $description);

        while ($description !== '' && preg_match(self::SUFFIX_PATTERN, $description, $matches)) {
            $description = trim(substr($description, 0, -strlen($matches[0])));
        }

        return $description;
    }

    public static function format(?string $description, int $number, int $total): string
    {
        $base = self::baseDescription($description);

        if ($total <= 1) {
            return $base;
        }

        return $base === ''
            ? self::label($number, $total)
            : $base . ' - ' . self::label($number, $total);
    }

    public static function withoutLegacyTransactionSuffix(?string $description): string
    {
        $description = trim((string) $description);
        $cleaned = preg_replace('/ - Transação \d+ de \d+/u', '', $description);

        return trim((string) $cleaned);
    }
}
