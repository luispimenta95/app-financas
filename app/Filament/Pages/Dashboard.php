<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\MonthProjectionWidget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Support\Enums\ActionSize;
use Filament\Support\Enums\IconSize;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard Financeiro';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-m-home';

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    /**
     * Data do filtro em Y-m-d, ou null quando o campo está vazio ("" conta como vazio).
     */
    public static function filterDate(?array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        return filled($value) ? Carbon::parse($value)->toDateString() : null;
    }

    public static function hasActiveFilters(?array $filters): bool
    {
        return static::filterDate($filters, 'startDate') !== null
            || static::filterDate($filters, 'endDate') !== null;
    }

    public function updatedFilters(mixed $value = null, ?string $key = null): void
    {
        $this->resetErrorBag();

        $filters = [
            'startDate' => static::filterDate($this->filters, 'startDate'),
            'endDate' => static::filterDate($this->filters, 'endDate'),
        ];

        if ($filters['startDate'] && $filters['endDate'] && $filters['startDate'] > $filters['endDate']) {
            $changed = $key === 'startDate' ? 'startDate' : 'endDate';
            $filters[$changed] = null;

            $this->addError("filters.{$changed}", 'A data inicial deve ser menor ou igual à data final.');
        }

        $this->filters = $filters;
    }

    /**
     * @return array<class-string | WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        $widgets = parent::getVisibleWidgets();

        if (! MonthProjectionWidget::shouldDisplay($this->filters)) {
            $widgets = array_values(array_filter(
                $widgets,
                fn (string|WidgetConfiguration $widget): bool => $this->normalizeWidgetClass($widget) !== MonthProjectionWidget::class,
            ));
        }

        return $widgets;
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Período')
                    ->description(fn (): string => $this->getPeriodDescription())
                    ->icon('heroicon-m-calendar-days')
                    ->iconSize(IconSize::Medium)
                    ->collapsible()
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        // Debounce evita aplicar anos incompletos enquanto a data é digitada
                        Forms\Components\DatePicker::make('startDate')
                            ->label('Data inicial')
                            ->live(debounce: 600)
                            ->maxDate(fn (Get $get): ?string => $get('endDate') ?: null),
                        Forms\Components\DatePicker::make('endDate')
                            ->label('Data final')
                            ->live(debounce: 600)
                            ->minDate(fn (Get $get): ?string => $get('startDate') ?: null),
                    ])
                    ->headerActions([
                        Forms\Components\Actions\Action::make('clearFilters')
                            ->label('Limpar')
                            ->icon('heroicon-m-x-mark')
                            ->color('gray')
                            ->size(ActionSize::Small)
                            ->visible(fn (): bool => static::hasActiveFilters($this->filters))
                            ->action(fn () => $this->clearFilters()),
                    ]),
            ]);
    }

    public function clearFilters(): void
    {
        $this->getFiltersForm()->fill();
        $this->updatedFilters();
    }

    private function getPeriodDescription(): string
    {
        $start = static::filterDate($this->filters, 'startDate');
        $end = static::filterDate($this->filters, 'endDate');

        if ($start === null && $end === null) {
            return 'Sem período definido: mostrando o mês atual.';
        }

        $format = fn (?string $date): string => $date ? Carbon::parse($date)->format('d/m/Y') : '…';

        return 'Mostrando de '.$format($start).' até '.$format($end).'.';
    }

    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'md' => 2,
        ];
    }
}
