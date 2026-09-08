<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('settings.title') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('settings.subtitle') ?>
        </p>
    </div>

    <div class="flex items-center gap-2">
        <a
            href="/settings/holidays"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-slate-800 rounded-md hover:bg-slate-700 transition"
        >
            <?= __('settings.manage_holidays') ?>
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<!-- Users table -->
<div class="bg-white shadow-sm border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr>
                <th class="px-5 py-2 font-medium"><?= __('settings.col_username') ?></th>
                <th class="px-5 py-2 font-medium"><?= __('settings.col_full_name') ?></th>
                <th class="px-5 py-2 font-medium"><?= __('settings.col_role') ?></th>
                <th class="px-5 py-2 font-medium"><?= __('common.status') ?></th>
                <th class="px-5 py-2 font-medium"><?= __('settings.col_last_login') ?></th>
                <th class="px-5 py-2 font-medium text-right"><?= __('common.actions') ?></th>
            </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800">
                        <?= htmlspecialchars($u['username']) ?>
                    </td>

                    <td class="px-5 py-3 text-slate-700">
                        <?= htmlspecialchars($u['full_name']) ?>
                    </td>

                    <td class="px-5 py-3 text-slate-600">
                        <?= htmlspecialchars($u['role']) ?>
                    </td>

                    <td class="px-5 py-3">
                        <?php if ((int) $u['is_active'] === 1): ?>
                            <span class="text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5">
                                <?= __('common.status.active') ?>
                            </span>
                        <?php else: ?>
                            <span class="text-xs bg-red-50 text-red-700 border border-red-200 rounded-full px-2 py-0.5">
                                <?= __('common.status.inactive') ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="px-5 py-3 text-slate-600">
                        <?= !empty($u['last_login'])
                            ? htmlspecialchars(date('M j, Y h:i A', strtotime($u['last_login'])))
                            : '—'
                        ?>
                    </td>

                    <td class="px-5 py-3 text-right">
                        <a
                            href="/settings/users/<?= (int) $u['id'] ?>/edit"
                            class="text-sm text-slate-600 hover:underline"
                        >
                            <?= __('common.edit') ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($users)): ?>
                <tr>
                    <td colspan="6" class="px-5 py-6 text-center text-slate-400">
                        <?= __('settings.no_users') ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>