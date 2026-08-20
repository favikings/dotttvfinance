if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        const base = window.APP_BASE_PATH || '';
        navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }).catch((err) => {
            console.error('Service worker registration failed:', err);
        });
    });
}

// UI Component Guide §9b — the ONLY alert/confirm surface in the app.
// dottAlert: themed confirmation/input modal (destroy = red confirm button).
// dottToast: success/error toast after a state-changing action.
// dottConfirm: thin wrapper for a plain confirm/cancel dialog.
const dottAlert = Swal.mixin({
    customClass: {
        popup: 'rounded-xl border border-outline-variant',
        confirmButton: 'bg-primary text-on-primary text-sm font-semibold px-4 py-2.5 rounded mx-1',
        cancelButton: 'border border-outline text-on-surface text-sm font-medium px-4 py-2.5 rounded mx-1',
        denyButton: 'bg-error text-on-error text-sm font-semibold px-4 py-2.5 rounded mx-1',
    },
    buttonsStyling: false,
});

const dottToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    customClass: { popup: 'rounded-lg' },
});

window.dottAlert = dottAlert;
window.dottToast = dottToast;

function dottConfirm(title, text, confirmButtonText = 'Confirm', danger = false) {
    return dottAlert.fire({
        title,
        text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText: 'Cancel',
        background: 'var(--color-surface-container-lowest)',
        color: 'var(--color-on-surface)',
        confirmButtonColor: danger ? 'var(--color-error)' : undefined,
        customClass: {
            popup: 'rounded-xl border border-outline-variant',
            confirmButton: danger
                ? 'bg-error text-on-error text-sm font-semibold px-4 py-2.5 rounded mx-1'
                : 'bg-primary text-on-primary text-sm font-semibold px-4 py-2.5 rounded mx-1',
            cancelButton: 'border border-outline text-on-surface text-sm font-medium px-4 py-2.5 rounded mx-1',
        },
    });
}

window.dottConfirm = dottConfirm;

// Destructive form guard: replaces the old synchronous
// `onsubmit="return confirm('...')"` with a themed async dialog.
// Returns false so the native submit never fires; submits manually on confirm.
window.dottConfirmSubmit = function (form, title, text, confirmButtonText = 'Delete', danger = true) {
    dottConfirm(title, text, confirmButtonText, danger).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
};

// Web Push opt-in (Tech Spec §15a / UI Component Guide §9c). Alpine
// component for the trigger banner; the actual permission/subscribe/dismiss
// logic lives here, not inline in the markup.
const PUSH_DISMISS_STORAGE_KEY = 'dott_push_banner_dismissed_at';
const PUSH_DISMISS_REOFFER_DAYS = 14;

function pushBannerState() {
    return {
        visible: false,
        iosNeedsInstall: false,
        init() {
            if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                return; // unsupported browser — never show the banner
            }
            if (!window.VAPID_PUBLIC_KEY || Notification.permission !== 'default') {
                return;
            }

            const dismissedAt = parseInt(localStorage.getItem(PUSH_DISMISS_STORAGE_KEY) || '0', 10);
            if (dismissedAt) {
                const daysSinceDismissal = (Date.now() - dismissedAt) / (1000 * 60 * 60 * 24);
                if (daysSinceDismissal < PUSH_DISMISS_REOFFER_DAYS) {
                    return;
                }
            }

            // iOS Safari (16.4+) only supports Web Push once the PWA is
            // installed to the home screen — a subscribe attempt in a
            // regular tab silently fails, so swap the banner copy instead.
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
            this.iosNeedsInstall = isIOS && !isStandalone;

            this.visible = true;
        },
    };
}
window.pushBannerState = pushBannerState;

// Not-now: a UI-preference nicety only — never used for financial data.
function dismissNotificationBanner() {
    localStorage.setItem(PUSH_DISMISS_STORAGE_KEY, String(Date.now()));
}
window.dismissNotificationBanner = dismissNotificationBanner;

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// UI Component Guide §8c — scrollable-table edge fade. Every table wrapper's
// inner `overflow-x-auto table-scroll` div gets a soft gradient fade on its
// trailing edge while more columns are hidden in that direction (and on the
// leading edge once the user has scrolled away from the start). Pure CSS
// can't detect overflow, so this measures each container and toggles the
// has-more-* classes on scroll, resize, and after Alpine re-renders.
function updateTableScrollFades() {
    document.querySelectorAll('.table-scroll').forEach((el) => {
        const canScroll = el.scrollWidth > el.clientWidth + 1;
        const atStart = el.scrollLeft <= 1;
        const atEnd = el.scrollLeft >= el.scrollWidth - el.clientWidth - 1;
        el.classList.toggle('has-more-right', canScroll && !atEnd);
        el.classList.toggle('has-more-left', canScroll && !atStart);
    });
}

