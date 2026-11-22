<?php

namespace App\Filament\Admin\Resources\PlanResource\Pages;

use App\Filament\Admin\Resources\PlanResource;
use App\Support\PlanCatalog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlan extends EditRecord
{
    protected static string $resource = PlanResource::class;

    /** @var list<int|string> */
    protected array $featureIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(fn () => app(PlanCatalog::class)->forgetCache()),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['feature_ids'] = $this->record
            ->features()
            ->pluck('plan_features.id')
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->featureIds = array_values(array_map('intval', $data['feature_ids'] ?? []));
        unset($data['feature_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->features()->sync($this->featureIds);
        app(PlanCatalog::class)->forgetCache();
    }
}
