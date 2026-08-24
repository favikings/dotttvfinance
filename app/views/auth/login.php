<div class="min-h-screen bg-surface-container flex items-center justify-center px-6">
    <div class="w-full max-w-[400px] bg-surface-container-lowest border border-outline-variant
                rounded-xl p-10 shadow-[0_4px_12px_rgba(0,0,0,0.04)]">

        <h1 class="text-2xl font-semibold tracking-tight text-on-surface mb-1.5">Welcome back</h1>
        <p class="text-sm text-on-surface-variant mb-7">Sign in to DOTT TV Finance</p>

        <?php if (!empty($error)): ?>
            <div class="mb-4 rounded bg-error-container text-on-error-container text-sm px-3 py-2">
                <?= View::e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= View::e(url('/login')) ?>"
              x-data="{ showPassword: false, loading: false }" x-on:submit="loading = true">
            <?= Csrf::field() ?>
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= View::e($redirect) ?>">
            <?php endif; ?>

            <label class="block text-sm font-medium text-on-surface mb-1.5" for="email">Email address</label>
            <input type="email" id="email" name="email" placeholder="ifeoma@dotttv.tv"
                   required autofocus autocomplete="username"
                   value="<?= View::e($old_email ?? '') ?>"
                   class="w-full px-3.5 py-2.5 rounded border border-outline bg-surface-container-lowest
                          text-on-surface text-sm mb-4.5 focus:outline-none focus:ring-2
                          focus:ring-secondary-container focus:border-transparent">

            <label class="block text-sm font-medium text-on-surface mb-1.5" for="password">Password</label>
            <div class="relative mb-5">
                <input :type="showPassword ? 'text' : 'password'" id="password" name="password"
                       placeholder="••••••••••" required autocomplete="current-password"
                       class="w-full px-3.5 py-2.5 pr-10 rounded border border-outline
                              bg-surface-container-lowest text-on-surface text-sm
                              focus:outline-none focus:ring-2 focus:ring-secondary-container
                              focus:border-transparent">
                <button type="button" x-on:click="showPassword = !showPassword"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-outline"
                        aria-label="Toggle password visibility">
                    <svg x-show="!showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a13.16 13.16 0 0 1-1.67 2.68M6.61 6.61A13.53 13.53 0 0 0 1 12s4 8 11 8a9.26 9.26 0 0 0 5.39-1.61M14.12 14.12a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>
            </div>

            <button type="submit" :disabled="loading"
                    class="w-full py-3 rounded bg-primary text-on-primary text-sm font-semibold
                           hover:opacity-90 transition-opacity mb-4.5 disabled:opacity-60 disabled:cursor-not-allowed
                           flex items-center justify-center gap-2">
                <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="loading ? 'Signing in...' : 'Sign in'"></span>
            </button>
        </form>

        <p class="text-xs text-outline text-center">Can't sign in? Contact your Super Admin.</p>
    </div>
</div>
