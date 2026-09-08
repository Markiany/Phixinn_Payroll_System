<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('nav.employees') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('employees.manage_subtitle') ?>
        </p>
    </div>

    <?php if (in_array($user['role'] ?? '', ['admin', 'payroll'], true)): ?>
        <a
            href="/employees/create"
            class="bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-700"
        >
            <?= __('employees.add_employee') ?>
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['employee_error'])): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <?= htmlspecialchars($_SESSION['employee_error']) ?>
    </div>
    <?php unset($_SESSION['employee_error']); ?>
<?php endif; ?>

<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="text-left px-4 py-3"><?= __('employees.field_employee_id') ?></th>
                    <th class="text-left px-4 py-3"><?= __('employees.col_name') ?></th>
                    <th class="text-left px-4 py-3"><?= __('employees.col_department') ?></th>
                    <th class="text-left px-4 py-3"><?= __('employees.field_employment_type') ?></th>
                    <th class="text-left px-4 py-3"><?= __('employees.field_daily_salary') ?></th>
                    <th class="text-left px-4 py-3"><?= __('employees.col_schedule') ?></th>
                    <th class="text-left px-4 py-3"><?= __('common.status') ?></th>
                    <th class="text-right px-4 py-3"><?= __('common.actions') ?></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-slate-500">
                            <?= __('employees.no_employees') ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($employees as $employee): ?>
                        <?php
                            $timeIn = $employee['schedule_time_in']
                                ? date('g:i A', strtotime($employee['schedule_time_in']))
                                : '-';
                            $timeOut = $employee['schedule_time_out']
                                ? date('g:i A', strtotime($employee['schedule_time_out']))
                                : '-';

                            $statusClass = match ($employee['status']) {
                                'Active' => 'bg-green-100 text-green-700',
                                'Suspended' => 'bg-yellow-100 text-yellow-700',
                                'Terminated' => 'bg-red-100 text-red-700',
                                default => 'bg-slate-100 text-slate-700',
                            };
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-700">
                                <?= htmlspecialchars((string) ($employee['employee_code'] ?? '-')) ?>
                            </td>

                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-800">
                                    <?= htmlspecialchars($employee['full_name']) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars($employee['department'] ?? '-') ?>
                            </td>

                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars($employee['salary_type'] ?? '-') ?>
                            </td>

                            <td class="px-4 py-3 text-slate-700">
                                ₱<?= number_format((float) $employee['salary_rate'], 2) ?>
                            </td>

                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars($timeIn) ?> - <?= htmlspecialchars($timeOut) ?>
                                <div class="text-xs text-slate-500">
                                    <?= __('employees.rest_day') ?>:
                                    <?= htmlspecialchars($employee['rest_day']) ?>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                    <?= htmlspecialchars($employee['status']) ?>
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="/employees/<?= urlencode($employee['id']) ?>/edit"
                                        class="px-3 py-1.5 rounded bg-slate-100 text-slate-700 hover:bg-slate-200"
                                    >
                                        <?= __('common.edit') ?>
                                    </a>

                                    <?php if (in_array($user['role'] ?? '', ['admin', 'payroll'], true)): ?>
                                        <?php if ($employee['status'] === 'Active'): ?>
                                            <form
                                                method="POST"
                                                action="/employees/<?= urlencode($employee['id']) ?>/deactivate"
                                                onsubmit="return confirm('<?= addslashes(__('employees.confirm_suspend')) ?>');"
                                            >
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1.5 rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200"
                                                >
                                                    <?= __('common.suspend') ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form
                                            method="POST"
                                            action="/employees/<?= urlencode($employee['id']) ?>/delete"
                                            onsubmit="return confirm('<?= addslashes(__('employees.confirm_delete')) ?>');"
                                        >
                                            <button
                                                type="submit"
                                                class="px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200"
                                            >
                                                <?= __('common.delete') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
