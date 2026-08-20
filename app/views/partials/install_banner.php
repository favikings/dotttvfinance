<?php

declare(strict_types=1);

// UI Component Guide §9d — PWA install prompt banner (Tech Spec §17).
// beforeinstallprompt support is entirely client-side (only Chrome desktop
// and Chrome Android fire it), so this just emits the trigger markup;
// installBannerState() in app.js captures the deferred prompt and calls
// prompt() on the Install click — a required user gesture. Dismissible and
// re-offered after 14 days, mirroring the notification banner's cadence.
if (!function_exists('install_banner')) {
    function install_banner(): string
    {
        $downloadHref = url('/assets/icons/sprite.svg') . '#download';

        return <<<HTML
            <div x-data="installBannerState()" x-show="visible" x-cloak
                 class="bg-secondary-container/15 border border-secondary-container rounded-lg p-4 mb-6 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-secondary shrink-0" stroke="currentColor" fill="none"><use href="{$downloadHref}"></use></svg>
                <p class="text-sm text-on-surface">Install DOTT TV Finance for a faster, offline-capable experience.</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button type="button" x-on:click="install()"
                        class="bg-primary text-on-primary text-sm font-semibold px-4 py-2 rounded hover:opacity-90 transition-opacity">
                  Install
                </button>
                <button type="button" x-on:click="dismiss()"
                        class="text-sm text-on-surface-variant px-3 py-2 hover:text-on-surface">
                  Not now
                </button>
              </div>
            </div>
            HTML;
    }
}