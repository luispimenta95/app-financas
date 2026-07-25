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

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'Renda Fixa',
            self::VariableIncome => 'Renda Variável',
        };
    }

    public function getPluralLabel(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'Renda Fixa',
            self::VariableIncome => 'Renda Variável',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::FixedIncome => 'heroicon-m-building-library',
            self::VariableIncome => 'heroicon-m-chart-bar',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FixedIncome => Color::Emerald,
            self::VariableIncome => Color::Sky,
        };
    }
}
