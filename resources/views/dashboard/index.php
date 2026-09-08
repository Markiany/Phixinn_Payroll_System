<?php require __DIR__ . '/../layouts/header.php'; ?>

<style>
/* =========================================================
   PHIXINN PAYROLL SYSTEM — DASHBOARD UI REFRESH
   Keeps the existing purple theme; layout/features inspired
   by the supplied HR dashboard reference.
   ========================================================= */

:root {
    --phixinn-purple: #5b4ce2;
    --phixinn-purple-dark: #4f46d8;
    --phixinn-purple-soft: #f0edff;
    --phixinn-purple-light: #eeeaff;
    --phixinn-purple-border: #d9d2ff;

    --dashboard-bg: #f4f7fb;
    --dashboard-border: #e7eaf0;
    --dashboard-text: #111827;
    --dashboard-muted: #6b7280;
}

/* =========================================================
   PAGE / HEADER
   ========================================================= */

.dashboard-page {
    width: 100%;
    max-width: 100%;
}

.dashboard-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 18px 20px;
    margin-bottom: 16px;
    background: linear-gradient(135deg, #5b4ce2 0%, #6b5ce8 100%);
    border-radius: 12px;
    box-shadow: 0 10px 24px rgba(91, 76, 226, .15);
}

.dashboard-header-copy h1 {
    margin: 0;
    color: #fff;
    font-size: 23px;
    line-height: 1.2;
    font-weight: 750;
}

.dashboard-header-copy p {
    margin: 5px 0 0;
    color: #fff;
    opacity: .82;
    font-size: 11px;
}

/* =========================================================
   DATE PICKER
   ========================================================= */

.dashboard-date-picker {
    position: relative;
    z-index: 30;
}

.date-picker-trigger {
    min-width: 218px;
    height: 39px;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 0 12px;
    background: #fff;
    border: 1px solid rgba(255,255,255,.7);
    border-radius: 8px;
    color: #374151;
    font-size: 11px;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(31,25,100,.12);
}

