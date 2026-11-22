<?php

namespace App\Filament\Pages\Auth;

use App\Support\PlanCatalog;
use Filament\Forms;
use Filament\Forms\Form;
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
        $user = $this->getUser();
        $effectiveKey = $user->effectivePlanKey();
        $planName = $plans->plan($effectiveKey)['name'] ?? $effectiveKey;
        $featureLabels = collect($plans->featureKeysForPlan($effectiveKey))
            ->map(fn (string $key): string => $plans->features()[$key]['label'] ?? $key)
            ->all();

        $planSummary = 'Plano atual: '.$planName;
        if ($user->onTrial()) {
            $planSummary .= ' (trial até '.$user->trial_ends_at->format('d/m/Y H:i').')';
        }
        $planSummary .= $featureLabels === []
            ? "\nNenhuma funcionalidade liberada."
            : "\n• ".implode("\n• ", $featureLabels);

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

                        Forms\Components\Section::make('Plano')
                            ->description('Alterações de plano serão feitas pela landing com pagamento (em breve).')
                            ->icon('heroicon-o-sparkles')
                            ->aside()
                            ->schema([
                                Forms\Components\Placeholder::make('plan_readonly')
                                    ->label('Seu plano')
                                    ->content($planSummary),
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
        // Plano nunca é editável pelo usuário no perfil.
        unset($data['plan'], $data['trial_ends_at']);

        return $data;
    }
}
