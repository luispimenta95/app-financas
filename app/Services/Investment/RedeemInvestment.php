<?php

namespace App\Services\Investment;

use App\Enums\InvestmentType;
use App\Enums\TransactionType;
use App\Models\Investment;
use App\Models\Transactions\Account;
use App\Models\Transactions\Category;
use App\Models\Transactions\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemInvestment
{
    /**
     * Baixa o valor do investimento e lança o resgate como receita
     * na categoria Investimentos, para entrar no saldo do mês.
     */
    public function handle(
        Investment $investment,
        int $amount,
        string $date,
        ?string $accountId = null,
        ?string $description = null,
    ): Transaction {
        if ($investment->type !== InvestmentType::FixedIncome) {
            throw ValidationException::withMessages([
                'amount' => 'Apenas investimentos de renda fixa podem ser resgatados.',
            ]);
        }

        $accountId ??= Account::query()
            ->where('user_id', $investment->user_id)
            ->value('id');

        if ($accountId === null) {
            throw ValidationException::withMessages([
                'amount' => 'Cadastre uma conta antes de registrar o resgate.',
            ]);
        }

        return DB::transaction(function () use ($investment, $amount, $date, $accountId, $description): Transaction {
            $locked = Investment::query()
                ->whereKey($investment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($amount <= 0 || $amount > $locked->amount) {
                throw ValidationException::withMessages([
                    'amount' => 'O valor do resgate deve ser maior que zero e no máximo o saldo do investimento.',
                ]);
            }

            $locked->update(['amount' => $locked->amount - $amount]);
            $investment->setRawAttributes($locked->getAttributes(), sync: true);

            $category = Category::query()
                ->where('user_id', $locked->user_id)
                ->where('slug', Category::INVESTMENTS_SLUG)
                ->firstOrFail();

            $day = Carbon::parse($date)->toDateString();

            return Transaction::query()->create([
                'user_id' => $locked->user_id,
                'account_id' => $accountId,
                'category_id' => $category->id,
                'transaction_type' => TransactionType::Income,
                'amount' => $amount,
                'description' => filled($description) ? $description : "Resgate {$locked->name}",
                'finished' => true,
                'recurrence' => false,
                'date' => $day,
                'due_date' => $day,
                'payment_date' => $day,
            ]);
        });
    }
}
