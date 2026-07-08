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
}
