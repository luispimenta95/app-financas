<?php

namespace App\Filament\Admin\Resources\FeatureResource\Pages;

use App\Filament\Admin\Resources\FeatureResource;
use App\Support\PlanCatalog;
use Filament\Resources\Pages\CreateRecord;

class CreateFeature extends CreateRecord
{
    protected static string $resource = FeatureResource::class;

    protected function afterCreate(): void
    {
        app(PlanCatalog::class)->forgetCache();
    }
}
