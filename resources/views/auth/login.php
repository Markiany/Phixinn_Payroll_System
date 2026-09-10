<?php $user = null; require __DIR__ . '/../layouts/header.php'; ?>

<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm bg-white shadow-sm border border-slate-200 rounded-lg p-8">

        <h1 class="text-xl font-semibold mb-1"><?= __('auth.sign_in') ?></h1>

        <p class="text-sm text-slate-500 mb-6">
            <?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Payroll System') ?>
        </p>

        <?php if (!empty($error)): ?>
            <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" class="space-y-4">

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.username') ?>
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    autofocus
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.password') ?>
                </label>

                <div class="relative">
                    <input
                        id="login-password"
                        type="password"
                        name="password"
                        required
                        class="w-full border border-slate-300 rounded px-3 py-2 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >

                    <button
                        type="button"
                        id="toggle-password"
                        aria-label="Show password"
                        title="Show password"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-500 hover:text-slate-700"
                    >
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded px-3 py-2"
            >
                <?= __('auth.sign_in') ?>
            </button>

        </form>

        <div class="mt-6 pt-5 border-t border-slate-200 text-center">
            <p class="text-sm text-slate-500 mb-2">
                <?= __('auth.no_account') ?>
            </p>

            <a
                href="/register"
                class="inline-block text-sm font-medium text-slate-900 hover:underline"
            >
                <?= __('auth.create_account_link') ?>
            </a>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('login-password');
    const button = document.getElementById('toggle-password');

    button.addEventListener('click', function () {
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        button.setAttribute('title', showing ? 'Show password' : 'Hide password');
        button.innerHTML = '👁';
    });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
