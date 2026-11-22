<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Transactions\InstallmentTitle;
use App\Support\PlanFeature;
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
                ->visible(fn (): bool => financas_user_can(PlanFeature::PAYSLIP_PDF) && $this->record->isPayslip())
                ->url(fn (): string => TransactionResource::getUrl('uploadPayslip', ['record' => $this->record])),
            Actions\Action::make('uploadBoleto')
                ->label(fn (): string => $this->record->hasBoleto() ? 'Atualizar boleto PDF' : 'Enviar boleto PDF')
                ->icon('heroicon-m-qr-code')
                ->visible(fn (): bool => financas_user_can(PlanFeature::BOLETO_PDF) && $this->record->isExpense())
                ->url(fn (): string => TransactionResource::getUrl('uploadBoleto', ['record' => $this->record])),
            Actions\Action::make('statement')
                ->label('Gastos da fatura')
                ->icon('heroicon-m-receipt-percent')
                ->url(fn (): string => TransactionResource::getUrl('statement', ['record' => $this->record]))
                ->visible(fn (): bool => $this->record->hasCreditCardStatement()),
            Actions\EditAction::make(),
        ];
    }
}
