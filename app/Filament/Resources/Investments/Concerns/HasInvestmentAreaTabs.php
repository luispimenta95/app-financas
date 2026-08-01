<?php

namespace App\Filament\Resources\Investments\Concerns;

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\InvestmentResource;
use App\Filament\Resources\Investments\MilestoneResource;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

trait HasInvestmentAreaTabs
{
    public const MARCOS_TAB = 'marcos';

    /**
     * @return array<string, Tab>
     */
    protected function baseInvestmentAreaTabs(): array
    {
        $tabs = [
            'all' => Tab::make()
                ->label('Todos'),
        ];

        foreach (InvestmentType::cases() as $investmentType) {
            $tabs[$investmentType->value] = Tab::make()
                ->label($investmentType->getPluralLabel())
                ->icon($investmentType->getIcon());
        }

        $tabs[self::MARCOS_TAB] = Tab::make()
            ->label('Marcos')
            ->icon('heroicon-m-flag');

        return $tabs;
    }

    /**
     * @return array<string, Tab>
     */
    protected function investmentRecordsTabs(): array
    {
        $tabs = $this->baseInvestmentAreaTabs();

        foreach (InvestmentType::cases() as $investmentType) {
            $tabs[$investmentType->value] = $tabs[$investmentType->value]
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('type', $investmentType)
                );
        }

        $tabs[self::MARCOS_TAB] = $tabs[self::MARCOS_TAB]
            ->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('0 = 1'));

        return $tabs;
    }

    protected function redirectToMilestonesTab(): void
    {
        $this->redirect(MilestoneResource::getUrl());
    }

    protected function redirectToInvestmentsTab(?string $activeTab): void
    {
        $url = InvestmentResource::getUrl();

        if (filled($activeTab) && $activeTab !== 'all') {
            $url .= (str_contains($url, '?') ? '&' : '?').'activeTab='.urlencode($activeTab);
        }

        $this->redirect($url);
    }
}
