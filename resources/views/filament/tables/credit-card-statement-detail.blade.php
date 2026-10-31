@php
    $sum = (int) $purchases->sum('amount');
    $pdfPath = $statement?->file_path ?: $transaction->attachment;
@endphp

{{-- width:0 + min-width:100% keeps the detail from widening the parent table; overflow stays inside. --}}
<div class="border-s-4 border-primary-500 bg-gray-50 px-4 py-3 dark:bg-white/5 sm:ps-6" style="width: 0; min-width: 100%; box-sizing: border-box;">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
        <span>
            Fatura {{ $transaction->displayTitle() }}
            @if ($statement?->imported_at)
                · lida em {{ $statement->imported_at->format('d/m/Y H:i') }}
            @endif
            · {{ $purchases->count() }} lançamentos
        </span>

        @if (filled($pdfPath))
            <a
                href="{{ asset('storage/'.$pdfPath) }}"
                target="_blank"
                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                {{ $statement?->original_name ?? 'Abrir PDF' }}
            </a>
        @endif
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="max-width: 100%; overflow-x: auto;">
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/5" style="min-width: 40rem;">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-start text-xs font-semibold text-gray-600 dark:text-gray-300">
                    <th class="w-24 px-3 py-2 text-start">Data</th>
                    <th class="px-3 py-2 text-start">Descrição</th>
                    <th class="w-24 px-3 py-2 text-center">Parcela</th>
                    <th class="w-32 px-3 py-2 text-end">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach ($purchases as $purchase)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="whitespace-nowrap px-3 py-1.5 tabular-nums text-gray-500 dark:text-gray-400">
                            {{ $purchase->occurred_on?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-3 py-1.5 text-gray-950 dark:text-white">
                            {{ $purchase->description }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-center text-gray-500 dark:text-gray-400">
                            {{ $purchase->installment_label ?? '—' }}
                        </td>
                        <td @class([
                            'whitespace-nowrap px-3 py-1.5 text-end font-medium tabular-nums',
                            'text-success-600 dark:text-success-400' => $purchase->amount < 0,
                            'text-gray-950 dark:text-white' => $purchase->amount >= 0,
                        ])>
                            R$ {{ number_format($purchase->amount / 100, 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold dark:bg-white/5">
                <tr>
                    <td colspan="3" class="px-3 py-2 text-gray-700 dark:text-gray-200">Soma dos lançamentos</td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-950 dark:text-white">
                        R$ {{ number_format($sum / 100, 2, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="px-3 py-2 text-gray-700 dark:text-gray-200">Valor da fatura</td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-950 dark:text-white">
                        R$ {{ number_format(((int) $transaction->amount) / 100, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
