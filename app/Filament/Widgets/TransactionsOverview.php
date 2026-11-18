<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\MonthlyBalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransactionsOverview extends BaseWidget
{
    use InteractsWithDashboardPeriod;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->getPeriod();

        $previous = $this->getPreviousPeriod($startDate, $endDate);

        $income = $this->getIncomes($startDate, $endDate);
        $expense = $this->getExpenses($startDate, $endDate);
        $investmentImpact = $this->getInvestmentBalanceImpact($startDate, $endDate);
        $carryover = $this->carryoverInto($startDate);
        $balance = app(MonthlyBalanceService::class)->currentBalance($startDate, $endDate);

        $prevIncome = $this->getIncomes($previous['start'], $previous['end']);
        $prevExpense = $this->getExpenses($previous['start'], $previous['end']);
        $prevInvestmentImpact = $this->getInvestmentBalanceImpact($previous['start'], $previous['end']);
        $prevBalance = $prevIncome - $prevExpense - $prevInvestmentImpact;

        return [
            $this->buildStat(
                label: 'Receitas',
                value: $income,
                previous: $prevIncome,
                icon: 'heroicon-m-arrow-trending-up',
                color: 'success',
                chart: $this->getSparkline($startDate, $endDate, TransactionType::Income),
            ),
            $this->buildStat(
                label: 'Despesas',
                value: $expense,
                previous: $prevExpense,
                icon: 'heroicon-m-arrow-trending-down',
                color: 'danger',
                chart: $this->getSparkline($startDate, $endDate, TransactionType::Expense),
                invertTrend: true,
            ),
            $this->buildStat(
                label: 'Saldo',
                value: $balance,
                previous: $prevBalance,
                icon: 'heroicon-m-building-library',
                color: 'primary',
                chart: $this->getBalanceSparkline($startDate, $endDate),
                extraDescription: $carryover > 0
                    ? 'Inclui '.$this->formatCurrency($carryover).' do mês anterior'
                    : null,
            ),
        ];
    }

    private function buildStat(
        string $label,
        int $value,
        int $previous,
        string $icon,
        string $color,
        array $chart,
        bool $invertTrend = false,
        ?string $extraDescription = null,
    ): Stat {
        $change = $this->percentageChange($value, $previous);
        $isUp = $change >= 0;
        $positive = $invertTrend ? !$isUp : $isUp;

        $description = abs($change) < 0.01
            ? 'Sem variação no período'
            : sprintf(
                '%s %.2f%%',
                $isUp ? 'Aumento de' : 'Queda de',
                abs($change)
            );

        if ($extraDescription) {
            $description .= ' · '.$extraDescription;
        }

        return Stat::make($label, $this->formatCurrency($value))
            ->icon($icon)
            ->description($description)
            ->descriptionIcon($isUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->descriptionColor($positive ? 'success' : 'danger')
            ->chart($chart)
            ->chartColor($color)
            ->color($color);
    }

    private function carryoverInto(string $startDate): int
    {
        $start = Carbon::parse($startDate);

        if ($start->day !== 1) {
            return 0;
        }

        return app(MonthlyBalanceService::class)->carriedInto($start);
    }

    private function getPreviousPeriod(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $days = max($start->diffInDays($end), 1);

        return [
            'start' => $start->copy()->subDays($days + 1)->toDateString(),
            'end' => $start->copy()->subDay()->toDateString(),
        ];
    }

    private function percentageChange(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : 100.0;
        }

        return (($current - $previous) / abs($previous)) * 100;
    }

    private function getTransactions(string $startDate, string $endDate, TransactionType $transactionType): Builder
    {
        return Transaction::where('transaction_type', $transactionType)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate);
    }

    private function getIncomes(string $startDate, string $endDate): int
    {
        return (int) $this->getTransactions($startDate, $endDate, TransactionType::Income)
            ->sum('amount');
    }

    private function getExpenses(string $startDate, string $endDate): int
    {
        return (int) $this->getTransactions($startDate, $endDate, TransactionType::Expense)
            ->sum('amount');
    }

    private function getInvestmentBalanceImpact(string $startDate, string $endDate): int
    {
        return $this->sumInvestmentAmount($startDate, $endDate, TransactionType::Expense)
            - $this->sumInvestmentAmount($startDate, $endDate, TransactionType::Income);
    }

    private function investmentQuery(string $startDate, string $endDate, TransactionType $transactionType): Builder
    {
        return Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', $transactionType)
            ->forCashFlowPeriod($startDate, $endDate);
    }

    private function sumInvestmentAmount(string $startDate, string $endDate, TransactionType $transactionType): int
    {
        return (int) $this->investmentQuery($startDate, $endDate, $transactionType)->sum('amount');
    }

    private function getSparkline(string $startDate, string $endDate, TransactionType $type): array
    {
        return $this->dailyTotals($this->getTransactions($startDate, $endDate, $type));
    }

    private function getBalanceSparkline(string $startDate, string $endDate): array
    {
        $income = $this->getSparkline($startDate, $endDate, TransactionType::Income);
        $expense = $this->getSparkline($startDate, $endDate, TransactionType::Expense);
        $investmentImpact = $this->getInvestmentBalanceSparkline($startDate, $endDate);

        return collect($income)
            ->zip($expense, $investmentImpact)
            ->map(fn ($values) => ($values[0] ?? 0) - ($values[1] ?? 0) - ($values[2] ?? 0))
            ->all();
    }

    private function getInvestmentBalanceSparkline(string $startDate, string $endDate): array
    {
        $contributions = $this->dailyTotals($this->investmentQuery($startDate, $endDate, TransactionType::Expense));
        $redemptions = $this->dailyTotals($this->investmentQuery($startDate, $endDate, TransactionType::Income));

        return collect($contributions)
            ->zip($redemptions)
            ->map(fn ($pair) => ($pair[0] ?? 0) - ($pair[1] ?? 0))
            ->all();
    }

    private function dailyTotals(Builder $query): array
    {
        $cashFlowDate = Transaction::cashFlowDateExpression();

        $rows = $query
            ->selectRaw("DATE({$cashFlowDate}) as day, SUM(amount) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total')
            ->map(fn ($total) => (float) $total / 100)
            ->values()
            ->all();

        return $this->padSparkline($rows);
    }

    private function padSparkline(array $rows): array
    {
        if (count($rows) === 0) {
            return [0, 0, 0, 0, 0, 0, 0];
        }

        if (count($rows) === 1) {
            return [$rows[0], $rows[0]];
        }

        return $rows;
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
