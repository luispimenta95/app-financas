<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum InvestmentType: string implements HasColor, HasIcon, HasLabel
{
    case FixedIncome = 'fixed_income';
    case VariableIncome = 'variable_income';
    case Abroad = 'abroad';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'Renda Fixa',
            self::VariableIncome => 'Renda Variável',
            self::Abroad => 'Fora do Brasil',
        };
    }

    public function getPluralLabel(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'Renda Fixa',
            self::VariableIncome => 'Renda Variável',
            self::Abroad => 'Fora do Brasil',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'heroicon-m-building-library',
            self::VariableIncome => 'heroicon-m-chart-bar',
            self::Abroad => 'heroicon-m-globe-alt',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FixedIncome => Color::Emerald,
            self::VariableIncome => Color::Sky,
            self::Abroad => Color::Amber,
        };
    }

    public function isEstimatedControl(): bool
    {
        return match ($this) {
            self::VariableIncome, self::Abroad => true,
            self::FixedIncome => false,
        };
    }
}
