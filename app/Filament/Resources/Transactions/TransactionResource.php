<?php

namespace App\Filament\Resources\Transactions;

use App\Enums\TransactionType;
use App\Filament\Exports\Transactions\TransactionExporter;
use App\Filament\Resources\Transactions\TransactionResource\Pages;
use App\Filament\Resources\Transactions\TransactionResource\Widgets;
use App\Models\Transactions\Transaction;
use App\Tables\Columns\MoneyColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Leandrocfe\FilamentPtbrFormFields\Money;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-m-banknotes';

    protected static ?string $modelLabel = 'transação';

    protected static ?string $pluralModelLabel = 'transações';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transação')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Hidden::make('account_id')
                            ->default(fn (): ?string => auth()->user()?->accounts()->value('id'))
                            ->required(),
                        Forms\Components\Select::make('category_id')
                            ->label('Categoria')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                            )
                            ->native(false)
                            ->required(),

                        Money::make('amount')
                            ->label('Total')
                            ->required()
                            ->formatStateUsing(fn (?int $state) => number_format($state / 100, 2, ',', '.'))
                            ->dehydrateStateUsing(fn (?string $state): ?int => str($state)->replace(['.', ','], '')->toInteger()),
                        Forms\Components\DatePicker::make('due_date')
                            ->label('Data de vencimento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->required(),
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->placeholder('Ex: Conta de Luz')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\ToggleButtons::make('transaction_type')
                            ->label('Tipo de transação')
                            ->required()
                            ->inline()
                            ->options(TransactionType::class),

                        Forms\Components\ToggleButtons::make('finished')
                            ->label('Recebida/Paga')
                            ->required()
                            ->live()
                            ->inline()
                            ->boolean(),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('Data de pagamento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->format('Y-m-d')
                            ->helperText('Usada nos totais de receitas e despesas do mês em que o valor foi pago/recebido.')
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('finished'))
                            ->required(fn (Forms\Get $get): bool => (bool) $get('finished'))
                            ->default(fn (Forms\Get $get): ?string => $get('due_date')),

                        Forms\Components\Toggle::make('recurrence')
                            ->label('Recorrencia mensal')
                            ->live()
                            ->default(false),

                        Forms\Components\TextInput::make('recurrence_months')
                            ->label('Cadastrar por quantos meses')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(1)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('recurrence')),

                        Forms\Components\Toggle::make('fixed_amount_recurrence')
                            ->label('Valor fixo nas recorrencias')
                            ->helperText('Marcado: repete o valor atual em todos os meses. Desmarcado: cria meses futuros com valor 0 para editar depois.')
                            ->default(false)
                            ->visible(fn (Forms\Get $get): bool => (bool) $get('recurrence')),

                        Forms\Components\FileUpload::make('attachment')
                            ->label('Anexo')
                            ->openable()
                            ->deletable()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->columnSpanFull(),

                        Forms\Components\Fieldset::make('Informações Adicionais')
                            ->columnSpanFull()
                            ->hidden(fn (?Transaction $record): bool => $record === null)
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('created_at')
                                    ->label('Criado em')
                                    ->content(fn ($record) => $record->created_at->format('d/m/Y H:i')),

                                Forms\Components\Placeholder::make('updated_at')
                                    ->label('Atualizado em')
                                    ->content(fn ($record) => $record->updated_at->format('d/m/Y H:i')),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $livewire = $table->getLivewire();

        return $table
            ->defaultSort(
                fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    Transaction::dueDateExpression() . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC')
                ),
                'asc',
            )
            ->defaultGroup('due_date')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->orderByRaw(Transaction::dueDateExpression() . ' ASC')
                ->orderByRaw("case when transaction_type = 'income' then 0 else 1 end")
                ->orderBy('created_at'))
            ->columns(
                $livewire->isGridLayout()
                    ? static::getGridTableColumns()
                    : static::getTableColumns()
            )
            ->contentGrid(
                fn () => $livewire->isListLayout()
                    ? null
                    : [
                        'md' => 2,
                        'lg' => 3,
                        'xl' => 3,
                    ]
            )
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->label('Mes')
                    ->form([
                        Forms\Components\Select::make('monthReference')
                            ->label('Mes de referencia')
                            ->native(false)
                            ->searchable()
                            ->placeholder('Selecione um mes')
                            ->options(
                                collect(range(-12, 24))
                                    ->mapWithKeys(function (int $offset): array {
                                        $month = Carbon::now()->startOfMonth()->addMonths($offset);

                                        return [$month->format('Y-m') => $month->format('m/Y')];
                                    })
                                    ->prepend('Todos', 'all')
                                    ->toArray()
                            )
                            ->default(now()->format('Y-m'))
                            ->live(),
                    ])
                    ->columns(1)
                    ->query(function (Builder $query, array $data): Builder {
                        $monthReference = $data['monthReference'] ?? null;

                        // Backward compatibility for previously persisted filter URLs.
                        if (blank($monthReference) && filled($data['startDate'] ?? null)) {
                            $monthReference = Carbon::parse($data['startDate'])->format('Y-m');
                        }

                        if (blank($monthReference) || $monthReference === 'all') {
                            return $query;
                        }

                        $baseDate = static::resolveMonthReference($monthReference);

                        if (is_null($baseDate)) {
                            return $query;
                        }

                        return $query->forDuePeriod(
                            $baseDate->copy()->startOfMonth()->toDateString(),
                            $baseDate->copy()->endOfMonth()->toDateString(),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $monthReference = $data['monthReference'] ?? null;

                        if (blank($monthReference) && filled($data['startDate'] ?? null)) {
                            $monthReference = Carbon::parse($data['startDate'])->format('Y-m');
                        }

                        if (blank($monthReference) || $monthReference === 'all') {
                            return 'Mes: Todos';
                        }

                        $baseDate = static::resolveMonthReference($monthReference);

                        if (is_null($baseDate)) {
                            return null;
                        }

                        return 'Mes: ' . $baseDate->format('m/Y');
                    }),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categorias')
                    ->relationship(name: 'category', titleAttribute: 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\SelectFilter::make('account_id')
                    ->label('Contas')
                    ->relationship(name: 'account', titleAttribute: 'name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\Filter::make('finished')
                    ->label('Finalizada')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('finished', true)),
            ])
            ->groups([
                Tables\Grouping\Group::make('due_date')
                    ->label('Vencimento')
                    ->getTitleFromRecordUsing(fn (Transaction $record): ?string => $record->displayDueDate()?->format('d/m/Y'))
                    ->getKeyFromRecordUsing(fn (Transaction $record): ?string => $record->displayDueDate()?->toDateString())
                    ->orderQueryUsing(fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                        Transaction::dueDateExpression() . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC')
                    )),
                Tables\Grouping\Group::make('category_id')
                    ->label('Categoria')
                    ->getTitleFromRecordUsing(fn (Transaction $record): ?string => $record->category?->name),
                Tables\Grouping\Group::make('account_id')
                    ->label('Conta')
                    ->getTitleFromRecordUsing(fn (Transaction $record): ?string => $record->account?->name),
            ])
            ->paginated([
                100,
                'all',
            ])
            ->defaultPaginationPageOption(25)
            ->actionsAlignment('right')
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->tooltip('Visualizar'),
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Editar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                Tables\Actions\ExportBulkAction::make()
                    ->label('Exportar')
                    ->exporter(TransactionExporter::class)
                    ->icon('heroicon-s-document-arrow-up')
                    ->color('primary'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getGridTableColumns(): array
    {
        return [
            Tables\Columns\Layout\Stack::make([
                Tables\Columns\Layout\Grid::make([
                    'lg' => 2,
                ])
                    ->schema([
                        Tables\Columns\TextColumn::make('due_date')
                            ->label('Vencimento')
                            ->getStateUsing(fn (Transaction $record) => $record->displayDueDate())
                            ->date('d/m/Y')
                            ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                                Transaction::dueDateExpression() . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC')
                            ))
                            ->badge(),
                        Tables\Columns\TextColumn::make('payment_date')
                            ->label('Pagamento')
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->sortable()
                            ->badge()
                            ->color('gray'),
                        Tables\Columns\TextColumn::make('transaction_type')
                            ->label('Tipo')
                            ->badge()
                            ->iconPosition(IconPosition::After)
                            ->alignEnd(),

                        Tables\Columns\TextColumn::make('description')
                            ->label('Descrição')
                            ->searchable()
                            ->size(Tables\Columns\TextColumn\TextColumnSize::Large),
                        Tables\Columns\TextColumn::make('category.name')
                            ->label('Categoria')
                            ->searchable()
                            ->badge()
                            ->icon(fn ($record) => $record->category->icon)
                            ->color(fn ($record) => Color::hex($record->category->color))
                            ->alignEnd(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->size(TextColumnSize::Large)
                    ->wrap(),

                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('category.name')
                        ->label('Categoria')
                        ->searchable()
                        ->badge()
                        ->icon(fn (Transaction $record): ?string => $record->category?->icon)
                        ->color(fn (Transaction $record) => $record->category?->color
                            ? Color::hex($record->category->color)
                            : 'gray'),
                    Tables\Columns\TextColumn::make('account.name')
                        ->label('Conta')
                        ->searchable()
                        ->color('gray')
                        ->icon('heroicon-m-wallet')
                        ->grow(false),
                ]),

                Tables\Columns\Layout\Split::make([
                    MoneyColumn::make('amount')
                        ->label('Total')
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->size(TextColumnSize::Large)
                        ->color(fn (Transaction $record): string => match ($record->transaction_type) {
                            TransactionType::Income => 'success',
                            TransactionType::Expense => 'danger',
                            default => 'gray',
                        }),
                    Tables\Columns\IconColumn::make('finished')
                        ->label('Finalizada')
                        ->boolean()
                        ->trueIcon('heroicon-m-check-circle')
                        ->falseIcon('heroicon-m-clock')
                        ->trueColor('success')
                        ->falseColor('warning')
                        ->grow(false),
                ]),
            ])->space(3),
        ];
    }

    public static function getTableColumns(): array
    {
        return [
            Tables\Columns\ToggleColumn::make('finished')
                ->label('Finalizada')
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
                ->getStateUsing(fn (Transaction $record) => $record->displayDueDate())
                ->date('d/m/Y')
                ->badge()
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                    Transaction::dueDateExpression() . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC')
                )),
            Tables\Columns\TextColumn::make('payment_date')
                ->label('Pagamento')
                ->date('d/m/Y')
                ->placeholder('—')
                ->badge()
                ->color('gray')
                ->sortable(),
            Tables\Columns\TextColumn::make('description')
                ->label('Descrição')
                ->searchable()
                ->weight(FontWeight::Medium)
                ->wrap()
                ->limit(48)
                ->tooltip(fn (Transaction $record): string => $record->description)
                ->description(fn (Transaction $record): ?string => $record->account?->name),
            Tables\Columns\TextColumn::make('category.name')
                ->label('Categoria')
                ->searchable()
                ->badge()
                ->icon(fn (Transaction $record): ?string => $record->category?->icon)
                ->color(fn (Transaction $record) => $record->category?->color
                    ? Color::hex($record->category->color)
                    : 'gray')
                ->toggleable(),
            Tables\Columns\TextColumn::make('transaction_type')
                ->label('Tipo')
                ->badge()
                ->iconPosition(IconPosition::After)
                ->alignCenter()
                ->toggleable(),
            Tables\Columns\TextColumn::make('account.name')
                ->label('Conta')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            MoneyColumn::make('amount')
                ->label('Total')
                ->sortable()
                ->alignEnd()
                ->weight(FontWeight::SemiBold)
                ->color(fn (Transaction $record): string => match ($record->transaction_type) {
                    TransactionType::Income => 'success',
                    TransactionType::Expense => 'danger',
                    default => 'gray',
                }),
            Tables\Columns\TextColumn::make('due_date')
                ->label('Vencimento')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\TransactionsOverview::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function normalizeDateInput(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
        } else {
            return null;
        }

        if ($year < 1 || $month < 1 || $month > 12) {
            return null;
        }

        if (!checkdate($month, $day, $year)) {
            $day = max(1, min($day, cal_days_in_month(CAL_GREGORIAN, $month, $year)));
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private static function resolveMonthReference(mixed $value): ?Carbon
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $matches)) {
            return Carbon::createFromDate((int) $matches[1], (int) $matches[2], 1);
        }

        if (preg_match('/^(\d{2})\/(\d{4})$/', $value, $matches)) {
            return Carbon::createFromDate((int) $matches[2], (int) $matches[1], 1);
        }

        return null;
    }
}
