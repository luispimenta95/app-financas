<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;

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

        // A single page option hides the per-page selector in every table.
        Table::configureUsing(function (Table $table): void {
            $table
                ->paginated([250])
                ->defaultPaginationPageOption(250);
        });
    }
}
