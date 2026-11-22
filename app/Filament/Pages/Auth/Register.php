<?php

namespace App\Filament\Pages\Auth;

use App\Support\PlanCatalog;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

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
}
