<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets;
use App\Support\AppAdmin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Hydrat\TableLayoutToggle\TableLayoutTogglePlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DashPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('dash')
            // Com LANDING_ENABLED=true a vitrine fica em `/` e o painel em `/app`.
            // Com false, mantém a estrutura atual (Filament na raiz).
            ->path(financas_panel_path())
            ->login()
            ->registration(Register::class)
            ->passwordReset()
            ->spa()
            ->profile(page: EditProfile::class, isSimple: false)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->databaseNotifications()
            ->userMenuItems([
                'admin' => MenuItem::make()
                    ->label('Administração')
                    ->url('/admin')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn (): bool => AppAdmin::check(auth()->user())),
            ])
            ->brandName((string) config('financas.brand', 'Finanças'))
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('favicon.ico'))
            ->font('DM Sans')
            ->colors([
                'danger' => Color::Rose,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'primary' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->viteTheme('resources/css/filament/dash/theme.css')
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<link rel="stylesheet" href="'.e(asset('css/statement-upload.css')).'">'
                    .'<link rel="icon" type="image/svg+xml" href="'.e(asset('images/logo.svg')).'">'
                    .'<link rel="icon" type="image/png" sizes="32x32" href="'.e(asset('favicon-32x32.png')).'">'
                    .'<link rel="apple-touch-icon" href="'.e(asset('apple-touch-icon.png')).'">',
            )
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                Widgets\MonthProjectionWidget::class,
                Widgets\TransactionsOverview::class,
                Widgets\CategoriesChart::class,
                Widgets\TopCategoriesWidget::class,
                Widgets\InvestmentsTotalWidget::class,
                Widgets\MonthRevenue::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                TableLayoutTogglePlugin::make()
                    ->setDefaultLayout('list')
                    ->persistLayoutInLocalStorage(true) // allow user to keep his layout preference in his local storage
                    ->shareLayoutBetweenPages(false) // allow all tables to share the layout option (requires persistLayoutInLocalStorage to be true)
                    ->displayToggleAction() // used to display the toogle button automatically, on the desired filament hook (defaults to table bar)
                    ->listLayoutButtonIcon('heroicon-o-list-bullet')
                    ->gridLayoutButtonIcon('heroicon-o-squares-2x2'),
            ]);
    }
}
