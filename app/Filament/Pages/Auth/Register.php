<?php

namespace App\Filament\Pages\Auth;

use App\Support\AppAdmin;
use App\Support\PlanCatalog;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class Register extends BaseRegister
{
    protected function handleRegistration(array $data): Model
    {
        $requestedPlan = request()->query('plan')
            ?? request()->input('plan')
            ?? session('financas.registration_plan');

        $attributes = app(PlanCatalog::class)->attributesForRegistration(
            is_string($requestedPlan) ? $requestedPlan : null,
        );

        session()->forget('financas.registration_plan');

        return $this->getUserModel()::create([
            ...$data,
            ...$attributes,
        ]);
    }

    public function mount(): void
    {
        parent::mount();

        $plan = request()->query('plan');

        if (is_string($plan) && $plan !== '') {
            session(['financas.registration_plan' => $plan]);
        }
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::pages/auth/register.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique($this->getUserModel())
            ->rule(Rule::notIn(array_filter([AppAdmin::email()])))
            ->validationMessages([
                'not_in' => 'Este e-mail não está disponível para cadastro.',
            ]);
    }
}
