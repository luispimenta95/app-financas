<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\PayslipImporter;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadPayslip extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transactions.upload-payslip';

    protected static bool $shouldRegisterNavigation = false;

    public mixed $pdf = null;

    public ?string $statement_password = null;

    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof Transaction && $record->isPayslip();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless($this->record->isPayslip(), 403);
    }

    public function submit(): void
    {
        $this->validate([
            'pdf' => ['required'],
            'statement_password' => ['nullable', 'string', 'max:255'],
        ], [
            'pdf.required' => 'Selecione o PDF do contracheque.',
        ]);

        if (!$this->pdf instanceof TemporaryUploadedFile) {
            $this->addError('pdf', 'Selecione o PDF do contracheque.');

            return;
        }

        $path = $this->pdf->store('payslips', 'public');

        $result = app(PayslipImporter::class)->import(
            $this->record,
            $path,
            $this->statement_password,
            $this->pdf->getClientOriginalName(),
        );

        if (!$result['imported']) {
            Notification::make()
                ->title('Não li o contracheque')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Contracheque lido')
            ->body($result['count'].' itens · líquido R$ '.number_format($result['net_amount'] / 100, 2, ',', '.'))
            ->success()
            ->send();

        session()->flash('expand_credit_card_statement', (string) $this->record->getKey());

        $this->redirect(TransactionResource::getUrl('index'));
    }

    public function getTitle(): string|Htmlable
    {
        return 'Enviar contracheque PDF';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getRecord()->displayTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Envie o demonstrativo de pagamento em PDF.';
    }

    public function getBreadcrumb(): string
    {
        return 'Enviar contracheque';
    }
}
