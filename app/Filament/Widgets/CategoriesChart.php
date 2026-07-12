<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Transaction;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CategoriesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Estatísticas por tipo';

    protected static ?string $description = 'Distribuição de receitas e despesas';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now()->endOfMonth();
        $accountId = $this->filters['accountId'] ?? null;
        $preview = (bool) ($this->filters['preview'] ?? false);

        $income = $this->sumAmount($startDate, $endDate, $preview, $accountId, TransactionType::Income);
        $expense = $this->sumAmount($startDate, $endDate, $preview, $accountId, TransactionType::Expense);
        $investments = $this->sumInvestments($startDate, $endDate, $preview, $accountId);

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

    private function sumAmount(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId, TransactionType $type): int
    {
        $query = Transaction::where('transaction_type', $type)
            ->withoutInvestments()
            ->forCashFlowPeriod($startDate, $endDate, $preview);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return (int) $query->sum('amount');
    }

    private function sumInvestments(mixed $startDate, mixed $endDate, bool $preview, ?string $accountId): int
    {
        $query = Transaction::query()
            ->onlyInvestments()
            ->where('transaction_type', TransactionType::Expense)
            ->forCashFlowPeriod($startDate, $endDate, $preview);

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        return (int) $query->sum('amount');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
