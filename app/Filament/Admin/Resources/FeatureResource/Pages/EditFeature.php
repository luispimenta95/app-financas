<?php

namespace App\Filament\Admin\Resources\FeatureResource\Pages;

use App\Filament\Admin\Resources\FeatureResource;
use App\Support\PlanCatalog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFeature extends EditRecord
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(fn () => app(PlanCatalog::class)->forgetCache()),
        ];
    }

    protected function afterSave(): void
    {
        app(PlanCatalog::class)->forgetCache();
    }
}
