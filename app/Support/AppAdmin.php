<?php

namespace App\Support;

use App\Models\User;

final class AppAdmin
{
    public static function email(): string
    {
        return strtolower(trim((string) config('financas.admin_email', '')));
    }

    public static function check(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $adminEmail = self::email();

        if ($adminEmail === '') {
            return false;
        }

        return strtolower((string) $user->email) === $adminEmail;
    }
}
