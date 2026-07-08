<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TransactionsOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->resolveDateRangeFromTableFilter();
        $categoriesIds = $this->tableFilters['category_id']['values'] ?? [];
        $accountsIds = $this->tableFilters['account_id']['values'] ?? [];
        $preview = $this->tableFilters['finished']['isActive'] ?? false;

        return [
            Stat::make(
                label: 'Receitas',
                value: $this->formatCurrency($this->getIncomes($startDate, $endDate, $preview, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-arrow-trending-up'),

            Stat::make(
                label: 'Despesas',
                value: $this->formatCurrency($this->getExpenses($startDate, $endDate, $preview, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-arrow-trending-down'),

            Stat::make(
                label: 'Saldo',
                value: $this->formatCurrency($this->getCurrentBalance($startDate, $endDate, $preview, $categoriesIds, $accountsIds))
            )->icon('heroicon-m-building-library'),
        ];
    }

    private function resolveDateRangeFromTableFilter(): array
    {
        $monthReference = $this->tableFilters['date']['monthReference'] ?? null;

        if (blank($monthReference) && filled($this->tableFilters['date']['startDate'] ?? null)) {
            $monthReference = Carbon::parse($this->tableFilters['date']['startDate'])->format('Y-m');
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

    private function getTransactions(?string $startDate, ?string $endDate, bool $preview, array $categoriesIds, array $accountsIds, TransactionType $transactionType): Builder
    {
        $query = Transaction::where('transaction_type', $transactionType)
            ->withoutInvestments();

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if (!$preview) {
            $query->where('finished', true);
        }

        if (!empty($categoriesIds)) {
            $query->whereIn('category_id', $categoriesIds);
        }

        if (!empty($accountsIds)) {
            $query->whereIn('account_id', $accountsIds);
        }

        return $query;
    }

    private function getIncomes($startDate, $endDate, bool $preview, array $categoriesIds, array $accountsIds)
    {
        if ($this->activeTab === TransactionType::Expense->value) {
            return 0;
        }

        return $this->getTransactions($startDate, $endDate, $preview, $categoriesIds, $accountsIds, TransactionType::Income)
            ->sum('amount');
    }

    private function getExpenses($startDate, $endDate, bool $preview, array $categoriesIds, array $accountsIds)
    {
        if ($this->activeTab === TransactionType::Income->value) {
            return 0;
        }

        return $this->getTransactions($startDate, $endDate, $preview, $categoriesIds, $accountsIds, TransactionType::Expense)
            ->sum('amount');
    }

    private function getCurrentBalance($startDate, $endDate, bool $preview, array $categoriesIds, array $accountsIds)
    {
        return $this->getIncomes($startDate, $endDate, $preview, $categoriesIds, $accountsIds)
            - $this->getExpenses($startDate, $endDate, $preview, $categoriesIds, $accountsIds);
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
