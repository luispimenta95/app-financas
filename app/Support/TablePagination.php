<?php

namespace App\Support;

use App\Models\User;

final class TablePagination
{
    public const DEFAULT = 50;

    /** @var list<int> */
    public const OPTIONS = [50, 100, 200];

    public static function normalize(mixed $value): int
    {
        $size = (int) $value;

        return in_array($size, self::OPTIONS, true) ? $size : self::DEFAULT;
    }

    public static function for(?User $user = null): int
    {
        $user ??= auth()->user();

        if ($user instanceof User) {
            return self::normalize($user->table_records_per_page);
        }

        return self::DEFAULT;
    }

    /**
     * @return array<int, string>
     */
    public static function selectOptions(): array
    {
        return collect(self::OPTIONS)
            ->mapWithKeys(fn (int $size): array => [$size => "{$size} por página"])
            ->all();
    }
}
