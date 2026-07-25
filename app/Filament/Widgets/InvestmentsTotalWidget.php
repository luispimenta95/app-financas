<?php

namespace App\Filament\Widgets;

use App\Enums\InvestmentType;
use App\Models\Investment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestmentsTotalWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Investimentos';

    protected ?string $description = 'Total da carteira cadastrada em Investimentos.';

    protected function getStats(): array
    {
        $total = (int) Investment::query()->sum('amount');
        $count = (int) Investment::query()->count();
        $fixedIncomeTotal = (int) Investment::query()
            ->fixedIncome()
            ->sum('amount');
        $variableIncomeTotal = (int) Investment::query()
            ->variableIncome()
            ->sum('amount');

        return [
            Stat::make('Total investido', $this->formatCurrency($total))
                ->icon('heroicon-m-chart-bar-square')
                ->color('info')
                ->description($count === 1 ? '1 aplicação' : "{$count} aplicações"),
            Stat::make(InvestmentType::FixedIncome->getLabel(), $this->formatCurrency($fixedIncomeTotal))
                ->icon(InvestmentType::FixedIncome->getIcon())
                ->color('success')
                ->description('Soma da renda fixa'),
            Stat::make(InvestmentType::VariableIncome->getLabel(), $this->formatCurrency($variableIncomeTotal))
                ->icon(InvestmentType::VariableIncome->getIcon())
                ->color('primary')
                ->description('Soma da renda variável'),
        ];
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
