<?php

namespace App\Models\Transactions;

use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class PayslipItem extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'user_id',
        'payslip_id',
        'code',
        'description',
        'quantity',
        'earnings',
        'deductions',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'earnings' => 'integer',
            'deductions' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
