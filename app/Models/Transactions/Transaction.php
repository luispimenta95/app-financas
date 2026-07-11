<?php

namespace App\Models\Transactions;

use App\Enums\TransactionType;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[ScopedBy([TenantScope::class])]
class Transaction extends Model
{
    use BelongsToUser, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_type',
        'amount',
        'date',
        'finished',
        'recurrence',
        'due_date',
        'payment_date',
        'description',
        'account_id',
        'category_id',
        'attachment',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'finished' => 'boolean',
            'recurrence' => 'boolean',
            'date' => 'date',
            'due_date' => 'date',
            'payment_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeWithoutInvestments(Builder $query): Builder
    {
        return $query->whereHas('category', function (Builder $categoryQuery): void {
            $categoryQuery->where('slug', '!=', Category::INVESTMENTS_SLUG);
        });
    }

    public function scopeOnlyInvestments(Builder $query): Builder
    {
        return $query->whereHas('category', function (Builder $categoryQuery): void {
            $categoryQuery->where('slug', Category::INVESTMENTS_SLUG);
        });
    }

    /**
     * Lista registros pelo mês de vencimento (due_date, com fallback para date).
     */
    public function scopeForDuePeriod(Builder $query, mixed $startDate, mixed $endDate): Builder
    {
        $start = static::normalizePeriodBoundary($startDate);
        $end = static::normalizePeriodBoundary($endDate);

        if (!$start || !$end) {
            return $query;
        }

        return $query->whereRaw(
            'COALESCE(due_date, date) BETWEEN ? AND ?',
            [$start, $end]
        );
    }

    /**
     * Totais de entradas/saídas pelo mês em que o valor foi pago/recebido.
     * Finalizadas usam payment_date (fallback due_date/date).
     * Em modo projeção, pendentes entram pelo vencimento.
     */
    public function scopeForCashFlowPeriod(Builder $query, mixed $startDate, mixed $endDate, bool $preview = false): Builder
    {
        $start = static::normalizePeriodBoundary($startDate);
        $end = static::normalizePeriodBoundary($endDate);

        if (!$start || !$end) {
            if (!$preview) {
                $query->where('finished', true);
            }

            return $query;
        }

        if ($preview) {
            return $query->where(function (Builder $periodQuery) use ($start, $end): void {
                $periodQuery
                    ->where(function (Builder $finishedQuery) use ($start, $end): void {
                        $finishedQuery
                            ->where('finished', true)
                            ->whereRaw(
                                'COALESCE(payment_date, due_date, date) BETWEEN ? AND ?',
                                [$start, $end]
                            );
                    })
                    ->orWhere(function (Builder $pendingQuery) use ($start, $end): void {
                        $pendingQuery
                            ->where('finished', false)
                            ->whereRaw(
                                'COALESCE(due_date, date) BETWEEN ? AND ?',
                                [$start, $end]
                            );
                    });
            });
        }

        return $query
            ->where('finished', true)
            ->whereRaw(
                'COALESCE(payment_date, due_date, date) BETWEEN ? AND ?',
                [$start, $end]
            );
    }

    public static function cashFlowDateExpression(): string
    {
        return 'COALESCE(payment_date, due_date, date)';
    }

    private static function normalizePeriodBoundary(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
