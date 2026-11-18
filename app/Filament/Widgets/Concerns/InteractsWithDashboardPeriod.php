<?php

namespace App\Filament\Widgets\Concerns;

use App\Filament\Pages\Dashboard;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

trait InteractsWithDashboardPeriod
{
    use InteractsWithPageFilters;

    protected function hasPeriodFilter(): bool
    {
        return Dashboard::hasActiveFilters($this->filters);
    }

    /**
     * Período do filtro em Y-m-d. Sem datas, usa o mês atual; com só uma delas,
     * completa com o início ou o fim do mês da data informada.
     *
     * @return array{0: string, 1: string}
     */
    protected function getPeriod(): array
    {
        $start = Dashboard::filterDate($this->filters, 'startDate');
        $end = Dashboard::filterDate($this->filters, 'endDate');

        $start ??= Carbon::parse($end ?? now())->startOfMonth()->toDateString();
        $end ??= Carbon::parse($start)->endOfMonth()->toDateString();

        return [$start, $end];
    }
}
