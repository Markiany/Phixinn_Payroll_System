<?php
$user = \App\Helpers\Auth::user();
require __DIR__ . '/../layouts/header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center">
    <div class="w-full max-w-sm bg-white shadow-sm border border-slate-200 rounded-lg p-8">

        <h1 class="text-xl font-semibold mb-1"><?= __('auth.create_account_title') ?></h1>

        <?php if (!empty($isFirstAccount)): ?>

            <p class="text-sm text-slate-500 mb-6">
                <?= __('auth.first_account_subtitle') ?>
            </p>

            <div class="mb-5 text-sm text-blue-700 bg-blue-50 border border-blue-200 rounded px-3 py-2">
                <?= __('auth.first_account_notice') ?>
            </div>

        <?php else: ?>

            <p class="text-sm text-slate-500 mb-6">
                <?= __('auth.register_subtitle') ?>
            </p>

        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/register" class="space-y-4">

            <!-- Username -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.username') ?>
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    autofocus
                    maxlength="50"
                    value="<?= htmlspecialchars($old['username'] ?? '') ?>"
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >

                <p class="text-xs text-slate-400 mt-1">
                    <?= __('auth.username_hint') ?>
                </p>
            </div>

            <!-- Full Name -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.full_name') ?>
                </label>

                <input
                    type="text"
                    name="full_name"
                    required
                    maxlength="100"
                    value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.password') ?>
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >

                <p class="text-xs text-slate-400 mt-1">
                    <?= __('auth.password_hint') ?>
                </p>
            </div>

            <!-- Confirm Password -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('auth.confirm_password') ?>
                </label>

                <input
                    type="password"
                    name="password_confirm"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
            </div>

            <!-- Submit -->
            <button
                type="submit"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded px-3 py-2"
            >
                <?= __('auth.create_account_btn') ?>
            </button>

        </form>

        <p class="text-sm text-slate-500 mt-6 text-center">

            <?php if (!empty($isFirstAccount)): ?>

                <a
                    href="/login"
                    class="text-slate-700 hover:underline"
                >
                    &larr; <?= __('auth.back_to_login') ?>
                </a>

            <?php else: ?>

                <a
                    href="/dashboard"
                    class="text-slate-700 hover:underline"
                >
                    &larr; <?= __('auth.back_to_dashboard') ?>
                </a>

            <?php endif; ?>

        </p>

    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>