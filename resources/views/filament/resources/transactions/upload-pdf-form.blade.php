    <form wire:submit="submit" class="grid max-w-xl gap-6">
        <div class="grid gap-2">
            <label for="statement_password" class="text-sm font-medium text-gray-950 dark:text-white">
                Senha do PDF
            </label>
            <input
                id="statement_password"
                type="password"
                wire:model="statement_password"
                autocomplete="off"
                class="block w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
            >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $passwordHelp }}
            </p>
        </div>

        <div class="grid gap-2">
            <label for="pdf" class="text-sm font-medium text-gray-950 dark:text-white">
                {{ $fileLabel }}
                <span class="text-danger-600">*</span>
            </label>
            <input
                id="pdf"
                type="file"
                wire:model="pdf"
                accept=".pdf,application/pdf"
                class="block w-full text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-500 dark:text-gray-400"
            >
            <p wire:loading wire:target="pdf" class="text-sm text-primary-600">
                Enviando arquivo...
            </p>
            @if ($pdf)
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    Arquivo pronto. Clique em {{ $submitLabel }}.
                </p>
            @endif
            @error('pdf')
                <p class="text-sm text-danger-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="pdf,submit">
                {{ $submitLabel }}
            </x-filament::button>

            <x-filament::button
                color="gray"
                tag="a"
                :href="\App\Filament\Resources\Transactions\TransactionResource::getUrl('index')"
            >
                Voltar
            </x-filament::button>
        </div>
    </form>
