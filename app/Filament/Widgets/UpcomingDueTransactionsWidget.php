<?php

namespace App\Filament\Widgets;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Transaction;
use App\Tables\Columns\MoneyColumn;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UpcomingDueTransactionsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Vencem nos próximos 7 dias';

    protected static ?string $pollingInterval = null;

    public function getTableHeading(): string
    {
        $query = $this->upcomingDueQuery();
        $total = (int) (clone $query)->sum('amount');
        $count = (int) (clone $query)->count();

        if ($count === 0) {
            return 'Vencem nos próximos 7 dias';
        }

        return 'Vencem nos próximos 7 dias · '.$count
            .' · R$ '.number_format($total / 100, 2, ',', '.');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->upcomingDueQuery())
            ->paginated(false)
            ->searchable(false)
            ->striped()
            ->emptyStateHeading('Nenhuma pendência')
            ->emptyStateDescription('Não há lançamentos em aberto com vencimento nos próximos 7 dias.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                Tables\Columns\ToggleColumn::make('finished')
                    ->label('Paga')
                    ->alignCenter()
                    ->updateStateUsing(function (Transaction $record, bool $state): bool {
                        $record->finished = $state;
                        $record->payment_date = $state
                            ? ($record->payment_date ?? now()->toDateString())
                            : null;
                        $record->save();

                        return $state;
                    }),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->getStateUsing(fn (Transaction $record): ?Carbon => $record->displayDueDate())
                    ->date('d/m/Y')
                    ->description(fn (Transaction $record): string => $this->relativeDueLabel($record))
                    ->color(fn (Transaction $record): string => $this->dueColor($record))
                    ->weight('medium')
                    ->sortable(false),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->getStateUsing(fn (Transaction $record): string => $record->displayTitle())
                    ->searchable(false)
                    ->wrap()
                    ->url(fn (Transaction $record): string => TransactionResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->badge()
                    ->color(fn (Transaction $record): array|string|null => filled($record->category?->color)
                        ? Color::hex($record->category->color)
                        : 'gray'),
                Tables\Columns\TextColumn::make('transaction_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(function (TransactionType|string|null $state): string {
                        if ($state instanceof TransactionType) {
                            return $state->getLabel() ?? '';
                        }

                        return (string) $state;
                    })
                    ->color(fn (Transaction $record): string => $record->transaction_type === TransactionType::Income
                        ? 'success'
                        : 'danger'),
                MoneyColumn::make('amount')
                    ->label('Valor')
                    ->alignEnd(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('all')
                    ->label('Ver lançamentos')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(TransactionResource::getUrl('index')),
            ]);
    }

    protected function upcomingDueQuery(): Builder
    {
        return Transaction::query()
            ->with(['category', 'account'])
            ->withoutInvestments()
            ->upcomingDue(7);
    }

    private function relativeDueLabel(Transaction $record): string
    {
        $due = $record->displayDueDate()?->startOfDay();

        if ($due === null) {
            return '';
        }

        $today = now()->startOfDay();
        $diff = (int) $today->diffInDays($due, false);

        return match (true) {
            $diff === 0 => 'Hoje',
            $diff === 1 => 'Amanhã',
            $diff > 1 => "Em {$diff} dias",
            default => 'Vencida',
        };
    }

    private function dueColor(Transaction $record): string
    {
        $due = $record->displayDueDate()?->startOfDay();

        if ($due === null) {
            return 'gray';
        }

        $diff = (int) now()->startOfDay()->diffInDays($due, false);

        return match (true) {
            $diff <= 0 => 'danger',
            $diff <= 2 => 'warning',
            default => 'gray',
        };
    }
}
