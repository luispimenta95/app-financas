<?php

namespace App\Services\Transactions;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class MonthlyBalanceService
{
    /**
     * Sobra positiva encadeada até o mês anterior.
     * Meses no vermelho não transferem dívida: o mês seguinte começa em zero.
     */
    public function carriedInto(
        DateTimeInterface|string $month,
        bool $preview = false,
        string|array|null $accountId = null,
    ): int {
        $monthStart = Carbon::parse($month)->startOfMonth();
        $until = $monthStart->copy()->subDay()->toDateString();
        $accountIds = $this->normalizeAccountIds($accountId);

        $carry = 0;

        foreach ($this->monthlyNets($until, $preview, $accountIds) as $net) {
            $carry = max(0, $carry + $net);
        }

        return $carry;
    }

    public function periodNet(
        DateTimeInterface|string $startDate,
        DateTimeInterface|string $endDate,
        bool $preview = false,
        string|array|null $accountId = null,
    ): int {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();
        $accountIds = $this->normalizeAccountIds($accountId);

        return $this->sumAmount(TransactionType::Income, $start, $end, $preview, $accountIds, investments: false)
            - $this->sumAmount(TransactionType::Expense, $start, $end, $preview, $accountIds, investments: false)
            - $this->sumAmount(TransactionType::Expense, $start, $end, $preview, $accountIds, investments: true)
            + $this->sumAmount(TransactionType::Income, $start, $end, $preview, $accountIds, investments: true);
    }

    /**
     * @return array<string, int> net por YYYY-MM, ordenado
     */
    private function monthlyNets(string $until, bool $preview, array $accountIds): array
    {
        $monthExpr = $this->monthExpression();

        $income = $this->groupedAmount(TransactionType::Income, $until, $preview, $accountIds, investments: false, monthExpr: $monthExpr);
        $expense = $this->groupedAmount(TransactionType::Expense, $until, $preview, $accountIds, investments: false, monthExpr: $monthExpr);
        $contributions = $this->groupedAmount(TransactionType::Expense, $until, $preview, $accountIds, investments: true, monthExpr: $monthExpr);
        $redemptions = $this->groupedAmount(TransactionType::Income, $until, $preview, $accountIds, investments: true, monthExpr: $monthExpr);

        $keys = collect($income)
            ->keys()
            ->merge(collect($expense)->keys())
            ->merge(collect($contributions)->keys())
            ->merge(collect($redemptions)->keys())
            ->unique()
            ->sort()
            ->values();

        $nets = [];

        foreach ($keys as $month) {
            $nets[$month] = (int) ($income[$month] ?? 0)
                - (int) ($expense[$month] ?? 0)
                - (int) ($contributions[$month] ?? 0)
                + (int) ($redemptions[$month] ?? 0);
        }

        return $nets;
    }

    /**
     * @return array<string, int>
     */
    private function groupedAmount(
        TransactionType $type,
        string $until,
        bool $preview,
        array $accountIds,
        bool $investments,
        string $monthExpr,
    ): array {
        $query = $this->baseQuery($type, $investments, $accountIds)
            ->forCashFlowPeriod('1970-01-01', $until, $preview)
            ->selectRaw("{$monthExpr} as month_key, SUM(amount) as total")
            ->groupBy('month_key')
            ->orderBy('month_key');

        return $query->pluck('total', 'month_key')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function sumAmount(
        TransactionType $type,
        string $start,
        string $end,
        bool $preview,
        array $accountIds,
        bool $investments,
    ): int {
        return (int) $this->baseQuery($type, $investments, $accountIds)
            ->forCashFlowPeriod($start, $end, $preview)
            ->sum('amount');
    }

    private function baseQuery(TransactionType $type, bool $investments, array $accountIds): Builder
    {
        $query = Transaction::query()->where('transaction_type', $type);

        if ($investments) {
            $query->onlyInvestments();
        } else {
            $query->withoutInvestments();
        }

        if ($accountIds !== []) {
            $query->whereIn('account_id', $accountIds);
        }

        return $query;
    }

    private function monthExpression(): string
    {
        $cashFlowDate = Transaction::cashFlowDateExpression();
        $driver = Transaction::query()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$cashFlowDate})";
        }

        return "DATE_FORMAT({$cashFlowDate}, '%Y-%m')";
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAccountIds(string|array|null $accountId): array
    {
        if ($accountId === null || $accountId === '' || $accountId === []) {
            return [];
        }

        return array_values(array_filter(
            is_array($accountId) ? $accountId : [$accountId],
            fn (mixed $id): bool => $id !== null && $id !== '',
        ));
    }
}
