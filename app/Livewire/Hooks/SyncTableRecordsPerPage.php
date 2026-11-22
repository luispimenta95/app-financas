<?php

namespace App\Livewire\Hooks;

use App\Support\TablePagination;
use Livewire\ComponentHook;

class SyncTableRecordsPerPage extends ComponentHook
{
    public function update($propertyName, $fullPath, $newValue): void
    {
        if ($propertyName !== 'tableRecordsPerPage' && $fullPath !== 'tableRecordsPerPage') {
            return;
        }

        $user = auth()->user();

        if ($user === null) {
            return;
        }

        $size = TablePagination::normalize($newValue);

        if ((int) $user->table_records_per_page === $size) {
            return;
        }

        $user->forceFill(['table_records_per_page' => $size])->save();
    }
}
