<?php

namespace App\Console\Commands;

use App\Services\Transactions\InstallmentBackfillService;
use Illuminate\Console\Command;

class BackfillTransactionInstallments extends Command
{
    protected $signature = 'transactions:backfill-installments {--description= : Atualiza só transações cuja descrição contém este texto}';

    protected $description = 'Numera parcelas já salvas com o título Transação X de Y';

    public function handle(InstallmentBackfillService $installmentBackfillService): int
    {
        $description = $this->option('description');
        $description = is_string($description) && trim($description) !== '' ? trim($description) : null;

        $updated = $installmentBackfillService->backfill($description);

        $this->info("Atualizadas {$updated} transações parceladas.");

        return self::SUCCESS;
    }
}
