<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\Transactions\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestmentContributions extends BaseWidget
{
    use InteractsWithDashboardPeriod;

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->getPeriod();

        $query = Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', TransactionType::Expense)
            ->forCashFlowPeriod($startDate, $endDate);

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
