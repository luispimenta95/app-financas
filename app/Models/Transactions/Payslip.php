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
class Payslip extends Model
{
    use BelongsToUser, HasUuids;

    public const KIND_ADVANCE = 'advance';

    public const KIND_MONTHLY = 'monthly';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'kind',
        'reference_month',
        'credit_date',
        'base_salary',
        'gross_amount',
        'deductions_amount',
        'net_amount',
        'file_path',
        'original_name',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_date' => 'date',
            'base_salary' => 'integer',
            'gross_amount' => 'integer',
            'deductions_amount' => 'integer',
            'net_amount' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort_order');
    }

    public function kindLabel(): string
    {
        return $this->kind === static::KIND_ADVANCE ? 'Adiantamento quinzenal' : 'Salário mensal';
    }
}
