<?php require __DIR__ . '/../layouts/header.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold"><?= __('salary.title') ?></h1>
        <p class="text-sm text-slate-500 mt-1">
            <?= __('salary.calculation_subtitle') ?>
        </p>
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

<!-- Generate new salary calculation -->
<form method="POST" action="/salary-calculation/generate" class="bg-white shadow-sm border border-slate-200 rounded-lg p-4 mb-6 flex flex-wrap items-end gap-4">
    <div>
        <label for="period_start" class="block text-xs font-medium text-slate-500 mb-1"><?= __('common.from_date') ?></label>
        <input
            id="period_start"
            type="date"
            name="period_start"
            value="<?= htmlspecialchars($defaultFrom ?? '') ?>"
            class="border border-slate-300 rounded px-3 py-1.5 text-sm"
            required
        >
    </div>

    <div>
        <label for="period_end" class="block text-xs font-medium text-slate-500 mb-1"><?= __('common.to_date') ?></label>
        <input
            id="period_end"
            type="date"
            name="period_end"
            value="<?= htmlspecialchars($defaultTo ?? '') ?>"
            class="border border-slate-300 rounded px-3 py-1.5 text-sm"
            required
        >
    </div>

    <button
        type="submit"
        class="bg-slate-900 text-white px-4 py-1.5 rounded text-sm hover:bg-slate-700"
    >
        <?= __('salary.generate_calculation') ?>
    </button>

    <p class="text-xs text-slate-400 pb-2">
        <?= __('salary.calculation_period_hint') ?>
    </p>
</form>

<!-- Salary calculation runs -->
<div class="bg-white shadow-sm border border-slate-200 rounded-lg overflow-hidden">
    <table class="w-full text-sm table-fixed">
        <thead class="bg-slate-50 text-slate-500 text-left">
            <tr>
                <th class="px-5 py-2 font-medium w-[24%]"><?= __('salary.period') ?></th>
                <th class="px-5 py-2 font-medium w-[12%]"><?= __('salary.employees') ?></th>
                <th class="px-5 py-2 font-medium w-[16%]"><?= __('salary.total_net_pay') ?></th>
                <th class="px-5 py-2 font-medium w-[12%]"><?= __('common.status') ?></th>
                <th class="px-5 py-2 font-medium w-[18%]"><?= __('salary.generated') ?></th>
                <th class="px-5 py-2 font-medium text-right w-[18%]"><?= __('common.actions') ?></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td class="px-5 py-3 font-medium text-slate-800 whitespace-nowrap">
                        <?= htmlspecialchars(date('M j, Y', strtotime($r['period_start']))) ?>
                        -
                        <?= htmlspecialchars(date('M j, Y', strtotime($r['period_end']))) ?>
                    </td>
                    <td class="px-5 py-3 text-slate-600">
                        <?= (int) $r['employee_count'] ?>
                    </td>
                    <td class="px-5 py-3 text-slate-700 font-medium">
                        ₱<?= number_format((float) $r['total_net_pay'], 2) ?>
                    </td>
                    <td class="px-5 py-3">
                        <?php
                            $statusColors = [
                                'Draft'     => 'bg-amber-50 text-amber-700 border-amber-200',
                                'Finalized' => 'bg-blue-50 text-blue-700 border-blue-200',
                                'Approved'  => 'bg-green-50 text-green-700 border-green-200',
                            ];
                            $color = $statusColors[$r['status']] ?? 'bg-slate-100 text-slate-600 border-slate-200';
                        ?>
                        <span class="text-xs <?= $color ?> border rounded-full px-2 py-0.5">
                            <?= htmlspecialchars($r['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-slate-500">
                        <?= htmlspecialchars(date('M j, Y h:i A', strtotime($r['created_at']))) ?>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-3">
                            <a href="/salary-calculation/<?= (int) $r['id'] ?>" class="text-sm text-slate-600 hover:underline">
                                <?= __('common.view') ?>
                            </a>

                            <a
                                href="/salary-calculation/<?= (int) $r['id'] ?>/export"
                                class="text-sm text-green-700 hover:underline"
                            >
                                <?= __('salary.excel') ?>
                            </a>

                            <?php if ((int) ($r['download_count'] ?? 0) > 0): ?>
                                <span class="text-xs text-slate-400">
                                    <?= __('salary.downloaded') ?> <?= (int)$r['download_count'] ?>x
                                </span>
                            <?php endif; ?>

                            <?php if (($userRole ?? '') === 'admin'): ?>
                                <form
                                    method="POST"
                                    action="/salary-calculation/<?= (int) $r['id'] ?>/delete"
                                    class="inline"
                                    onsubmit="return confirm('Delete this salary calculation for <?= htmlspecialchars(date('M j, Y', strtotime($r['period_start'])), ENT_QUOTES) ?> - <?= htmlspecialchars(date('M j, Y', strtotime($r['period_end'])), ENT_QUOTES) ?>? This cannot be undone.');"
                                >
                                    <button
                                        type="submit"
                                        class="text-sm text-red-600 hover:text-red-800 hover:underline"
                                    >
                                        <?= __('common.delete') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (empty($runs)): ?>
                <tr>
                    <td colspan="6" class="px-5 py-6 text-center text-slate-400">
                        <?= __('salary.no_runs') ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>