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

<div class="bg-white shadow-sm border border-slate-200 rounded-lg overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
        <div>
            <h2 class="text-base font-semibold text-slate-800">Department Overtime Settings</h2>
            <p class="text-xs text-slate-500 mt-1">
                Select which departments are allowed to earn Early / Morning OT.
            </p>
        </div>
    </div>

    <form method="POST" action="/settings/departments/overtime">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-5 py-2 font-medium">Department Name</th>
                    <th class="px-5 py-2 font-medium">Department Code</th>
                    <th class="px-5 py-2 font-medium text-right">Morning Person OT</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                <?php
                    $allowedMorningOT = array_values(array_filter(
                        ($departments ?? []),
                        static fn ($dept): bool => (int) ($dept['allow_early_ot'] ?? 0) === 1
                    ));
                ?>

                <?php foreach (($departments ?? []) as $dept): ?>
                    <tr>
                        <td class="px-5 py-3 font-medium text-slate-800">
                            <?= htmlspecialchars($dept['department_name']) ?>
                        </td>

                        <td class="px-5 py-3 text-slate-600">
                            <?= htmlspecialchars($dept['department_code'] ?? '—') ?>
                        </td>

                        <?php if ((int) ($dept['allow_early_ot'] ?? 0) === 1): ?>
                            <td class="px-5 py-3 text-right text-green-700 font-medium">
                                Allowed
                            </td>
                        <?php else: ?>
                            <td class="px-5 py-3 text-right text-slate-500">
                                Not Allowed
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($departments)): ?>
                    <tr>
                        <td colspan="3" class="px-5 py-6 text-center text-slate-400">
                            No departments found from the Employees records.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($departments)): ?>
            <div class="px-5 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
                <details class="relative">
                    <summary class="list-none cursor-pointer inline-flex items-center justify-between gap-3 min-w-[250px] px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">
                        <span>
                            Morning Person OT
                            <span class="text-xs text-slate-400 ml-1">
                                (<?= count($allowedMorningOT) ?> allowed)
                            </span>
                        </span>
                        <span class="text-slate-400">▼</span>
                    </summary>

                    <div class="absolute right-0 bottom-full mb-2 z-20 w-[360px] max-h-[420px] overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg p-4">
                        <div class="mb-3">
                            <div class="text-sm font-semibold text-slate-800">Morning Person OT</div>
                            <div class="text-xs text-slate-500 mt-1">
                                Check the departments that are allowed to receive Morning / Early OT.
                            </div>
                        </div>

                        <div class="space-y-2">
                            <?php foreach (($departments ?? []) as $dept): ?>
                                <?php $deptId = (int) ($dept['id'] ?? 0); ?>
                                <label class="flex items-center justify-between gap-3 px-3 py-2 rounded-md hover:bg-slate-50 cursor-pointer">
                                    <span class="min-w-0">
                                        <span class="block text-sm text-slate-700 truncate">
                                            <?= htmlspecialchars($dept['department_name']) ?>
                                        </span>
                                        <?php if (!empty($dept['department_code'])): ?>
                                            <span class="block text-xs text-slate-400">
                                                <?= htmlspecialchars($dept['department_code']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>

                                    <input
                                        type="checkbox"
                                        name="allowed_department_ids[]"
                                        value="<?= $deptId ?>"
                                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        <?= (int) ($dept['allow_early_ot'] ?? 0) === 1 ? 'checked' : '' ?>
                                    >
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-200 flex justify-end">
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-slate-800 rounded-md hover:bg-slate-700 transition"
                            >
                                Save
                            </button>
                        </div>
                    </div>
                </details>
            </div>
        <?php endif; ?>
    </form>
</div>

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
