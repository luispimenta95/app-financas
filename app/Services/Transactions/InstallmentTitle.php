<?php

namespace App\Services\Transactions;

class InstallmentTitle
{
    public const SUFFIX_PATTERN = '/ - Transação (\d+) de (\d+)$/u';

    public static function label(int $number, int $total): string
    {
        return "Transação {$number} de {$total}";
    }

    public static function baseDescription(?string $description): string
    {
        $description = trim((string) $description);

        if ($description !== '' && preg_match(self::SUFFIX_PATTERN, $description, $matches)) {
            return trim(substr($description, 0, -strlen($matches[0])));
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
}
