<?php

namespace App\Models\Transactions;

use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TenantScope::class])]
class Category extends Model
{
    use BelongsToUser, HasFactory, HasUuids, SoftDeletes;

    public const INVESTMENTS_NAME = 'Investimentos';

    public const INVESTMENTS_SLUG = 'investimentos';

    public const CREDIT_CARD_NAME = 'Cartão de Crédito';

    public const CREDIT_CARD_SLUG = 'cartao-de-credito';

    public const SALARY_SLUG = 'salario';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'icon',
        'color',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isInvestments(): bool
    {
        return $this->slug === static::INVESTMENTS_SLUG;
    }

    public function isCreditCard(): bool
    {
        return $this->slug === static::CREDIT_CARD_SLUG;
    }

    public function isSalary(): bool
    {
        return $this->slug === static::SALARY_SLUG;
    }
}
