<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= View::e($title ?? 'Dashboard') ?> — <?= View::e(APP_NAME) ?></title>
    <link rel="manifest" href="<?= View::e(url('/manifest.json')) ?>">
    <meta name="theme-color" content="#000d35">
    <link rel="stylesheet" href="<?= View::e(url('/assets/css/app.css')) ?>?v=<?= filemtime(APP_ROOT . '/public_html/assets/css/app.css') ?>">
    <script>
        window.APP_BASE_PATH = <?= json_encode(BASE_PATH) ?>;
        window.CSRF_TOKEN = <?= json_encode(Csrf::token()) ?>;
        window.VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
    </script>
    <script defer src="<?= View::e(url('/assets/js/sweetalert2.min.js')) ?>"></script>
    <script defer src="<?= View::e(url('/assets/js/app.js')) ?>?v=<?= filemtime(APP_ROOT . '/public_html/assets/js/app.js') ?>"></script>
    <script defer src="<?= View::e(url('/assets/js/alpine.min.js')) ?>"></script>
</head>
<body class="bg-surface text-on-surface font-sans antialiased">
    <div class="flex min-h-screen" x-data="{ drawerOpen: false }">
        <aside class="hidden md:flex md:flex-col md:w-sidebar-width md:fixed md:inset-y-0 bg-primary text-on-primary">
            <div class="flex items-center gap-2 px-6 py-6">
                <span class="text-headline-sm font-semibold tracking-tight">DOTT TV</span>
                <span class="text-label-sm uppercase text-on-primary-container">Finance</span>
            </div>
            <?php
                // Tech Spec §6: hidden nav items are a UX nicety, not the
                // security control (that's Permission::require() in each
                // controller) — filtered here purely so a role doesn't see
                // links to screens it can't reach.
                $navItems = array_values(array_filter(
                    require APP_ROOT . '/app/config/nav.php',
                    static function (array $item): bool {
                        if ($item['permission'] === null) {
                            return true;
                        }
                        [$module, $action] = explode('.', $item['permission'], 2);
                        return Permission::check(Auth::user(), $module, $action);
                    }
                ));
                // Shared current-path detection reused by the mobile drawer
                // and bottom tab bar so all three navs highlight identically.
                $activeHref = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
                if (BASE_PATH !== '' && str_starts_with($activeHref, BASE_PATH)) {
                    $activeHref = substr($activeHref, strlen(BASE_PATH)) ?: '/';
                }
            ?>
            <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
                <?php foreach ($navItems as $item): ?>
                    <?php
                        $currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
                        if (BASE_PATH !== '' && str_starts_with($currentPath, BASE_PATH)) {
                            $currentPath = substr($currentPath, strlen(BASE_PATH)) ?: '/';
                        }
                        $href = rtrim($item['href'], '/') ?: '/';
                        // Exact match, or prefix match so /settings/departments
                        // keeps "Settings" highlighted in the sidebar.
                        $isActive = $currentPath === $href || ($href !== '/' && str_starts_with($currentPath, $href . '/'));
                    ?>
                    <a href="<?= View::e(url($item['href'])) ?>"
                       class="flex items-center gap-3 rounded px-3 py-2.5 text-body-md text-on-primary/80 hover:bg-white/10 hover:text-on-primary transition-colors <?= $isActive ? 'bg-white/15 text-on-primary border-l-2 border-secondary -ml-3 pl-[calc(0.75rem-2px)]' : '' ?>">
                        <svg class="w-4 h-4 shrink-0" stroke="currentColor" fill="none">
                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#<?= View::e($item['icon']) ?>"></use>
                        </svg>
                        <span><?= View::e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <?php $currentUser = Auth::user(); ?>
            <?php if ($currentUser): ?>
                <?= View::renderPartial('partials/account_menu', ['currentUser' => $currentUser]) ?>
            <?php endif; ?>

            <div class="m-3 mb-6 rounded-md bg-white/10 p-4">
                <p class="text-label-sm text-on-primary-container uppercase">System status</p>
                <p class="text-body-md mt-1">All systems normal</p>
                <div class="mt-3 h-1.5 rounded-full bg-white/15 overflow-hidden">
                    <div class="h-full w-4/5 rounded-full bg-secondary"></div>
                </div>
            </div>
        </aside>

        <div class="flex-1 md:ml-sidebar-width flex flex-col min-h-screen min-w-0">
            <header class="md:hidden sticky top-0 z-30 flex items-center justify-between bg-primary text-on-primary px-4 py-3">
                <span class="text-headline-sm font-semibold">DOTT TV Finance</span>
                <button type="button" class="rounded p-2 hover:bg-white/10" aria-label="Open menu" @click="drawerOpen = true">
                    <svg class="w-5 h-5" stroke="currentColor" fill="none">
                        <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#menu"></use>
                    </svg>
                </button>
            </header>

            <main class="flex-1 p-4 md:px-8 md:pt-8 pb-24 md:pb-8">
                <div class="max-w-6xl mx-auto">
                    <?= install_banner() ?>
                    <?= notification_banner() ?>
                    <?= $content ?? '' ?>
                </div>
            </main>

            <nav class="md:hidden fixed bottom-0 inset-x-0 z-30 bg-primary text-on-primary flex justify-around border-t border-white/10 pt-2 pb-[calc(0.5rem+env(safe-area-inset-bottom))]">
                <?php
                    // Bottom tab bar is intentionally a fixed set of the three
                    // core destinations — everything else lives in the drawer.
                    $bottomItems = array_values(array_filter(
                        $navItems,
                        static fn (array $item): bool => in_array($item['href'], ['/', '/expenses', '/fund-topups'], true)
                    ));
                ?>
                <?php foreach ($bottomItems as $item): ?>
                    <?php
                        $href = rtrim($item['href'], '/') ?: '/';
                        $isActive = $activeHref === $href || ($href !== '/' && str_starts_with($activeHref, $href . '/'));
                    ?>
                    <a href="<?= View::e(url($item['href'])) ?>"
                       class="flex flex-col items-center gap-1 px-3 py-1 text-label-sm <?= $isActive ? 'text-on-primary border-t-2 border-secondary' : 'text-on-primary/80 hover:text-on-primary' ?>">
                        <svg class="w-5 h-5" stroke="currentColor" fill="none">
                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#<?= View::e($item['icon']) ?>"></use>
                        </svg>
                        <span><?= View::e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div x-show="drawerOpen"
             x-transition.opacity.duration.200ms
             @click="drawerOpen = false"
             class="fixed inset-0 z-40 bg-black/40 md:hidden">
            <div x-show="drawerOpen"
                 @click.stop
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 class="absolute inset-y-0 right-0 flex w-72 max-w-[85vw] flex-col bg-primary text-on-primary">
                <div class="flex items-center justify-between px-6 py-6">
                    <span class="text-headline-sm font-semibold tracking-tight">DOTT TV</span>
                    <button type="button" class="rounded p-2 hover:bg-white/10" aria-label="Close menu" @click="drawerOpen = false">
                        <svg class="w-5 h-5" stroke="currentColor" fill="none">
                            <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#x"></use>
                        </svg>
                    </button>
                </div>
                <nav class="flex-1 px-3 py-2 space-y-1 overflow-y-auto">
                    <?php foreach ($navItems as $item): ?>
                        <?php
                            $href = rtrim($item['href'], '/') ?: '/';
                            $isActive = $activeHref === $href || ($href !== '/' && str_starts_with($activeHref, $href . '/'));
                        ?>
                        <a href="<?= View::e(url($item['href'])) ?>"
                           class="flex items-center gap-3 rounded px-3 py-2.5 text-body-md text-on-primary/80 hover:bg-white/10 hover:text-on-primary transition-colors <?= $isActive ? 'bg-white/15 text-on-primary border-l-2 border-secondary -ml-3 pl-[calc(0.75rem-2px)]' : '' ?>">
                            <svg class="w-4 h-4 shrink-0" stroke="currentColor" fill="none">
                                <use href="<?= View::e(url('/assets/icons/sprite.svg')) ?>#<?= View::e($item['icon']) ?>"></use>
                            </svg>
                            <span><?= View::e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <?php if ($currentUser): ?>
                    <?= View::renderPartial('partials/account_menu', ['currentUser' => $currentUser]) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
