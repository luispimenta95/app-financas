<div class="fi-wi-widget rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-slate-900 dark:ring-blue-500/10">
    <div class="mb-5 flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Principais categorias
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                Despesas por categoria no período
            </p>
        </div>
    </div>

    @if (count($categories) === 0)
        <p class="text-sm text-gray-500 dark:text-slate-400">
            Nenhuma despesa encontrada no período.
        </p>
    @else
        <div class="dash-category-bar mb-5">
            @foreach ($categories as $category)
                <div
                    class="dash-category-bar-segment"
                    style="width: {{ max($category['share'], 2) }}%; background-color: {{ $category['color'] }};"
                    title="{{ $category['name'] }}"
                ></div>
            @endforeach
        </div>

        <ul class="space-y-3">
            @foreach ($categories as $category)
                <li class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            style="background-color: {{ $category['color'] }};"
                        ></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $category['name'] }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-slate-400">
                                {{ number_format($category['percent'], 1, ',', '.') }}% do total
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $category['formatted'] }}
                        </p>
                        <span
                            class="inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-medium"
                            style="background-color: {{ $category['color'] }}22; color: {{ $category['color'] }};"
                        >
                            {{ number_format($category['percent'], 1, ',', '.') }}%
                        </span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
