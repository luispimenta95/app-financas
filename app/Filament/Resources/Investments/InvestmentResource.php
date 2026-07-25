<?php

namespace App\Filament\Resources\Investments;

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\Pages;
use App\Models\Investment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;
use Leandrocfe\FilamentPtbrFormFields\Money;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;

    protected static ?string $navigationIcon = 'heroicon-m-chart-bar-square';

    protected static ?string $modelLabel = 'investimento';

    protected static ?string $pluralModelLabel = 'investimentos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->columns(2)
            ->schema([
                Forms\Components\Hidden::make('type')
                    ->default(InvestmentType::FixedIncome->value)
                    ->dehydrated(),

                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->placeholder('Ex: CDB Liquidez Diária')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('institution')
                    ->label('Instituição')
                    ->placeholder('Ex: Nubank, XP, Itaú')
                    ->required()
                    ->maxLength(255),

                Money::make('amount')
                    ->label('Valor aplicado')
                    ->required()
                    ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                    ->dehydrateStateUsing(fn (?string $state): ?int => str((string) $state)->replace(['.', ','], '')->toInteger()),

                Forms\Components\DatePicker::make('application_date')
                    ->label('Data de aplicação')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->required()
                    ->default(now()->toDateString()),

                Forms\Components\TextInput::make('cdi_rate')
                    ->label('Taxa de juros (% do CDI)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01)
                    ->suffix('% CDI')
                    ->placeholder('100')
                    ->helperText('Informe o percentual do CDI. Ex: 100 = 100% do CDI.'),

                Forms\Components\ToggleButtons::make('daily_liquidity')
                    ->label('Liquidez diária')
                    ->boolean()
                    ->inline()
                    ->required()
                    ->live()
                    ->default(true)
                    ->columnSpanFull(),

                Forms\Components\DatePicker::make('maturity_date')
                    ->label('Data de vencimento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->visible(fn (Get $get): bool => ! (bool) $get('daily_liquidity'))
                    ->required(fn (Get $get): bool => ! (bool) $get('daily_liquidity'))
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
                    ->sortable(),

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
                    ->sortable(),

                TextColumn::make('cdi_rate')
                    ->label('% CDI')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => filled($state) ? number_format((float) $state, 2, ',', '.') . '%' : '—')
                    ->sortable(),

                IconColumn::make('daily_liquidity')
                    ->label('Liq. diária')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('maturity_date')
                    ->label('Vencimento')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('application_date', 'desc')
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
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
}
