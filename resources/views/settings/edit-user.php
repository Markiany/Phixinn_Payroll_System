<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('settings.edit_user_title') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('settings.edit_user_subtitle') ?>
        </p>
    </div>

    <a
        href="/settings"
        class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200"
    >
        <?= __('settings.back_to_settings') ?>
    </a>
</div>

<?php if (!empty($error)): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<form method="POST" action="/settings/users/<?= (int) $editUser['id'] ?>">

    <div class="bg-white border border-slate-200 rounded-lg p-6">

        <h2 class="text-lg font-semibold mb-5"><?= __('settings.section_account') ?></h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <!-- Username -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('settings.field_username') ?>
                </label>

                <input
                    type="text"
                    name="username"
                    value="<?= htmlspecialchars((string) ($editUser['username'] ?? '')) ?>"
                    required
                    maxlength="50"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <!-- Full Name -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('settings.field_full_name') ?>
                </label>

                <input
                    type="text"
                    name="full_name"
                    value="<?= htmlspecialchars((string) ($editUser['full_name'] ?? '')) ?>"
                    required
                    maxlength="100"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <!-- Role (read-only - every account is admin, see SettingsController::updateUser) -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    <?= __('settings.field_role') ?>
                </label>

                <input
                    type="text"
                    value="<?= htmlspecialchars((string) ($editUser['role'] ?? 'admin')) ?>"
                    disabled
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-slate-50 text-slate-500"
                >
            </div>

            <!-- Active -->
            <div class="flex items-center gap-2 md:pt-6">
                <input
                    type="checkbox"
                    id="is_active"
                    name="is_active"
                    value="1"
                    <?= (int) ($editUser['is_active'] ?? 1) === 1 ? 'checked' : '' ?>
                    class="rounded border-slate-300"
                >
                <label for="is_active" class="text-sm text-slate-700">
                    <?= __('settings.field_active') ?>
                </label>
            </div>

        </div>

        <p class="text-xs text-slate-500 mt-2">
            <?= __('settings.field_active_hint') ?>
        </p>

        <!-- Password -->
        <div class="mt-6 pt-5 border-t border-slate-200">

            <h3 class="text-sm font-semibold text-slate-700 mb-1">
                <?= __('settings.section_password') ?>
            </h3>

            <p class="text-xs text-slate-500 mb-4">
                <?= __('settings.password_hint') ?>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        <?= __('settings.field_new_password') ?>
                    </label>
                    <input
                        type="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        <?= __('settings.field_confirm_password') ?>
                    </label>
                    <input
                        type="password"
                        name="password_confirm"
                        minlength="8"
                        autocomplete="new-password"
                        class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                </div>

            </div>

        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-200">

            <a
                href="/settings"
                class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200"
            >
                <?= __('common.cancel') ?>
            </a>

            <button
                type="submit"
                class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-700"
            >
                <?= __('common.save_changes') ?>
            </button>

        </div>

    </div>

</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>