function initTableScrollFades() {
    // scroll events don't bubble but do capture — listening on the document
    // catches every .table-scroll's own scroll without per-table handlers.
    document.addEventListener('scroll', updateTableScrollFades, { capture: true, passive: true });
    window.addEventListener('resize', updateTableScrollFades);
    updateTableScrollFades();

    // Alpine-driven tables (x-show toggling, x-for rows) change their
    // scrollability after Alpine re-renders the DOM — re-measure then,
    // debounced to one frame so rapid mutations don't thrash.
    if ('MutationObserver' in window) {
        const observer = new MutationObserver(() => window.requestAnimationFrame(updateTableScrollFades));
        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden'],
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTableScrollFades);
} else {
    initTableScrollFades();
}

// Enable click handler: requestPermission() needs a real user gesture, so
// this only ever runs from the banner's button click (Tech Spec §15a).
function enablePushNotifications() {
    if (!('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    Notification.requestPermission()
        .then((permission) => {
            if (permission !== 'granted') {
                return null;
            }
            return navigator.serviceWorker.ready;
        })
        .then((registration) => {
            if (!registration) {
                return null;
            }
            return registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(window.VAPID_PUBLIC_KEY),
            });
        })
        .then((subscription) => {
            if (!subscription) {
                return null;
            }
            const json = subscription.toJSON();
            const base = window.APP_BASE_PATH || '';
            return fetch(base + '/push/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    keys: json.keys,
                    _csrf: window.CSRF_TOKEN,
                }),
            });
        })
        .catch((err) => {
            console.error('Push subscription failed:', err);
        });
}
window.enablePushNotifications = enablePushNotifications;

// PWA install prompt (Tech Spec §17). Standard beforeinstallprompt handling:
// Chrome fires the event on both desktop and mobile once the manifest is
// installable; we preventDefault() it so the browser never auto-prompts, then
// offer the install banner and call prompt() only on the user's explicit
// click (a required user gesture). The module-level listener runs immediately
// (script is deferred) so the event is captured even if it beats Alpine's
// init; the banner component just reads deferredInstallPrompt state.
const INSTALL_DISMISS_KEY = 'dott_install_dismissed_at';
const INSTALL_REOFFER_DAYS = 14;
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    window.dispatchEvent(new CustomEvent('dott-install-prompt-ready'));
});

function installBannerState() {
    return {
        visible: false,
        init() {
            const offer = () => {
                const dismissedAt = parseInt(localStorage.getItem(INSTALL_DISMISS_KEY) || '0', 10);
                const withinCooldown = dismissedAt && Date.now() - dismissedAt < INSTALL_REOFFER_DAYS * 86400000;
                this.visible = deferredInstallPrompt !== null && !withinCooldown;
            };
            window.addEventListener('dott-install-prompt-ready', offer);
            window.addEventListener('appinstalled', () => {
                this.visible = false;
                deferredInstallPrompt = null;
            });
            offer();
        },
        async install() {
            if (!deferredInstallPrompt) {
                return;
            }
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            this.visible = false;
            localStorage.setItem(INSTALL_DISMISS_KEY, String(Date.now()));
        },
        dismiss() {
            this.visible = false;
            localStorage.setItem(INSTALL_DISMISS_KEY, String(Date.now()));
        },
    };
}
window.installBannerState = installBannerState;

// PWA "last synced" indicator (Tech Spec §17). Report pages may be viewed
// offline from the service-worker cache; the indicator records the last time
// the app successfully talked to the server so the user can judge how stale
// a cached report is. Writing the timestamp is the ONLY client-side write to
// localStorage — never to financial data (writes still require a connection).
const SYNC_STORAGE_KEY = 'dott_last_synced_at';

function syncIndicator() {
    return {
        offline: false,
        label: 'Synced just now',
        init() {
            window.addEventListener('online', () => this.refresh());
            window.addEventListener('offline', () => this.refresh());
            this.refresh();
        },
        refresh() {
            if (navigator.onLine) {
                localStorage.setItem(SYNC_STORAGE_KEY, String(Date.now()));
                this.offline = false;
                this.label = 'Synced ' + this.formatTime(Date.now());
            } else {
                this.offline = true;
                const t = parseInt(localStorage.getItem(SYNC_STORAGE_KEY) || '0', 10);
                this.label = t > 0 ? 'Last synced ' + this.formatTime(t) : 'Offline — no cached data';
            }
        },
        formatTime(ts) {
            return new Date(ts).toLocaleString([], {
                day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit',
            });
        },
    };
}
window.syncIndicator = syncIndicator;

// App-wide offline/online signal (Tech Spec §17): going offline surfaces an
// immediate toast on every screen, and coming back online confirms recovery.
// Individual write actions (e.g. the approval queue's postAction) additionally
// fail loudly with their own offline-specific message rather than relying on
// this alone. Toast availability is guarded so a CDN hiccup can't break it.
window.addEventListener('offline', () => {
    if (window.dottToast) {
        dottToast.fire({ icon: 'warning', title: "You're offline. Changes won't save until you reconnect." });
    }
});
window.addEventListener('online', () => {
    if (window.dottToast) {
        dottToast.fire({ icon: 'success', title: 'Back online.' });
    }
});
