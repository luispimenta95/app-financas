<?php

namespace App\Filament\Resources\Investments;

use App\Enums\InvestmentRateType;
use App\Enums\InvestmentType;
use App\Models\Investment;
use App\Services\Investment\RedeemInvestment;
use App\Support\PlanFeature;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Leandrocfe\FilamentPtbrFormFields\Money;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-bar-square';

    protected static ?string $modelLabel = 'investimento';

    protected static ?string $pluralModelLabel = 'investimentos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return financas_user_can(PlanFeature::INVESTMENTS);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->schema([
                Forms\Components\Hidden::make('type')
                    ->default(InvestmentType::FixedIncome->value)
                    ->dehydrated(),

                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->placeholder('Ex: CDB Liquidez Diária')
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->maxLength(255),

                Forms\Components\TextInput::make('institution')
                    ->label('Instituição')
                    ->placeholder('Ex: Nubank, XP, Itaú')
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->maxLength(255),

                Money::make('amount')
                    ->label('Valor aplicado')
                    ->required()
                    ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                    ->dehydrateStateUsing(fn (?string $state): ?int => str((string) $state)->replace(['.', ','], '')->toInteger())
                    ->columnSpan(fn (Get $get, ?Investment $record): int => static::isFixedIncomeForm($get, $record) ? 1 : 2),

                Forms\Components\DatePicker::make('application_date')
                    ->label('Data de aplicação')
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->default(now()->toDateString()),

                Forms\Components\ToggleButtons::make('rate_type')
                    ->label('Tipo de rentabilidade')
                    ->options(InvestmentRateType::class)
                    ->inline()
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->live()
                    ->default(InvestmentRateType::Cdi->value)
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('interest_rate')
                    ->label(fn (Get $get): string => static::resolveRateType($get('rate_type'))->getRateLabel())
                    ->numeric()
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->minValue(0)
                    ->step(0.01)
                    ->suffix(fn (Get $get): string => static::resolveRateType($get('rate_type'))->getRateSuffix())
                    ->placeholder(fn (Get $get): string => static::resolveRateType($get('rate_type'))->getRatePlaceholder())
                    ->helperText(fn (Get $get): string => static::resolveRateType($get('rate_type'))->getRateHelperText()),

                Forms\Components\ToggleButtons::make('daily_liquidity')
                    ->label('Liquidez diária')
                    ->boolean()
                    ->inline()
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record))
                    ->live()
                    ->default(true)
                    ->columnSpanFull(),

                Forms\Components\DatePicker::make('maturity_date')
                    ->label('Data de vencimento')
                    ->visible(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record) && !(bool) $get('daily_liquidity'))
                    ->required(fn (Get $get, ?Investment $record): bool => static::isFixedIncomeForm($get, $record) && !(bool) $get('daily_liquidity'))
                    ->helperText('Obrigatório quando o investimento não tem liquidez diária.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('institution')
                    ->label('Instituição')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->visibleFrom('md'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amount')
                    ->label('Valor aplicado')
                    ->money('BRL', divideBy: 100)
                    ->sortable()
                    ->summarize(
                        Sum::make()
                            ->label('Total')
                            ->money('BRL', divideBy: 100)
                    ),

                TextColumn::make('application_date')
                    ->label('Aplicação')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->visibleFrom('lg'),

                TextColumn::make('interest_rate')
                    ->label('Rentabilidade')
                    ->alignEnd()
                    ->state(fn (Investment $record): string => $record->formattedInterestRate())
                    ->sortable()
                    ->visibleFrom('md'),

                IconColumn::make('daily_liquidity')
                    ->label('Liq. diária')
                    ->boolean()
                    ->alignCenter()
                    ->getStateUsing(fn (Investment $record): ?bool => $record->type?->isEstimatedControl()
                        ? null
                        : $record->daily_liquidity)
                    ->visibleFrom('lg'),

                TextColumn::make('maturity_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable()
                    ->visibleFrom('lg'),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort(
                fn (Builder $query): Builder => $query->orderByRedemption(),
            )
            ->defaultGroup('daily_liquidity')
            ->groups([
                Tables\Grouping\Group::make('daily_liquidity')
                    ->label('Tipo de resgate')
                    ->titlePrefixedWithLabel(false)
                    ->getKeyFromRecordUsing(
                        fn (Investment $record): string => $record->redemptionGroupKey()
                    )
                    ->getTitleFromRecordUsing(
                        fn (Investment $record): string => $record->redemptionTypeLabel()
                    )
                    ->groupQueryUsing(
                        fn (QueryBuilder $query): QueryBuilder => $query->groupByRaw(
                            Investment::redemptionGroupExpression()
                        )
                    )
                    ->orderQueryUsing(
                        fn (Builder $query, string $direction): Builder => $query->orderByRaw(
                            Investment::redemptionOrderExpression() . ' ' . ($direction === 'desc' ? 'DESC' : 'ASC')
                        )
                    )
                    ->scopeQueryByKeyUsing(
                        fn (Builder $query, string $key): Builder => $query->where(
                            'daily_liquidity',
                            $key === Investment::REDEMPTION_GROUP_DAILY
                        )
                    ),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByRedemption())
            ->actions([
                static::getRedeemAction(),
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data, Investment $record): array {
                        if ($record->type?->isEstimatedControl()) {
                            return Investment::estimatedControlAttributes($record->type, (int) $data['amount']);
                        }

                        if (($data['daily_liquidity'] ?? true) === true) {
                            $data['maturity_date'] = null;
                        }

                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInvestments::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getRedeemAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('redeem')
            ->label('Resgatar')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (Investment $record): bool => $record->type === InvestmentType::FixedIncome && $record->amount > 0)
            ->modalHeading(fn (Investment $record): string => "Resgatar {$record->name}")
            ->modalDescription('O valor resgatado é descontado do investimento e lançado como receita na categoria Investimentos, entrando no saldo do mês do resgate.')
            ->modalSubmitActionLabel('Resgatar')
            ->fillForm(fn (Investment $record): array => [
                'amount' => $record->amount,
                'date' => now()->toDateString(),
            ])
            ->form([
                Money::make('amount')
                    ->label('Valor resgatado')
                    ->required()
                    ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                    ->dehydrateStateUsing(fn (?string $state): int => static::parseMoney($state))
                    ->helperText(fn (Investment $record): string => 'Saldo atual: R$ '.number_format($record->amount / 100, 2, ',', '.'))
                    ->rule(fn (Investment $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $amount = static::parseMoney($value);

                        if ($amount <= 0) {
                            $fail('Informe um valor maior que zero.');
                        } elseif ($amount > $record->amount) {
                            $fail('O valor não pode ser maior que o saldo do investimento.');
                        }
                    }),
                Forms\Components\DatePicker::make('date')
                    ->label('Data do resgate')
                    ->required(),
            ])
            ->action(function (array $data, Investment $record): void {
                app(RedeemInvestment::class)->handle(
                    investment: $record,
                    amount: $data['amount'],
                    date: $data['date'],
                );

                Notification::make()
                    ->title('Resgate registrado')
                    ->body($record->amount > 0
                        ? 'Saldo restante: R$ '.number_format($record->amount / 100, 2, ',', '.')
                        : 'Investimento resgatado por completo.')
                    ->success()
                    ->send();
            });
    }

    private static function parseMoney(mixed $state): int
    {
        if (is_int($state)) {
            return $state;
        }

        return str((string) $state)->replace(['.', ','], '')->toInteger();
    }

    private static function isFixedIncomeForm(Get $get, ?Investment $record): bool
    {
        if ($record instanceof Investment) {
            return $record->type === InvestmentType::FixedIncome;
        }

        $type = $get('type');

        if ($type instanceof InvestmentType) {
            return $type === InvestmentType::FixedIncome;
        }

        $resolvedType = InvestmentType::tryFrom((string) $type);

        return !($resolvedType?->isEstimatedControl() ?? false);
    }

    private static function resolveRateType(mixed $rateType): InvestmentRateType
    {
        if ($rateType instanceof InvestmentRateType) {
            return $rateType;
        }

        return InvestmentRateType::tryFrom((string) $rateType) ?? InvestmentRateType::Cdi;
    }
}
