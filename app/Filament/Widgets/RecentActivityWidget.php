<?php

namespace App\Filament\Widgets;

use App\Models\Transactions\Transaction;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class RecentActivityWidget extends Widget
{
    protected static string $view = 'filament.widgets.recent-activity';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public function render(): View
    {
        return view(static::$view, [
            'activities' => $this->getActivities(),
        ]);
    }

    private function getActivities(): Collection
    {
        return Transaction::query()
            ->with(['category', 'account'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(function (Transaction $transaction) {
                $type = $transaction->transaction_type?->getLabel() ?? 'Transação';

                return [
                    'title' => $transaction->displayTitle() ?: $type,
                    'meta' => trim(($transaction->category?->name ?? 'Sem categoria') . ' · ' . ($transaction->account?->name ?? '')),
                    'time' => $transaction->created_at?->diffForHumans() ?? '',
                    'amount' => 'R$ ' . number_format($transaction->amount / 100, 2, ',', '.'),
                    'color' => $transaction->category?->color ?: '#3b82f6',
                    'finished' => (bool) $transaction->finished,
                ];
            });
    }
}
