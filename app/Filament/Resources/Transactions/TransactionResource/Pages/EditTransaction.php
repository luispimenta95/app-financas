<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Transactions\InstallmentTitle;
use App\Support\PlanFeature;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['due_date'] = TransactionResource::normalizeDateInput(
            $data['due_date'] ?? ($data['date'] ?? null)
        ) ?? $this->record?->displayDueDate()?->toDateString();

        $data['payment_date'] = TransactionResource::normalizeDateInput(
            $data['payment_date'] ?? null
        ) ?? $this->record?->payment_date?->toDateString();

        $data['description'] = InstallmentTitle::baseDescription($data['description'] ?? null);

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $rawDue = $data['due_date'] ?? ($data['date'] ?? null);
        $dueDate = TransactionResource::normalizeDateInput($rawDue);

        // Em edição, campo vazio preserva a data do cadastro; valor inválido já falhou na validação.
        if ($dueDate === null && blank($rawDue)) {
            $dueDate = $this->record?->displayDueDate()?->toDateString();
        }

        $data['due_date'] = $dueDate;
        $data['date'] = $dueDate;

        if (!($data['finished'] ?? false)) {
            $data['payment_date'] = null;
        } else {
            $rawPayment = $data['payment_date'] ?? null;
            $paymentDate = TransactionResource::normalizeDateInput($rawPayment);

            if ($paymentDate === null && blank($rawPayment)) {
                $paymentDate = $this->record?->payment_date?->toDateString() ?? $dueDate;
            }

            $data['payment_date'] = $paymentDate ?? $dueDate;
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
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
