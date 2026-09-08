<?php

/** Attendance Page */

$csrfToken = $csrfToken ?? '';

/**
 * Format attendance datetime to 12-hour time.
 */
function attendanceTime12(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime($value);

    return $timestamp !== false
        ? date('h:i:s A', $timestamp)
        : $value;
}


/**
 * Format worked minutes to hours/minutes.
 */
function attendanceWorkHours($minutes): string
{
    $minutes = (int) $minutes;

    if ($minutes <= 0) {
        return '0h';
    }

    $hours = intdiv($minutes, 60);

    $remainingMinutes =
        $minutes % 60;

    if (
        $hours > 0 &&
        $remainingMinutes > 0
    ) {
        return $hours . 'h ' .
            $remainingMinutes . 'm';
    }

    if ($hours > 0) {
        return $hours . 'h';
    }

    return $remainingMinutes . 'm';
}


/*
|--------------------------------------------------------------------------
| Import Preview Data
|--------------------------------------------------------------------------
*/

$previewData =
    is_array($importPreview ?? null)
        ? $importPreview
        : [];

$previewRows = [];

if (
    isset($previewData['preview_rows']) &&
    is_array($previewData['preview_rows'])
) {

    $previewRows =
        $previewData['preview_rows'];

} elseif (
    isset($previewData['rows']) &&
    is_array($previewData['rows'])
) {

    $previewRows =
        $previewData['rows'];

} elseif (
    isset($previewData['records']) &&
    is_array($previewData['records'])
) {

    $previewRows =
        $previewData['records'];
}


$skippedRows = [];

if (
    isset($previewData['skipped']) &&
    is_array($previewData['skipped'])
) {

    $skippedRows =
        $previewData['skipped'];
}


$fileName = '';

if (
    isset($previewData['file_name']) &&
    is_scalar($previewData['file_name'])
) {

    $fileName =
        (string) $previewData['file_name'];

} elseif (
    isset($previewData['filename']) &&
    is_scalar($previewData['filename'])
) {

    $fileName =
        (string) $previewData['filename'];
}


$totalPreviewRows =
    count($previewRows);

$totalSkippedRows =
    count($skippedRows);


$totalDays =
    isset($previewData['total_days'])
        ? (int) $previewData['total_days']
        : $totalPreviewRows;


$matched =
    isset($previewData['matched'])
        ? (int) $previewData['matched']
        : 0;


$unmatched =
    isset($previewData['unmatched'])
        ? (int) $previewData['unmatched']
        : 0;


$invalidRows =
    isset($previewData['invalid_rows'])
        ? (int) $previewData['invalid_rows']
        : $totalSkippedRows;


/*
|--------------------------------------------------------------------------
| Upload / Import Messages
|--------------------------------------------------------------------------
|
| These values are already prepared by AttendanceController.
| Do not read/clear the session here because the controller handles
| the one-time session messages.
|
*/

$uploadSuccess = false;

$error =
    $error ?? '';

$importSuccess =
    $importSuccess ?? false;

?>


<?php require __DIR__ . '/../layouts/header.php'; ?>


<style>

/* =========================================================
   ATTENDANCE PAGE
   ========================================================= */

.attendance-container {
    width: 100%;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.attendance-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 20px;
}


.attendance-title h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #111827;
}


.attendance-title p {
    margin: 6px 0 0;
    color: #6b7280;
    font-size: 12px;
}


/* =========================================================
   IMPORT ACTIONS
   ========================================================= */

.import-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}


.upload-btn,
.import-btn,
.cancel-btn {
    border: 0;
    border-radius: 6px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}


.upload-btn,
.import-btn {
    background: #111827;
    color: #ffffff;
}


.upload-btn:hover,
.import-btn:hover {
    background: #1f2937;
}


.import-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}


.cancel-btn {
    background: #f3f4f6;
    color: #374151;
}


.cancel-btn:hover {
    background: #e5e7eb;
}


