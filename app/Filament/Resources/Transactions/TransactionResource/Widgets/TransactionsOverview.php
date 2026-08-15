<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransactionsOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->resolveDateRangeFromTableFilter();
        $categoriesIds = data_get($this->tableFilters, 'category_id.values', []);
        $accountsIds = data_get($this->tableFilters, 'account_id.values', []);
        $onlyFinished = (bool) data_get($this->tableFilters, 'finished.isActive', false);

        return [
            Stat::make(
                label: 'Receitas',
                value: $this->formatCurrency($this->getIncomes($startDate, $endDate, $onlyFinished, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-arrow-trending-up'),

            Stat::make(
                label: 'Despesas',
                value: $this->formatCurrency($this->getExpenses($startDate, $endDate, $onlyFinished, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-arrow-trending-down'),

            Stat::make(
                label: 'Saldo',
                value: $this->formatCurrency($this->getCurrentBalance($startDate, $endDate, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-building-library'),
        ];
    }

    private function resolveDateRangeFromTableFilter(): array
    {
        $monthReference = data_get($this->tableFilters, 'date.monthReference');

        if (blank($monthReference) && filled(data_get($this->tableFilters, 'date.startDate'))) {
            $monthReference = Carbon::parse(data_get($this->tableFilters, 'date.startDate'))->format('Y-m');
        }

        if (blank($monthReference)) {
            $monthReference = now()->format('Y-m');
        }

        if ($monthReference === 'all') {
            return [null, null];
        }

        if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $monthReference, $matches)) {
            return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        }

        $baseDate = Carbon::createFromDate((int) $matches[1], (int) $matches[2], 1);

        return [
            $baseDate->copy()->startOfMonth()->toDateString(),
            $baseDate->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function getListedTransactions(
        ?string $startDate,
        ?string $endDate,
        bool $onlyFinished,
        array $categoriesIds,
        array $accountsIds,
        TransactionType $transactionType,
    ): Builder {
        $query = Transaction::query()
            ->where('transaction_type', $transactionType)
            ->withoutInvestments()
            ->forDuePeriod($startDate, $endDate)
            ->searchTerm($this->tableSearch);

        if ($onlyFinished) {
            $query->where('finished', true);
        }

        return $this->constrainByFilters($query, $categoriesIds, $accountsIds);
    }

    private function getCashFlowTransactions(
        ?string $startDate,
        ?string $endDate,
        array $categoriesIds,
        array $accountsIds,
        TransactionType $transactionType,
    ): Builder {
        $query = Transaction::query()
            ->where('transaction_type', $transactionType)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate)
            ->searchTerm($this->tableSearch);

        return $this->constrainByFilters($query, $categoriesIds, $accountsIds);
    }

    private function constrainByFilters(Builder $query, array $categoriesIds, array $accountsIds): Builder
    {
        if (!empty($categoriesIds)) {
            $query->whereIn('category_id', $categoriesIds);
        }

        if (!empty($accountsIds)) {
            $query->whereIn('account_id', $accountsIds);
        }

        return $query;
    }

    private function getIncomes($startDate, $endDate, bool $onlyFinished, array $categoriesIds, array $accountsIds)
    {
        if ($this->activeTab === TransactionType::Expense->value) {
            return 0;
        }

        return (int) $this->getListedTransactions($startDate, $endDate, $onlyFinished, $categoriesIds, $accountsIds, TransactionType::Income)
            ->sum('amount');
    }

    private function getExpenses($startDate, $endDate, bool $onlyFinished, array $categoriesIds, array $accountsIds)
    {
        if ($this->activeTab === TransactionType::Income->value) {
            return 0;
        }

        return (int) $this->getListedTransactions($startDate, $endDate, $onlyFinished, $categoriesIds, $accountsIds, TransactionType::Expense)
            ->sum('amount');
    }

    private function getCurrentBalance($startDate, $endDate, array $categoriesIds, array $accountsIds)
    {
        if ($this->activeTab === TransactionType::Expense->value) {
            $income = 0;
        } else {
            $income = (int) $this->getCashFlowTransactions($startDate, $endDate, $categoriesIds, $accountsIds, TransactionType::Income)
                ->sum('amount');
        }

        if ($this->activeTab === TransactionType::Income->value) {
            $expense = 0;
        } else {
            $expense = (int) $this->getCashFlowTransactions($startDate, $endDate, $categoriesIds, $accountsIds, TransactionType::Expense)
                ->sum('amount');
        }

        return $income - $expense - $this->getInvestmentBalanceImpact($startDate, $endDate, $categoriesIds, $accountsIds);
    }

    private function getInvestmentBalanceImpact(
        ?string $startDate,
        ?string $endDate,
        array $categoriesIds,
        array $accountsIds,
    ): int {
        $contributions = $this->sumInvestmentAmount($startDate, $endDate, $categoriesIds, $accountsIds, TransactionType::Expense);
        $redemptions = $this->sumInvestmentAmount($startDate, $endDate, $categoriesIds, $accountsIds, TransactionType::Income);

        return $contributions - $redemptions;
    }

    private function sumInvestmentAmount(
        ?string $startDate,
        ?string $endDate,
        array $categoriesIds,
        array $accountsIds,
        TransactionType $transactionType,
    ): int {
        $query = Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', $transactionType)
            ->forCashFlowPeriod($startDate, $endDate)
            ->searchTerm($this->tableSearch);

        return (int) $this->constrainByFilters($query, $categoriesIds, $accountsIds)->sum('amount');
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
