<div class="max-w-md mx-auto" x-data="changePasswordForm()">
    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant p-8">
        <h2 class="text-xl font-semibold text-on-surface mb-6">Change Password</h2>

        <form x-on:submit.prevent="submit()">
            <label class="block text-sm font-medium text-on-surface mb-1.5" for="current_password">Current Password</label>
            <input type="password" id="current_password" x-model="currentPassword"
                   class="w-full px-3.5 py-2.5 rounded border border-outline text-sm mb-4">

            <label class="block text-sm font-medium text-on-surface mb-1.5" for="new_password">New Password</label>
            <input type="password" id="new_password" x-model="newPassword"
                   class="w-full px-3.5 py-2.5 rounded border border-outline text-sm mb-1">
            <p class="text-xs text-on-surface-variant mb-4">Minimum 8 characters.</p>

            <label class="block text-sm font-medium text-on-surface mb-1.5" for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" x-model="confirmPassword"
                   class="w-full px-3.5 py-2.5 rounded border border-outline text-sm mb-6">

            <button type="submit"
                    class="w-full py-3 rounded bg-primary text-on-primary text-sm font-semibold hover:opacity-90 transition-opacity
                           flex items-center justify-center gap-2"
                    :disabled="busy"
                    :class="busy ? 'opacity-60 cursor-not-allowed' : ''">
                <svg x-show="busy" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span x-text="busy ? 'Updating...' : 'Update Password'"></span>
            </button>
        </form>
    </div>
</div>

<script>
    function changePasswordForm() {
        return {
            currentPassword: '',
            newPassword: '',
            confirmPassword: '',
            busy: false,

            // Tech Spec §5a: change succeeds via AJAX round-trip, fields
            // cleared and a toast confirms it — no full page reload.
            async submit() {
                if (this.busy) {
                    return;
                }
                this.busy = true;
                try {
                    const res = await fetch(window.APP_BASE_PATH + '/account/password', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            _csrf: window.CSRF_TOKEN,
                            current_password: this.currentPassword,
                            new_password: this.newPassword,
                            confirm_password: this.confirmPassword,
                        }),
                    });
                    const json = await res.json().catch(() => null);
                    if (!json || !json.ok) {
                        dottToast.fire({ icon: 'error', title: (json && json.message) || 'Something went wrong. Please try again.' });
                        return;
                    }
                    this.currentPassword = '';
                    this.newPassword = '';
                    this.confirmPassword = '';
                    dottToast.fire({ icon: 'success', title: json.message });
                } catch (err) {
                    dottToast.fire({
                        icon: 'error',
                        title: navigator.onLine
                            ? 'Could not reach the server. Check your connection and try again.'
                            : "You're offline — reconnect to change your password.",
                    });
                } finally {
                    this.busy = false;
                }
            },
        };
    }
</script>
