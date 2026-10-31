<x-filament-panels::page>
    @include('filament.resources.transactions.upload-pdf-form', [
        'passwordHelp' => 'Só preencha se o PDF do contracheque tiver senha.',
        'fileLabel' => 'PDF do contracheque',
        'submitLabel' => 'Ler contracheque',
    ])
</x-filament-panels::page>
