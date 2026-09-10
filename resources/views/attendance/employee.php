<?php

/**
 * Employee Attendance Details
 */

$employee = $employee ?? [];

/*
 * AttendanceController::employee()
 * provides the complete attendance list as $dailyAttendance.
 */
$attendance =
    $dailyAttendance
    ?? $attendance
    ?? [];

$csrfToken =
    $csrfToken
    ?? '';

/*
|--------------------------------------------------------------------------
| Employee ID
|--------------------------------------------------------------------------
*/

$employeeId =
    (int) (
        $employee['id']
        ?? 0
    );

/*
|--------------------------------------------------------------------------
| Download URL
|--------------------------------------------------------------------------
|
| Always generate the download URL here as a fallback.
|
*/

$downloadUrl =
    $downloadUrl
    ?? (
        $employeeId > 0
            ? '/attendance/employee/' .
              $employeeId .
              '/download'
            : '#'
    );


function employeeAttendanceTime12(
    ?string $value
): string {

    if (!$value) {
        return '-';
    }

    $timestamp =
        strtotime($value);

    return $timestamp !== false
        ? date('h:i:s A', $timestamp)
        : $value;
}


function employeeAttendanceDate(
    ?string $value
): string {

    if (!$value) {
        return '-';
    }

    $timestamp =
        strtotime($value);

    return $timestamp !== false
        ? date('M d, Y', $timestamp)
        : $value;
}


function employeeEarlyOvertimeSeconds(?string $timeIn): int
{
    if (!$timeIn) {
        return 0;
    }

    $timeInTimestamp = strtotime($timeIn);

    if ($timeInTimestamp === false) {
        return 0;
    }

    // Use the same attendance date as Time In.
    $date = date('Y-m-d', $timeInTimestamp);
    $nineAmTimestamp = strtotime($date . ' 09:00:00');

    if ($nineAmTimestamp === false || $timeInTimestamp >= $nineAmTimestamp) {
        return 0;
    }

    // All time before 9:00 AM is Early OT.
    return $nineAmTimestamp - $timeInTimestamp;
}

