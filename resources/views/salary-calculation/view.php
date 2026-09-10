<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $currentUser = \App\Helpers\Auth::user(); ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold">
            <?= __('salary.view_title_prefix') ?>
            <?= htmlspecialchars(date('M j', strtotime($run['period_start'] ?? 'now'))) ?> -
            <?= htmlspecialchars(date('M j, Y', strtotime($run['period_end'] ?? 'now'))) ?>
        </h1>

        <p class="text-sm text-slate-500 mt-1">
            <?= __('common.status') ?>:
            <?= htmlspecialchars($run['status'] ?? 'Draft') ?>

            <?php if (!empty($department)): ?>
                &middot;
                <?= __('salary.department') ?>:
                <span class="font-medium text-slate-700">
                    <?= htmlspecialchars($department) ?>
                </span>
            <?php endif; ?>
        </p>
    </div>

    <div class="flex items-center gap-4">
        <div class="flex items-center gap-2">
            <a
                href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/export<?= !empty($department) ? '?department=' . urlencode($department) : '' ?>"
                class="bg-green-700 text-white px-4 py-2 rounded-lg hover:bg-green-800 text-sm"
            >
                <?= __('salary.download_excel') ?>
            </a>

            <?php if ((int) ($run['download_count'] ?? 0) > 0): ?>
                <span class="text-xs text-slate-400 whitespace-nowrap">
                    <?= __('common.already_downloaded') ?>
                </span>
            <?php endif; ?>
        </div>

        <a
            href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/payslips-zip<?= !empty($department) ? '?department=' . urlencode($department) : '' ?>"
            class="bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-700 text-sm"
        >
            <?= __('salary.download_payslips_zip') ?>
        </a>

        <a
            href="/salary-calculation"
            class="text-sm text-slate-500 hover:underline"
        >
            &larr; <?= __('salary.back_to_salary') ?>
        </a>
    </div>
</div>

<form
    method="GET"
    action="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>"
    class="bg-white shadow-sm border border-slate-200 rounded-lg p-4 mb-6 flex flex-wrap items-end gap-4"
>
    <div>
        <label class="block text-xs font-medium text-slate-500 mb-1">
            <?= __('salary.department') ?>
        </label>

        <select
            name="department"
            class="border border-slate-300 rounded px-3 py-1.5 text-sm min-w-[12rem]"
        >
            <option value="">
                <?= __('salary.all_departments') ?>
            </option>

            <?php if (!empty($departments) && is_array($departments)): ?>
                <?php foreach ($departments as $dept): ?>
                    <option
                        value="<?= htmlspecialchars($dept) ?>"
                        <?= ($dept === ($department ?? '')) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($dept) ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>

    <button
        type="submit"
        class="bg-slate-900 text-white px-4 py-1.5 rounded text-sm hover:bg-slate-700"
    >
        <?= __('common.filter') ?>
    </button>

    <a
        href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>"
        class="text-sm text-slate-500 hover:underline"
    >
        <?= __('common.reset') ?>
    </a>
</form>

<div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">

    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">
            <?= __('salary.total_basic_pay') ?>
        </p>

        <p class="text-xl font-semibold mt-1">
            ₱<?= number_format($totals['basic_pay'] ?? 0, 2) ?>
        </p>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">
            <?= __('salary.total_deductions') ?>
        </p>

        <p class="text-xl font-semibold mt-1">
            ₱<?= number_format($totals['total_deduction'] ?? 0, 2) ?>
        </p>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <p class="text-xs text-slate-500 uppercase tracking-wide">
            <?= __('salary.employees') ?>
        </p>

        <p class="text-xl font-semibold mt-1">
            <?= count($lines ?? []) ?>
        </p>
    </div>

</div>

