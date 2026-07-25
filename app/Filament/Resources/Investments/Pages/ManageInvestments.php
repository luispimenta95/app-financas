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
        if ($this->isVariableIncomeTab()) {
            return [$this->getVariableIncomeAction()];
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

    protected function isVariableIncomeTab(): bool
    {
        return $this->activeTab === InvestmentType::VariableIncome->value;
    }

    protected function getVariableIncomeAction(): Actions\Action
    {
        $existing = Investment::query()->variableIncome()->first();

        if ($existing instanceof Investment) {
            return Actions\Action::make('updateVariableIncome')
                ->label('Atualizar valor')
                ->icon('heroicon-m-pencil-square')
                ->modalHeading('Atualizar renda variável')
                ->modalDescription('Controle parcial: informe apenas o valor aplicado atual.')
                ->fillForm([
                    'amount' => $existing->amount,
                ])
                ->form($this->getVariableIncomeFormSchema())
                ->action(function (array $data) use ($existing): void {
                    $existing->update([
                        'amount' => $data['amount'],
                    ]);
                });
        }

        return Actions\CreateAction::make('createVariableIncome')
            ->label('Definir valor')
            ->icon('heroicon-m-plus')
            ->modalHeading('Definir renda variável')
            ->modalDescription('Controle parcial: informe apenas o valor aplicado.')
            ->form($this->getVariableIncomeFormSchema())
            ->mutateFormDataUsing(fn (array $data): array => Investment::variableIncomeAttributes($data['amount']));
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getVariableIncomeFormSchema(): array
    {
        return [
            Money::make('amount')
                ->label('Valor aplicado')
                ->required()
                ->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2, ',', '.'))
                ->dehydrateStateUsing(fn (?string $state): ?int => str((string) $state)->replace(['.', ','], '')->toInteger()),
        ];
    }
}
