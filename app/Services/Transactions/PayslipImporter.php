<?php

namespace App\Services\Transactions;

use App\Exceptions\EncryptedCreditCardStatementException;
use App\Models\Transactions\Payslip;
use App\Models\Transactions\PayslipItem;
use App\Models\Transactions\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PayslipImporter
{
    public function __construct(
        private readonly PayslipParser $parser,
        private readonly CreditCardStatementTextExtractor $extractor,
    ) {}

    /**
     * @return array{imported: bool, count: int, net_amount: int, message: ?string}
     */
    public function import(Transaction $transaction, string $relativePath, ?string $password = null, ?string $originalName = null): array
    {
        $failed = fn (string $message): array => ['imported' => false, 'count' => 0, 'net_amount' => 0, 'message' => $message];

        if (!$transaction->isPayslip()) {
            return $failed('Esta transação não é da categoria Salário.');
        }

        $path = Storage::disk('public')->exists($relativePath)
            ? Storage::disk('public')->path($relativePath)
            : null;

        if ($path === null) {
            return $failed('Arquivo do contracheque não encontrado.');
        }

        try {
            $data = $this->parser->parseText($this->extractor->extract($path, $password));
        } catch (EncryptedCreditCardStatementException) {
            return $failed(filled($password)
                ? 'A senha do PDF está incorreta.'
                : 'O PDF está protegido. Informe a senha.');
        } catch (Throwable $exception) {
            report($exception);

            return $failed('Não foi possível ler o PDF do contracheque.');
        }

        if ($data === null) {
            return $failed('Não encontrei proventos e líquido no PDF. Confira se o arquivo é o demonstrativo de pagamento.');
        }

        DB::transaction(function () use ($transaction, $data, $relativePath, $originalName): void {
            $payslip = Payslip::query()->updateOrCreate(
                ['transaction_id' => $transaction->id],
                [
                    'user_id' => $transaction->user_id,
                    'kind' => $data['kind'],
                    'reference_month' => $data['reference_month'],
                    'credit_date' => $data['credit_date'],
                    'base_salary' => $data['base_salary'],
                    'gross_amount' => $data['gross_amount'],
                    'deductions_amount' => $data['deductions_amount'],
                    'net_amount' => $data['net_amount'],
                    'file_path' => $relativePath,
                    'original_name' => $originalName ?? basename($relativePath),
                    'imported_at' => now(),
                ],
            );

            $payslip->items()->delete();

            foreach ($data['items'] as $item) {
                PayslipItem::query()->create([
                    ...$item,
                    'user_id' => $transaction->user_id,
                    'payslip_id' => $payslip->id,
                ]);
            }

            $transaction->forceFill(['amount' => $data['net_amount']])->save();
        });

        return [
            'imported' => true,
            'count' => count($data['items']),
            'net_amount' => $data['net_amount'],
            'message' => null,
        ];
    }
}
