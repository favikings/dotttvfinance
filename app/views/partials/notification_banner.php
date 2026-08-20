<?php

declare(strict_types=1);

// UI Component Guide §9c — Web Push opt-in banner (Tech Spec §15a). Visibility
// and the iOS "install first" copy swap are entirely client-side (Notification
// permission state and matchMedia can't be known server-side), so this just
// emits the trigger markup; pushBannerState() in app.js does the real work.
if (!function_exists('notification_banner')) {
    function notification_banner(): string
    {
        $bellHref = url('/assets/icons/sprite.svg') . '#bell';

        return <<<HTML
            <div x-data="pushBannerState()" x-show="visible" x-cloak
                 class="bg-secondary-container/15 border border-secondary-container rounded-lg p-4 mb-6 flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-secondary shrink-0" stroke="currentColor" fill="none"><use href="{$bellHref}"></use></svg>
                <p class="text-sm text-on-surface" x-show="!iosNeedsInstall">Get notified the moment an expense needs your approval.</p>
                <p class="text-sm text-on-surface" x-show="iosNeedsInstall" x-cloak>Install this app to your home screen to get approval notifications on iPhone.</p>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <button type="button" x-show="!iosNeedsInstall" x-on:click="enablePushNotifications(); visible = false"
                        class="bg-primary text-on-primary text-sm font-semibold px-4 py-2 rounded hover:opacity-90 transition-opacity">
                  Enable
                </button>
                <button type="button" x-on:click="visible = false; dismissNotificationBanner()"
                        class="text-sm text-on-surface-variant px-3 py-2 hover:text-on-surface">
                  Not now
                </button>
              </div>
            </div>
            HTML;
    }
}