.upload-file-name {
    color: #6b7280;
    font-size: 11px;
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


.upload-loading {
    display: none;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 11px;
}


.upload-loading.active {
    display: inline-flex;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {
    border-radius: 6px;
    padding: 11px 13px;
    margin-bottom: 14px;
    font-size: 12px;
}


.alert-success {
    background: #ecfdf3;
    border: 1px solid #bbf7d0;
    color: #166534;
}


.alert-error {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #be123c;
}


/* =========================================================
   IMPORT PREVIEW
   ========================================================= */

.import-preview-card {
    display: grid;
    grid-template-columns: 330px minmax(0, 1fr);
    background: #ffffff;
    border: 1px solid #dbe1ea;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 18px;
}


.preview-summary {
    padding: 20px;
    border-right: 1px solid #e5e7eb;
}


.preview-summary h2,
.uploaded-preview h2 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #111827;
}


.preview-summary p {
    margin: 5px 0 18px;
    color: #6b7280;
    font-size: 11px;
}


.preview-stat {
    padding: 10px 0;
    border-bottom: 1px solid #f0f2f5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
}


.preview-stat-label {
    color: #6b7280;
}


.preview-stat-value {
    font-weight: 700;
    color: #111827;
}


.preview-actions {
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}


.uploaded-preview {
    min-width: 0;
    padding: 20px;
}


.uploaded-file-meta {
    margin: 5px 0 14px;
    font-size: 11px;
    color: #6b7280;
}


/* =========================================================
   TABLE WRAPPERS
   ========================================================= */

.preview-table-wrapper,
.attendance-table-wrapper {
    width: 100%;
    overflow-x: auto;
}


.preview-table-wrapper {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
}


/* =========================================================
   TABLE
   ========================================================= */

.preview-table,
.attendance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}


.preview-table {
    min-width: 650px;
}


.preview-table th,
.attendance-table th {
    background: #f8fafc;
    color: #6b7280;
    font-weight: 600;
    text-align: left;
    padding: 9px 10px;
    border-bottom: 1px solid #e5e7eb;
    white-space: nowrap;
}


.preview-table td,
.attendance-table td {
    padding: 9px 10px;
    border-bottom: 1px solid #f0f2f5;
    color: #374151;
    white-space: nowrap;
}


/* =========================================================
   FILTER
   ========================================================= */

.attendance-filter-card {
    background: #ffffff;
    border: 1px solid #dbe1ea;
    border-radius: 7px;
    padding: 12px;
    margin-bottom: 14px;
}


.attendance-filter-form {
    display: grid;
    grid-template-columns:
        minmax(0, 2fr)
        1fr
        auto
        auto;
    gap: 9px;
    align-items: end;
}


.filter-field {
    display: flex;
    flex-direction: column;
    gap: 5px;
}


.filter-field label {
    font-size: 10px;
    color: #6b7280;
    font-weight: 500;
}


.filter-field input,
.filter-field select {
    width: 100%;
    height: 38px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    padding: 0 10px;
    font-size: 11px;
    color: #374151;
    background: #ffffff;
}


.filter-button {
    height: 38px;
    border: 0;
    border-radius: 5px;
    background: #111827;
    color: #ffffff;
    padding: 0 14px;
    font-size: 11px;
    cursor: pointer;
}


.filter-button:hover {
    background: #1f2937;
}


.reset-link {
    height: 38px;
    display: inline-flex;
    align-items: center;
    padding: 0 5px;
    color: #6b7280;
    font-size: 11px;
    text-decoration: none;
}


.reset-link:hover {
    color: #111827;
}


/* =========================================================
   ATTENDANCE TABLE CARD
   ========================================================= */

.attendance-table-card {
    background: #ffffff;
    border: 1px solid #dbe1ea;
    border-radius: 7px;
    overflow: hidden;
}


.attendance-table th {
    padding: 10px;
}


.attendance-table td {
    padding: 10px;
}


/* =========================================================
   STATUS
   ========================================================= */

.status-badge {
    display: inline-flex;
    padding: 4px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
}


.status-present {
    background: #dcfce7;
    color: #15803d;
}


.status-late,
.status-half-day {
    background: #fef3c7;
    color: #a16207;
}


.status-absent {
    background: #fee2e2;
    color: #b91c1c;
}


.status-on-leave,
.status-rest-day,
.status-holiday {
    background: #e0e7ff;
    color: #3730a3;
}


