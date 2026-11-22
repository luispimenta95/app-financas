<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ManagedUserResource\Pages;
use App\Models\User;
use App\Support\PlanCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ManagedUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Contas';

    protected static ?string $navigationLabel = 'Usuários';

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?string $slug = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        $plans = app(PlanCatalog::class);

        return $form
            ->schema([
                Forms\Components\Section::make('Conta')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Plano')
                    ->description('Alteração manual até o pagamento pela landing estar pronto.')
                    ->schema([
                        Forms\Components\Select::make('plan')
                            ->label('Plano')
                            ->options(fn (): array => $plans->planOptions(includeInactive: true))
                            ->required()
                            ->native(false)
                            ->live(),
                        Forms\Components\DateTimePicker::make('trial_ends_at')
                            ->label('Trial válido até')
                            ->helperText('Só se aplica quando o plano não é o Pro/trial permanente.')
                            ->native(false)
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => (string) $get('plan') !== $plans->trialPlanKey()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('plan')
                    ->label('Plano')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->label('Trial até')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Plano'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListManagedUsers::route('/'),
            'edit' => Pages\EditManagedUser::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
