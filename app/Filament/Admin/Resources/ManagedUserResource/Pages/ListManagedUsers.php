<?php

namespace App\Filament\Admin\Resources\ManagedUserResource\Pages;

use App\Filament\Admin\Resources\ManagedUserResource;
use Filament\Resources\Pages\ListRecords;

class ListManagedUsers extends ListRecords
{
    protected static string $resource = ManagedUserResource::class;
}
