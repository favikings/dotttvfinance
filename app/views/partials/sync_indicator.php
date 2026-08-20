<?php

declare(strict_types=1);

// UI Component Guide §9e — "last synced" indicator (Tech Spec §17). Report
// pages are cacheable by the service worker for offline viewing, so each one
// shows how current the data is: a green dot + "Synced <time>" when online,
// an error-colored dot + "Last synced <time>" when serving a cached copy
// offline. Pure client-side (syncIndicator() in app.js) — online state can't
// be known server-side.
if (!function_exists('sync_indicator')) {
    function sync_indicator(): string
    {
        return <<<HTML
            <div x-data="syncIndicator()"
                 class="inline-flex items-center gap-1.5 text-label-sm text-on-surface-variant">
              <span class="inline-block w-1.5 h-1.5 rounded-full" :class="offline ? 'bg-error' : 'bg-success'"></span>
              <span x-text="label"></span>
            </div>
            HTML;
    }
}