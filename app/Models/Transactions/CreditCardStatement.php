<?php

namespace App\Models\Transactions;

use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy([TenantScope::class])]
class CreditCardStatement extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'file_path',
        'original_name',
        'items_count',
        'total_amount',
        'bill_amount',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'total_amount' => 'integer',
            'bill_amount' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(CreditCardPurchase::class)->orderBy('sort_order')->orderBy('occurred_on');
    }
}
