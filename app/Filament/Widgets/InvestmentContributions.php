<?php

namespace App\Filament\Widgets;

use App\Models\Transactions\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestmentContributions extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();
        $preview = $this->filters['preview'] ?? false;

        $query = Transaction::query()
            ->onlyInvestments()
            ->whereBetween('date', [$startDate, $endDate]);

        if (!$preview) {
            $query->where('finished', true);
        }

        return [
            Stat::make(
                label: 'Aportes',
                value: $this->formatCurrency((int) $query->sum('amount'))
            )->icon('heroicon-m-chart-bar-square'),
        ];
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
