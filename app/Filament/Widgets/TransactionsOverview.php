<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransactionsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();
        $preview = (bool) ($this->filters['preview'] ?? false);
        $accountId = $this->filters['accountId'] ?? null;

        $previous = $this->getPreviousPeriod($startDate, $endDate);

        $income = $this->getIncomes($startDate, $endDate, $preview, $accountId);
        $expense = $this->getExpenses($startDate, $endDate, $preview, $accountId);
        $balance = $income - $expense;
        $investments = $this->getInvestments($startDate, $endDate, $preview, $accountId);

        $prevIncome = $this->getIncomes($previous['start'], $previous['end'], $preview, $accountId);
        $prevExpense = $this->getExpenses($previous['start'], $previous['end'], $preview, $accountId);
        $prevBalance = $prevIncome - $prevExpense;
        $prevInvestments = $this->getInvestments($previous['start'], $previous['end'], $preview, $accountId);

        return [
            $this->buildStat(
                label: 'Receitas',
                value: $income,
                previous: $prevIncome,
                icon: 'heroicon-m-arrow-trending-up',
                color: 'success',
                chart: $this->getSparkline($startDate, $endDate, $preview, $accountId, TransactionType::Income),
            ),
            $this->buildStat(
                label: 'Despesas',
                value: $expense,
                previous: $prevExpense,
                icon: 'heroicon-m-arrow-trending-down',
                color: 'danger',
                chart: $this->getSparkline($startDate, $endDate, $preview, $accountId, TransactionType::Expense),
                invertTrend: true,
            ),
            $this->buildStat(
                label: 'Saldo',
                value: $balance,
                previous: $prevBalance,
                icon: 'heroicon-m-building-library',
                color: 'primary',
                chart: $this->getBalanceSparkline($startDate, $endDate, $preview, $accountId),
            ),
            $this->buildStat(
                label: 'Aportes',
                value: $investments,
                previous: $prevInvestments,
                icon: 'heroicon-m-chart-bar-square',
                color: 'info',
                chart: $this->getInvestmentSparkline($startDate, $endDate, $preview, $accountId),
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

        return Stat::make($label, $this->formatCurrency($value))
            ->icon($icon)
            ->description($description)
            ->descriptionIcon($isUp ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->descriptionColor($positive ? 'success' : 'danger')
            ->chart($chart)
            ->chartColor($color)
            ->color($color);
    }

    private function getPreviousPeriod(mixed $startDate, mixed $endDate): array
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

    private function getTransactions(
        mixed $startDate,
        mixed $endDate,
        bool $preview,
        ?string $accountId,
        TransactionType $transactionType,
    ): Builder {
        $query = Transaction::where('transaction_type', $transactionType)
            ->withoutInvestments()
            ->forCashFlowPeriod(
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
                $preview,
            );

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query;
    }

    private function getIncomes(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): int
    {
        return (int) $this->getTransactions($startDate, $endDate, $preview, $accountId, TransactionType::Income)
            ->sum('amount');
    }

    private function getExpenses(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): int
    {
        return (int) $this->getTransactions($startDate, $endDate, $preview, $accountId, TransactionType::Expense)
            ->sum('amount');
    }

    private function getInvestments(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): int
    {
        $query = Transaction::query()
            ->onlyInvestments()
            ->forCashFlowPeriod(
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
                $preview,
            );

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return (int) $query->sum('amount');
    }

    private function getSparkline(
        mixed $startDate,
        mixed $endDate,
        bool $preview,
        ?string $accountId,
        TransactionType $type,
    ): array {
        $cashFlowDate = Transaction::cashFlowDateExpression();

        $rows = $this->getTransactions($startDate, $endDate, $preview, $accountId, $type)
            ->selectRaw("DATE({$cashFlowDate}) as day, SUM(amount) as total")
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total')
            ->map(fn ($total) => (float) $total / 100)
            ->values()
            ->all();

        return $this->padSparkline($rows);
    }

    private function getBalanceSparkline(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): array
    {
        $income = $this->getSparkline($startDate, $endDate, $preview, $accountId, TransactionType::Income);
        $expense = $this->getSparkline($startDate, $endDate, $preview, $accountId, TransactionType::Expense);

        return collect($income)
            ->zip($expense)
            ->map(fn ($pair) => ($pair[0] ?? 0) - ($pair[1] ?? 0))
            ->all();
    }

    private function getInvestmentSparkline(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): array
    {
        $cashFlowDate = Transaction::cashFlowDateExpression();

        $query = Transaction::query()
            ->onlyInvestments()
            ->forCashFlowPeriod(
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
                $preview,
            )
            ->selectRaw("DATE({$cashFlowDate}) as day, SUM(amount) as total")
            ->groupBy('day')
            ->orderBy('day');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $rows = $query->pluck('total')
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
