<?php

namespace App\Models;

use App\Enums\InvestmentRateType;
use App\Enums\InvestmentType;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

#[ScopedBy([TenantScope::class])]
class Investment extends Model
{
    use BelongsToUser, HasFactory, HasUuids;

    public const VARIABLE_INCOME_NAME = 'Renda Variável';

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'institution',
        'amount',
        'application_date',
        'rate_type',
        'interest_rate',
        'daily_liquidity',
        'maturity_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvestmentType::class,
            'amount' => 'integer',
            'application_date' => 'date',
            'rate_type' => InvestmentRateType::class,
            'interest_rate' => 'decimal:2',
            'daily_liquidity' => 'boolean',
            'maturity_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Investment $investment): void {
            if ($investment->type !== InvestmentType::VariableIncome) {
                return;
            }

            $alreadyExists = static::query()
                ->where('type', InvestmentType::VariableIncome)
                ->when(
                    filled($investment->user_id),
                    fn (Builder $query) => $query->where('user_id', $investment->user_id),
                )
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'amount' => 'Já existe um controle de renda variável. Atualize o valor existente.',
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public static function variableIncomeAttributes(int $amount): array
    {
        return [
            'type' => InvestmentType::VariableIncome,
            'name' => self::VARIABLE_INCOME_NAME,
            'institution' => null,
            'amount' => $amount,
            'application_date' => null,
            'rate_type' => InvestmentRateType::Cdi,
            'interest_rate' => null,
            'daily_liquidity' => true,
            'maturity_date' => null,
        ];
    }

    public function formattedInterestRate(): string
    {
        if ($this->type === InvestmentType::VariableIncome || blank($this->interest_rate)) {
            return '—';
        }

        return ($this->rate_type ?? InvestmentRateType::Cdi)->formatRate($this->interest_rate);
    }

    public function scopeOfType(Builder $query, InvestmentType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeFixedIncome(Builder $query): Builder
    {
        return $query->ofType(InvestmentType::FixedIncome);
    }

    public function scopeVariableIncome(Builder $query): Builder
    {
        return $query->ofType(InvestmentType::VariableIncome);
    }
}
