<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class MonthRevenue extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Visão geral financeira';

    protected static ?string $description = 'Receitas, despesas e saldo ao longo do período';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected static ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfYear()->toDateString();
        $endDate = $this->filters['endDate'] ?? now()->endOfYear()->toDateString();
        $accountId = $this->filters['accountId'] ?? null;
        $preview = (bool) ($this->filters['preview'] ?? false);

        // Default to year view for a richer chart when no filter is set
        if (!($this->filters['startDate'] ?? null) && !($this->filters['endDate'] ?? null)) {
            $startDate = now()->startOfYear()->toDateString();
            $endDate = now()->endOfYear()->toDateString();
        }

        $income = $this->getPeriodAmount($startDate, $endDate, $preview, $accountId, TransactionType::Income);
        $expense = $this->getPeriodAmount($startDate, $endDate, $preview, $accountId, TransactionType::Expense);
        $investmentContributions = $this->getPeriodInvestmentAmount($startDate, $endDate, $preview, $accountId, TransactionType::Expense);
        $investmentRedemptions = $this->getPeriodInvestmentAmount($startDate, $endDate, $preview, $accountId, TransactionType::Income);

        $labels = $this->buildMonthLabels($startDate, $endDate);
        $incomeMap = $income->keyBy('new_date');
        $expenseMap = $expense->keyBy('new_date');
        $investmentContributionMap = $investmentContributions->keyBy('new_date');
        $investmentRedemptionMap = $investmentRedemptions->keyBy('new_date');

        $incomeData = [];
        $expenseData = [];
        $balanceData = [];

        foreach ($labels as $key => $label) {
            $inc = (float) (($incomeMap[$key]->aggregate ?? 0) / 100);
            $exp = (float) (($expenseMap[$key]->aggregate ?? 0) / 100);
            $investmentImpact = (float) ((($investmentContributionMap[$key]->aggregate ?? 0) - ($investmentRedemptionMap[$key]->aggregate ?? 0)) / 100);
            $incomeData[] = $inc;
            $expenseData[] = $exp;
            $balanceData[] = $inc - $exp - $investmentImpact;
        }

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Receitas',
                    'data' => $incomeData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.75)',
                    'borderRadius' => 6,
                    'order' => 2,
                ],
                [
                    'type' => 'bar',
                    'label' => 'Despesas',
                    'data' => $expenseData,
                    'backgroundColor' => 'rgba(14, 165, 233, 0.45)',
                    'borderRadius' => 6,
                    'order' => 3,
                ],
                [
                    'type' => 'line',
                    'label' => 'Saldo',
                    'data' => $balanceData,
                    'borderColor' => 'rgb(96, 165, 250)',
                    'backgroundColor' => 'rgba(96, 165, 250, 0.15)',
                    'pointBackgroundColor' => 'rgb(147, 197, 253)',
                    'pointBorderColor' => 'rgb(59, 130, 246)',
                    'tension' => 0.35,
                    'fill' => false,
                    'order' => 1,
                ],
            ],
            'labels' => array_values($labels),
        ];
    }

    private function buildMonthLabels(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfMonth();
        $end = Carbon::parse($endDate)->startOfMonth();
        $labels = [];

        while ($start->lte($end)) {
            $labels[$start->format('Y-m')] = $start->translatedFormat('M');
            $start->addMonth();
        }

        return $labels;
    }

    private function getPeriodAmount(string $startDate, string $endDate, bool $preview, ?string $accountId, TransactionType $transactionType): Collection
    {
        $cashFlowDate = Transaction::cashFlowDateExpression();

        $query = Transaction::selectRaw("
                sum(`amount`) as `aggregate`, 
                DATE_FORMAT({$cashFlowDate}, '%Y-%m') AS `new_date`, 
                YEAR({$cashFlowDate}) AS `year`, 
                MONTH({$cashFlowDate}) AS `month`
            ")
            ->where('transaction_type', $transactionType)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate, $preview);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->groupBy('year', 'month')->get();
    }

    private function getPeriodInvestmentAmount(string $startDate, string $endDate, bool $preview, ?string $accountId, TransactionType $transactionType): Collection
    {
        $cashFlowDate = Transaction::cashFlowDateExpression();

        $query = Transaction::selectRaw("
                sum(`amount`) as `aggregate`, 
                DATE_FORMAT({$cashFlowDate}, '%Y-%m') AS `new_date`, 
                YEAR({$cashFlowDate}) AS `year`, 
                MONTH({$cashFlowDate}) AS `month`
            ")
            ->onlyInvestments()
            ->where('transaction_type', $transactionType)
            ->forCashFlowPeriod($startDate, $endDate, $preview);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return $query->groupBy('year', 'month')->get();
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' },
                    },
                    y: {
                        grid: { color: 'rgba(148, 163, 184, 0.12)' },
                        ticks: {
                            color: '#94a3b8',
                            callback: function(tooltipItem){
                                return Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL',
                                    notation: 'compact',
                                }).format(tooltipItem);
                            }
                        }
                    },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem){
                                var datasetLabel = tooltipItem.dataset.label || '';

                                return datasetLabel + ': ' + Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL',
                                }).format(tooltipItem.raw);
                            }
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#94a3b8',
                            usePointStyle: true,
                            padding: 16,
                        },
                    },
                }
            }
        JS);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
