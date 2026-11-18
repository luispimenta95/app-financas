<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Models\Transactions\Category;
use App\Filament\Widgets\Concerns\InteractsWithDashboardPeriod;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class TopCategoriesWidget extends Widget
{
    use InteractsWithDashboardPeriod;

    protected static string $view = 'filament.widgets.top-categories';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function render(): View
    {
        $categories = $this->getCategories();

        return view(static::$view, [
            'categories' => $categories,
            'total' => (int) collect($categories)->sum('amount'),
        ]);
    }

    /**
     * @return array<int, array{name: string, color: string, amount: int, percent: float, share: float}>
     */
    private function getCategories(): array
    {
        [$startDate, $endDate] = $this->getPeriod();

        /** @var User $user */
        $user = User::find(auth()->id());

        $categories = $user->categories()
            ->where('slug', '!=', Category::INVESTMENTS_SLUG)
            ->whereHas('transactions', fn ($query) => $query->withoutInvestments())
            ->orderBy('name')
            ->get();

        $rows = [];
        $total = 0;

        foreach ($categories as $category) {
            $amount = (int) $category->transactions()
                ->where('transaction_type', TransactionType::Expense)
                ->where('finished', true)
                ->forDuePeriod($startDate, $endDate)
                ->sum('amount');

            if ($amount <= 0) {
                continue;
            }

            $rows[] = [
                'name' => $category->name,
                'color' => $category->color ?: '#3b82f6',
                'amount' => $amount,
            ];
            $total += $amount;
        }

        usort($rows, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        $rows = array_slice($rows, 0, 6);

        return collect($rows)
            ->map(function (array $row) use ($total) {
                $share = $total > 0 ? ($row['amount'] / $total) * 100 : 0;

                return [
                    ...$row,
                    'percent' => $share,
                    'share' => $share,
                    'formatted' => 'R$ ' . number_format($row['amount'] / 100, 2, ',', '.'),
                ];
            })
            ->values()
            ->all();
    }
}