function employeeFormatDurationSeconds(?int $seconds): string
{
    $seconds = max(0, (int) ($seconds ?? 0));

    if ($seconds <= 0) {
        return '0m';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;
    $parts = [];

    if ($hours > 0) $parts[] = $hours . 'h';
    if ($minutes > 0) $parts[] = $minutes . 'm';
    if ($remainingSeconds > 0) $parts[] = $remainingSeconds . 's';

    return implode(' ', $parts);
}


function employeeAttendanceMinutes(
    ?int $minutes
): string {

    $minutes =
        (int) (
            $minutes
            ?? 0
        );

    $hours =
        intdiv(
            $minutes,
            60
        );

    $mins =
        $minutes % 60;

    if ($hours > 0) {

        return $hours .
            'h ' .
            $mins .
            'm';
    }

    return $mins . 'm';
}


$statusClass =
    static function (
        ?string $status
    ): string {

        return match ($status) {

            'Present' =>
                'status-present',

            'Late' =>
                'status-late',

            'Half-Day' =>
                'status-halfday',

            'Absent' =>
                'status-absent',

            'On-Leave' =>
                'status-leave',

            'Rest-Day' =>
                'status-rest',

            'Holiday' =>
                'status-holiday',

            default =>
                'status-default',
        };
    };

?>


<?php require __DIR__ . '/../layouts/header.php'; ?>


<style>

    .employee-attendance-container {
        width: 100%;
    }


    .employee-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 20px;
        margin-bottom: 20px;
    }


    .employee-header-left h1 {
        margin: 0;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }


    .employee-header-left p {
        margin: 6px 0 0;
        font-size: 12px;
        color: #6b7280;
    }


    .employee-header-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }


    .download-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 14px;
        border-radius: 6px;
        background: #111827;
        color: #fff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }


    .download-btn:hover {
        background: #1f2937;
    }


    .back-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 9px 14px;
        border-radius: 6px;
        background: #f3f4f6;
        color: #374151;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
    }


    .back-btn:hover {
        background: #e5e7eb;
    }


    .employee-info {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }


    .info-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
    }


    .info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #6b7280;
        margin-bottom: 5px;
    }


    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }


    .filter-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
        margin-bottom: 20px;
    }


    .filter-form {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }


    .filter-field {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }


    .filter-field label {
        font-size: 11px;
        color: #6b7280;
        font-weight: 600;
    }


    .filter-field input {
        height: 36px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 0 10px;
        font-size: 12px;
        color: #111827;
    }


    .filter-btn {
        height: 36px;
        padding: 0 14px;
        border: 0;
        border-radius: 6px;
        background: #111827;
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }


    .filter-btn:hover {
        background: #1f2937;
    }


    .table-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
    }


    .table-wrapper {
        overflow-x: auto;
    }


    .attendance-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1120px;
        table-layout: fixed;
    }

    .attendance-table th,
    .attendance-table td {
        box-sizing: border-box;
    }

    .attendance-table th:nth-child(1), .attendance-table td:nth-child(1) { width: 13%; }
    .attendance-table th:nth-child(2), .attendance-table td:nth-child(2) { width: 12%; }
    .attendance-table th:nth-child(3), .attendance-table td:nth-child(3) { width: 12%; }
    .attendance-table th:nth-child(4), .attendance-table td:nth-child(4) { width: 10%; }
    .attendance-table th:nth-child(5), .attendance-table td:nth-child(5) { width: 9%; }
    .attendance-table th:nth-child(6), .attendance-table td:nth-child(6) { width: 11%; }
    .attendance-table th:nth-child(7), .attendance-table td:nth-child(7) { width: 12%; }
    .attendance-table th:nth-child(8), .attendance-table td:nth-child(8) { width: 11%; }
    .attendance-table th:nth-child(9), .attendance-table td:nth-child(9) { width: 10%; }


    .attendance-table th {
        background: #f9fafb;
        color: #6b7280;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        text-align: left;
        padding: 12px 14px;
        border-bottom: 1px solid #e5e7eb;
        white-space: nowrap;
    }


    .attendance-table td {
        padding: 12px 14px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 12px;
        color: #374151;
        white-space: nowrap;
    }


    .attendance-table tbody tr:hover {
        background: #fafafa;
    }


    .date-main {
        font-weight: 600;
        color: #111827;
    }


    .time-main {
        color: #111827;
        font-weight: 500;
    }


    .time-empty {
        color: #9ca3af;
    }


    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 700;
    }


    .status-present {
        background: #dcfce7;
        color: #166534;
    }


    .status-late {
        background: #fef3c7;
        color: #92400e;
    }


    .status-halfday {
        background: #ffedd5;
        color: #9a3412;
    }


    .status-absent {
        background: #fee2e2;
        color: #991b1b;
    }


    .status-leave {
        background: #ede9fe;
        color: #6d28d9;
    }


    .status-rest {
        background: #e0f2fe;
        color: #075985;
    }


    .status-holiday {
        background: #fce7f3;
        color: #9d174d;
    }


    .status-default {
        background: #f3f4f6;
        color: #374151;
    }


    .empty-state {
        padding: 50px 20px;
        text-align: center;
        color: #6b7280;
        font-size: 13px;
    }


    @media (max-width: 900px) {

        .employee-info {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }


    @media (max-width: 600px) {

        .employee-header {
            flex-direction: column;
        }


        .employee-info {
            grid-template-columns: 1fr;
        }

    }

</style>


<div class="employee-attendance-container">


    <div class="employee-header">

        <div class="employee-header-left">

            <h1>
                <?= htmlspecialchars(
                    $employee['full_name']
                    ?? 'Employee'
                ) ?>
            </h1>

            <p>
                <?= __('common.employee_attendance_records') ?>
            </p>

        </div>


        <div class="employee-header-actions">

            <?php if (
                $employeeId > 0
            ): ?>

                <a
                    href="<?= htmlspecialchars(
                        $downloadUrl
                    ) ?>"
                    class="download-btn"
                >
                    ↓ <?= __('common.download_attendance') ?>
                </a>

            <?php endif; ?>


            <a
                href="/attendance"
                class="back-btn"
            >
                ← <?= __('common.back_to_attendance') ?>
            </a>

        </div>

    </div>


    <div class="employee-info">


        <div class="info-card">

            <div class="info-label">
                <?= __('common.employee_id') ?>
            </div>

            <div class="info-value">

                <?= htmlspecialchars(
                    $employee['employee_code']
                    ?? '-'
                ) ?>

            </div>

        </div>


        <div class="info-card">

            <div class="info-label">
                <?= __('common.employee_name') ?>
            </div>

            <div class="info-value">

                <?= htmlspecialchars(
                    $employee['full_name']
                    ?? '-'
                ) ?>

            </div>

        </div>


        <div class="info-card">

            <div class="info-label">
                <?= __('salary.department') ?>
            </div>

            <div class="info-value">

                <?= htmlspecialchars(
                    $employee['department']
                    ?? '-'
                ) ?>

            </div>

        </div>


        <div class="info-card">

            <div class="info-label">
                <?= __('common.status') ?>
            </div>

            <div class="info-value">

                <?= htmlspecialchars(
                    $employee['status']
                    ?? '-'
                ) ?>

            </div>

        </div>

    </div>


    <div class="filter-card">

        <form
            method="GET"
            action="/attendance/employee/<?= urlencode(
                (string) $employeeId
            ) ?>"
            class="filter-form"
        >


            <div class="filter-field">

                <label for="date_from">
                    <?= __('attendance.from') ?>
                </label>

                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?= htmlspecialchars(
                        $_GET['date_from']
                        ?? date('Y-m-01')
                    ) ?>"
                >

            </div>


            <div class="filter-field">

                <label for="date_to">
                    <?= __('attendance.to') ?>
                </label>

                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?= htmlspecialchars(
                        $_GET['date_to']
                        ?? date('Y-m-d')
                    ) ?>"
                >

            </div>


            <button
                type="submit"
                class="filter-btn"
            >
                <?= __('common.search') ?>
            </button>

        </form>

    </div>


    <div class="table-card">


        <?php if (
            empty($attendance)
        ): ?>

            <div class="empty-state">

                No attendance records found
                for this employee in the
                selected date range.

            </div>

        <?php else: ?>


            <div class="table-wrapper">

                <table class="attendance-table">

                    <thead>

                        <tr>

                            <th>
                                <?= __('attendance.date') ?>
                            </th>

                            <th>
                                <?= __('attendance.time_in') ?>
                            </th>

                            <th>
                                <?= __('attendance.time_out') ?>
                            </th>

                            <th>
                                <?= __('attendance.worked') ?>
                            </th>

                            <th>
                                <?= __('attendance.late') ?>
                            </th>

                            <th>
                                <?= __('attendance.undertime') ?>
                            </th>

                            <th>
                                <?= __('attendance.early_ot') ?>
                            </th>

                            <th>
                                <?= __('attendance.overtime') ?>
                            </th>

                            <th>
                                <?= __('common.status') ?>
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $attendance
                            as $row
                        ): ?>


                            <tr>


                                <td>

                                    <div class="date-main">

                                        <?= htmlspecialchars(
                                            employeeAttendanceDate(
                                                $row[
                                                    'attendance_date'
                                                ]
                                                ?? null
                                            )
                                        ) ?>

                                    </div>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $row['time_in']
                                        )
                                    ): ?>

                                        <span
                                            class="time-main"
                                        >

                                            <?= htmlspecialchars(
                                                employeeAttendanceTime12(
                                                    $row['time_in']
                                                )
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="time-empty"
                                        >
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if (
                                        !empty(
                                            $row['time_out']
                                        )
                                    ): ?>

                                        <span
                                            class="time-main"
                                        >

                                            <?= htmlspecialchars(
                                                employeeAttendanceTime12(
                                                    $row['time_out']
                                                )
                                            ) ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="time-empty"
                                        >
                                            -
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        employeeAttendanceMinutes(
                                            $row[
                                                'worked_minutes'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        employeeAttendanceMinutes(
                                            $row[
                                                'late_minutes'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        employeeAttendanceMinutes(
                                            $row[
                                                'undertime_minutes'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        employeeFormatDurationSeconds(
                                            employeeEarlyOvertimeSeconds(
                                                $row['time_in'] ?? null
                                            )
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        employeeAttendanceMinutes(
                                            $row[
                                                'overtime_minutes'
                                            ]
                                            ?? 0
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?php

                                    $hasTimeOut =
                                        trim((string) ($row['time_out'] ?? '')) !== '';

                                    $currentStatus =
                                        !$hasTimeOut
                                            ? ''
                                            : (
                                                !empty($row['holiday_name'])
                                                    ? 'Holiday'
                                                    : (
                                                        $row['attendance_status']
                                                        ?? 'Present'
                                                    )
                                            );

                                    ?>

                                    <span
                                        class="status-badge <?= htmlspecialchars(
                                            $statusClass(
                                                $currentStatus
                                            )
                                        ) ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $currentStatus
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?php if ($hasTimeOut && !empty($row['holiday_name'])): ?>

                                        <div class="font-medium">
                                            <?= htmlspecialchars($row['holiday_name']) ?>
                                        </div>

                                        <?php if (!empty($row['holiday_type'])): ?>
                                            <div class="text-xs text-slate-500 mt-0.5">
                                                <?= htmlspecialchars($row['holiday_type']) ?>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>

                                        <span class="time-empty">-</span>

                                    <?php endif; ?>

                                </td>

                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>

</div>