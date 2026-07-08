<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
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

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
