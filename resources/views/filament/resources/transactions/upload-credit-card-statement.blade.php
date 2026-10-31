<x-filament-panels::page>
    @include('filament.resources.transactions.upload-pdf-form', [
        'passwordHelp' => 'Se o banco proteger o PDF, informe a senha (no Itaú costuma ser o CPF).',
        'fileLabel' => 'PDF da fatura',
        'submitLabel' => 'Ler fatura',
    ])
</x-filament-panels::page>
