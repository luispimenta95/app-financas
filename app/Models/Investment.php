<?php

namespace App\Models;

use App\Enums\InvestmentType;
use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class Investment extends Model
{
    use BelongsToUser, HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'institution',
        'amount',
        'application_date',
        'cdi_rate',
        'daily_liquidity',
        'maturity_date',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvestmentType::class,
            'amount' => 'integer',
            'application_date' => 'date',
            'cdi_rate' => 'decimal:2',
            'daily_liquidity' => 'boolean',
            'maturity_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
