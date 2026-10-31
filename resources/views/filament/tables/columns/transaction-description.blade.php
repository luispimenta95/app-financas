@php
    $record = $getRecord();
    $livewire = $getLivewire();
    $hasStatement = $record->creditCardPurchases->isNotEmpty();
    $expanded = $hasStatement && $livewire->isCreditCardStatementExpanded($record->getKey());
    $showInlineRows = $expanded && $livewire->isGridLayout();
    $purchases = $showInlineRows ? $record->creditCardPurchases : collect();
    $sum = (int) $purchases->sum('amount');
@endphp

<div class="min-w-0">
    <div class="min-w-0 break-words font-medium text-gray-950 dark:text-white">
        {{ $record->displayTitle() }}
    </div>

    @if ($showInlineRows)
        <div class="mt-2 divide-y divide-gray-200/80 dark:divide-white/10">
            @foreach ($purchases as $purchase)
                <div class="flex items-center gap-3 py-1 text-sm">
                    <span class="w-12 shrink-0 tabular-nums text-gray-500 dark:text-gray-400">
                        {{ $purchase->occurred_on?->format('d/m') ?? '—' }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-gray-800 dark:text-gray-200">
                        {{ $purchase->description }}
                        @if (filled($purchase->installment_label))
                            <span class="text-gray-400">{{ $purchase->installment_label }}</span>
                        @endif
                    </span>
                    <span @class([
                        'shrink-0 tabular-nums font-medium',
                        'text-success-600 dark:text-success-400' => $purchase->amount < 0,
                        'text-gray-950 dark:text-white' => $purchase->amount >= 0,
                    ])>
                        R$ {{ number_format($purchase->amount / 100, 2, ',', '.') }}
                    </span>
                </div>
            @endforeach
            <div class="flex items-center justify-between py-1.5 text-sm font-semibold">
                <span class="text-gray-600 dark:text-gray-300">Soma</span>
                <span class="tabular-nums text-gray-950 dark:text-white">
                    R$ {{ number_format($sum / 100, 2, ',', '.') }}
                </span>
            </div>
        </div>
    @endif
</div>
