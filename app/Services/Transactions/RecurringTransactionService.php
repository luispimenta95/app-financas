<?php

namespace App\Services\Transactions;

use App\Models\Transactions\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RecurringTransactionService
{
    public function __construct(
        private readonly InstallmentBackfillService $installmentBackfillService,
    ) {}

    public function createFutureWithZero(Transaction $transaction, int $months = 1, bool $fixedAmount = false): void
    {
        if (!$transaction->recurrence) {
            return;
        }

        $totalMonths = max(1, $months);
        $futureMonths = $totalMonths - 1;
        $baseDescription = InstallmentTitle::baseDescription($transaction->description);
        $baseDate = $transaction->due_date ?? $transaction->date;

        if ($baseDate && $futureMonths > 0) {
            for ($month = 1; $month <= $futureMonths; $month++) {
                $dueDate = Carbon::parse($baseDate)->addMonthsNoOverflow($month)->toDateString();

                $alreadyExists = Transaction::query()
                    ->where('user_id', $transaction->user_id)
                    ->where('account_id', $transaction->account_id)
                    ->where('category_id', $transaction->category_id)
                    ->whereDate('due_date', $dueDate)
                    ->where(function (Builder $query) use ($baseDescription): void {
                        $escaped = addcslashes($baseDescription, '%_\\');

                        $query
                            ->where('description', $baseDescription)
                            ->orWhere('description', 'like', $escaped . ' - Transação %')
                            ->orWhere('description', 'like', $escaped . ' - Parcela %');
                    })
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                Transaction::query()->create([
                    'user_id' => $transaction->user_id,
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => $fixedAmount ? $transaction->amount : 0,
                    'date' => $dueDate,
                    'finished' => false,
                    'recurrence' => true,
                    'is_installment' => (bool) $transaction->is_installment,
                    'due_date' => $dueDate,
                    'payment_date' => null,
                    'description' => $baseDescription,
                    'account_id' => $transaction->account_id,
                    'category_id' => $transaction->category_id,
                    'attachment' => null,
                ]);
            }
        }

        if ($totalMonths < 2 || !$transaction->is_installment) {
            return;
        }

        if ($transaction->description !== $baseDescription) {
            $transaction->description = $baseDescription;
            $transaction->save();
        }

        $series = Transaction::query()
            ->where('user_id', $transaction->user_id)
            ->where('account_id', $transaction->account_id)
            ->where('category_id', $transaction->category_id)
            ->where('is_installment', true)
            ->where(function (Builder $query) use ($baseDescription): void {
                $escaped = addcslashes($baseDescription, '%_\\');

                $query
                    ->where('description', $baseDescription)
                    ->orWhere('description', 'like', $escaped . ' - Transação %')
                    ->orWhere('description', 'like', $escaped . ' - Parcela %');
            })
            ->get();

        $this->installmentBackfillService->numberSeries($series, $totalMonths);
    }
}
