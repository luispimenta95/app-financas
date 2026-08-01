<?php

namespace App\Filament\Resources\Investments\Pages;

use App\Enums\InvestmentType;
use App\Filament\Resources\Investments\InvestmentResource;
use App\Models\Investment;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Leandrocfe\FilamentPtbrFormFields\Money;

class ManageInvestments extends ManageRecords
{
    protected static string $resource = InvestmentResource::class;

    public function updatedActiveTab(): void
    {
        $this->cachedHeaderActions = [];
        $this->cacheHeaderActions();
    }

    protected function getHeaderActions(): array
    {
        $estimatedType = $this->resolveEstimatedControlTab();

        if ($estimatedType instanceof InvestmentType) {
            return [$this->getEstimatedControlAction($estimatedType)];
        }

        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $data['type'] = InvestmentType::FixedIncome->value;

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

    protected function resolveEstimatedControlTab(): ?InvestmentType
    {
        $type = InvestmentType::tryFrom((string) $this->activeTab);

        return $type?->isEstimatedControl() ? $type : null;
    }

    protected function getEstimatedControlAction(InvestmentType $type): Actions\Action
    {
        $existing = Investment::query()->ofType($type)->first();
        $label = $type->getLabel();
        $amountNoun = $type === InvestmentType::Abroad ? 'valor estimado' : 'valor aplicado';

        if ($existing instanceof Investment) {
            return Actions\Action::make('update'.$type->name)
                ->label('Atualizar valor')
                ->icon('heroicon-m-pencil-square')
                ->modalHeading("Atualizar {$label}")
                ->modalDescription("Controle parcial: informe apenas o {$amountNoun} atual.")
                ->fillForm([
                    'amount' => $existing->amount,
                ])
                ->form($this->getEstimatedControlFormSchema($type))
                ->action(function (array $data) use ($existing): void {
                    $existing->update([
                        'amount' => $data['amount'],
                    ]);
                });
        }

        return Actions\CreateAction::make('create'.$type->name)
            ->label('Definir valor')
            ->icon('heroicon-m-plus')
            ->modalHeading("Definir {$label}")
            ->modalDescription("Controle parcial: informe apenas o {$amountNoun}.")
            ->form($this->getEstimatedControlFormSchema($type))
            ->mutateFormDataUsing(
                fn (array $data): array => Investment::estimatedControlAttributes($type, $data['amount'])
            );
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getEstimatedControlFormSchema(InvestmentType $type): array
    {
        $amountLabel = $type === InvestmentType::Abroad ? 'Valor estimado' : 'Valor aplicado';

        return [
            Money::make('amount')
                ->label($amountLabel)
                ->required()
                ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                ->dehydrateStateUsing(fn (?string $state): ?int => str((string) $state)->replace(['.', ','], '')->toInteger()),
        ];
    }
}
