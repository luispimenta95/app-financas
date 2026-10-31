<?php

namespace App\Filament\Actions;

use App\Forms\Components\NativeFileUpload;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\CreditCardStatementImporter;
use Filament\Forms;
use Filament\Notifications\Notification;

class UploadCreditCardStatementAction
{
    /**
     * @return array<int, mixed>
     */
    public static function form(): array
    {
        return [
            Forms\Components\TextInput::make('statement_password')
                ->label('Senha do PDF')
                ->password()
                ->revealable()
                ->helperText('Se o banco proteger o PDF, informe a senha.'),
            NativeFileUpload::make('pdf')
                ->label('PDF da fatura')
                ->disk('public')
                ->directory('transactions')
                ->acceptedFileTypes(['application/pdf'])
                ->required(),
        ];
    }

    /**
     * @return array{imported: bool, count: int, bill_amount: ?int, message: ?string}
     */
    public static function handle(Transaction $record, array $data): array
    {
        $path = $data['pdf'] ?? null;

        if (is_string($path) && $path !== '') {
            $record->forceFill(['attachment' => $path])->save();
        }

        $result = app(CreditCardStatementImporter::class)->import(
            $record->fresh() ?? $record,
            is_string($data['statement_password'] ?? null) ? $data['statement_password'] : null,
            is_string($data['original_name'] ?? null) ? $data['original_name'] : null,
        );

        if (!$result['imported']) {
            Notification::make()
                ->title('Não li a fatura')
                ->body($result['message'] ?? 'Não encontrei lançamentos no PDF.')
                ->danger()
                ->send();

            return $result;
        }

        $billAmount = $result['bill_amount'] ?? null;

        Notification::make()
            ->title('Fatura lida')
            ->body($result['count'].' lançamentos encontrados no PDF. '.($billAmount === null
                ? 'Não achei o total da fatura no PDF; o valor da transação foi mantido.'
                : 'Valor da transação atualizado para R$ '.number_format($billAmount / 100, 2, ',', '.').'.'))
            ->status($billAmount === null ? 'warning' : 'success')
            ->send();

        return $result;
    }
}
