<?php

namespace App\Services\Transactions;

use App\Models\Transactions\Transaction;
use Illuminate\Support\Carbon;

class RecurringTransactionService
{
    public function createFutureWithZero(Transaction $transaction, int $months = 1, bool $fixedAmount = false): void
    {
        if (!$transaction->recurrence) {
            return;
        }

        $baseDate = $transaction->due_date ?? $transaction->date;

        if (!$baseDate) {
            return;
        }

        for ($month = 1; $month <= $months; $month++) {
            $dueDate = Carbon::parse($baseDate)->addMonthsNoOverflow($month)->toDateString();

            $alreadyExists = Transaction::query()
                ->where('user_id', $transaction->user_id)
                ->where('description', $transaction->description)
                ->where('account_id', $transaction->account_id)
                ->where('category_id', $transaction->category_id)
                ->whereDate('due_date', $dueDate)
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
                'due_date' => $dueDate,
                'payment_date' => null,
                'description' => $transaction->description,
                'account_id' => $transaction->account_id,
                'category_id' => $transaction->category_id,
                'attachment' => null,
            ]);
        }
    }
}
