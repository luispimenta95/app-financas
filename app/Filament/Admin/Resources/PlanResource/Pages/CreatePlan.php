<?php

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use App\Support\PlanCatalog;
use Filament\Resources\Pages\CreateRecord;

class CreatePlan extends CreateRecord
{
    protected static string $resource = PlanResource::class;

    /** @var list<int|string> */
    protected array $featureIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->featureIds = array_values(array_map('intval', $data['feature_ids'] ?? []));
        unset($data['feature_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->features()->sync($this->featureIds);
        app(PlanCatalog::class)->forgetCache();
    }
}
