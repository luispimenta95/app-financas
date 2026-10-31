@php
    $money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');
    $difference = (int) $transaction->amount - $payslip->net_amount;
    $reference = $payslip->reference_month
        ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $payslip->reference_month)->format('m/Y')
        : null;
@endphp

{{-- width:0 + min-width:100% keeps the detail from widening the parent table; overflow stays inside. --}}
<div class="border-s-4 border-success-500 bg-gray-50 px-4 py-3 dark:bg-white/5 sm:ps-6" style="width: 0; min-width: 100%; box-sizing: border-box;">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
        <span>
            {{ $payslip->kindLabel() }}
            @if ($reference)
                · competência {{ $reference }}
            @endif
            @if ($payslip->credit_date)
                · crédito em {{ $payslip->credit_date->format('d/m/Y') }}
            @endif
            @if ($payslip->base_salary)
                · salário base {{ $money($payslip->base_salary) }}
            @endif
            @if ($payslip->imported_at)
                · lido em {{ $payslip->imported_at->format('d/m/Y H:i') }}
            @endif
        </span>

        <span class="flex flex-wrap gap-3">
            <a
                href="{{ asset('storage/'.$payslip->file_path) }}"
                target="_blank"
                class="font-medium text-primary-600 hover:underline dark:text-primary-400"
            >
                {{ $payslip->original_name ?? 'Abrir PDF' }}
            </a>
            <a
                href="{{ \App\Filament\Resources\Transactions\TransactionResource::getUrl('uploadPayslip', ['record' => $transaction]) }}"
                class="font-medium text-gray-600 hover:underline dark:text-gray-300"
            >
                Enviar outro PDF
            </a>
        </span>
    </div>

    <div class="overflow-x-auto rounded-lg bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" style="max-width: 100%; overflow-x: auto;">
        <table class="w-full table-auto divide-y divide-gray-200 text-sm dark:divide-white/5" style="min-width: 40rem;">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                    <th class="px-3 py-2 text-start">Cód.</th>
                    <th class="px-3 py-2 text-start">Descrição</th>
                    <th class="px-3 py-2 text-end">Qtde.</th>
                    <th class="px-3 py-2 text-end">Vencimentos</th>
                    <th class="px-3 py-2 text-end">Descontos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach ($payslip->items as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="whitespace-nowrap px-3 py-1.5 tabular-nums text-gray-500 dark:text-gray-400">{{ $item->code }}</td>
                        <td class="px-3 py-1.5 text-gray-950 dark:text-white">{{ $item->description }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-end tabular-nums text-gray-500 dark:text-gray-400">{{ $item->quantity ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-end font-medium tabular-nums text-success-600 dark:text-success-400">
                            {{ $item->earnings ? $money($item->earnings) : '' }}
                        </td>
                        <td class="whitespace-nowrap px-3 py-1.5 text-end font-medium tabular-nums text-danger-600 dark:text-danger-400">
                            {{ $item->deductions ? $money($item->deductions) : '' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold dark:bg-white/5">
                <tr>
                    <td colspan="3" class="px-3 py-2 text-gray-700 dark:text-gray-200">Totais</td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-success-600 dark:text-success-400">{{ $money($payslip->gross_amount) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-danger-600 dark:text-danger-400">{{ $money($payslip->deductions_amount) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="px-3 py-2 text-gray-700 dark:text-gray-200">Líquido</td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-950 dark:text-white">{{ $money($payslip->net_amount) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="px-3 py-2 text-gray-700 dark:text-gray-200">
                        Valor lançado na transação
                        @if ($difference !== 0)
                            <span class="font-normal text-warning-600 dark:text-warning-400">
                                (diferença de {{ $money(abs($difference)) }})
                            </span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-end tabular-nums text-gray-950 dark:text-white">{{ $money((int) $transaction->amount) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
