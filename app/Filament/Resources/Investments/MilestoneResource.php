<?php

namespace App\Filament\Resources\Investments;

use App\Models\InvestmentMilestone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Leandrocfe\FilamentPtbrFormFields\Money;

class MilestoneResource extends Resource
{
    protected static ?string $model = InvestmentMilestone::class;

    protected static ?string $navigationIcon = 'heroicon-m-flag';

    protected static ?string $modelLabel = 'marco';

    protected static ?string $pluralModelLabel = 'marcos';

    protected static ?string $slug = 'investimentos/marcos';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Money::make('amount')
                    ->label('Valor')
                    ->required()
                    ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                    ->dehydrateStateUsing(fn (?string $state): ?int => str((string) $state)->replace(['.', ','], '')->toInteger()),

                Forms\Components\DatePicker::make('achieved_on')
                    ->label('Data')
                    ->required()
                    ->default(now()->toDateString())
                    ->native(false)
                    ->displayFormat('d/m/Y'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL', divideBy: 100)
                    ->sortable(),

                TextColumn::make('achieved_on')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('achieved_on', 'desc')
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
            'index' => Pages\ManageMilestones::route('/'),
        ];
    }
}
