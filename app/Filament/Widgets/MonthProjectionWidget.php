<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Pages\Dashboard;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\MonthlyBalanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthProjectionWidget extends BaseWidget
{
    protected static ?int $sort = 0;

    protected ?string $heading = 'Projeção do mês';

    protected ?string $description = 'Saldo esperado quando as transações pendentes do mês forem finalizadas, incluindo aportes e resgates.';

    /**
     * Widget de projeção futura: só faz sentido sem filtros,
     * pois filtros limitam o período a datas já ocorridas (até hoje).
     */
    public static function shouldDisplay(?array $filters): bool
    {
        return ! Dashboard::hasActiveFilters($filters);
    }

    protected function getStats(): array
    {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $income = $this->sumAmount(TransactionType::Income, $startDate, $endDate);
        $expense = $this->sumAmount(TransactionType::Expense, $startDate, $endDate);

        return [
            Stat::make('Receitas projetadas', $this->formatCurrency($income))
                ->icon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->description('Finalizadas e pendentes do mês'),
            Stat::make('Despesas projetadas', $this->formatCurrency($expense))
                ->icon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->description('Finalizadas e pendentes do mês'),
            $this->balanceStat($startDate, $endDate),
        ];
    }

    private function balanceStat(string $startDate, string $endDate): Stat
    {
        $service = app(MonthlyBalanceService::class);
        $current = $service->currentBalance($startDate, $endDate);

        if (! $service->hasPending($startDate, $endDate)) {
            return Stat::make('Saldo ao fim do mês', $this->formatCurrency($current))
                ->icon('heroicon-m-check-circle')
                ->color($current >= 0 ? 'success' : 'danger')
                ->description('Todas as transações do mês foram finalizadas');
        }

        $balance = $current + $service->pendingNet($startDate, $endDate);

        return Stat::make('Saldo projetado', $this->formatCurrency($balance))
            ->icon('heroicon-m-calendar-days')
            ->color($balance >= 0 ? 'success' : 'danger')
            ->description('Saldo atual de '.$this->formatCurrency($current).' mais as pendentes do mês');
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
