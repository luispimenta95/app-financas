<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Transaction;
use App\Tables\Columns\MoneyColumn;
use Filament\Actions;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class ListCreditCardPurchases extends ManageRelatedRecords
{
    protected static string $resource = TransactionResource::class;

    protected static string $relationship = 'creditCardPurchases';

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $title = 'Gastos da fatura';

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        if (!$record instanceof Transaction) {
            return false;
        }

        return $record->isCreditCardBill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Gastos da fatura';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->displayTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();
        $sum = (int) $record->creditCardPurchases()->sum('amount');
        $bill = (int) $record->amount;

        return sprintf(
            'Soma dos gastos: R$ %s · Valor da fatura: R$ %s',
            number_format($sum / 100, 2, ',', '.'),
            number_format($bill / 100, 2, ',', '.')
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('occurred_on')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('installment_label')
                    ->label('Parcela')
                    ->placeholder('—')
                    ->alignCenter(),
                MoneyColumn::make('amount')
                    ->label('Valor')
                    ->alignEnd()
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->formatStateUsing(fn ($state): string => 'R$ '.number_format(((int) $state) / 100, 2, ',', '.'))
                    ),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadStatement')
                ->label('Enviar fatura PDF')
                ->icon('heroicon-m-document-arrow-up')
                ->url(TransactionResource::getUrl('uploadStatement', ['record' => $this->getRecord()])),
            Actions\Action::make('editBill')
                ->label('Editar fatura')
                ->icon('heroicon-m-pencil-square')
                ->url(TransactionResource::getUrl('edit', ['record' => $this->getRecord()])),
            Actions\Action::make('openPdf')
                ->label('Abrir PDF')
                ->icon('heroicon-m-document-arrow-down')
                ->url(fn (): ?string => $this->statementPdfUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->getRecord()->attachment)),
        ];
    }

    private function statementPdfUrl(): ?string
    {
        $attachment = $this->getRecord()->attachment;

        if (!is_string($attachment) || $attachment === '') {
            return null;
        }

        return asset('storage/'.$attachment);
    }
}
