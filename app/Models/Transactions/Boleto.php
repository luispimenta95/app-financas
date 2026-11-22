<?php

namespace App\Models\Transactions;

use App\Models\Scopes\TenantScope;
use App\Models\Traits\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy([TenantScope::class])]
class Boleto extends Model
{
    use BelongsToUser, HasUuids;

    protected $fillable = [
        'user_id',
        'transaction_id',
        'digitable_line',
        'barcode',
        'bank_code',
        'beneficiary',
        'payer',
        'document_number',
        'our_number',
        'amount',
        'due_date',
        'file_path',
        'original_name',
        'raw_text',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'due_date' => 'date',
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

    public function formattedDigitableLine(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->digitable_line);

        if (! is_string($digits)) {
            return $this->digitable_line;
        }

        if (strlen($digits) === 47) {
            return sprintf(
                '%s.%s %s.%s %s.%s %s %s',
                substr($digits, 0, 5),
                substr($digits, 5, 5),
                substr($digits, 10, 5),
                substr($digits, 15, 6),
                substr($digits, 21, 5),
                substr($digits, 26, 6),
                substr($digits, 32, 1),
                substr($digits, 33, 14),
            );
        }

        if (strlen($digits) === 48) {
            return sprintf(
                '%s-%s %s-%s %s-%s %s-%s',
                substr($digits, 0, 11),
                substr($digits, 11, 1),
                substr($digits, 12, 11),
                substr($digits, 23, 1),
                substr($digits, 24, 11),
                substr($digits, 35, 1),
                substr($digits, 36, 11),
                substr($digits, 47, 1),
            );
        }

        return $this->digitable_line;
    }
}
