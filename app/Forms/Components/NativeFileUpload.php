<?php

namespace App\Forms\Components;

use Filament\Forms\Components\BaseFileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class NativeFileUpload extends BaseFileUpload
{
    protected string $view = 'forms.components.native-file-upload';

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateUpdated(static function (NativeFileUpload $component, mixed $state): void {
            if ($state instanceof TemporaryUploadedFile) {
                $component->state([(string) str()->uuid() => $state]);
            }
        });
    }
}
