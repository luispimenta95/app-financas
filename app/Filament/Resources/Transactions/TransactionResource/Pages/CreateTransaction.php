<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Transactions\RecurringTransactionService;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    private int $recurrenceMonths = 1;

    private bool $fixedAmountRecurrence = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->recurrenceMonths = max(1, (int) ($data['recurrence_months'] ?? 1));
        $this->fixedAmountRecurrence = (bool) ($data['fixed_amount_recurrence'] ?? false);

        $dueDate = TransactionResource::normalizeDateInput($data['due_date'] ?? ($data['date'] ?? null));
        $data['due_date'] = $dueDate;
        $data['date'] = $dueDate;

        if (!($data['finished'] ?? false)) {
            $data['payment_date'] = null;
        }

        if (($data['transaction_type'] ?? null) === 'income') {
            $data['payment_date'] = null;
        } else {
            $data['payment_date'] = TransactionResource::normalizeDateInput($data['payment_date'] ?? null);
        }

        unset($data['recurrence_months']);
        unset($data['fixed_amount_recurrence']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $futureMonths = max(0, $this->recurrenceMonths - 1);

        app(RecurringTransactionService::class)
            ->createFutureWithZero($this->record, $futureMonths, $this->fixedAmountRecurrence);
    }
}
