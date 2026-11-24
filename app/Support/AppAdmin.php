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

    /**
     * E-mail reservado ao admin: não pode ser usado no cadastro nem
     * reivindicado por outro usuário no perfil.
     */
    public static function isReservedEmail(?string $email): bool
    {
        $adminEmail = self::email();

        if ($adminEmail === '' || $email === null || trim($email) === '') {
            return false;
        }

        return strtolower(trim($email)) === $adminEmail;
    }
}
