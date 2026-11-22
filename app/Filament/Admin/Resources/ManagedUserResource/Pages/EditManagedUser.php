<?php

namespace App\Filament\Admin\Resources\ManagedUserResource\Pages;

use App\Filament\Admin\Resources\ManagedUserResource;
use App\Support\PlanCatalog;
use Filament\Resources\Pages\EditRecord;

class EditManagedUser extends EditRecord
{
    protected static string $resource = ManagedUserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $plans = app(PlanCatalog::class);

        if (($data['plan'] ?? null) === $plans->trialPlanKey()) {
            $data['trial_ends_at'] = null;
        }

        return $data;
    }
}
