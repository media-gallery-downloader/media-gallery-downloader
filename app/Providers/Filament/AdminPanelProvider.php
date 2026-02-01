<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->font(false)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->pages([])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => '<style>
                    /* Fix select dropdown hover state */
                    .fi-fo-select-option:hover,
                    .fi-fo-select-option:focus-visible,
                    .fi-fo-select-option[aria-selected="true"] {
                        background-color: #4b5563 !important;
                        color: white !important;
                    }

                    /* Fix mobile hamburger menu hover states */
                    .fi-sidebar-nav-item:hover,
                    .fi-sidebar-nav-item:focus,
                    .fi-sidebar-item:hover,
                    .fi-sidebar-item:focus,
                    [class*="fi-sidebar"] a:hover,
                    [class*="fi-sidebar"] button:hover {
                        background-color: #374151 !important;
                        color: white !important;
                    }

                    /* Ensure text is readable on hover in dark mode navigation */
                    .dark .fi-sidebar-nav a:hover,
                    .dark .fi-sidebar a:hover {
                        background-color: #4b5563 !important;
                        color: white !important;
                    }

                    /* Fix dropdown/popover menu items */
                    .fi-dropdown-list-item:hover,
                    .fi-dropdown-list-item:focus,
                    [role="menuitem"]:hover,
                    [role="menuitem"]:focus {
                        background-color: #374151 !important;
                        color: white !important;
                    }

                    /* Ensure child elements also have readable text */
                    .fi-dropdown-list-item:hover *,
                    [role="menuitem"]:hover * {
                        color: white !important;
                    }
                </style>'
            )
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
            ->brandName('Media Gallery Downloader');
    }
}
