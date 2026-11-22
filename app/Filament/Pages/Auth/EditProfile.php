<?php

namespace App\Filament\Pages\Auth;

use App\Support\PlanCatalog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BasePage;
use Filament\Support\Enums\Alignment;

class EditProfile extends BasePage
{
    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        $plans = app(PlanCatalog::class);

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        Forms\Components\Section::make('Informações Pessoais')
                            ->description('Seus dados de usuário')
                            ->icon('heroicon-o-user-circle')
                            ->aside()
                            ->columns([
                                'default' => 1,
                                'lg' => 4,
                            ])
                            ->schema([
                                Forms\Components\Group::make()
                                    ->columnSpan([
                                        'default' => 'full',
                                        'lg' => 3,
                                    ])
                                    ->schema([
                                        $this->getNameFormComponent(),
                                        $this->getEmailFormComponent(),
                                    ]),

                                Forms\Components\FileUpload::make('avatar')
                                    ->label('Avatar')
                                    ->avatar()
                                    ->image()
                                    ->imageEditor()
                                    ->imageEditorMode(2)
                                    ->directory('users')
                                    ->columnSpan([
                                        'default' => 'full',
                                        'lg' => 1,
                                    ]),
                            ]),

                        Forms\Components\Section::make('Plano e funcionalidades')
                            ->description('Pro sem data de trial = acesso ilimitado. Features por plano em config/financas.php.')
                            ->icon('heroicon-o-sparkles')
                            ->aside()
                            ->schema([
                                Forms\Components\Select::make('plan')
                                    ->label('Plano')
                                    ->options(fn (): array => $plans->planOptions())
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->helperText('Escolha Pro para acesso completo sem limite de dias.'),
                                Forms\Components\DateTimePicker::make('trial_ends_at')
                                    ->label('Trial válido até')
                                    ->helperText('Só se aplica no plano Gratuito: libera o Pro temporariamente. No Pro o trial é ignorado.')
                                    ->native(false)
                                    ->seconds(false)
                                    ->visible(fn (Get $get): bool => (string) $get('plan') !== $plans->trialPlanKey())
                                    ->dehydrated(fn (Get $get): bool => (string) $get('plan') !== $plans->trialPlanKey()),
                                Forms\Components\Placeholder::make('plan_features')
                                    ->label('Funcionalidades do plano efetivo')
                                    ->content(function (Get $get) use ($plans): string {
                                        $selectedPlan = (string) ($get('plan') ?: $plans->defaultPlanKey());
                                        $trialEnds = $get('trial_ends_at');

                                        $effective = $selectedPlan;

                                        if ($plans->isUnlimitedPlan($selectedPlan)) {
                                            $effective = $selectedPlan;
                                        } elseif (filled($trialEnds)) {
                                            try {
                                                if (\Illuminate\Support\Carbon::parse($trialEnds)->isFuture()) {
                                                    $effective = $plans->trialPlanKey();
                                                }
                                            } catch (\Throwable) {
                                                // ignore parse errors in preview
                                            }
                                        }

                                        $labels = collect($plans->featureKeysForPlan($effective))
                                            ->map(fn (string $key): string => $plans->features()[$key]['label'] ?? $key)
                                            ->all();

                                        $suffix = $plans->isUnlimitedPlan($effective)
                                            ? ' (ilimitado)'
                                            : '';

                                        if ($labels === []) {
                                            return 'Nenhuma feature configurada para este plano.';
                                        }

                                        return 'Plano efetivo: '.$effective.$suffix."\n• ".implode("\n• ", $labels);
                                    }),
                            ]),

                        Forms\Components\Section::make('Segurança')
                            ->description('Seus dados de segurança')
                            ->icon('heroicon-o-lock-closed')
                            ->aside()
                            ->schema([
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),
                        Forms\Components\Section::make('Perigo')
                            ->description('Muito cuidado')
                            ->icon('heroicon-o-exclamation-triangle')
                            ->aside()
                            ->schema([
                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('deleteAccount')
                                        ->label(__('Excluir conta'))
                                        ->icon('heroicon-m-trash')
                                        ->color('danger')
                                        ->requiresConfirmation()
                                        ->action(function () {
                                            $user = auth()->user();

                                            auth()->logout();

                                            if ($user->delete()) {
                                                return redirect(filament()->getLoginUrl());
                                            }
                                        }),
                                ]),
                            ]),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data'),
            ),
        ];
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::End;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $plans = app(PlanCatalog::class);

        if (($data['plan'] ?? null) === $plans->trialPlanKey()) {
            // Pro permanente: sem contagem de dias.
            $data['trial_ends_at'] = null;
        }

        return $data;
    }
}
