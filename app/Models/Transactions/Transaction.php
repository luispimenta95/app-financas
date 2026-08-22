<?php

namespace App\Models\Transactions;

use App\Enums\TransactionType;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use App\Services\Transactions\InstallmentTitle;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'is_installment',
        'installment_number',
        'installment_total',
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
            'is_installment' => 'boolean',
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

    protected function installmentNumber(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?int => $value === null ? null : (int) $value,
            set: fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value,
        );
    }

    protected function installmentTotal(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?int => $value === null ? null : (int) $value,
            set: fn (mixed $value): ?int => $value === null || $value === '' ? null : (int) $value,
        );
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
     * Pesquisa usada na listagem: descrição, categoria ou conta.
     */
    public function scopeSearchTerm(Builder $query, mixed $search): Builder
    {
        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        return $query->where(function (Builder $searchQuery) use ($like): void {
            $searchQuery
                ->where('description', 'like', $like)
                ->orWhereHas('category', function (Builder $categoryQuery) use ($like): void {
                    $categoryQuery->where('name', 'like', $like);
                })
                ->orWhereHas('account', function (Builder $accountQuery) use ($like): void {
                    $accountQuery->where('name', 'like', $like);
                });
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

    public static function dueDateExpression(): string
    {
        return 'COALESCE(due_date, date)';
    }

    public function displayDueDate(): ?Carbon
    {
        return $this->due_date ?? $this->date;
    }

    public function isInstallment(): bool
    {
        return (bool) $this->is_installment
            && (int) $this->installment_total > 1
            && (int) $this->installment_number > 0;
    }

    public function installmentLabel(): ?string
    {
        if (!$this->isInstallment()) {
            return null;
        }

        return InstallmentTitle::label(
            (int) $this->installment_number,
            (int) $this->installment_total,
        );
    }

    public function displayTitle(): string
    {
        $number = (int) $this->installment_number;
        $total = (int) $this->installment_total;

        $title = ($number > 0 && $total > 1)
            ? InstallmentTitle::format($this->description, $number, $total)
            : (string) $this->description;

        return InstallmentTitle::withoutLegacyTransactionSuffix($title);
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
