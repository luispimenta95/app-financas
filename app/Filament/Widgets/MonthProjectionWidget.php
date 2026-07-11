<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthProjectionWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Projeção do mês';

    protected ?string $description = 'Valor esperado se todas as transações do mês atual forem finalizadas neste mês.';

    /**
     * Widget de projeção futura: só faz sentido sem filtros,
     * pois filtros limitam o período a datas já ocorridas (até hoje).
     */
    public static function shouldDisplay(?array $filters): bool
    {
        if ($filters === null || $filters === []) {
            return true;
        }

        return collect($filters)
            ->filter(fn ($value) => ! is_null($value))
            ->isEmpty();
    }

    protected function getStats(): array
    {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $income = $this->sumAmount(TransactionType::Income, $startDate, $endDate);
        $expense = $this->sumAmount(TransactionType::Expense, $startDate, $endDate);
        $balance = $income - $expense;

        return [
            Stat::make('Receitas projetadas', $this->formatCurrency($income))
                ->icon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->description('Finalizadas e pendentes do mês'),
            Stat::make('Despesas projetadas', $this->formatCurrency($expense))
                ->icon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->description('Finalizadas e pendentes do mês'),
            Stat::make('Saldo projetado', $this->formatCurrency($balance))
                ->icon('heroicon-m-calendar-days')
                ->color($balance >= 0 ? 'success' : 'danger')
                ->description($balance >= 0 ? 'Quanto você terá no mês' : 'Quanto você deverá no mês'),
        ];
    }

    private function sumAmount(TransactionType $type, string $startDate, string $endDate): int
    {
        return (int) Transaction::query()
            ->where('transaction_type', $type)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate, preview: true)
            ->sum('amount');
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
