<?php

namespace App\Filament\Resources\Transactions\TransactionResource\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Services\Transactions\BoletoImporter;
use App\Support\PlanFeature;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UploadBoleto extends Page
{
    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transactions.upload-boleto';

    protected static bool $shouldRegisterNavigation = false;

    public mixed $pdf = null;

    public ?string $statement_password = null;

    public ?string $account_id = null;

    public ?string $category_id = null;

    public ?string $transaction_id = null;

    public static function canAccess(array $parameters = []): bool
    {
        return financas_user_can(PlanFeature::BOLETO_PDF);
    }

    public function mount(?string $record = null): void
    {
        abort_unless(financas_user_can(PlanFeature::BOLETO_PDF), 403);

        $user = auth()->user();

        $this->account_id = Account::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->value('id');

        $this->category_id = Category::query()
            ->where('user_id', $user->id)
            ->where('slug', 'outros')
            ->value('id');

        if (filled($record)) {
            $transaction = Transaction::query()->whereKey($record)->first();

            abort_unless($transaction !== null, 404);
            abort_unless($transaction->isExpense(), 403, 'Boleto só pode ser vinculado a despesas.');

            $this->transaction_id = $transaction->id;
            $this->account_id = $transaction->account_id ?: $this->account_id;
            $this->category_id = $transaction->category_id ?: $this->category_id;
        }
    }

    public function submit(): void
    {
        $this->validate([
            'pdf' => ['required'],
            'account_id' => ['required', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'statement_password' => ['nullable', 'string', 'max:255'],
        ], [
            'pdf.required' => 'Selecione o PDF do boleto.',
            'account_id.required' => 'Selecione a conta.',
        ]);

        if (! $this->pdf instanceof TemporaryUploadedFile) {
            $this->addError('pdf', 'Selecione o PDF do boleto.');

            return;
        }

        $path = $this->pdf->store('boletos', 'public');

        $transaction = filled($this->transaction_id)
            ? Transaction::query()->whereKey($this->transaction_id)->first()
            : null;

        $result = app(BoletoImporter::class)->import(
            user: auth()->user(),
            relativePath: $path,
            password: $this->statement_password,
            originalName: $this->pdf->getClientOriginalName(),
            accountId: $this->account_id,
            categoryId: $this->category_id,
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

    /**
     * @return array<string, string>
     */
    public function accountOptions(): array
    {
        return Account::query()
            ->where('user_id', auth()->id())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function categoryOptions(): array
    {
        return Category::query()
            ->where('user_id', auth()->id())
            ->where('slug', '!=', Category::INVESTMENTS_SLUG)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
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
