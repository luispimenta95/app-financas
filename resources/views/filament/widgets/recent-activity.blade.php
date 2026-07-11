<div class="fi-wi-widget rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-slate-900 dark:ring-blue-500/10">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Atividade recente
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                Últimas movimentações registradas
            </p>
        </div>
        <a
            href="{{ \App\Filament\Resources\Transactions\TransactionResource::getUrl('index') }}"
            class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
        >
            Ver todas →
        </a>
    </div>

    @if ($activities->isEmpty())
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Nenhuma atividade recente.
        </p>
    @else
        <ul class="space-y-4">
            @foreach ($activities as $activity)
                <li class="dash-activity-item">
                    <span class="dash-activity-dot" style="background-color: {{ $activity['color'] }};"></span>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $activity['title'] }}
                            </p>
                            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-slate-400">
                                {{ $activity['meta'] }}
                            </p>
                            <p class="mt-1 text-xs text-blue-600/80 dark:text-blue-300/80">
                                {{ $activity['time'] }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $activity['amount'] }}
                            </p>
                            <span @class([
                                'mt-1 inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-medium',
                                'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' => $activity['finished'],
                                'bg-amber-500/10 text-amber-600 dark:text-amber-400' => ! $activity['finished'],
                            ])>
                                {{ $activity['finished'] ? 'Concluída' : 'Pendente' }}
                            </span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
