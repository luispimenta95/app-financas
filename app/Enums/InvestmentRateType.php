<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvestmentRateType: string implements HasLabel
{
    case Cdi = 'cdi';
    case Prefixed = 'prefixed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cdi => '% do CDI',
            self::Prefixed => 'Prefixada',
        };
    }

    public function getRateLabel(): string
    {
        return match ($this) {
            self::Cdi => 'Taxa de juros (% do CDI)',
            self::Prefixed => 'Taxa de juros (% a.a.)',
        };
    }

    public function getRateSuffix(): string
    {
        return match ($this) {
            self::Cdi => '% CDI',
            self::Prefixed => '% a.a.',
        };
    }

    public function getRateHelperText(): string
    {
        return match ($this) {
            self::Cdi => 'Informe o percentual do CDI. Ex: 100 = 100% do CDI.',
            self::Prefixed => 'Informe a taxa prefixada ao ano. Ex: 15 = 15% a.a.',
        };
    }

    public function getRatePlaceholder(): string
    {
        return match ($this) {
            self::Cdi => '100',
            self::Prefixed => '15',
        };
    }

    public function formatRate(float|string|null $rate): string
    {
        if ($rate === null || $rate === '') {
            return '—';
        }

        return number_format((float) $rate, 2, ',', '.') . ' ' . $this->getRateSuffix();
    }
}
