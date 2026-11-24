<?php

namespace App\Support;

use App\Models\Transactions\CreditCardStatement;
use App\Models\Transactions\Payslip;
use App\Models\Transactions\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class PrivateAttachments
{
    public const DISK = 'local';

    /** @var list<string> */
    private const ALLOWED_PREFIXES = [
        'boletos/',
        'payslips/',
        'transactions/',
    ];

    public static function disk(): string
    {
        return self::DISK;
    }

    public static function normalize(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $path;
            }
        }

        return null;
    }

    public static function absolutePath(string $relativePath): ?string
    {
        $relative = self::normalize($relativePath);

        if ($relative === null) {
            return null;
        }

        // Prefer private disk; keep reading legacy public uploads.
        foreach ([self::DISK, 'public'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                return Storage::disk($disk)->path($relative);
            }
        }

        return null;
    }

    public static function url(string $relativePath): ?string
    {
        $relative = self::normalize($relativePath);

        if ($relative === null) {
            return null;
        }

        return route('attachments.show', ['path' => $relative]);
    }

    public static function userOwns(User $user, string $relativePath): bool
    {
        $relative = self::normalize($relativePath);

        if ($relative === null) {
            return false;
        }

        if (Transaction::query()
            ->where('user_id', $user->id)
            ->where('attachment', $relative)
            ->exists()) {
            return true;
        }

        if (Payslip::query()
            ->where('user_id', $user->id)
            ->where('file_path', $relative)
            ->exists()) {
            return true;
        }

        if (CreditCardStatement::query()
            ->where('user_id', $user->id)
            ->where('file_path', $relative)
            ->exists()) {
            return true;
        }

        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereHas('boleto', fn ($q) => $q->where('file_path', $relative))
            ->exists();
    }
}