/* =========================================================
   EMPLOYEE LINK
   ========================================================= */

.employee-link {
    color: #111827;
    text-decoration: none;
    font-weight: 600;
}


.employee-link:hover {
    text-decoration: underline;
}


/* =========================================================
   ACTIONS
   ========================================================= */

.attendance-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}


.view-action,
.delete-action {
    height: 30px;
    padding: 0 9px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    cursor: pointer;
    box-sizing: border-box;
}


.view-action {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #d1d5db;
}


.view-action:hover {
    background: #e5e7eb;
}


.delete-attendance-form {
    margin: 0;
}


.delete-action {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}


.delete-action:hover {
    background: #fecaca;
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty-state {
    padding: 30px;
    text-align: center;
    color: #9ca3af;
    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 900px) {

    .attendance-page-header {
        flex-direction: column;
    }

    .attendance-filter-form {
        grid-template-columns: 1fr 1fr;
    }

    .import-preview-card {
        grid-template-columns: 1fr;
    }

    .preview-summary {
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
    }

}


@media (max-width: 600px) {

    .attendance-filter-form {
        grid-template-columns: 1fr;
    }

}

</style>


<div class="attendance-container">


    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <div class="attendance-page-header">

        <div class="attendance-title">

            <h1>
                <?= __('attendance.list_title') ?>
            </h1>

            <p>
                <?= __('attendance.subtitle_new') ?>
            </p>

        </div>


        <div class="import-actions">

            <!-- UPLOAD FORM -->

            <form
                id="attendanceUploadForm"
                method="POST"
                action="/attendance/import/preview"
                enctype="multipart/form-data"
                style="display:inline;"
            >

                <input
                    type="hidden"
                    name="_token"
                    value="<?= htmlspecialchars(
                        (string) $csrfToken
                    ) ?>"
                >

                <label
                    for="ngteco_file"
                    class="upload-btn"
                >

                    <span>⇧</span>

                    <span>
                        <?= __('common.upload') ?>
                    </span>

                </label>


                <input
                    type="file"
                    id="ngteco_file"
                    name="attendance_file"
                    accept=".xlsx,.xls,.csv"
                    hidden
                >

            </form>


            <span
                id="selectedFileName"
                class="upload-file-name"
            ></span>


            <span
                id="uploadLoading"
                class="upload-loading"
            >
                <?= __('common.uploading') ?>
            </span>

        </div>

    </div>


    <!-- =========================================================
         SUCCESS
         ========================================================= -->

    <?php if (
        !empty($importSuccess)
    ): ?>

        <div class="alert alert-success">

            ✓

            <?= htmlspecialchars(
                (string) $importSuccess
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         ERROR
         ========================================================= -->

    <?php if (!empty($error)): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars(
                (string) $error
            ) ?>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         IMPORT PREVIEW
         ========================================================= -->

    <?php if (
        !empty($previewData) &&
        $totalPreviewRows > 0
    ): ?>

        <div class="import-preview-card">


            <!-- LEFT -->

            <div class="preview-summary">

                <h2>
                    <?= __('attendance.ngteco_preview') ?>
                </h2>


                <p>
                    <?= __('attendance.review_import') ?>
                </p>


                <div class="preview-stat">

                    <span class="preview-stat-label">
                        <?= __('attendance.days') ?>
                    </span>

                    <span class="preview-stat-value">
                        <?= $totalDays ?>
                    </span>

                </div>


                <div class="preview-stat">

                    <span class="preview-stat-label">
                        <?= __('attendance.matched') ?>
                    </span>

                    <span class="preview-stat-value">
                        <?= $matched ?>
                    </span>

                </div>


                <div class="preview-stat">

                    <span class="preview-stat-label">
                        <?= __('attendance.unmatched') ?>
                    </span>

                    <span class="preview-stat-value">
                        <?= $unmatched ?>
                    </span>

                </div>


                <div class="preview-stat">

                    <span class="preview-stat-label">
                        <?= __('attendance.invalid_rows') ?>
                    </span>

                    <span class="preview-stat-value">
                        <?= $invalidRows ?>
                    </span>

                </div>


                <div class="preview-actions">


                    <!-- CONFIRM -->

                    <form
                        id="confirmImportForm"
                        method="POST"
                        action="/attendance/import/confirm"
                    >

                        <input
                            type="hidden"
                            name="_token"
                            value="<?= htmlspecialchars(
                                (string) $csrfToken
                            ) ?>"
                        >


                        <button
                            type="submit"
                            class="import-btn"
                            id="confirmImportBtn"
                        >
                            <?= __('common.confirm_import') ?>
                        </button>

                    </form>


                    <!-- CANCEL -->

                    <button
                        type="button"
                        class="cancel-btn"
                        id="cancelPreviewBtn"
                    >
                        <?= __('common.cancel') ?>
                    </button>


                </div>

            </div>


            <!-- RIGHT -->

            <div class="uploaded-preview">

                <h2>
                    <?= __('attendance.uploaded_preview') ?>
                </h2>


                <div class="uploaded-file-meta">

                    <strong>

                        <?= htmlspecialchars(
                            $fileName !== ''
                                ? $fileName
                                : 'Uploaded NGTeco file'
                        ) ?>

                    </strong>

                    <?= __('common.showing') ?>

                    <?= $totalPreviewRows ?>

                    <?= __('common.of') ?>

                    <?= $totalPreviewRows ?>

                    <?= __('common.rows') ?>

                </div>


                <div class="preview-table-wrapper">

                    <table class="preview-table">

                        <thead>

                            <tr>

                                <th>
                                    <?= __('common.employee_id') ?>
                                </th>

                                <th>
                                    <?= __('common.employee_name') ?>
                                </th>

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
                                    <?= __('attendance.work_code') ?>
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach (
                            $previewRows as $row
                        ): ?>

                            <?php

                            $row =
                                is_array($row)
                                    ? $row
                                    : [];

                            $employeeId =
                                $row['employee_id']
                                ?? $row['employee_code']
                                ?? '-';

                            $employeeName =
                                $row['employee_name']
                                ?? $row['person_name']
                                ?? '-';

                            $date =
                                $row['attendance_date']
                                ?? $row['date']
                                ?? '-';

                            $timeIn =
                                $row['time_in']
                                ?? '-';

                            $timeOut =
                                $row['time_out']
                                ?? '-';

                            $workCode =
                                $row['work_code']
                                ?? '0';

                            ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $employeeId
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $employeeName
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $date
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $timeIn
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $timeOut
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        (string) $workCode
                                    ) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         FILTER
         ========================================================= -->

    <div class="attendance-filter-card">

        <form
            method="GET"
            action="/attendance"
            class="attendance-filter-form"
        >


            <!-- SEARCH -->

            <div
                class="filter-field"
                style="grid-column: span 2;"
            >

                <label>
                    <?= __('attendance.search') ?>
                </label>


                <input
                    type="search"
                    name="search"
                    value="<?= htmlspecialchars(
                        (string) (
                            $_GET['search']
                            ?? ''
                        )
                    ) ?>"
                    placeholder="Search employee, Employee ID, or date"
                    autocomplete="off"
                >

            </div>


            <!-- STATUS -->

            <div class="filter-field">

                <label>
                    <?= __('common.status') ?>
                </label>


                <select name="status">

                    <option value="">
                        <?= __('attendance.all_statuses') ?>
                    </option>


                    <?php

                    $statuses = [
                        'Present',
                        'Absent',
                        'Late',
                        'Half-Day',
                        'On-Leave',
                        'Rest-Day',
                        'Holiday'
                    ];

                    ?>


                    <?php foreach (
                        $statuses as $status
                    ): ?>

                        <option
                            value="<?= htmlspecialchars(
                                $status
                            ) ?>"
                            <?= (
                                ($_GET['status'] ?? '')
                                === $status
                            )
                                ? 'selected'
                                : ''
                            ?>
                        >

                            <?= htmlspecialchars(
                                $status
                            ) ?>

                        </option>

                    <?php endforeach; ?>


                </select>

            </div>


            <!-- SEARCH BUTTON -->

            <button
                type="submit"
                class="filter-button"
            >
                <?= __('attendance.search') ?>
            </button>


            <!-- RESET -->

            <a
                href="/attendance"
                class="reset-link"
            >
                <?= __('common.reset') ?>
            </a>


        </form>

    </div>


    <!-- =========================================================
         ATTENDANCE TABLE
         ========================================================= -->

    <div class="attendance-table-card">

        <div class="attendance-table-wrapper">

            <table class="attendance-table">

                <thead>

                    <tr>

                        <th>
                            <?= __('attendance.employee') ?>
                        </th>

                        <th>
                            <?= __('common.employee_id') ?>
                        </th>

                        <th>
                            <?= __('attendance.title') ?>
                        </th>

                        <th>
                            <?= __('attendance.latest_date') ?>
                        </th>

                        <th>
                            <?= __('attendance.latest_time_in') ?>
                        </th>

                        <th>
                            <?= __('attendance.latest_time_out') ?>
                        </th>

                        <th>
                            <?= __('attendance.work_hours') ?>
                        </th>

                        <th>
                            <?= __('common.status') ?>
                        </th>

                        <th>
                            <?= __('holidays.holiday') ?>
                        </th>

                        <th>
                            <?= __('attendance.actions') ?>
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    is_array($attendance ?? null) &&
                    !empty($attendance)
                ): ?>


                    <?php foreach (
                        $attendance as $record
                    ): ?>


                        <?php

                        $record =
                            is_array($record)
                                ? $record
                                : [];

                        $employeeId =
                            $record['employee_id']
                            ?? null;

                        $employeeCode =
                            $record['employee_code']
                            ?? '-';

                        $employeeName =
                            $record['employee_name']
                            ?? trim(
                                ($record['first_name'] ?? '')
                                . ' '
                                . ($record['last_name'] ?? '')
                            );

                        if (
                            $employeeName === ''
                        ) {
                            $employeeName = '-';
                        }

                        $attendanceCount =
                            (int) (
                                $record['attendance_count']
                                ?? 0
                            );

                        $latestDate =
                            $record['latest_attendance_date']
                            ?? '-';

                        $timeIn =
                            $record['time_in']
                            ?? null;

                        $timeOut =
                            $record['time_out']
                            ?? null;

                        $workedMinutes =
                            $record['worked_minutes']
                            ?? 0;

                        $status =
                            $record['attendance_status']
                            ?? 'Present';

                        $holidayName =
                            $record['holiday_name']
                            ?? null;

                        $holidayType =
                            $record['holiday_type']
                            ?? null;

                        $statusClass =
                            strtolower(
                                str_replace(
                                    [' ', '-'],
                                    '-',
                                    (string) $status
                                )
                            );

                        ?>


                        <tr>


                            <!-- EMPLOYEE -->

                            <td>

                                <?php if (
                                    $employeeId
                                ): ?>

                                    <a
                                        href="/attendance/employee/<?= (int) $employeeId ?>"
                                        class="employee-link"
                                    >

                                        <?= htmlspecialchars(
                                            (string) $employeeName
                                        ) ?>

                                    </a>

                                <?php else: ?>

                                    <?= htmlspecialchars(
                                        (string) $employeeName
                                    ) ?>

                                <?php endif; ?>

                            </td>


                            <!-- EMPLOYEE ID -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) $employeeCode
                                ) ?>

                            </td>


                            <!-- ATTENDANCE COUNT -->

                            <td>

                                <?= $attendanceCount ?>

                                <?= $attendanceCount === 1
                                    ? 'record'
                                    : 'records'
                                ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <?= htmlspecialchars(
                                    (string) $latestDate
                                ) ?>

                            </td>


                            <!-- TIME IN -->

                            <td>

                                <?= htmlspecialchars(
                                    attendanceTime12(
                                        $timeIn
                                    )
                                ) ?>

                            </td>


                            <!-- TIME OUT -->

                            <td>

                                <?= htmlspecialchars(
                                    attendanceTime12(
                                        $timeOut
                                    )
                                ) ?>

                            </td>


                            <!-- WORK HOURS -->

                            <td>

                                <?= htmlspecialchars(
                                    attendanceWorkHours(
                                        $workedMinutes
                                    )
                                ) ?>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status-badge status-<?= htmlspecialchars(
                                        $statusClass
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        (string) $status
                                    ) ?>

                                </span>

                            </td>


                            <!-- HOLIDAY -->

                            <td>

                                <?php if ($holidayName): ?>

                                    <div class="font-medium text-slate-700">
                                        <?= htmlspecialchars((string) $holidayName) ?>
                                    </div>

                                    <?php if ($holidayType): ?>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            <?= htmlspecialchars((string) $holidayType) ?>
                                        </div>
                                    <?php endif; ?>

                                <?php else: ?>

                                    <span class="text-slate-400">-</span>

                                <?php endif; ?>

                            </td>


                            <!-- ACTIONS -->

                            <td>

                                <?php if (
                                    $employeeId
                                ): ?>

                                    <div class="attendance-actions">


                                        <!-- VIEW -->

                                        <a
                                            href="/attendance/employee/<?= (int) $employeeId ?>"
                                            class="view-action"
                                        >
                                            <?= __('attendance.view') ?>
                                        </a>


                                        <!-- DELETE EMPLOYEE ATTENDANCE -->

                                        <form
                                            method="POST"
                                            action="/attendance/<?= (int) $employeeId ?>/delete"
                                            class="delete-attendance-form"
                                            onsubmit="return confirm('Delete ALL attendance records for <?= htmlspecialchars(
                                                addslashes(
                                                    (string) $employeeName
                                                )
                                            ) ?>? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="_token"
                                                value="<?= htmlspecialchars(
                                                    (string) $csrfToken
                                                ) ?>"
                                            >


                                            <button
                                                type="submit"
                                                class="delete-action"
                                            >
                                                <?= __('common.delete') ?>
                                            </button>

                                        </form>


                                    </div>

                                <?php else: ?>

                                    -

                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="9"
                            class="empty-state"
                        >
                            <?= __('attendance.no_records_short') ?>
                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Attendance Page JavaScript
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const fileInput =
            document.getElementById(
                'ngteco_file'
            );


        const uploadForm =
            document.getElementById(
                'attendanceUploadForm'
            );


        const selectedFileName =
            document.getElementById(
                'selectedFileName'
            );


        const uploadLoading =
            document.getElementById(
                'uploadLoading'
            );


        const confirmForm =
            document.getElementById(
                'confirmImportForm'
            );


        const cancelPreviewBtn =
            document.getElementById(
                'cancelPreviewBtn'
            );


        /*
        |--------------------------------------------------------------------------
        | Upload
        |--------------------------------------------------------------------------
        */

        if (
            fileInput &&
            uploadForm
        ) {

            fileInput.addEventListener(
                'change',
                function () {

                    if (
                        !this.files ||
                        !this.files.length
                    ) {
                        return;
                    }


                    const file =
                        this.files[0];


                    const allowedExtensions = [
                        'xlsx',
                        'xls',
                        'csv'
                    ];


                    const extension =
                        file.name
                            .split('.')
                            .pop()
                            .toLowerCase();


                    if (
                        !allowedExtensions.includes(
                            extension
                        )
                    ) {

                        alert(
                            'Please select an Excel or CSV file.'
                        );


                        this.value = '';


                        if (
                            selectedFileName
                        ) {

                            selectedFileName.textContent =
                                '';

                        }


                        return;
                    }


                    if (
                        selectedFileName
                    ) {

                        selectedFileName.textContent =
                            file.name;

                    }


                    if (
                        uploadLoading
                    ) {

                        uploadLoading.classList.add(
                            'active'
                        );

                    }


                    uploadForm.submit();

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Confirm Import
        |--------------------------------------------------------------------------
        */

        if (
            confirmForm
        ) {

            confirmForm.addEventListener(
                'submit',
                function () {

                    const button =
                        document.getElementById(
                            'confirmImportBtn'
                        );


                    if (button) {

                        button.disabled =
                            true;

                        button.textContent =
                            'Importing...';

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Cancel Preview
        |--------------------------------------------------------------------------
        */

        if (
            cancelPreviewBtn
        ) {

            cancelPreviewBtn.addEventListener(
                'click',
                function () {

                    window.location.href =
                        '/attendance/import/cancel';

                }
            );

        }

    }
);

</script>