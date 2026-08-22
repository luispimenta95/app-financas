<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Transactions\InstallmentTitle;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['due_date'] = TransactionResource::normalizeDateInput(
            $data['due_date'] ?? ($data['date'] ?? null)
        );
        $data['description'] = InstallmentTitle::baseDescription($data['description'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $dueDate = TransactionResource::normalizeDateInput($data['due_date'] ?? ($data['date'] ?? null));
        $data['due_date'] = $dueDate;
        $data['date'] = $dueDate;

        if (!($data['finished'] ?? false)) {
            $data['payment_date'] = null;
        } else {
            $data['payment_date'] = TransactionResource::normalizeDateInput(
                $data['payment_date'] ?? $dueDate
            ) ?? $dueDate;
        }

        if (!($data['recurrence'] ?? false)) {
            $data['is_installment'] = false;
        }

        $installmentNumber = (int) ($this->record?->installment_number ?? 0);
        $installmentTotal = (int) ($this->record?->installment_total ?? 0);

        if ($installmentTotal > 1 && $installmentNumber > 0 && ($this->record?->is_installment || ($data['is_installment'] ?? false))) {
            $data['description'] = InstallmentTitle::format(
                $data['description'] ?? null,
                $installmentNumber,
                $installmentTotal,
            );
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
