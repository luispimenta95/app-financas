<?php

use Illuminate\Support\Facades\File;

if (!function_exists('get_avaliable_icons')) {
    function get_avaliable_icons(?string $path = null, string $sufix = 'fas', bool $icons = false)
    {
        $mediaPath = $path ?? base_path('vendor/owenvoke/blade-fontawesome/resources/svg/solid/');

        $filesInFolder = File::allFiles($mediaPath);

        $allIcons = [];

        foreach ($filesInFolder as $file) {
            $iconName = str($file->getBaseName())->remove('.svg');

            $option = "{$sufix}-{$iconName}";

            $allIcons[$option] = '';

            if ($icons) {
                $allIcons[$option] = $option;
            }
        }

        return $allIcons;
    }
}

if (!function_exists('financas_landing_enabled')) {
    function financas_landing_enabled(): bool
    {
        return (bool) config('financas.landing_enabled');
    }
}

if (!function_exists('financas_panel_path')) {
    /**
     * Prefixo do painel Filament: `app` com landing ligada, vazio no modo atual.
     */
    function financas_panel_path(): string
    {
        return financas_landing_enabled() ? 'app' : '';
    }
}

if (!function_exists('financas_panel_url')) {
    /**
     * URL dentro do painel (login, register, dashboard…).
     */
    function financas_panel_url(string $path = ''): string
    {
        $base = trim(financas_panel_path(), '/');
        $path = trim($path, '/');

        if ($base === '') {
            return url($path === '' ? '/' : '/' . $path);
        }

        return url($path === '' ? '/' . $base : '/' . $base . '/' . $path);
    }
}
