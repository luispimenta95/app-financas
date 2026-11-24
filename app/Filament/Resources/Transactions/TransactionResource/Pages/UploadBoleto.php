<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\BoletoImporter;
use App\Support\PlanFeature;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadBoleto extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transactions.upload-boleto';

    protected static bool $shouldRegisterNavigation = false;

    public mixed $pdf = null;

    public ?string $statement_password = null;

    public ?string $transaction_id = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(array $parameters = []): bool
    {
        return financas_user_can(PlanFeature::BOLETO_PDF);
    }

    public function mount(?string $record = null): void
    {
        abort_unless(financas_user_can(PlanFeature::BOLETO_PDF), 403);

        $user = auth()->user();

        $defaultCategoryId = Category::query()
            ->where('user_id', $user->id)
            ->where('slug', 'outros')
            ->value('id');

        if (filled($record)) {
            $transaction = Transaction::query()->whereKey($record)->first();

            abort_unless($transaction !== null, 404);
            abort_unless($transaction->isExpense(), 403, 'Boleto só pode ser vinculado a despesas.');

            $this->transaction_id = $transaction->id;
            $defaultCategoryId = $transaction->category_id ?: $defaultCategoryId;
        }

        $this->form->fill([
            'category_id' => $defaultCategoryId,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Categoria')
                    ->options(fn (): array => Category::query()
                        ->where('user_id', auth()->id())
                        ->where('slug', '!=', Category::INVESTMENTS_SLUG)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->native(false)
                    ->required()
                    ->helperText('Escolha a categoria do lançamento.'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $formState = $this->form->getState();

        $this->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'statement_password' => ['nullable', 'string', 'max:255'],
        ], [
            'pdf.required' => 'Selecione o PDF do boleto.',
            'pdf.mimes' => 'O arquivo precisa ser um PDF.',
        ]);

        if (! $this->pdf instanceof TemporaryUploadedFile) {
            $this->addError('pdf', 'Selecione o PDF do boleto.');

            return;
        }

        $accountId = Account::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->value('id');

        if (blank($accountId)) {
            Notification::make()
                ->title('Nenhuma conta encontrada')
                ->body('Cadastre uma conta antes de importar o boleto.')
                ->danger()
                ->send();

            return;
        }

        $path = $this->pdf->store('boletos', \App\Support\PrivateAttachments::disk());


        $transaction = filled($this->transaction_id)
            ? Transaction::query()->whereKey($this->transaction_id)->first()
            : null;

        $result = app(BoletoImporter::class)->import(
            user: auth()->user(),
            relativePath: $path,
            password: $this->statement_password,
            originalName: $this->pdf->getClientOriginalName(),
            accountId: $accountId,
            categoryId: $formState['category_id'] ?? null,
            transaction: $transaction,
        );

        if (! $result['imported']) {
            Notification::make()
                ->title('Não li o boleto')
                ->body($result['message'])
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title($result['created'] ? 'Boleto cadastrado' : 'Boleto atualizado')
            ->body(($result['description'] ?? 'Lançamento').' · R$ '.number_format($result['amount'] / 100, 2, ',', '.'))
            ->success()
            ->send();

        $this->redirect(TransactionResource::getUrl('index'));
    }

    public function getTitle(): string|Htmlable
    {
        return filled($this->transaction_id) ? 'Atualizar boleto PDF' : 'Enviar boleto PDF';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Envie o PDF do boleto. Valor, vencimento e beneficiário serão lidos automaticamente. Se a linha digitável já existir, o lançamento é atualizado.';
    }

    public function getBreadcrumb(): string
    {
        return 'Enviar boleto';
    }
}
