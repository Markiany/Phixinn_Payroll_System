<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('holidays.title') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('holidays.subtitle') ?>
        </p>
    </div>
    <a href="/settings" class="text-sm text-slate-500 hover:underline">&larr; Back to Settings</a>
</div>

<?php if (!empty($error)): ?>
    <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-1 bg-white shadow-sm border border-slate-200 rounded-lg p-5">
        <h2 class="text-lg font-semibold mb-1">
            <?= $editHoliday ? 'Edit Holiday' : 'Add Holiday' ?>
        </h2>
        <p class="text-xs text-slate-500 mb-4">
            <?= __('holidays.date_hint') ?>
        </p>

        <form
            method="POST"
            action="<?= $editHoliday
                ? '/settings/holidays/' . (int) $editHoliday['id']
                : '/settings/holidays' ?>"
            class="space-y-4"
        >
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= __('holidays.name') ?></label>
                <input
                    type="text"
                    name="holiday_name"
                    required
                    maxlength="150"
                    value="<?= htmlspecialchars($editHoliday['holiday_name'] ?? '') ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="e.g. Christmas Day"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= __('holidays.date') ?></label>
                <input
                    type="date"
                    name="holiday_date"
                    required
                    value="<?= htmlspecialchars($editHoliday['holiday_date'] ?? '') ?>"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= __('holidays.type') ?></label>
                <select
                    name="holiday_type"
                    required
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                >
                    <?php
                    $selectedType = $editHoliday['holiday_type'] ?? '';
                    $types = [
                        'Regular Holiday',
                        'Special Non-Working Holiday',
                        'Special Working Holiday',
                    ];
                    ?>
                    <option value=""><?= __('holidays.select_type') ?></option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1"><?= __('holidays.description') ?></label>
                <textarea
                    name="description"
                    rows="3"
                    maxlength="500"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm"
                    placeholder="Optional notes"
                ><?= htmlspecialchars($editHoliday['description'] ?? '') ?></textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?= !isset($editHoliday['is_active']) || (int) $editHoliday['is_active'] === 1 ? 'checked' : '' ?>
                    class="rounded border-slate-300"
                >
                <?= __('holidays.active_holiday') ?>
            </label>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    class="bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-700 text-sm"
                >
                    <?= $editHoliday ? 'Update Holiday' : 'Add Holiday' ?>
                </button>

                <?php if ($editHoliday): ?>
                    <a
                        href="/settings/holidays"
                        class="px-4 py-2 rounded-lg border border-slate-300 text-sm text-slate-600 hover:bg-slate-50"
                    >
                        <?= __('common.cancel') ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="lg:col-span-2 bg-white shadow-sm border border-slate-200 rounded-lg overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200">
            <h2 class="font-semibold"><?= __('holidays.calendar') ?></h2>
            <p class="text-xs text-slate-500 mt-1">
                <?= __('holidays.match_hint') ?>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-5 py-3 font-medium"><?= __('holidays.holiday') ?></th>
                        <th class="px-5 py-3 font-medium"><?= __('holidays.date') ?></th>
                        <th class="px-5 py-3 font-medium"><?= __('holidays.type') ?></th>
                        <th class="px-5 py-3 font-medium"><?= __('holidays.status') ?></th>
                        <th class="px-5 py-3 font-medium text-right"><?= __('holidays.actions') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($holidays as $holiday): ?>
                        <?php
                        $type = (string) $holiday['holiday_type'];
                        $typeClass = $type === 'Regular Holiday'
                            ? 'bg-blue-50 text-blue-700 border-blue-200'
                            : ($type === 'Special Non-Working Holiday'
                                ? 'bg-purple-50 text-purple-700 border-purple-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200');
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-slate-800">
                                    <?= htmlspecialchars($holiday['holiday_name']) ?>
                                </div>
                                <?php if (!empty($holiday['description'])): ?>
                                    <div class="text-xs text-slate-400 mt-0.5">
                                        <?= htmlspecialchars($holiday['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                <?= htmlspecialchars(date('M j, Y', strtotime($holiday['holiday_date']))) ?>
                            </td>
                            <td class="px-5 py-3">
                                <span class="text-xs border rounded-full px-2 py-1 <?= $typeClass ?>">
                                    <?= htmlspecialchars($type) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <?php if ((int) $holiday['is_active'] === 1): ?>
                                    <span class="text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-1"><?= __('holidays.active') ?></span>
                                <?php else: ?>
                                    <span class="text-xs bg-slate-50 text-slate-500 border border-slate-200 rounded-full px-2 py-1"><?= __('common.status.inactive') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a
                                    href="/settings/holidays?edit=<?= (int) $holiday['id'] ?>"
                                    class="text-sm text-slate-600 hover:underline mr-3"
                                ><?= __('holidays.edit') ?></a>

                                <form
                                    method="POST"
                                    action="/settings/holidays/<?= (int) $holiday['id'] ?>/delete"
                                    class="inline"
                                    onsubmit="return confirm('Delete this holiday?');"
                                >
                                    <button type="submit" class="text-sm text-red-600 hover:underline">
                                        <?= __('holidays.delete') ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($holidays)): ?>
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-slate-400">
                                <?= __('holidays.empty') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
