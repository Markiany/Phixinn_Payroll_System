<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('employees.edit_title') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('employees.edit_subtitle') ?>
        </p>
    </div>

    <a href="/employees" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200">
        <?= __('employees.back_to_employees') ?>
    </a>
</div>

<?php if (!empty($_SESSION['employee_error'])): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
        <?= htmlspecialchars($_SESSION['employee_error']) ?>
    </div>
    <?php unset($_SESSION['employee_error']); ?>
<?php endif; ?>

<form method="POST" action="/employees/<?= (int) $employee['id'] ?>">
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h2 class="text-lg font-semibold mb-5"><?= __('employees.section_info') ?></h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_employee_id') ?></label>
                <input
                    type="text"
                    name="employee_code"
                    required
                    maxlength="50"
                    value="<?= htmlspecialchars((string) ($employee['employee_code'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                <p class="text-xs text-slate-500 mt-1">
                    <?= __('employees.employee_id_hint') ?>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_first_name') ?></label>
                <input
                    type="text"
                    name="first_name"
                    required
                    value="<?= htmlspecialchars((string) ($employee['first_name'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_last_name') ?></label>
                <input
                    type="text"
                    name="last_name"
                    required
                    value="<?= htmlspecialchars((string) ($employee['last_name'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_birthdate') ?></label>
                <input
                    type="date"
                    name="birthdate"
                    value="<?= htmlspecialchars((string) ($employee['birthdate'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                <p class="text-xs text-slate-500 mt-1">
                    <?= __('employees.birthdate_hint') ?>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_department') ?></label>
                <input
                    type="text"
                    name="department"
                    value="<?= htmlspecialchars((string) ($employee['department'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_employment_type') ?></label>
                <select
                    name="employment_type"
                    required
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                    <option value="Regular" <?= ($employee['salary_type'] ?? '') === 'Regular' ? 'selected' : '' ?>><?= __('employees.field_regular') ?></option>
                    <option value="Part-Time" <?= ($employee['salary_type'] ?? '') === 'Part-Time' ? 'selected' : '' ?>><?= __('employees.field_part_time') ?></option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_daily_salary') ?></label>
                <div class="relative">
                    <span class="absolute left-3 top-2 text-slate-500">₱</span>
                    <input
                        type="number"
                        name="salary_rate"
                        min="0"
                        step="0.01"
                        required
                        value="<?= htmlspecialchars((string) ($employee['salary_rate'] ?? '0.00')) ?>"
                        class="w-full border border-slate-300 rounded-lg pl-8 pr-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                    >
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_schedule_time_in') ?></label>
                <input
                    type="time"
                    name="schedule_time_in"
                    value="<?= htmlspecialchars((string) ($employee['schedule_time_in'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_schedule_time_out') ?></label>
                <input
                    type="time"
                    name="schedule_time_out"
                    value="<?= htmlspecialchars((string) ($employee['schedule_time_out'] ?? '')) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_rest_day') ?></label>
                <select
                    name="rest_day"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                    <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','None'] as $day): ?>
                        <option value="<?= $day ?>" <?= ($employee['rest_day'] ?? 'Sunday') === $day ? 'selected' : '' ?>>
                            <?= $day ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_status') ?></label>
                <select
                    name="status"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
                    <?php foreach (['Active','Suspended','Terminated'] as $status): ?>
                        <option value="<?= $status ?>" <?= ($employee['status'] ?? 'Active') === $status ? 'selected' : '' ?>>
                            <?= $status ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1"><?= __('employees.field_effective_date') ?></label>
                <input
                    type="date"
                    name="effective_date"
                    value="<?= htmlspecialchars((string) ($employee['effective_date'] ?? date('Y-m-d'))) ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-slate-400"
                >
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-5 border-t border-slate-200">
            <a href="/employees" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200">
                <?= __('common.cancel') ?>
            </a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-700">
                <?= __('common.save_changes') ?>
            </button>
        </div>
    </div>
</form>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
