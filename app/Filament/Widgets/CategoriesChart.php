<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\Transactions\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class CategoriesChart extends ChartWidget
{
    use InteractsWithDashboardPeriod;

    protected static ?string $heading = 'Estatísticas por tipo';

    protected static ?string $description = 'Distribuição de receitas e despesas';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = 'min(320px, 60vw)';

    protected function getData(): array
    {
        [$startDate, $endDate] = $this->getPeriod();

        $income = $this->sumAmount($startDate, $endDate, TransactionType::Income);
        $expense = $this->sumAmount($startDate, $endDate, TransactionType::Expense);
        $investments = $this->sumInvestments($startDate, $endDate);

        return [
            'datasets' => [
                [
                    'data' => [
                        $income / 100,
                        $expense / 100,
                        $investments / 100,
                    ],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.9)',
                        'rgba(14, 165, 233, 0.75)',
                        'rgba(96, 165, 250, 0.55)',
                    ],
                    'borderColor' => [
                        'rgb(37, 99, 235)',
                        'rgb(2, 132, 199)',
                        'rgb(59, 130, 246)',
                    ],
                    'borderWidth' => 2,
                    'hoverOffset' => 6,
                ],
            ],
            'labels' => ['Receitas', 'Despesas', 'Aportes'],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                cutout: '72%',
                scales: {
                    y: { display: false },
                    x: { display: false },
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem){
                                var datasetLabel = tooltipItem.label || '';

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
                            padding: 14,
                        },
                    },
                }
            }
        JS);
    }

    private function sumAmount(string $startDate, string $endDate, TransactionType $type): int
    {
        return (int) Transaction::where('transaction_type', $type)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate)
            ->sum('amount');
    }

    private function sumInvestments(string $startDate, string $endDate): int
    {
        return (int) Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', TransactionType::Expense)
            ->forCashFlowPeriod($startDate, $endDate)
            ->sum('amount');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
