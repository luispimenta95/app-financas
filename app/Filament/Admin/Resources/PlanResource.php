<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PlanResource\Pages;
use App\Models\Feature;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Leandrocfe\FilamentPtbrFormFields\Money;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Catálogo';

    protected static ?string $navigationLabel = 'Planos';

    protected static ?string $modelLabel = 'plano';

    protected static ?string $pluralModelLabel = 'planos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificação')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Chave')
                            ->required()
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->helperText('Ex: free, pro. Usada no usuário e na landing.')
                            ->disabled(fn (?Plan $record): bool => $record !== null)
                            ->dehydrated(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordem')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true),
                        Forms\Components\Toggle::make('featured')
                            ->label('Destaque na landing')
                            ->default(false),
                    ]),
                Forms\Components\Section::make('Preço e textos')
                    ->columns(2)
                    ->schema([
                        Money::make('price_monthly')
                            ->label('Preço mensal')
                            ->required()
                            ->default(0)
                            ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, ',', '.'))
                            ->dehydrateStateUsing(fn (?string $state): int => (int) str((string) $state)->replace(['.', ','], '')->toInteger())
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if (filled($get('price_label'))) {
                                    return;
                                }

                                $cents = (int) str((string) $state)->replace(['.', ','], '')->toInteger();
                                $set('price_label', 'R$ '.number_format($cents / 100, 2, ',', '.'));
                            })
                            ->helperText('Informe o valor em reais. Ex: 29,90'),
                        Forms\Components\TextInput::make('price_label')
                            ->label('Rótulo de preço')
                            ->maxLength(50)
                            ->placeholder('R$ 29,90')
                            ->helperText('Texto exibido na landing. Se vazio, pode preencher a partir do preço.'),
                        Forms\Components\TextInput::make('period')
                            ->label('Período')
                            ->maxLength(50)
                            ->placeholder('/mês'),
                        Forms\Components\TextInput::make('cta')
                            ->label('Texto do CTA')
                            ->maxLength(100)
                            ->placeholder('Começar grátis'),
                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TagsInput::make('highlights')
                            ->label('Highlights (landing)')
                            ->placeholder('Adicionar item')
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Funcionalidades')
                    ->description('Marque para liberar ou desmarque para remover do plano.')
                    ->schema([
                        Forms\Components\CheckboxList::make('feature_ids')
                            ->label('Liberadas neste plano')
                            ->options(fn (): array => Feature::query()
                                ->orderBy('sort_order')
                                ->orderBy('id')
                                ->pluck('label', 'id')
                                ->all())
                            ->columns(2)
                            ->bulkToggleable()
                            ->searchable()
                            ->helperText('As mudanças passam a valer assim que salvar o plano.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->label('Chave')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_monthly')
                    ->label('Preço')
                    ->formatStateUsing(fn (?int $state): string => 'R$ '.number_format(($state ?? 0) / 100, 2, ',', '.')),
                Tables\Columns\IconColumn::make('featured')
                    ->label('Destaque')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('features_count')
                    ->counts('features')
                    ->label('Qtd.'),
                Tables\Columns\TextColumn::make('features.label')
                    ->label('Funcionalidades')
                    ->badge()
                    ->separator(',')
                    ->wrap()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
