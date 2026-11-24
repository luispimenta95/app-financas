<?php

namespace App\Services\Transactions;

use App\Enums\TransactionType;
use App\Exceptions\EncryptedCreditCardStatementException;
use App\Models\Transactions\Account;
use App\Models\Transactions\Boleto;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use App\Models\User;
use App\Support\PrivateAttachments;
use Illuminate\Support\Facades\DB;
use Throwable;


class BoletoImporter
{
    public function __construct(
        private readonly BoletoParser $parser,
        private readonly CreditCardStatementTextExtractor $extractor,
    ) {}

    /**
     * @return array{
     *     imported: bool,
     *     created: bool,
     *     transaction_id: string|null,
     *     amount: int,
     *     description: string|null,
     *     message: string|null
     * }
     */
    public function import(
        User $user,
        string $relativePath,
        ?string $password = null,
        ?string $originalName = null,
        ?string $accountId = null,
        ?string $categoryId = null,
        ?Transaction $transaction = null,
    ): array {
        $failed = fn (string $message): array => [
            'imported' => false,
            'created' => false,
            'transaction_id' => null,
            'amount' => 0,
            'description' => null,
            'message' => $message,
        ];

        $path = PrivateAttachments::absolutePath($relativePath);

        if ($path === null) {
            return $failed('Arquivo do boleto não encontrado.');
        }

        try {
            $data = $this->parser->parseText($this->extractor->extract($path, $password));
        } catch (EncryptedCreditCardStatementException) {
            return $failed(filled($password)
                ? 'A senha do PDF está incorreta.'
                : 'O PDF está protegido. Informe a senha.');
        } catch (Throwable $exception) {
            report($exception);

            return $failed('Não foi possível ler o PDF do boleto.');
        }

        if ($data === null) {
            return $failed('Não encontrei a linha digitável no PDF. Confira se o arquivo é um boleto legível.');
        }

        if ($data['amount'] <= 0) {
            return $failed('Li o boleto, mas o valor veio zerado. Confira o PDF.');
        }

        $account = $this->resolveAccount($user, $accountId);

        if ($account === null) {
            return $failed('Nenhuma conta encontrada. Cadastre uma conta antes de importar o boleto.');
        }

        $category = $this->resolveCategory($user, $categoryId);

        $created = false;

        $resultTransaction = DB::transaction(function () use (
            $user,
            $data,
            $relativePath,
            $originalName,
            $account,
            $category,
            $transaction,
            &$created,
        ): Transaction {
            $existingByLine = Boleto::query()
                ->where('user_id', $user->id)
                ->where('digitable_line', $data['digitable_line'])
                ->first();

            if ($existingByLine !== null) {
                $transaction = $existingByLine->transaction;
            }

            if ($transaction === null) {
                $created = true;
                $transaction = Transaction::query()->create([
                    'user_id' => $user->id,
                    'transaction_type' => TransactionType::Expense,
                    'amount' => $data['amount'],
                    'date' => $data['due_date']?->toDateString() ?? now()->toDateString(),
                    'due_date' => $data['due_date']?->toDateString(),
                    'finished' => false,
                    'recurrence' => false,
                    'is_installment' => false,
                    'description' => $data['description'],
                    'account_id' => $account->id,
                    'category_id' => $category?->id,
                    'attachment' => $relativePath,
                ]);
            } else {
                // Ao atualizar um lançamento existente (ex.: Conta de Água), preserva a descrição.
                $transaction->forceFill([
                    'amount' => $data['amount'],
                    'date' => $data['due_date']?->toDateString() ?? $transaction->date?->toDateString() ?? now()->toDateString(),
                    'due_date' => $data['due_date']?->toDateString() ?? $transaction->due_date?->toDateString(),
                    'description' => filled($transaction->description)
                        ? $transaction->description
                        : $data['description'],
                    'account_id' => $account->id,
                    'category_id' => $category?->id ?? $transaction->category_id,
                    'attachment' => $relativePath,
                    'transaction_type' => TransactionType::Expense,
                ])->save();
            }

            $boleto = $existingByLine ?? $transaction->boleto;

            $payload = [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'digitable_line' => $data['digitable_line'],
                'barcode' => $data['barcode'],
                'bank_code' => $data['bank_code'],
                'beneficiary' => $data['beneficiary'],
                'payer' => $data['payer'],
                'document_number' => $data['document_number'],
                'our_number' => $data['our_number'],
                'amount' => $data['amount'],
                'due_date' => $data['due_date'],
                'file_path' => $relativePath,
                'original_name' => $originalName ?? basename($relativePath),
                'imported_at' => now(),
            ];

            if ($boleto !== null) {
                $boleto->fill($payload)->save();
            } else {
                Boleto::query()->create($payload);
            }

            return $transaction->fresh(['boleto']);
        });

        return [
            'imported' => true,
            'created' => $created,
            'transaction_id' => $resultTransaction->id,
            'amount' => $data['amount'],
            'description' => $data['description'],
            'message' => null,
        ];
    }

    private function resolveAccount(User $user, ?string $accountId): ?Account
    {
        if (filled($accountId)) {
            return Account::query()
                ->where('user_id', $user->id)
                ->whereKey($accountId)
                ->first();
        }

        return Account::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->first();
    }

    private function resolveCategory(User $user, ?string $categoryId): ?Category
    {
        if (filled($categoryId)) {
            return Category::query()
                ->where('user_id', $user->id)
                ->whereKey($categoryId)
                ->first();
        }

        return Category::query()
            ->where('user_id', $user->id)
            ->where('slug', 'outros')
            ->first()
            ?? Category::query()
                ->where('user_id', $user->id)
                ->where('slug', '!=', Category::INVESTMENTS_SLUG)
                ->orderBy('name')
                ->first();
    }
}
