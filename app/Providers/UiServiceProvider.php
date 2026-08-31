<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class UiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Larger controls and RTL support for low-literacy / Arabic users.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render(<<<'HTML'
                @if(\App\Support\Locales::isArabicScript())
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
                @endif
                <style>
                    /* Sidebar: 20rem is far wider than these short labels need. */
                    :root {
                        --sidebar-width: 15rem;
                        --collapsed-sidebar-width: 4.25rem;
                    }

                    /* Tighter rows, softer resting state, clearer active state. */
                    .fi-sidebar-nav { gap: .25rem; padding-inline: .625rem; }
                    .fi-sidebar-group { gap: .125rem; }
                    .fi-sidebar-group-label {
                        font-size: .7rem;
                        font-weight: 700;
                        letter-spacing: .04em;
                        text-transform: uppercase;
                        opacity: .55;
                    }
                    .fi-sidebar-item-button {
                        padding-block: .5rem;
                        padding-inline: .625rem;
                        border-radius: .5rem;
                        gap: .625rem;
                    }
                    .fi-sidebar-item-label {
                        white-space: nowrap;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    .fi-sidebar-item-icon { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
                    .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover {
                        background-color: rgb(0 0 0 / .04);
                    }
                    .dark .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover {
                        background-color: rgb(255 255 255 / .06);
                    }
                    .fi-sidebar-item.fi-active .fi-sidebar-item-button {
                        background-color: rgb(var(--primary-50));
                        font-weight: 600;
                    }
                    .dark .fi-sidebar-item.fi-active .fi-sidebar-item-button {
                        background-color: rgb(255 255 255 / .08);
                    }
                    .fi-sidebar-header { padding-inline: 1rem; }

                    /* Bigger touch targets and type everywhere. */
                    .fi-btn { font-size: 1rem; padding-top: .7rem; padding-bottom: .7rem; }
                    .fi-ta-row { font-size: 1.02rem; }
                    .fi-input, .fi-select-input { font-size: 1.05rem; padding-top: .6rem; padding-bottom: .6rem; }
                    .fi-fo-field-wrp-label { font-size: 1rem; font-weight: 600; }
                    .fi-sidebar-item-label { font-size: 1.02rem; }

                    @if(\App\Support\Locales::isArabicScript())
                        /* Tajawal: loaded via the <link> tags above. */
                        html[dir="rtl"] body,
                        html[dir="rtl"] .fi-sidebar-item-label,
                        html[dir="rtl"] .fi-btn,
                        html[dir="rtl"] .fi-input { font-family: "Tajawal", system-ui, sans-serif; }
                        html[dir="rtl"] body { font-size: 1.05rem; line-height: 1.75; letter-spacing: 0; }
                        html[dir="rtl"] .fi-sidebar-item-label { font-size: 1rem; }
                        html[dir="rtl"] .fi-sidebar-group-label { font-size: .8rem; font-weight: 700; letter-spacing: 0; text-transform: none; }
                        html[dir="rtl"] .fi-fo-field-wrp-label { font-size: 1.05rem; }

                        /* Money, dates, references and IDs stay left-to-right inside RTL text. */
                        html[dir="rtl"] .fi-ta-text-item-label,
                        html[dir="rtl"] .fi-in-text,
                        html[dir="rtl"] input[type="number"],
                        html[dir="rtl"] .fi-ta-record-checkbox { unicode-bidi: plaintext; }

                        /* Numeric table cells read right-aligned in RTL, not centre-drifted. */
                        html[dir="rtl"] .fi-ta-cell .fi-ta-text { text-align: start; }

                        /* Icons that imply direction must mirror. */
                        html[dir="rtl"] .fi-icon-btn svg.fi-chevron-right,
                        html[dir="rtl"] .fi-pagination-item svg { transform: scaleX(-1); }
                    @endif
                </style>
            HTML),
        );
    }
}
