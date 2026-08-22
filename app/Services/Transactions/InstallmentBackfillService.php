<?php

namespace App\Services\Transactions;

use App\Models\Transactions\Transaction;
use Illuminate\Support\Collection;

class InstallmentBackfillService
{
    /**
     * Numera parcelas já salvas em grupos de 2 ou mais meses.
     * Sem $description, só atualiza linhas com is_installment = true.
     * Com $description, atualiza só o grupo cujo título base contém o texto.
     */
    public function backfill(?string $description = null): int
    {
        $updated = 0;

        foreach ($this->existingSeries($description) as $series) {
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
                || $transaction->description !== $title
                || !$transaction->is_installment;

            if (!$dirty) {
                continue;
            }

            $transaction->is_installment = true;
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
    public function existingSeries(?string $description = null): Collection
    {
        $query = Transaction::query()
            ->where('recurrence', true)
            ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
            ->orderBy('created_at')
            ->orderBy('id');

        $filter = trim((string) $description);

        if ($filter !== '') {
            $base = InstallmentTitle::baseDescription($filter);
            $like = '%' . addcslashes($base, '%_\\') . '%';
            $query->where('description', 'like', $like);
        } else {
            $query->where('is_installment', true);
        }

        return $query
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
