<?php

namespace App\Providers;

use App\Livewire\Hooks\SyncTableRecordsPerPage;
use App\Support\TablePagination;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Native HTML5 date inputs allow typing; Filament's JS picker is readonly.
        DatePicker::configureUsing(function (DatePicker $component): void {
            $component
                ->native(true)
                ->format('Y-m-d');
        });

        DateTimePicker::configureUsing(function (DateTimePicker $component): void {
            $component->native(true);
        });

        // Preferência do usuário (50/100/200) aplica a todas as tabelas Filament.
        Table::configureUsing(function (Table $table): void {
            $size = TablePagination::for();

            $table
                ->paginated(TablePagination::OPTIONS)
                ->defaultPaginationPageOption($size);
        });

        Livewire::componentHook(SyncTableRecordsPerPage::class);
    }
}
