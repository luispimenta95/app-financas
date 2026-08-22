<?php

namespace App\Console\Commands;

use App\Services\Transactions\InstallmentBackfillService;
use Illuminate\Console\Command;

class BackfillTransactionInstallments extends Command
{
    protected $signature = 'transactions:backfill-installments';

    protected $description = 'Numera parcelas já salvas com o título Parcela X de Y';

    public function handle(InstallmentBackfillService $installmentBackfillService): int
    {
        $updated = $installmentBackfillService->backfill();

        $this->info("Atualizadas {$updated} transações parceladas.");

        return self::SUCCESS;
    }
}