<div class="bg-white shadow-sm border border-slate-200 rounded-lg overflow-hidden">

    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[1200px]">

            <thead class="bg-slate-50 text-slate-500 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">
                        <?= __('common.employee') ?>
                    </th>

                    <th class="px-4 py-2 font-medium">
                        <?= __('salary.department') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.basic') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.ot') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.allowance') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.late_ded') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.undertime_ded') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.deductions') ?>
                    </th>

                    <th class="px-4 py-2 font-medium text-right">
                        <?= __('salary.col_payslip') ?>
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">

                <?php if (!empty($lines) && is_array($lines)): ?>
                    <?php foreach ($lines as $l): ?>

                        <?php
                        $hasCalculableAttendance =
                            !empty($l['has_calculable_attendance']);

                        $hasIncompleteAttendance =
                            !empty($l['has_incomplete_attendance']);

                        $money = static function ($value): string {
                            return '₱' . number_format((float) $value, 2);
                        };
                        ?>

                        <tr class="hover:bg-slate-50">

                            <td class="px-4 py-3 font-medium text-slate-800">
                                <a
                                    href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/employee/<?= (int) ($l['employee_id'] ?? 0) ?>"
                                    class="text-slate-800 hover:underline"
                                >
                                    <?= htmlspecialchars($l['employee_name'] ?? 'N/A') ?>
                                </a>
                            </td>

                            <td class="px-4 py-3 text-slate-600">
                                <?= htmlspecialchars($l['department'] ?? '-') ?>
                            </td>

                            <!-- No Time Out = no salary value yet -->
                            <td class="px-4 py-3 text-right text-slate-600">
                                <?= $hasCalculableAttendance
                                    ? $money($l['basic_pay'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right text-slate-600">
                                <?= $hasCalculableAttendance
                                    ? $money($l['overtime_pay'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right text-slate-600">
                                <?= $hasCalculableAttendance
                                    ? $money($l['allowances'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right text-red-600">
                                <?= $hasCalculableAttendance
                                    ? $money($l['late_deduction'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right text-red-600">
                                <?= $hasCalculableAttendance
                                    ? $money($l['undertime_deduction'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right text-red-700">
                                <?= $hasCalculableAttendance
                                    ? $money($l['total_deduction'] ?? 0)
                                    : '—' ?>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2 whitespace-nowrap">

                                    <?php if ($hasCalculableAttendance): ?>

                                        <a
                                            href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/payslip/<?= (int) ($l['employee_id'] ?? 0) ?>"
                                            class="inline-flex items-center justify-center gap-1.5 bg-green-700 text-white px-3 py-1.5 rounded-md hover:bg-green-800 text-xs font-medium whitespace-nowrap min-w-[100px]"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="w-3.5 h-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h7l5 5v11a2 2 0 01-2-2z"
                                                />
                                            </svg>

                                            <?= __('salary.download') ?>
                                        </a>

                                        <a
                                            href="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/payslip/<?= (int) ($l['employee_id'] ?? 0) ?>/download"
                                            class="inline-flex items-center justify-center gap-1.5 bg-slate-900 text-white px-3 py-1.5 rounded-md hover:bg-slate-700 text-xs font-medium whitespace-nowrap min-w-[120px]"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="w-3.5 h-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-4-7h.01M5 20h14a1 1 0 001-1v14a1 1 0 001 1z"
                                                />
                                            </svg>

                                            <?= __('salary.payslip_image') ?>
                                        </a>

                                    <?php elseif ($hasIncompleteAttendance): ?>

                                        <span class="text-xs text-amber-600 font-medium">
                                            Waiting for Time Out
                                        </span>

                                    <?php else: ?>

                                        <span class="text-xs text-slate-400">
                                            No completed attendance
                                        </span>

                                    <?php endif; ?>

                                    <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                                        <form
                                            method="POST"
                                            action="/salary-calculation/<?= (int) ($run['id'] ?? 0) ?>/line/<?= (int) ($l['id'] ?? 0) ?>/delete"
                                            class="inline"
                                            onsubmit="return confirm('Delete salary calculation for <?= htmlspecialchars($l['employee_name'] ?? 'Employee', ENT_QUOTES) ?> from this payroll run? This cannot be undone.');"
                                        >
                                            <?php if (!empty($department)): ?>
                                                <input
                                                    type="hidden"
                                                    name="department"
                                                    value="<?= htmlspecialchars($department) ?>"
                                                >
                                            <?php endif; ?>

                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center gap-1.5 bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 text-xs font-medium whitespace-nowrap min-w-[80px]"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="w-3.5 h-3.5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M6 7h12m-10 0v10m4-10v10m4-10v10M9 7V4h6v3m-9 0h12l-1 13H7L6 7z"
                                                    />
                                                </svg>

                                                <?= __('common.delete') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>

                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (empty($lines)): ?>
                    <tr>
                        <td
                            colspan="9"
                            class="px-4 py-6 text-center text-slate-400"
                        >
                            <?= __('salary.no_employee_data') ?>
                        </td>
                    </tr>
                <?php endif; ?>

            </tbody>

        </table>
    </div>

</div>

<p class="mt-4 text-xs text-slate-400">
    <?= __('salary.tip_note') ?>
</p>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
