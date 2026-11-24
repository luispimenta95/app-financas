<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Actions\UploadCreditCardStatementAction;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Transaction;
use App\Support\PlanFeature;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadCreditCardStatement extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transactions.upload-credit-card-statement';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Enviar fatura';

    public mixed $pdf = null;

    public ?string $statement_password = null;

    public static function canAccess(array $parameters = []): bool
    {
        if (! financas_user_can(PlanFeature::CREDIT_CARD_PDF)) {
            return false;
        }

        $record = $parameters['record'] ?? null;

        if (!$record instanceof Transaction) {
            return false;
        }

        return $record->isCreditCardBill();
    }

    public function mount(int|string $record): void
    {
        abort_unless(financas_user_can(PlanFeature::CREDIT_CARD_PDF), 403);

        $this->record = $this->resolveRecord($record);

        abort_unless($this->record->isCreditCardBill(), 403);
    }

    public function submit(): void
    {
        $this->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'statement_password' => ['nullable', 'string', 'max:255'],
        ], [
            'pdf.required' => 'Selecione o PDF da fatura.',
            'pdf.mimes' => 'O arquivo precisa ser um PDF.',
        ]);

        $file = $this->pdf instanceof TemporaryUploadedFile
            ? $this->pdf
            : (is_array($this->pdf) ? collect($this->pdf)->first() : null);

        if (!$file instanceof TemporaryUploadedFile) {
            $this->addError('pdf', 'Selecione o PDF da fatura.');

            return;
        }

        $path = $file->store('transactions', \App\Support\PrivateAttachments::disk());


        $result = UploadCreditCardStatementAction::handle($this->record, [
            'pdf' => $path,
            'statement_password' => $this->statement_password,
            'original_name' => $file->getClientOriginalName(),
        ]);

        if (!($result['imported'] ?? false)) {
            return;
        }

        session()->flash('expand_credit_card_statement', (string) $this->record->getKey());

        $this->redirect(TransactionResource::getUrl('index'));
    }

    public function getTitle(): string|Htmlable
    {
        return 'Enviar fatura PDF';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->displayTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Envie o PDF disponibilizado pelo banco.';
    }

    public function getBreadcrumb(): string
    {
        return 'Enviar fatura';
    }
}