.date-picker-trigger:hover { border-color: #fff; }

.date-picker-trigger-left {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}

.date-picker-icon {
    color: var(--phixinn-purple);
    font-size: 14px;
}

.date-picker-chevron {
    color: #6b7280;
    font-size: 14px;
}

.date-picker-popup {
    display: none;
    position: absolute;
    z-index: 1000;
    top: calc(100% + 8px);
    right: 0;
    width: 700px;
    max-width: calc(100vw - 30px);
    background: #fff;
    border: 1px solid var(--phixinn-purple-border);
    border-radius: 12px;
    box-shadow: 0 22px 55px rgba(15,23,42,.18);
    overflow: hidden;
}

.date-picker-popup.open { display: block; }

.date-picker-popup-header {
    padding: 14px 18px;
    background: linear-gradient(135deg, #5b4ce2 0%, #6b5ce8 100%);
    color: #fff;
}

.date-picker-popup-title {
    font-size: 12px;
    font-weight: 700;
}

.date-picker-popup-subtitle {
    margin-top: 3px;
    font-size: 10px;
    opacity: .84;
}

.date-picker-body {
    display: grid;
    grid-template-columns: 145px minmax(0,1fr);
}

.date-shortcuts {
    padding: 13px 10px;
    border-right: 1px solid #eceaf8;
    background: #fbfaff;
}

.date-shortcut {
    width: 100%;
    border: 0;
    background: transparent;
    text-align: left;
    padding: 9px 11px;
    border-radius: 7px;
    color: #374151;
    font-size: 11px;
    cursor: pointer;
}

.date-shortcut:hover,
.date-shortcut.active {
    background: var(--phixinn-purple-soft);
    color: var(--phixinn-purple-dark);
    font-weight: 700;
}

.calendar-area { padding: 15px 17px; }

.calendar-toolbar {
    display: grid;
    grid-template-columns: 32px minmax(0,1fr) 32px;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}

.calendar-months {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.calendar-month {
    text-align: center;
    color: #1f2937;
    font-size: 13px;
    font-weight: 700;
}

.calendar-nav {
    width: 30px;
    height: 30px;
    border: 0;
    background: transparent;
    border-radius: 7px;
    color: #6b7280;
    cursor: pointer;
    font-size: 21px;
}

.calendar-nav:hover {
    background: var(--phixinn-purple-soft);
    color: var(--phixinn-purple);
}

.calendar-panels {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7,1fr);
    gap: 3px;
}

.calendar-weekday {
    padding: 5px 0;
    text-align: center;
    color: #6b7280;
    font-size: 9px;
    font-weight: 700;
}

.calendar-day {
    position: relative;
    min-height: 31px;
    border: 0;
    background: transparent;
    border-radius: 7px;
    color: #374151;
    font-size: 10px;
    cursor: pointer;
}

.calendar-day:hover {
    background: var(--phixinn-purple-soft);
    color: var(--phixinn-purple-dark);
}

.calendar-day.muted { color: #cbd5e1; }
.calendar-day.today { box-shadow: inset 0 0 0 1px #b8affb; }

.calendar-day.start,
.calendar-day.end {
    background: var(--phixinn-purple);
    color: #fff;
    font-weight: 700;
}

.calendar-day.in-range {
    background: var(--phixinn-purple-light);
    color: #4738bd;
    border-radius: 0;
}

.calendar-day.start { border-radius: 7px 0 0 7px; }
.calendar-day.end { border-radius: 0 7px 7px 0; }
.calendar-day.start.end { border-radius: 7px; }

.date-picker-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 16px;
    border-top: 1px solid #eceaf8;
}

.date-picker-cancel,
.date-picker-apply {
    height: 34px;
    border-radius: 7px;
    padding: 0 16px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.date-picker-cancel {
    border: 1px solid #d1d5db;
    background: #fff;
    color: #374151;
}

.date-picker-apply {
    border: 1px solid var(--phixinn-purple);
    background: var(--phixinn-purple);
    color: #fff;
}

/* =========================================================
   STAT CARDS — reference-style compact cards
   ========================================================= */

.dashboard-stat-grid {
    display: grid;
    grid-template-columns: repeat(5,minmax(0,1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.dashboard-stat-card {
    position: relative;
    min-height: 108px;
    padding: 15px 16px;
    background: #fff;
    border: 1px solid var(--dashboard-border);
    border-radius: 11px;
    box-shadow: 0 3px 12px rgba(15,23,42,.035);
    overflow: hidden;
}

.dashboard-stat-card::after {
    content: "";
    position: absolute;
    right: -18px;
    bottom: -25px;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: var(--phixinn-purple-soft);
    opacity: .55;
}

.dashboard-stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.dashboard-stat-icon {
    width: 30px;
    height: 30px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--phixinn-purple-soft);
    color: var(--phixinn-purple-dark);
    font-size: 13px;
    font-weight: 800;
}

.dashboard-stat-label {
    margin-top: 11px;
    color: var(--dashboard-muted);
    font-size: 10px;
    font-weight: 600;
}

.dashboard-stat-value {
    margin-top: 3px;
    color: var(--dashboard-text);
    font-size: 25px;
    line-height: 1;
    font-weight: 750;
}

/* =========================================================
   CARDS
   ========================================================= */

.dashboard-main-grid {
    display: grid;
    grid-template-columns: minmax(0,1.65fr) minmax(285px,.75fr);
    gap: 16px;
    margin-bottom: 16px;
    align-items: start;
}

.dashboard-card {
    background: #fff;
    border: 1px solid var(--dashboard-border);
    border-radius: 11px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(15,23,42,.035);
}

.dashboard-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 17px 12px;
    border-bottom: 1px solid #f0f2f5;
}

.dashboard-card-title {
    margin: 0;
    color: var(--dashboard-text);
    font-size: 14px;
    font-weight: 750;
}

.dashboard-card-subtitle {
    margin: 4px 0 0;
    color: #9ca3af;
    font-size: 10px;
}

/* =========================================================
   ATTENDANCE PERFORMANCE
   ========================================================= */

.chart-body { padding: 14px 17px 17px; }

.chart-summary {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 8px;
    margin-bottom: 14px;
}

.chart-stat {
    padding: 9px 10px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
    border-radius: 8px;
}

.chart-stat-label {
    color: #6b7280;
    font-size: 9px;
    font-weight: 600;
}

.chart-stat-value {
    margin-top: 3px;
    color: #111827;
    font-size: 17px;
    font-weight: 750;
}

.performance-chart {
    padding-top: 4px;
}

.performance-row {
    display: grid;
    grid-template-columns: 62px minmax(0,1fr) 38px;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}

.performance-row:last-child { margin-bottom: 0; }

.performance-label {
    color: #4b5563;
    font-size: 10px;
    font-weight: 650;
}

.performance-track {
    height: 10px;
    background: #eef2f7;
    border-radius: 999px;
    overflow: hidden;
}

.performance-fill {
    height: 100%;
    min-width: 3px;
    border-radius: 999px;
}

.performance-fill.present { background: #22c55e; }
.performance-fill.late { background: #f59e0b; }
.performance-fill.absent { background: #ef4444; }

.performance-number {
    color: #374151;
    font-size: 10px;
    font-weight: 750;
    text-align: right;
}

.performance-footer {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 17px;
    padding-top: 12px;
    border-top: 1px solid #f1f5f9;
}

.performance-legend {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    color: #6b7280;
    font-size: 9px;
}

.performance-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
}

.performance-dot.present { background:#22c55e; }
.performance-dot.late { background:#f59e0b; }
.performance-dot.absent { background:#ef4444; }

/* =========================================================
   PAYROLL CARD — compact reference-style information panel
   ========================================================= */

.payroll-content { padding: 16px 17px 17px; }

.payroll-highlight {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px;
    background: var(--phixinn-purple-soft);
    border: 1px solid var(--phixinn-purple-border);
    border-radius: 9px;
}

.payroll-label {
    color: var(--phixinn-purple-dark);
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
}

.payroll-period {
    margin-top: 4px;
    color: #111827;
    font-size: 14px;
    line-height: 1.35;
    font-weight: 750;
}

.payroll-status {
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
    padding: 5px 8px;
    border-radius: 999px;
    background: #fff;
    color: var(--phixinn-purple-dark);
    border: 1px solid var(--phixinn-purple-border);
    font-size: 8px;
    font-weight: 700;
}

.payroll-mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 11px;
}

.payroll-mini {
    padding: 10px;
    border: 1px solid #eef2f7;
    border-radius: 8px;
    background: #fafbfc;
}

.payroll-mini-label {
    color: #9ca3af;
    font-size: 8px;
    text-transform: uppercase;
    font-weight: 700;
}

.payroll-mini-value {
    margin-top: 4px;
    color: #374151;
    font-size: 10px;
    font-weight: 650;
}

.payroll-note {
    margin-top: 11px;
    color: #9ca3af;
    font-size: 9px;
    line-height: 1.5;
}

/* =========================================================
   HOLIDAYS / BIRTHDAYS
   ========================================================= */

.dashboard-lower-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.month-list { padding: 3px 17px 8px; }

.month-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
}

.month-item:last-child { border-bottom: 0; }

.month-date {
    width: 45px;
    flex: 0 0 45px;
    text-align: center;
    padding: 6px 4px;
    background: var(--phixinn-purple-soft);
    border-radius: 7px;
}

.month-date-day {
    color: var(--phixinn-purple-dark);
    font-size: 14px;
    font-weight: 750;
    line-height: 1;
}

.month-date-month {
    margin-top: 3px;
    color: #6b7280;
    font-size: 8px;
    text-transform: uppercase;
    font-weight: 700;
}

.month-item-title {
    color: #374151;
    font-size: 11px;
    font-weight: 650;
}

.month-item-meta {
    margin-top: 3px;
    color: #9ca3af;
    font-size: 9px;
}

.month-empty {
    padding: 20px 0;
    color: #9ca3af;
    text-align: center;
    font-size: 10px;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width:1100px) {
    .dashboard-stat-grid { grid-template-columns: repeat(3,1fr); }
    .dashboard-main-grid { grid-template-columns: 1fr; }
}

@media (max-width:800px) {
    .dashboard-header-bar {
        align-items: stretch;
        flex-direction: column;
    }

    .date-picker-trigger { width: 100%; }

    .date-picker-popup {
        left: 0;
        right: auto;
    }

    .dashboard-lower-grid { grid-template-columns:1fr; }
}

@media (max-width:650px) {
    .dashboard-stat-grid { grid-template-columns:repeat(2,1fr); }
    .chart-summary { grid-template-columns:repeat(2,1fr); }
    .date-picker-body { grid-template-columns:1fr; }
    .date-shortcuts {
        display:grid;
        grid-template-columns:repeat(2,1fr);
        border-right:0;
        border-bottom:1px solid #eceaf8;
    }
    .calendar-panels { grid-template-columns:1fr; }
    .calendar-months { grid-template-columns:1fr; }
    .calendar-month:nth-child(2),
    .calendar-panels > div:nth-child(2) { display:none; }
}

@media (max-width:430px) {
    .dashboard-stat-grid { grid-template-columns:1fr 1fr; gap:8px; }
    .dashboard-stat-card { padding:12px; }
    .performance-row {
        grid-template-columns:55px minmax(0,1fr) 30px;
        gap:7px;
    }
}
</style>

<div class="dashboard-page">

    <!-- =====================================================
         HEADER
         ===================================================== -->
    <div class="dashboard-header dashboard-header-bar">
        <div class="dashboard-header-copy">
            <h1><?= __('nav.dashboard') ?></h1>
            <p><?= __('dashboard.overview_subtitle') ?></p>
        </div>

        <div class="dashboard-date-picker" id="dashboardDatePicker">
            <button type="button" class="date-picker-trigger" id="datePickerTrigger" aria-expanded="false">
                <span class="date-picker-trigger-left">
                    <span class="date-picker-icon">▣</span>
                    <span id="datePickerLabel">
                        <?= htmlspecialchars($dateFrom) ?> – <?= htmlspecialchars($dateTo) ?>
                    </span>
                </span>
                <span class="date-picker-chevron">⌄</span>
            </button>

            <div class="date-picker-popup" id="datePickerPopup">
                <div class="date-picker-popup-header">
                    <div class="date-picker-popup-title"><?= __('dashboard.select_date_range') ?></div>
                    <div class="date-picker-popup-subtitle" id="selectedRangeText">
                        <?= htmlspecialchars($dateFrom) ?> – <?= htmlspecialchars($dateTo) ?>
                    </div>
                </div>

                <div class="date-picker-body">
                    <div class="date-shortcuts">
                        <button type="button" class="date-shortcut" data-shortcut="today"><?= __('common.today') ?></button>
                        <button type="button" class="date-shortcut" data-shortcut="yesterday"><?= __('common.yesterday') ?></button>
                        <button type="button" class="date-shortcut" data-shortcut="last_week"><?= __('common.last_week') ?></button>
                        <button type="button" class="date-shortcut" data-shortcut="this_month"><?= __('common.this_month') ?></button>
                        <button type="button" class="date-shortcut" data-shortcut="all_time"><?= __('common.all_time') ?></button>
                    </div>

                    <div class="calendar-area">
                        <div class="calendar-toolbar">
                            <button type="button" class="calendar-nav" id="calendarPrev" aria-label="Previous month">‹</button>

                            <div class="calendar-months">
                                <div class="calendar-month" id="calendarMonthLeft">-</div>
                                <div class="calendar-month" id="calendarMonthRight">-</div>
                            </div>

                            <button type="button" class="calendar-nav" id="calendarNext" aria-label="Next month">›</button>
                        </div>

                        <div class="calendar-panels">
                            <div><div class="calendar-grid" id="calendarGridLeft"></div></div>
                            <div><div class="calendar-grid" id="calendarGridRight"></div></div>
                        </div>
                    </div>
                </div>

                <div class="date-picker-footer">
                    <button type="button" class="date-picker-cancel" id="datePickerCancel"><?= __('common.cancel') ?></button>
                    <button type="button" class="date-picker-apply" id="datePickerApply"><?= __('dashboard.apply') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================
         KPI CARDS
         ===================================================== -->
    <div class="dashboard-stat-grid">
        <?php
        $cards = [
            ['label' => __('dashboard.total_employees'), 'value' => $stats['total_employees'], 'icon' => '◎'],
            ['label' => 'Present', 'value' => $stats['present_today'], 'icon' => '✓'],
            ['label' => 'Absent', 'value' => $stats['absent_today'], 'icon' => '×'],
            ['label' => 'Late', 'value' => $stats['late_today'], 'icon' => '◷'],
            ['label' => 'Overtime', 'value' => $stats['overtime_today'], 'icon' => '↗'],
        ];
        ?>

        <?php foreach ($cards as $card): ?>
            <div class="dashboard-stat-card">
                <div class="dashboard-stat-top">
                    <div class="dashboard-stat-icon"><?= htmlspecialchars($card['icon']) ?></div>
                </div>

                <div class="dashboard-stat-label">
                    <?= htmlspecialchars($card['label']) ?>
                </div>

                <div class="dashboard-stat-value">
                    <?= htmlspecialchars((string) $card['value']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- =====================================================
         PERFORMANCE + PAYROLL
         ===================================================== -->
    <div class="dashboard-main-grid">

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title"><?= __('dashboard.attendance_overview') ?></h2>
                    <p class="dashboard-card-subtitle"><?= __('dashboard.attendance_range_text') ?></p>
                </div>
            </div>

            <div class="chart-body">
                <?php
                $present = (int) ($attendanceChart['present'] ?? 0);
                $late = (int) ($attendanceChart['late'] ?? 0);
                $absent = (int) ($attendanceChart['absent'] ?? 0);
                $overtime = (int) ($attendanceChart['overtime'] ?? 0);
                $chartMax = max($present, $late, $absent, 1);
                ?>

                <div class="chart-summary">
                    <div class="chart-stat">
                        <div class="chart-stat-label"><?= __('dashboard.present') ?></div>
                        <div class="chart-stat-value"><?= $present ?></div>
                    </div>
                    <div class="chart-stat">
                        <div class="chart-stat-label"><?= __('dashboard.late') ?></div>
                        <div class="chart-stat-value"><?= $late ?></div>
                    </div>
                    <div class="chart-stat">
                        <div class="chart-stat-label"><?= __('dashboard.absent') ?></div>
                        <div class="chart-stat-value"><?= $absent ?></div>
                    </div>
                    <div class="chart-stat">
                        <div class="chart-stat-label"><?= __('dashboard.overtime') ?></div>
                        <div class="chart-stat-value"><?= $overtime ?></div>
                    </div>
                </div>

                <div class="performance-chart">
                    <div class="performance-row">
                        <div class="performance-label"><?= __('dashboard.present') ?></div>
                        <div class="performance-track">
                            <div class="performance-fill present" style="width: <?= max(2, round(($present / $chartMax) * 100)) ?>%;"></div>
                        </div>
                        <div class="performance-number"><?= $present ?></div>
                    </div>

                    <div class="performance-row">
                        <div class="performance-label"><?= __('dashboard.late') ?></div>
                        <div class="performance-track">
                            <div class="performance-fill late" style="width: <?= max(2, round(($late / $chartMax) * 100)) ?>%;"></div>
                        </div>
                        <div class="performance-number"><?= $late ?></div>
                    </div>

                    <div class="performance-row">
                        <div class="performance-label"><?= __('dashboard.absent') ?></div>
                        <div class="performance-track">
                            <div class="performance-fill absent" style="width: <?= max(2, round(($absent / $chartMax) * 100)) ?>%;"></div>
                        </div>
                        <div class="performance-number"><?= $absent ?></div>
                    </div>
                </div>

                <div class="performance-footer">
                    <span class="performance-legend"><span class="performance-dot present"></span><?= __('dashboard.present') ?></span>
                    <span class="performance-legend"><span class="performance-dot late"></span><?= __('dashboard.late') ?></span>
                    <span class="performance-legend"><span class="performance-dot absent"></span><?= __('dashboard.absent') ?></span>
                </div>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title"><?= __('dashboard.current_payroll') ?></h2>
                    <p class="dashboard-card-subtitle"><?= __('dashboard.latest_payroll_status') ?></p>
                </div>
            </div>

            <div class="payroll-content">
                <div class="payroll-highlight">
                    <div>
                        <div class="payroll-label">Payroll Period</div>
                        <div class="payroll-period"><?= htmlspecialchars($stats['current_period']) ?></div>
                    </div>

                    <span class="payroll-status"><?= htmlspecialchars($stats['payroll_status']) ?></span>
                </div>

                <div class="payroll-mini-grid">
                    <div class="payroll-mini">
                        <div class="payroll-mini-label">Employees</div>
                        <div class="payroll-mini-value"><?= htmlspecialchars((string) $stats['total_employees']) ?> Active</div>
                    </div>

                    <div class="payroll-mini">
                        <div class="payroll-mini-label">Attendance</div>
                        <div class="payroll-mini-value"><?= htmlspecialchars((string) ($present + $late + $absent)) ?> Records</div>
                    </div>
                </div>

                <div class="payroll-note">
                    Payroll calculations use the attendance records imported and processed by the system.
                </div>
            </div>
        </div>

    </div>

    <!-- =====================================================
         HOLIDAYS + BIRTHDAYS
         ===================================================== -->
    <div class="dashboard-lower-grid">

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">🎉 <?= __('dashboard.holidays') ?></h2>
                    <p class="dashboard-card-subtitle"><?= __('dashboard.active_holidays') ?></p>
                </div>
            </div>

            <div class="month-list">
                <?php if (!empty($holidays)): ?>
                    <?php foreach ($holidays as $holiday): ?>
                        <?php
                        $holidayDate = $holiday['holiday_date'] ?? null;
                        $holidayTimestamp = $holidayDate ? strtotime($holidayDate) : false;
                        ?>
                        <div class="month-item">
                            <div class="month-date">
                                <div class="month-date-day"><?= $holidayTimestamp !== false ? date('d', $holidayTimestamp) : '-' ?></div>
                                <div class="month-date-month"><?= $holidayTimestamp !== false ? date('M', $holidayTimestamp) : '' ?></div>
                            </div>

                            <div>
                                <div class="month-item-title">
                                    <?= htmlspecialchars((string) ($holiday['holiday_name'] ?? 'Holiday')) ?>
                                </div>
                                <div class="month-item-meta">
                                    <?= htmlspecialchars((string) ($holiday['holiday_type'] ?? '')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="month-empty"><?= __('dashboard.no_holidays') ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-header">
                <div>
                    <h2 class="dashboard-card-title">🎂 <?= __('dashboard.birthdays') ?></h2>
                    <p class="dashboard-card-subtitle"><?= __('dashboard.birthday_range_text') ?></p>
                </div>
            </div>

            <div class="month-list">
                <?php if (!empty($birthdays)): ?>
                    <?php foreach ($birthdays as $birthday): ?>
                        <?php
                        $birthdate = $birthday['birthdate'] ?? null;
                        $birthTimestamp = $birthdate ? strtotime($birthdate) : false;

                        $birthdayName = trim(
                            ($birthday['first_name'] ?? '') . ' ' .
                            ($birthday['last_name'] ?? '')
                        );

                        if ($birthdayName === '') {
                            $birthdayName = 'Employee';
                        }
                        ?>
                        <div class="month-item">
                            <div class="month-date">
                                <div class="month-date-day"><?= $birthTimestamp !== false ? date('d', $birthTimestamp) : '-' ?></div>
                                <div class="month-date-month"><?= $birthTimestamp !== false ? date('M', $birthTimestamp) : '' ?></div>
                            </div>

                            <div>
                                <div class="month-item-title"><?= htmlspecialchars($birthdayName) ?></div>
                                <div class="month-item-meta">
                                    <?= __('common.employee_id') ?>
                                    <?= htmlspecialchars((string) ($birthday['employee_code'] ?? '-')) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="month-empty"><?= __('dashboard.no_birthdays') ?></div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker = document.getElementById('dashboardDatePicker');
    const trigger = document.getElementById('datePickerTrigger');
    const popup = document.getElementById('datePickerPopup');
    const leftGrid = document.getElementById('calendarGridLeft');
    const rightGrid = document.getElementById('calendarGridRight');
    const leftMonthLabel = document.getElementById('calendarMonthLeft');
    const rightMonthLabel = document.getElementById('calendarMonthRight');
    const prevButton = document.getElementById('calendarPrev');
    const nextButton = document.getElementById('calendarNext');
    const applyButton = document.getElementById('datePickerApply');
    const cancelButton = document.getElementById('datePickerCancel');
    const selectedRangeText = document.getElementById('selectedRangeText');
    const pickerLabel = document.getElementById('datePickerLabel');

    if (!picker || !trigger || !popup || !leftGrid || !rightGrid) return;

    const initialFrom = '<?= htmlspecialchars($dateFrom, ENT_QUOTES) ?>';
    const initialTo = '<?= htmlspecialchars($dateTo, ENT_QUOTES) ?>';

    let selectedStart = initialFrom;
    let selectedEnd = initialTo;
    let draftStart = selectedStart;
    let draftEnd = selectedEnd;
    let calendarDate = parseDate(selectedStart);

    if (Number.isNaN(calendarDate.getTime())) calendarDate = new Date();

    function pad(number) { return String(number).padStart(2, '0'); }

    function toIsoDate(date) {
        return date.getFullYear() + '-' +
            pad(date.getMonth() + 1) + '-' +
            pad(date.getDate());
    }

    function parseDate(value) {
        if (!value) return new Date(NaN);
        return new Date(value + 'T00:00:00');
    }

    function formatDisplayDate(value) {
        if (!value) return '';
        const date = parseDate(value);
        if (Number.isNaN(date.getTime())) return value;

        return date.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }

    function formatMonth(date) {
        return date.toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric'
        });
    }

    function addMonths(date, amount) {
        return new Date(date.getFullYear(), date.getMonth() + amount, 1);
    }

    function updateRangeText() {
        if (!draftStart) {
            selectedRangeText.textContent = 'Select a start date and end date.';
            return;
        }

        if (!draftEnd) {
            selectedRangeText.textContent = formatDisplayDate(draftStart) + ' – Select end date';
            return;
        }

        selectedRangeText.textContent =
            formatDisplayDate(draftStart) + ' – ' + formatDisplayDate(draftEnd);
    }

    function updateTriggerLabel() {
        if (!selectedStart || !selectedEnd) {
            pickerLabel.textContent = 'Select date range';
            return;
        }

        pickerLabel.textContent =
            formatDisplayDate(selectedStart) + ' – ' + formatDisplayDate(selectedEnd);
    }

    function isInRange(dateString) {
        if (!draftStart || !draftEnd) return false;
        return dateString > draftStart && dateString < draftEnd;
    }

    function isStart(dateString) { return draftStart === dateString; }
    function isEnd(dateString) { return draftEnd === dateString; }

    function chooseDate(dateString) {
        if (!draftStart || (draftStart && draftEnd)) {
            draftStart = dateString;
            draftEnd = '';
        } else {
            if (dateString < draftStart) {
                draftEnd = draftStart;
                draftStart = dateString;
            } else {
                draftEnd = dateString;
            }
        }

        document.querySelectorAll('.date-shortcut').forEach(function (button) {
            button.classList.remove('active');
        });

        updateRangeText();
        renderCalendars();
    }

    function renderMonth(grid, label, date) {
        grid.innerHTML = '';
        label.textContent = formatMonth(date);

        ['S','M','T','W','T','F','S'].forEach(function (day) {
            const element = document.createElement('div');
            element.className = 'calendar-weekday';
            element.textContent = day;
            grid.appendChild(element);
        });

        const year = date.getFullYear();
        const month = date.getMonth();
        const firstDay = new Date(year, month, 1);
        const startWeekday = firstDay.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPreviousMonth = new Date(year, month, 0).getDate();
        const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;
        const today = toIsoDate(new Date());

        for (let cell = 0; cell < totalCells; cell++) {
            let dayNumber;
            let cellDate;
            let muted = false;

            if (cell < startWeekday) {
                dayNumber = daysInPreviousMonth - startWeekday + cell + 1;
                cellDate = new Date(year, month - 1, dayNumber);
                muted = true;
            } else if (cell >= startWeekday + daysInMonth) {
                dayNumber = cell - (startWeekday + daysInMonth) + 1;
                cellDate = new Date(year, month + 1, dayNumber);
                muted = true;
            } else {
                dayNumber = cell - startWeekday + 1;
                cellDate = new Date(year, month, dayNumber);
            }

            const dateString = toIsoDate(cellDate);
            const button = document.createElement('button');

            button.type = 'button';
            button.textContent = String(dayNumber);
            button.className = 'calendar-day';

            if (muted) button.classList.add('muted');
            if (dateString === today) button.classList.add('today');
            if (isInRange(dateString)) button.classList.add('in-range');
            if (isStart(dateString)) button.classList.add('start');
            if (isEnd(dateString)) button.classList.add('end');

            button.addEventListener('click', function () {
                chooseDate(dateString);
            });

            grid.appendChild(button);
        }
    }

    function renderCalendars() {
        const rightDate = addMonths(calendarDate, 1);
        renderMonth(leftGrid, leftMonthLabel, calendarDate);
        renderMonth(rightGrid, rightMonthLabel, rightDate);
        updateRangeText();
    }

    function setShortcut(shortcut) {
        const today = new Date();
        const todayString = toIsoDate(today);

        if (shortcut === 'today') {
            draftStart = todayString;
            draftEnd = todayString;
        } else if (shortcut === 'yesterday') {
            const yesterday = new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate() - 1
            );
            draftStart = toIsoDate(yesterday);
            draftEnd = toIsoDate(yesterday);
        } else if (shortcut === 'last_week') {
            const day = today.getDay();
            const mondayOffset = day === 0 ? -6 : 1 - day;

            const thisMonday = new Date(
                today.getFullYear(),
                today.getMonth(),
                today.getDate() + mondayOffset
            );

            const lastMonday = new Date(
                thisMonday.getFullYear(),
                thisMonday.getMonth(),
                thisMonday.getDate() - 7
            );

            const lastSunday = new Date(
                thisMonday.getFullYear(),
                thisMonday.getMonth(),
                thisMonday.getDate() - 1
            );

            draftStart = toIsoDate(lastMonday);
            draftEnd = toIsoDate(lastSunday);
        } else if (shortcut === 'this_month') {
            const first = new Date(today.getFullYear(), today.getMonth(), 1);
            const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            draftStart = toIsoDate(first);
            draftEnd = toIsoDate(last);
        } else if (shortcut === 'all_time') {
            draftStart = '2000-01-01';
            draftEnd = '2099-12-31';
        }

        calendarDate = parseDate(draftStart);
        if (Number.isNaN(calendarDate.getTime())) calendarDate = new Date();

        document.querySelectorAll('.date-shortcut').forEach(function (button) {
            button.classList.toggle('active', button.dataset.shortcut === shortcut);
        });

        renderCalendars();
    }

    document.querySelectorAll('.date-shortcut').forEach(function (button) {
        button.addEventListener('click', function () {
            setShortcut(this.dataset.shortcut);
        });
    });

    trigger.addEventListener('click', function (event) {
        event.stopPropagation();

        const isOpen = popup.classList.toggle('open');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen) {
            draftStart = selectedStart;
            draftEnd = selectedEnd;
            calendarDate = parseDate(draftStart);

            if (Number.isNaN(calendarDate.getTime())) calendarDate = new Date();

            renderCalendars();
        }
    });

    popup.addEventListener('click', function (event) {
        event.stopPropagation();
    });

    document.addEventListener('click', function () {
        popup.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });

    prevButton.addEventListener('click', function () {
        calendarDate = addMonths(calendarDate, -1);
        renderCalendars();
    });

    nextButton.addEventListener('click', function () {
        calendarDate = addMonths(calendarDate, 1);
        renderCalendars();
    });

    cancelButton.addEventListener('click', function () {
        draftStart = selectedStart;
        draftEnd = selectedEnd;
        popup.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    });

    applyButton.addEventListener('click', function () {
        if (!draftStart) return;
        if (!draftEnd) draftEnd = draftStart;

        selectedStart = draftStart;
        selectedEnd = draftEnd;

        const params = new URLSearchParams(window.location.search);
        params.set('date_from', selectedStart);
        params.set('date_to', selectedEnd);

        window.location.href =
            window.location.pathname + '?' + params.toString();
    });

    updateTriggerLabel();
    renderCalendars();
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
