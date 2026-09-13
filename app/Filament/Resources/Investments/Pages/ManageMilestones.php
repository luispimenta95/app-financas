<?php

namespace App\Filament\Resources\Investments\Pages;

use App\Filament\Resources\Investments\Concerns\HasInvestmentAreaTabs;
use App\Filament\Resources\Investments\MilestoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageMilestones extends ManageRecords
{
    use HasInvestmentAreaTabs;

    protected static string $resource = MilestoneResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Marcos';
    }

    public function mount(): void
    {
        parent::mount();

        $this->activeTab = self::MARCOS_TAB;
    }

    public function updatedActiveTab(): void
    {
        if ($this->activeTab !== self::MARCOS_TAB) {
            $this->redirectToInvestmentsTab($this->activeTab);

            return;
        }

        $this->cachedHeaderActions = [];
        $this->cacheHeaderActions();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo marco')
                ->modalHeading('Registrar marco')
                ->modalDescription('Informe o valor atingido e a data do marco.'),
        ];
    }

    public function getTabs(): array
    {
        return $this->baseInvestmentAreaTabs();
    }
}
