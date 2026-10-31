<?php

namespace App\Services\Transactions;

use App\Exceptions\EncryptedCreditCardStatementException;
use App\Models\Transactions\CreditCardPurchase;
use App\Models\Transactions\CreditCardStatement;
use App\Models\Transactions\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreditCardStatementImporter
{
    public function __construct(
        private readonly CreditCardStatementParser $parser,
        private readonly CreditCardStatementTextExtractor $extractor,
    ) {}

    /**
     * @return array{imported: bool, count: int, bill_amount: ?int, message: ?string}
     */
    public function import(Transaction $transaction, ?string $password = null, ?string $originalName = null): array
    {
        $transaction->loadMissing('category');

        if (!$transaction->isCreditCardBill()) {
            return ['imported' => false, 'count' => 0, 'bill_amount' => null, 'message' => null];
        }

        $relativePath = $this->relativeAttachment($transaction->attachment);
        $path = $this->resolveAttachmentPath($relativePath);

        if ($path === null) {
            return ['imported' => false, 'count' => 0, 'bill_amount' => null, 'message' => null];
        }

        try {
            $text = $this->extractor->extract($path, $password);
            $items = $this->parser->parseText(
                $text,
                $transaction->due_date ?? $transaction->date ?? now()
            );
            $billAmount = $this->parser->parseBillTotal($text);
        } catch (EncryptedCreditCardStatementException $exception) {
            return ['imported' => false, 'count' => 0, 'bill_amount' => null, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            report($exception);

            return ['imported' => false, 'count' => 0, 'bill_amount' => null, 'message' => 'Não foi possível ler o PDF da fatura.'];
        }

        if ($items === []) {
            return [
                'imported' => false,
                'count' => 0,
                'bill_amount' => null,
                'message' => 'Não encontrei lançamentos no PDF. Confira se o arquivo é a fatura do banco.',
            ];
        }

        DB::transaction(function () use ($transaction, $items, $billAmount, $relativePath, $originalName): void {
            $statement = CreditCardStatement::query()->updateOrCreate(
                ['transaction_id' => $transaction->id],
                [
                    'user_id' => $transaction->user_id,
                    'file_path' => (string) $relativePath,
                    'original_name' => $originalName ?? basename((string) $relativePath),
                    'items_count' => count($items),
                    'total_amount' => array_sum(array_column($items, 'amount')),
                    'bill_amount' => $billAmount,
                    'imported_at' => now(),
                ],
            );

            CreditCardPurchase::query()
                ->where('transaction_id', $transaction->id)
                ->delete();

            foreach ($items as $index => $item) {
                CreditCardPurchase::query()->create([
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transaction->id,
                    'credit_card_statement_id' => $statement->id,
                    'occurred_on' => $item['occurred_on'] ?? null,
                    'description' => $item['description'],
                    'amount' => $item['amount'],
                    'installment_label' => $item['installment_label'] ?? null,
                    'sort_order' => $item['sort_order'] ?? $index,
                ]);
            }

            if ($billAmount !== null) {
                $transaction->forceFill(['amount' => $billAmount])->save();
            }
        });

        return [
            'imported' => true,
            'count' => count($items),
            'bill_amount' => $billAmount,
            'message' => null,
        ];
    }

    private function relativeAttachment(mixed $attachment): ?string
    {
        $relative = is_array($attachment) ? ($attachment[0] ?? null) : $attachment;

        if (!is_string($relative) || trim($relative) === '') {
            return null;
        }

        return $relative;
    }

    private function resolveAttachmentPath(?string $relative): ?string
    {
        if ($relative === null) {
            return null;
        }

        if (is_file($relative)) {
            return $relative;
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                return Storage::disk($disk)->path($relative);
            }
        }

        return null;
    }
}
