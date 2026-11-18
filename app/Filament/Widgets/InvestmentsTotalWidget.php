<?php

namespace App\Filament\Widgets;

use App\Enums\InvestmentType;
use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\Investment;
use App\Models\Transactions\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestmentsTotalWidget extends BaseWidget
{
    use InteractsWithDashboardPeriod;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Carteira de investimentos';

    protected ?string $description = 'Posição atual da carteira e aportes líquidos no período filtrado.';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        [$startDate, $endDate] = $this->getPeriod();

        $total = (int) Investment::query()->sum('amount');
        $count = (int) Investment::query()->count();
        $fixedIncomeTotal = (int) Investment::query()
            ->fixedIncome()
            ->sum('amount');
        $variableIncomeTotal = (int) Investment::query()
            ->variableIncome()
            ->sum('amount');
        $abroadTotal = (int) Investment::query()
            ->abroad()
            ->sum('amount');

        $contributions = $this->sumInvestmentAmount($startDate, $endDate, TransactionType::Expense);
        $redemptions = $this->sumInvestmentAmount($startDate, $endDate, TransactionType::Income);
        $periodNet = $contributions - $redemptions;

        $candidates = [
            [$total, Stat::make('Total investido', $this->formatCurrency($total))
                ->icon('heroicon-m-chart-bar-square')
                ->color('info')
                ->description($count === 1 ? '1 aplicação' : "{$count} aplicações")],
            [$fixedIncomeTotal, Stat::make(InvestmentType::FixedIncome->getLabel(), $this->formatCurrency($fixedIncomeTotal))
                ->icon(InvestmentType::FixedIncome->getIcon())
                ->color('success')
                ->description('Soma da renda fixa (sem contar juros)')],
            [$variableIncomeTotal, Stat::make(InvestmentType::VariableIncome->getLabel(), $this->formatCurrency($variableIncomeTotal))
                ->icon(InvestmentType::VariableIncome->getIcon())
                ->color('primary')
                ->description('Controle parcial do valor aplicado')],
            [$abroadTotal, Stat::make(InvestmentType::Abroad->getLabel(), $this->formatCurrency($abroadTotal))
                ->icon(InvestmentType::Abroad->getIcon())
                ->color('warning')
                ->description('Controle parcial do valor estimado')],
            [$periodNet, Stat::make('Aportes no período', $this->formatCurrency($periodNet))
                ->icon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->description(
                    $redemptions > 0
                        ? 'Líquido de '.$this->formatCurrency($redemptions).' em resgates'
                        : 'Aportes menos resgates no filtro'
                )],
        ];

        return collect($candidates)
            ->filter(fn (array $row): bool => $row[0] > 0)
            ->map(fn (array $row): Stat => $row[1])
            ->values()
            ->all();
    }

    private function sumInvestmentAmount(string $startDate, string $endDate, TransactionType $transactionType): int
    {
        return (int) Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', $transactionType)
            ->forCashFlowPeriod($startDate, $endDate)
            ->sum('amount');
    }

    private function formatCurrency(int $currency): string
    {
        return 'R$ ' . number_format($currency / 100, 2, decimal_separator: ',', thousands_separator: '.');
    }
}
