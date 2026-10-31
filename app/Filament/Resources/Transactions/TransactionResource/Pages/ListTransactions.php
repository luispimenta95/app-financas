<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Transactions\TransactionResource\Widgets;
use App\Models\Transactions\Transaction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Hydrat\TableLayoutToggle\Concerns\HasToggleableTable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListTransactions extends ListRecords
{
    use ExposesTableToWidgets, HasToggleableTable;

    protected static string $resource = TransactionResource::class;

    /**
     * Evita reaplicar o foco em cada request Livewire depois da carga inicial
     * (ou após troca de aba/filtro, quando o foco é resetado).
     */
    public bool $hasFocusedOnCurrentDate = false;

    /**
     * @var list<string>
     */
    public array $expandedStatements = [];

    public function mount(): void
    {
        parent::mount();

        $expandedId = session()->pull('expand_credit_card_statement');

        if (filled($expandedId)) {
            $this->expandedStatements = [(string) $expandedId];
        }
    }

    public function toggleCreditCardStatement(string $transactionId): void
    {
        if ($this->isCreditCardStatementExpanded($transactionId)) {
            $this->expandedStatements = array_values(array_filter(
                $this->expandedStatements,
                fn (string $id): bool => $id !== $transactionId
            ));

            return;
        }

        $this->expandedStatements[] = $transactionId;
    }

    public function isCreditCardStatementExpanded(string $transactionId): bool
    {
        return in_array($transactionId, $this->expandedStatements, true);
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordAction(fn (Transaction $record): ?string => $this->hasExpandableStatement($record)
                ? 'toggleCreditCardStatement'
                : null)
            ->recordUrl(function (Transaction $record): ?string {
                if ($this->hasExpandableStatement($record)) {
                    return null;
                }

                foreach (['view', 'edit'] as $page) {
                    if (TransactionResource::{'can' . ucfirst($page)}($record)) {
                        return TransactionResource::getUrl($page, ['record' => $record]);
                    }
                }

                return null;
            });
    }

    protected function hasExpandableStatement(Transaction $record): bool
    {
        return $record->creditCardPurchases->isNotEmpty()
            || ($record->payslip !== null && ! $this->isGridLayout());
    }

    public function getTableRecordDetailView(Model $record): ?View
    {
        if (
            $this->isGridLayout()
            || ! $record instanceof Transaction
            || ! $this->isCreditCardStatementExpanded($record->getKey())
        ) {
            return null;
        }

        if ($record->payslip !== null) {
            return view('filament.tables.payslip-detail', [
                'transaction' => $record,
                'payslip' => $record->payslip,
            ]);
        }

        if ($record->creditCardPurchases->isEmpty()) {
            return null;
        }

        return view('filament.tables.credit-card-statement-detail', [
            'transaction' => $record,
            'statement' => $record->creditCardStatement,
            'purchases' => $record->creditCardPurchases,
        ]);
    }

    public function rendering(): void
    {
        // Roda depois de bootedInteractsWithTable, com filtros/paginação prontos.
        $this->focusTableOnCurrentDateIfNeeded();
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->hasFocusedOnCurrentDate = false;
    }

    public function updatedTableFilters(): void
    {
        parent::updatedTableFilters();

        $this->hasFocusedOnCurrentDate = false;
    }

    public function updatedTableSearch(): void
    {
        parent::updatedTableSearch();

        $this->useAllMonthsWhenSearchingWithoutExplicitDate();
    }

    /**
     * Pesquisa por texto sem um mês escolhido (ou com o padrão do mês atual)
     * deve olhar todos os períodos, alinhada aos widgets.
     */
    protected function useAllMonthsWhenSearchingWithoutExplicitDate(): void
    {
        if (blank(trim((string) $this->tableSearch))) {
            return;
        }

        $monthReference = data_get($this->tableFilters, 'date.monthReference');

        if ($monthReference === 'all') {
            return;
        }

        if (filled($monthReference) && $monthReference !== now()->format('Y-m')) {
            return;
        }

        data_set($this->tableFilters, 'date.monthReference', 'all');
        $this->hasFocusedOnCurrentDate = false;
    }

    public function updatedTableGrouping(): void
    {
        $this->hasFocusedOnCurrentDate = false;
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make()->label('Todos')];

        $cases = TransactionType::cases();

        rsort($cases);

        foreach ($cases as $transactionType) {
            $tabs[$transactionType->value] = Tab::make()
                ->label($transactionType->getPluralLabel())
                ->icon($transactionType->getIcon())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('transaction_type', $transactionType));
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\TransactionsOverview::class,
        ];
    }

    protected function focusTableOnCurrentDateIfNeeded(): void
    {
        if (!$this->shouldFocusOnCurrentDate()) {
            return;
        }

        $this->hasFocusedOnCurrentDate = true;

        $today = now()->toDateString();

        $focusDateLabel = $this->resolveFocusDateLabel($today);

        if (filled($focusDateLabel)) {
            $this->dispatchScrollToDateGroup($focusDateLabel);
        }
    }

    protected function shouldFocusOnCurrentDate(): bool
    {
        if ($this->hasFocusedOnCurrentDate) {
            return false;
        }

        $grouping = $this->tableGrouping ?? $this->getTable()->getDefaultGroup()?->getId();

        if ($grouping !== 'due_date') {
            return false;
        }

        if (
            filled($this->tableSortColumn)
            && $this->tableSortDirection === 'desc'
        ) {
            return false;
        }

        $monthReference = data_get($this->tableFilters, 'date.monthReference');

        if (blank($monthReference)) {
            $monthReference = now()->format('Y-m');
        }

        if ($monthReference === 'all') {
            return true;
        }

        return $monthReference === now()->format('Y-m');
    }

    protected function resolveFocusDateLabel(string $today): ?string
    {
        $record = (clone $this->getFilteredSortedTableQuery())
            ->whereRaw(Transaction::dueDateExpression() . ' >= ?', [$today])
            ->first();

        if (!$record instanceof Transaction) {
            $record = (clone $this->getFilteredSortedTableQuery())
                ->reorder()
                ->orderByRaw(Transaction::dueDateExpression() . ' DESC')
                ->first();
        }

        if (!$record instanceof Transaction) {
            return null;
        }

        return $record->displayDueDate()?->format('d/m/Y');
    }

    protected function dispatchScrollToDateGroup(string $dateLabel): void
    {
        $escapedLabel = json_encode($dateLabel, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->js(<<<JS
            (() => {
                const dateLabel = {$escapedLabel};
                const scrollToGroup = () => {
                    const headers = Array.from(document.querySelectorAll('.fi-ta-group-header'));
                    const target = headers.find((el) => (el.textContent || '').includes(dateLabel));

                    if (! target) {
                        return;
                    }

                    const topbar = document.querySelector('.fi-topbar');
                    const widgets = document.querySelector('.fi-page-header-widgets');
                    const offset = (topbar?.getBoundingClientRect().height ?? 0)
                        + (widgets?.getBoundingClientRect().height ?? 0)
                        + 8;
                    const top = target.getBoundingClientRect().top + window.scrollY - offset;

                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                };

                requestAnimationFrame(() => requestAnimationFrame(scrollToGroup));
            })()
        JS);
    }
}
