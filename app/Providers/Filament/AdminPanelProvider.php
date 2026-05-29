<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('')
            ->favicon(asset('img/logo/favicon.ico'))
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([])

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn(): HtmlString => new HtmlString('
                <style>
                    body { padding-top: 120px !important; }
                    @media (max-width: 768px) { body { padding-top: 64px !important; } }
                    .fi-main-ctn { max-width: 100% !important; width: 100% !important; }
                    .fi-main { max-width: 100% !important; width: 100% !important; }
                    .fi-body { overflow-x: hidden; }
                    [data-sidebar-collapsed] .fi-main-ctn,
                    [data-sidebar-collapsed] .fi-main { max-width: 100% !important; }

                    /* Light theme */
                    html:not(.dark) body,
                    html:not(.dark) .fi-body {
                        background-image: url("/img/bg-main.webp?v=3") !important;
                        background-size: auto !important;
                        background-attachment: fixed !important;
                        background-repeat: repeat !important;
                    }
                    html:not(.dark) .fi-topbar { background: #ffffff !important; border-bottom: 1px solid #7a7a7a !important; }
                    html:not(.dark) .fi-sidebar-nav { background: #ffffff !important; border-right: 1px solid #7a7a7a !important; }
                    html:not(.dark) .fi-card,
                    html:not(.dark) .fi-wi-widget,
                    html:not(.dark) .fi-modal-window,
                    html:not(.dark) .fi-ta-ctn,
                    html:not(.dark) .fi-wi-stats-overview-stat {
                        background: #ffffff !important;
                        border: 1px solid #7a7a7a !important;
                        border-radius: 12px !important;
                    }
                    html:not(.dark) .fi-wi-stats-overview,
                    html:not(.dark) .fi-section {
                        border: none !important;
                        background: transparent !important;
                        box-shadow: none !important;
                    }
                    html:not(.dark) .fi-input,
                    html:not(.dark) .fi-select-input,
                    html:not(.dark) textarea.fi-fo-textarea,
                    html:not(.dark) .fi-fo-textarea {
                        border: 1px solid #7A7A7A !important;
                    }

                    /* Dark theme */
                    html.dark body,
                    html.dark .fi-body {
                        background-color: #111827 !important;
                        background-image: none !important;
                    }

                    .filepond--image-preview-wrapper,
                    .filepond--image-preview { background: #f3f4f6 !important; }
                    .filepond--panel-root { background: #f9fafb !important; }
                    .fi-fo-file-upload .filepond--root { width: 150px !important; height: 150px !important; }
                </style>
                '),
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn(): HtmlString => new HtmlString(View::make('components.header')->render()),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): HtmlString => new HtmlString(View::make('components.footer')->render()),
            );
    }
}
