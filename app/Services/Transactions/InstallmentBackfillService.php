<?php

namespace App\Services\Transactions;

use App\Models\Transactions\Transaction;
use Illuminate\Support\Collection;

class InstallmentBackfillService
{
    /**
     * Numera transações recorrentes já salvas em grupos de 2 ou mais meses.
     */
    public function backfill(): int
    {
        $updated = 0;

        foreach ($this->existingSeries() as $series) {
            $updated += $this->numberSeries($series);
        }

        return $updated;
    }

    /**
     * @param  Collection<int, Transaction>  $series
     */
    public function numberSeries(Collection $series, ?int $totalMonths = null): int
    {
        $series = $series
            ->sortBy(function (Transaction $transaction): string {
                $dueDate = $transaction->displayDueDate()?->toDateString() ?? '9999-12-31';

                return $dueDate . '|' . ($transaction->created_at?->toDateTimeString() ?? '') . '|' . $transaction->id;
            })
            ->values();

        $total = max($series->count(), $totalMonths ?? 0);

        if ($total <= 1) {
            return 0;
        }

        $updated = 0;

        foreach ($series as $index => $transaction) {
            $number = $index + 1;
            $baseDescription = InstallmentTitle::baseDescription($transaction->description);
            $title = InstallmentTitle::format($baseDescription, $number, $total);

            $dirty = $transaction->installment_number !== $number
                || $transaction->installment_total !== $total
                || $transaction->description !== $title;

            if (!$dirty) {
                continue;
            }

            $transaction->installment_number = $number;
            $transaction->installment_total = $total;
            $transaction->description = $title;
            $transaction->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @return Collection<int, Collection<int, Transaction>>
     */
    public function existingSeries(): Collection
    {
        return Transaction::query()
            ->where('recurrence', true)
            ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy(function (Transaction $transaction): string {
                return implode('|', [
                    $transaction->user_id,
                    InstallmentTitle::baseDescription($transaction->description),
                    $transaction->account_id,
                    $transaction->category_id,
                ]);
            })
            ->filter(fn (Collection $series): bool => $series->count() > 1)
            ->values();
    }
}
