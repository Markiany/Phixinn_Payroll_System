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

                <input
                    type="password"
                    name="password"
                    required
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded px-3 py-2"
            >
                <?= __('auth.sign_in') ?>
            </button>

        </form>

        <!-- REGISTER -->
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

<?php require __DIR__ . '/../layouts/footer.php'; ?>