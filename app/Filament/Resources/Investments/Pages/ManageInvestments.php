<?php

namespace App\Filament\Resources\Investments\Pages;

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManageInvestments extends ManageRecords
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['type'] = $data['type'] ?? InvestmentType::FixedIncome->value;

                    if (($data['daily_liquidity'] ?? true) === true) {
                        $data['maturity_date'] = null;
                    }

                    return $data;
                }),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make()
                ->label('Todos'),
        ];

        foreach (InvestmentType::cases() as $investmentType) {
            $tabs[$investmentType->value] = Tab::make()
                ->label($investmentType->getPluralLabel())
                ->icon($investmentType->getIcon())
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->where('type', $investmentType)
                );
        }

        return $tabs;
    }
}
