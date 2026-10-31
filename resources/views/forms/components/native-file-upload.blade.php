@php
    use Illuminate\Support\Arr;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $acceptedFileTypes = $getAcceptedFileTypes() ?? [];
    $files = collect(Arr::wrap($getState()))
        ->filter(fn (mixed $file): bool => filled($file));
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="fi-fo-native-upload space-y-2">
        @if ($files->isNotEmpty())
            <ul class="space-y-2">
                @foreach ($files as $fileKey => $file)
                    @php
                        $isTemporary = $file instanceof TemporaryUploadedFile;
                        $name = $isTemporary ? $file->getClientOriginalName() : basename((string) $file);
                        $url = $isTemporary ? null : $field->getDisk()->url((string) $file);
                    @endphp
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                        <span class="min-w-0 truncate text-gray-950 dark:text-white">{{ $name }}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            @if ($url)
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    class="text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    Abrir
                                </a>
                            @endif
                            @unless ($isDisabled)
                                <button
                                    type="button"
                                    class="text-danger-600 hover:underline dark:text-danger-400"
                                    wire:click="$set('{{ $statePath }}', [])"
                                >
                                    Remover
                                </button>
                            @endunless
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @unless ($isDisabled)
            <input
                type="file"
                @if (count($acceptedFileTypes))
                    accept="{{ implode(',', $acceptedFileTypes) }}"
                @endif
                class="block w-full text-sm text-gray-600 file:me-3 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-500 dark:text-gray-400"
                x-on:change="
                    const file = $event.target.files[0]
                    if (! file) return
                    const key = crypto.randomUUID()
                    $wire.upload('{{ $statePath }}.' + key, file)
                    $event.target.value = ''
                "
            >
        @endunless
    </div>
</x-dynamic-component>
