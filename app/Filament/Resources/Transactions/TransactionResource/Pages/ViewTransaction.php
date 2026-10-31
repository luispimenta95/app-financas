<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Transactions\InstallmentTitle;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['description'] = InstallmentTitle::baseDescription($data['description'] ?? null);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadStatement')
                ->label('Enviar fatura PDF')
                ->icon('heroicon-m-document-arrow-up')
                ->visible(fn (): bool => $this->record->isCreditCardBill())
                ->url(fn (): string => TransactionResource::getUrl('uploadStatement', ['record' => $this->record])),
            Actions\Action::make('uploadPayslip')
                ->label('Enviar contracheque PDF')
                ->icon('heroicon-m-document-currency-dollar')
                ->visible(fn (): bool => $this->record->isPayslip())
                ->url(fn (): string => TransactionResource::getUrl('uploadPayslip', ['record' => $this->record])),
            Actions\Action::make('statement')
                ->label('Gastos da fatura')
                ->icon('heroicon-m-receipt-percent')
                ->url(fn (): string => TransactionResource::getUrl('statement', ['record' => $this->record]))
                ->visible(fn (): bool => $this->record->hasCreditCardStatement()),
            Actions\EditAction::make(),
        ];
    }
}
