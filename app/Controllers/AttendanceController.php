<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\NGTecoExcelImporter;
use App\Services\DepartmentOTSettings;
use App\Services\AttendanceCalculator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PDO;

class AttendanceController
{
    /**
     * Format attendance minutes the same way the Attendance page does.
     * Example: 64 => 1h 4m, 600 => 10h, 0 => 0m.
     */
    private function calculateEarlyOvertimeSeconds(
        ?string $timeIn,
        ?string $scheduleTimeIn
    ): int {
        if (!$timeIn || !$scheduleTimeIn) {
            return 0;
        }

        $timeInTimestamp = strtotime($timeIn);

        if ($timeInTimestamp === false) {
            return 0;
        }

        // Use the same attendance date as Time In and the employee's
        // configured Schedule Time In. There is no global 9:00 AM rule.
        $date = date('Y-m-d', $timeInTimestamp);
        $scheduleInTimestamp = strtotime(
            $date . ' ' . $scheduleTimeIn
        );

        if (
            $scheduleInTimestamp === false
            || $timeInTimestamp >= $scheduleInTimestamp
        ) {
            return 0;
        }

        return $scheduleInTimestamp - $timeInTimestamp;
    }

    /**
     * Display minute values without changing the underlying calculation.
     * Exact decimal minutes are kept internally; only the text display
     * is truncated to whole minutes (no rounding).
     */
    private function formatAttendanceMinutes($minutes): string
    {
        $minutes = max(0.0, (float) ($minutes ?? 0));
        $wholeMinutes = (int) floor($minutes);

        if ($wholeMinutes <= 0) {
            return '0m';
        }

        $hours = intdiv($wholeMinutes, 60);
        $remainingMinutes = $wholeMinutes % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }

        if ($remainingMinutes > 0) {
            $parts[] = $remainingMinutes . 'm';
        }

        return implode(' ', $parts);
    }

private function formatAttendanceSeconds($seconds): string
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

    private function calculateCountedEarlyOvertimeMinutes(int $earlyOtSeconds): int
    {
        $minutes = max(0, intdiv($earlyOtSeconds, 60));

        return $minutes >= AttendanceCalculator::EARLY_OT_MINUTES
            ? $minutes
            : 0;
    }

    /**
     * Attendance list.
     */
    public function index(): void
    {
        Auth::requireLogin();

        $db = Database::connection();
        $user = Auth::user();

        /*
        |--------------------------------------------------------------------------
        | Search / Status
        |--------------------------------------------------------------------------
        */

        $search = trim(
            (string) ($_GET['search'] ?? '')
        );

        $status = trim(
            (string) ($_GET['status'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Main Attendance Query
        |--------------------------------------------------------------------------
        |
        | One employee = one row.
        |
        */

        $sql = "
            SELECT
                e.id AS employee_id,
                e.employee_code,
                e.first_name,
                e.last_name,

                COUNT(a.id) AS attendance_count,

                MAX(a.attendance_date) AS latest_attendance_date,

                (
                    SELECT a2.time_in
                    FROM attendance a2
                    WHERE
                        (
                            CAST(a2.employee_id AS CHAR) = CAST(e.id AS CHAR)
                            OR CAST(a2.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                            OR CAST(a2.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                        )
                        AND a2.attendance_date = (
                            SELECT MAX(a3.attendance_date)
                            FROM attendance a3
                            WHERE
                                (
                                    CAST(a3.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                    OR CAST(a3.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                    OR CAST(a3.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                                )
                        )
                    ORDER BY a2.id DESC
                    LIMIT 1
                ) AS time_in,

                (
                    SELECT a2.time_out
                    FROM attendance a2
                    WHERE
                        (
                            CAST(a2.employee_id AS CHAR) = CAST(e.id AS CHAR)
                            OR CAST(a2.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                            OR CAST(a2.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                        )
                        AND a2.attendance_date = (
                            SELECT MAX(a3.attendance_date)
                            FROM attendance a3
                            WHERE
                                (
                                    CAST(a3.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                    OR CAST(a3.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                    OR CAST(a3.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                                )
                        )
                    ORDER BY a2.id DESC
                    LIMIT 1
                ) AS time_out,

                (
                    SELECT a2.worked_minutes
                    FROM attendance a2
                    WHERE
                        (
                            CAST(a2.employee_id AS CHAR) = CAST(e.id AS CHAR)
                            OR CAST(a2.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                            OR CAST(a2.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                        )
                        AND a2.attendance_date = (
                            SELECT MAX(a3.attendance_date)
                            FROM attendance a3
                            WHERE
                                (
                                    CAST(a3.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                    OR CAST(a3.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                    OR CAST(a3.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                                )
                        )
                    ORDER BY a2.id DESC
                    LIMIT 1
                ) AS worked_minutes,

                (
                    SELECT a2.attendance_status
                    FROM attendance a2
                    WHERE
                        (
                            CAST(a2.employee_id AS CHAR) = CAST(e.id AS CHAR)
                            OR CAST(a2.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                            OR CAST(a2.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                        )
                        AND a2.attendance_date = (
                            SELECT MAX(a3.attendance_date)
                            FROM attendance a3
                            WHERE
                                (
                                    CAST(a3.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                    OR CAST(a3.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                    OR CAST(a3.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                                )
                        )
                    ORDER BY a2.id DESC
                    LIMIT 1
                ) AS attendance_status,

                (
                    SELECT h.holiday_name
                    FROM holidays h
                    WHERE h.holiday_date = (
                        SELECT MAX(a4.attendance_date)
                        FROM attendance a4
                        WHERE
                            (
                                CAST(a4.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                OR CAST(a4.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                OR CAST(a4.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                            )
                    )
                    AND h.is_active = 1
                    ORDER BY h.id DESC
                    LIMIT 1
                ) AS holiday_name,

                (
                    SELECT h.holiday_type
                    FROM holidays h
                    WHERE h.holiday_date = (
                        SELECT MAX(a4.attendance_date)
                        FROM attendance a4
                        WHERE
                            (
                                CAST(a4.employee_id AS CHAR) = CAST(e.id AS CHAR)
                                OR CAST(a4.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                                OR CAST(a4.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                            )
                    )
                    AND h.is_active = 1
                    ORDER BY h.id DESC
                    LIMIT 1
                ) AS holiday_type

            FROM employees e

            INNER JOIN attendance a
                ON (
                    CAST(a.employee_id AS CHAR) = CAST(e.id AS CHAR)
                    OR CAST(a.employee_id AS CHAR) = CAST(e.employee_code AS CHAR)
                    OR CAST(a.ngteco_user_id AS CHAR) = CAST(e.ngteco_user_id AS CHAR)
                )

            WHERE 1 = 1
        ";

        $params = [];

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($search !== '') {

            $sql .= "
                AND (
                    CONCAT(
                        COALESCE(e.first_name, ''),
                        ' ',
                        COALESCE(e.last_name, '')
                    ) LIKE :search_name

                    OR e.first_name LIKE :search_first_name

                    OR e.last_name LIKE :search_last_name

                    OR CAST(e.id AS CHAR) LIKE :search_employee_id

                    OR CAST(e.employee_code AS CHAR) LIKE :search_employee_code

                    OR DATE_FORMAT(
                        a.attendance_date,
                        '%Y-%m-%d'
                    ) LIKE :search_date

                    OR DATE_FORMAT(
                        a.attendance_date,
                        '%M %d, %Y'
                    ) LIKE :search_date_text
                )
            ";

            $searchValue =
                '%' . $search . '%';

            $params[':search_name'] =
                $searchValue;

            $params[':search_first_name'] =
                $searchValue;

            $params[':search_last_name'] =
                $searchValue;

            $params[':search_employee_id'] =
                $searchValue;

            $params[':search_employee_code'] =
                $searchValue;

            $params[':search_date'] =
                $searchValue;

            $params[':search_date_text'] =
                $searchValue;
        }

        /*
        |--------------------------------------------------------------------------
        | Status Filter
        |--------------------------------------------------------------------------
        */

        if ($status !== '') {

            $sql .= "
                AND a.attendance_status = :status
            ";

            $params[':status'] =
                $status;
        }

        /*
        |--------------------------------------------------------------------------
        | Group / Order
        |--------------------------------------------------------------------------
        */

        $sql .= "
            GROUP BY
                e.id,
                e.employee_code,
                e.first_name,
                e.last_name,
                e.ngteco_user_id

            HAVING COUNT(a.id) > 0

            ORDER BY
                e.last_name ASC,
                e.first_name ASC
        ";

        $stmt =
            $db->prepare($sql);

        $stmt->execute($params);

        $attendance =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Import Preview
        |--------------------------------------------------------------------------
        */

        $importPreview =
            $_SESSION['attendance_import_preview']
            ?? [];

        /*
        |--------------------------------------------------------------------------
        | Upload / Import Messages
        |--------------------------------------------------------------------------
        */

        $importSuccess =
            $_SESSION['attendance_import_success']
            ?? $_SESSION['attendance_upload_success']
            ?? false;

        $error =
            $_SESSION['attendance_import_error']
            ?? $_SESSION['attendance_upload_error']
            ?? '';

        /*
        |--------------------------------------------------------------------------
        | Clear One-Time Messages
        |--------------------------------------------------------------------------
        */

        unset(
            $_SESSION['attendance_import_success'],
            $_SESSION['attendance_upload_success'],
            $_SESSION['attendance_import_error'],
            $_SESSION['attendance_upload_error']
        );

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */

        require __DIR__
            . '/../../resources/views/attendance/index.php';
    }


    /**
     * Employee attendance details.
     */
    public function employee(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();
        $user = Auth::user();

        $employeeId =
            (int) $id;

        /*
        |--------------------------------------------------------------------------
        | Get Employee
        |--------------------------------------------------------------------------
        */

        $stmt =
            $db->prepare("
                SELECT *
                FROM employees
                WHERE id = :id
                LIMIT 1
            ");

        $stmt->execute([
            ':id' => $employeeId
        ]);

        $employee =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {

            http_response_code(404);

            echo 'Employee not found.';

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Full Name
        |--------------------------------------------------------------------------
        */

        if (
            empty($employee['full_name'])
        ) {

            $employee['full_name'] =
                trim(
                    ($employee['first_name'] ?? '')
                    . ' '
                    . ($employee['last_name'] ?? '')
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Date Filters
        |--------------------------------------------------------------------------
        */

        $dateFrom =
            trim(
                (string) (
                    $_GET['date_from']
                    ?? date('Y-m-01')
                )
            );

        $dateTo =
            trim(
                (string) (
                    $_GET['date_to']
                    ?? date('Y-m-d')
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Attendance Records
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT
                a.*,
                h.holiday_name,
                h.holiday_type
            FROM attendance a
            LEFT JOIN holidays h
                ON h.holiday_date = a.attendance_date
                AND h.is_active = 1
            WHERE
                (
                    CAST(a.employee_id AS CHAR) = CAST(:employee_id AS CHAR)
                    OR CAST(a.employee_id AS CHAR) = CAST(:employee_code AS CHAR)
                    OR CAST(a.ngteco_user_id AS CHAR) = CAST(:ngteco_user_id AS CHAR)
                )
        ";

        $params = [
            ':employee_id' =>
                $employee['id'],

            ':employee_code' =>
                $employee['employee_code'] ?? '',

            ':ngteco_user_id' =>
                $employee['ngteco_user_id'] ?? ''
        ];

        if ($dateFrom !== '') {

            $sql .= "
                AND a.attendance_date >= :date_from
            ";

            $params[':date_from'] =
                $dateFrom;
        }

        if ($dateTo !== '') {

            $sql .= "
                AND a.attendance_date <= :date_to
            ";

            $params[':date_to'] =
                $dateTo;
        }

        $sql .= "
            ORDER BY
                a.attendance_date DESC,
                a.id DESC
        ";

        $stmt =
            $db->prepare($sql);

        $stmt->execute($params);

        $records =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as &$record) {
            $metrics = AttendanceCalculator::evaluate(
                (string) ($record['attendance_date'] ?? ''),
                $record['time_in'] ?? null,
                $record['time_out'] ?? null,
                $employee['schedule_time_in'] ?? null,
                $employee['schedule_time_out'] ?? null,
                $employee['rest_day'] ?? null,
                !empty($record['holiday_name']),
                (string) ($employee['department'] ?? '')
            );

            // Keep exact calculator values separately.
            $record['worked_minutes_exact'] = (float) $metrics['worked_minutes'];
            $record['regular_minutes_exact'] = (float) $metrics['regular_minutes'];
            $record['late_minutes_exact'] = (float) $metrics['late_minutes'];
            $record['undertime_minutes_exact'] = (float) $metrics['undertime_minutes'];
            $record['overtime_minutes_exact'] = (float) $metrics['overtime_minutes'];
            $record['break_minutes_exact'] = (float) $metrics['break_minutes'];

            // Existing Attendance view consumes integer minute fields.
            // Truncate for display only; do not round the calculation.
            $record['worked_minutes'] = (int) floor(max(0.0, (float) $metrics['worked_minutes']));
            $record['regular_minutes'] = (int) floor(max(0.0, (float) $metrics['regular_minutes']));
            $record['late_minutes'] = (int) floor(max(0.0, (float) $metrics['late_minutes']));
            $record['undertime_minutes'] = (int) floor(max(0.0, (float) $metrics['undertime_minutes']));
            $record['overtime_minutes'] = (int) floor(max(0.0, (float) $metrics['overtime_minutes']));
            $record['break_minutes'] = (int) floor(max(0.0, (float) $metrics['break_minutes']));
            $record['attendance_status'] = $metrics['attendance_status'];

            $allowEarlyOT = DepartmentOTSettings::isEarlyOTAllowed(
                (string) ($employee['department'] ?? '')
            );

            $record['early_overtime_seconds'] =
                $allowEarlyOT
                    ? $this->calculateEarlyOvertimeSeconds(
                        $record['time_in'] ?? null,
                        $employee['schedule_time_in'] ?? null
                    )
                    : 0;

            $record['counted_early_overtime_minutes'] =
                $allowEarlyOT
                    ? $this->calculateCountedEarlyOvertimeMinutes(
                        $record['early_overtime_seconds']
                    )
                    : 0;
        }
        unset($record);

        $dailyAttendance =
            $records;

        $attendance =
            $records;

        /*
        |--------------------------------------------------------------------------
        | Download URL
        |--------------------------------------------------------------------------
        */

        $downloadUrl =
            '/attendance/employee/' .
            $employeeId .
            '/download';

        /*
        |--------------------------------------------------------------------------
        | View
        |--------------------------------------------------------------------------
        */

        require __DIR__
            . '/../../resources/views/attendance/employee.php';
    }


    /**
     * Download employee attendance as Excel.
     */
    public function downloadEmployee(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $employeeId =
            (int) $id;

        /*
        |--------------------------------------------------------------------------
        | Employee
        |--------------------------------------------------------------------------
        */

        $stmt =
            $db->prepare("
                SELECT *
                FROM employees
                WHERE id = :id
                LIMIT 1
            ");

        $stmt->execute([
            ':id' => $employeeId
        ]);

        $employee =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {

            http_response_code(404);

            echo 'Employee not found.';

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Employee Name
        |--------------------------------------------------------------------------
        */

        $employeeName =
            trim(
                ($employee['first_name'] ?? '')
                . ' '
                . ($employee['last_name'] ?? '')
            );

        if (
            $employeeName === ''
            &&
            !empty($employee['full_name'])
        ) {

            $employeeName =
                (string) $employee['full_name'];
        }

        /*
        |--------------------------------------------------------------------------
        | Date Filters
        |--------------------------------------------------------------------------
        |
        | If the user searched From/To on the employee page,
        | those dates will also be used for the download.
        |
        */

        $dateFrom =
            trim(
                (string) (
                    $_GET['date_from']
                    ?? ''
                )
            );

        $dateTo =
            trim(
                (string) (
                    $_GET['date_to']
                    ?? ''
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Attendance Query
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | No work_code here because that column does not exist
        | in the attendance table.
        |
        */

        $sql = "
            SELECT
                a.attendance_date,
                a.time_in,
                a.time_out,
                a.worked_minutes,
                a.late_minutes,
                a.undertime_minutes,
                a.overtime_minutes,
                a.attendance_status
            FROM attendance a
            WHERE
                (
                    CAST(a.employee_id AS CHAR) = CAST(:employee_id AS CHAR)
                    OR CAST(a.employee_id AS CHAR) = CAST(:employee_code AS CHAR)
                    OR CAST(a.ngteco_user_id AS CHAR) = CAST(:ngteco_user_id AS CHAR)
                )
        ";

        $params = [
            ':employee_id' =>
                $employee['id'],

            ':employee_code' =>
                $employee['employee_code'] ?? '',

            ':ngteco_user_id' =>
                $employee['ngteco_user_id'] ?? ''
        ];

        if ($dateFrom !== '') {

            $sql .= "
                AND a.attendance_date >= :date_from
            ";

            $params[':date_from'] =
                $dateFrom;
        }

        if ($dateTo !== '') {

            $sql .= "
                AND a.attendance_date <= :date_to
            ";

            $params[':date_to'] =
                $dateTo;
        }

        $sql .= "
            ORDER BY
                a.attendance_date ASC,
                a.id ASC
        ";

        $stmt =
            $db->prepare($sql);

        $stmt->execute($params);

        $records =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as &$record) {
            $metrics = AttendanceCalculator::evaluate(
                (string) ($record['attendance_date'] ?? ''),
                $record['time_in'] ?? null,
                $record['time_out'] ?? null,
                $employee['schedule_time_in'] ?? null,
                $employee['schedule_time_out'] ?? null,
                $employee['rest_day'] ?? null,
                (($record['attendance_status'] ?? '') === 'Holiday'),
                (string) ($employee['department'] ?? '')
            );

            // Keep exact calculator values separately.
            $record['worked_minutes_exact'] = (float) $metrics['worked_minutes'];
            $record['late_minutes_exact'] = (float) $metrics['late_minutes'];
            $record['undertime_minutes_exact'] = (float) $metrics['undertime_minutes'];
            $record['overtime_minutes_exact'] = (float) $metrics['overtime_minutes'];
            $record['break_minutes_exact'] = (float) $metrics['break_minutes'];

            // Download/view fields are display values only.
            $record['worked_minutes'] = (int) floor(max(0.0, (float) $metrics['worked_minutes']));
            $record['late_minutes'] = (int) floor(max(0.0, (float) $metrics['late_minutes']));
            $record['undertime_minutes'] = (int) floor(max(0.0, (float) $metrics['undertime_minutes']));
            $record['overtime_minutes'] = (int) floor(max(0.0, (float) $metrics['overtime_minutes']));
            $record['break_minutes'] = (int) floor(max(0.0, (float) $metrics['break_minutes']));
            $record['attendance_status'] = $metrics['attendance_status'];

            $allowEarlyOT = DepartmentOTSettings::isEarlyOTAllowed(
                (string) ($employee['department'] ?? '')
            );

            $record['early_overtime_seconds'] =
                $allowEarlyOT
                    ? $this->calculateEarlyOvertimeSeconds(
                        $record['time_in'] ?? null,
                        $employee['schedule_time_in'] ?? null
                    )
                    : 0;

            $record['counted_early_overtime_minutes'] =
                $allowEarlyOT
                    ? $this->calculateCountedEarlyOvertimeMinutes(
                        $record['early_overtime_seconds']
                    )
                    : 0;
        }
        unset($record);

        /*
        |--------------------------------------------------------------------------
        | Spreadsheet
        |--------------------------------------------------------------------------
        */

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->setTitle(
            'Attendance'
        );

        /*
        |--------------------------------------------------------------------------
        | Report Title
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A1:K1'
        );

        $sheet->setCellValue(
            'A1',
            'EMPLOYEE ATTENDANCE REPORT'
        );

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(true);

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setSize(16);

        $sheet
            ->getStyle('A1')
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            );

        $sheet
            ->getStyle('A1')
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet
            ->getRowDimension(1)
            ->setRowHeight(30);

        /*
        |--------------------------------------------------------------------------
        | Employee Information
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A2:K2'
        );

        $sheet->setCellValue(
            'A2',
            'Employee: ' .
            $employeeName .
            '    |    Employee ID: ' .
            ($employee['employee_code'] ?? '-')
        );

        $sheet
            ->getStyle('A2')
            ->getFont()
            ->setBold(true);

        $sheet
            ->getStyle('A2')
            ->getFont()
            ->setSize(11);

        $sheet
            ->getStyle('A2')
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $sheet
            ->getRowDimension(2)
            ->setRowHeight(22);

        /*
        |--------------------------------------------------------------------------
        | Date Range
        |--------------------------------------------------------------------------
        */

        $rangeText =
            'Date Range: ' .
            (
                $dateFrom !== ''
                    ? $dateFrom
                    : 'All'
            ) .
            ' to ' .
            (
                $dateTo !== ''
                    ? $dateTo
                    : 'All'
            );

        $sheet->mergeCells(
            'A3:K3'
        );

        $sheet->setCellValue(
            'A3',
            $rangeText
        );

        $sheet
            ->getStyle('A3')
            ->getFont()
            ->setItalic(true);

        $sheet
            ->getStyle('A3')
            ->getFont()
            ->setSize(10);

        $sheet
            ->getRowDimension(3)
            ->setRowHeight(20);

        /*
        |--------------------------------------------------------------------------
        | Generated Date
        |--------------------------------------------------------------------------
        */

        $sheet->mergeCells(
            'A4:K4'
        );

        $sheet->setCellValue(
            'A4',
            'Generated: ' .
            date('M d, Y h:i A')
        );

        $sheet
            ->getStyle('A4')
            ->getFont()
            ->setSize(9);

        $sheet
            ->getStyle('A4')
            ->getFont()
            ->setItalic(true);

        $sheet
            ->getRowDimension(4)
            ->setRowHeight(18);

        /*
        |--------------------------------------------------------------------------
        | Table Header
        |--------------------------------------------------------------------------
        */

        $headerRow =
            6;

        $headers = [
            'Employee ID',
            'Employee Name',
            'Date',
            'Time In',
            'Time Out',
            'Worked',
            'Late',
            'Undertime',
            'Early OT',

                    'Overtime',
            'Status'
        ];

        $sheet->fromArray(
            [$headers],
            null,
            'A' . $headerRow
        );

        /*
        |--------------------------------------------------------------------------
        | Header Style
        |--------------------------------------------------------------------------
        */

        $headerRange =
            'A' .
            $headerRow .
            ':K' .
            $headerRow;

        $headerStyle =
            $sheet->getStyle(
                $headerRange
            );

        $headerStyle
            ->getFont()
            ->setBold(true);

        $headerStyle
            ->getFont()
            ->setSize(10);

        $headerStyle
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            );

        $headerStyle
            ->getAlignment()
            ->setVertical(
                Alignment::VERTICAL_CENTER
            );

        $headerStyle
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        $headerStyle
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            );

        /*
        |--------------------------------------------------------------------------
        | Attendance Data
        |--------------------------------------------------------------------------
        */

        $rowNumber =
            $headerRow + 1;

        foreach ($records as $record) {

            $sheet->fromArray(
                [[
                    $employee['employee_code']
                        ?? '',

                    $employeeName,

                    $record['attendance_date']
                        ?? '',

                    !empty($record['time_in'])
                        ? date('h:i:s A', strtotime($record['time_in']))
                        : '',

                    !empty($record['time_out'])
                        ? date('h:i:s A', strtotime($record['time_out']))
                        : '',

                    $this->formatAttendanceMinutes(
                        $record['worked_minutes'] ?? 0
                    ),

                    $this->formatAttendanceMinutes(
                        $record['late_minutes'] ?? 0
                    ),

                    $this->formatAttendanceMinutes(
                        $record['undertime_minutes'] ?? 0
                    ),

                    $this->formatAttendanceSeconds(
                        $record['early_overtime_seconds'] ?? 0
                    ),

                    $this->formatAttendanceMinutes(
                        $record['overtime_minutes'] ?? 0
                    ),

                    $record['attendance_status']
                        ?? ''
                ]],
                null,
                'A' . $rowNumber
            );

            $rowNumber++;
        }

        /*
        |--------------------------------------------------------------------------
        | Data Formatting
        |--------------------------------------------------------------------------
        */

        if (
            $rowNumber >
            $headerRow + 1
        ) {

            $dataRange =
                'A' .
                ($headerRow + 1) .
                ':K' .
                ($rowNumber - 1);

            $dataStyle =
                $sheet->getStyle(
                    $dataRange
                );

            $dataStyle
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(
                    Border::BORDER_THIN
                );

            $dataStyle
                ->getAlignment()
                ->setVertical(
                    Alignment::VERTICAL_CENTER
                );

            /*
            |--------------------------------------------------------------------------
            | Center Alignment
            |--------------------------------------------------------------------------
            */

            $sheet
                ->getStyle(
                    'A' .
                    ($headerRow + 1) .
                    ':A' .
                    ($rowNumber - 1)
                )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

            $sheet
                ->getStyle(
                    'C' .
                    ($headerRow + 1) .
                    ':J' .
                    ($rowNumber - 1)
                )
                ->getAlignment()
                ->setHorizontal(
                    Alignment::HORIZONTAL_CENTER
                );

        }

        /*
        |--------------------------------------------------------------------------
        | Column Widths
        |--------------------------------------------------------------------------
        */

        $columnWidths = [
            'A' => 15,
            'B' => 26,
            'C' => 15,
            'D' => 18,
            'E' => 18,
            'F' => 12,
            'G' => 12,
            'H' => 14,
            'I' => 12,
            'J' => 12,
            'K' => 16
        ];

        foreach (
            $columnWidths as
            $column =>
            $width
        ) {

            $sheet
                ->getColumnDimension(
                    $column
                )
                ->setWidth(
                    $width
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Row Height
        |--------------------------------------------------------------------------
        */

        if (
            $rowNumber >
            $headerRow + 1
        ) {

            for (
                $i = $headerRow + 1;
                $i < $rowNumber;
                $i++
            ) {

                $sheet
                    ->getRowDimension($i)
                    ->setRowHeight(20);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Freeze Header
        |--------------------------------------------------------------------------
        */

        $sheet->freezePane(
            'A7'
        );

        /*
        |--------------------------------------------------------------------------
        | Auto Filter
        |--------------------------------------------------------------------------
        */

        if (
            $rowNumber >
            $headerRow
        ) {

            $sheet->setAutoFilter(
                'A' .
                $headerRow .
                ':K' .
                ($rowNumber - 1)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Print Setup
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageSetup()
            ->setOrientation(
                PageSetup::ORIENTATION_LANDSCAPE
            );

        $sheet
            ->getPageSetup()
            ->setPaperSize(
                PageSetup::PAPERSIZE_A4
            );

        $sheet
            ->getPageSetup()
            ->setFitToWidth(1);

        $sheet
            ->getPageSetup()
            ->setFitToHeight(0);

        $sheet
            ->getPageMargins()
            ->setTop(0.4);

        $sheet
            ->getPageMargins()
            ->setBottom(0.4);

        $sheet
            ->getPageMargins()
            ->setLeft(0.3);

        $sheet
            ->getPageMargins()
            ->setRight(0.3);

        /*
        |--------------------------------------------------------------------------
        | Repeat Header When Printing
        |--------------------------------------------------------------------------
        */

        $sheet
            ->getPageSetup()
            ->setRowsToRepeatAtTopByStartAndEnd(
                $headerRow,
                $headerRow
            );

        /*
        |--------------------------------------------------------------------------
        | File Name
        |--------------------------------------------------------------------------
        */

        $safeName =
            preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $employeeName
            );

        if (
            !$safeName
        ) {

            $safeName =
                'employee_' .
                $employeeId;
        }

        $fileName =
            'attendance_' .
            $safeName .
            '.xlsx';

        /*
        |--------------------------------------------------------------------------
        | Download Headers
        |--------------------------------------------------------------------------
        */

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $fileName .
            '"'
        );

        header(
            'Cache-Control: max-age=0'
        );

        header(
            'Pragma: public'
        );

        /*
        |--------------------------------------------------------------------------
        | Output Excel
        |--------------------------------------------------------------------------
        */

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            'php://output'
        );

        exit;
    }


    /**
     * Delete attendance records for one employee.
     */
    public function delete(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $employeeId =
            (int) $id;

        /*
        |--------------------------------------------------------------------------
        | Find Employee
        |--------------------------------------------------------------------------
        */

        $stmt =
            $db->prepare("
                SELECT
                    id,
                    employee_code,
                    ngteco_user_id,
                    first_name,
                    last_name
                FROM employees
                WHERE id = :id
                LIMIT 1
            ");

        $stmt->execute([
            ':id' => $employeeId
        ]);

        $employee =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$employee) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'Employee not found.';

            header(
                'Location: /attendance'
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Delete
        |--------------------------------------------------------------------------
        */

        $stmt =
            $db->prepare("
                DELETE FROM attendance
                WHERE
                    CAST(employee_id AS CHAR) = CAST(:employee_id AS CHAR)
                    OR CAST(employee_id AS CHAR) = CAST(:employee_code AS CHAR)
                    OR CAST(ngteco_user_id AS CHAR) = CAST(:ngteco_user_id AS CHAR)
            ");

        $stmt->execute([
            ':employee_id' =>
                $employee['id'],

            ':employee_code' =>
                $employee['employee_code']
                ?? '',

            ':ngteco_user_id' =>
                $employee['ngteco_user_id']
                ?? ''
        ]);

        /*
        |--------------------------------------------------------------------------
        | Employee Name
        |--------------------------------------------------------------------------
        */

        $employeeName =
            trim(
                ($employee['first_name'] ?? '')
                . ' '
                . ($employee['last_name'] ?? '')
            );

        $_SESSION[
            'attendance_upload_success'
        ] =
            $employeeName .
            ' attendance records deleted successfully.';

        header(
            'Location: /attendance'
        );

        exit;
    }


    /**
     * Upload NGTeco Excel / CSV.
     */
    public function upload(): void
    {
        Auth::requireLogin();

        /*
        |--------------------------------------------------------------------------
        | Clear Old Messages
        |--------------------------------------------------------------------------
        */

        unset(
            $_SESSION['attendance_upload_success'],
            $_SESSION['attendance_upload_error'],
            $_SESSION['attendance_import_success'],
            $_SESSION['attendance_import_error']
        );

        /*
        |--------------------------------------------------------------------------
        | File Check
        |--------------------------------------------------------------------------
        */

        if (
            !isset($_FILES['attendance_file'])
            ||
            !is_array(
                $_FILES['attendance_file']
            )
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'Please select an Excel or CSV file.';

            header(
                'Location: /attendance'
            );

            exit;
        }

        $file =
            $_FILES['attendance_file'];

        /*
        |--------------------------------------------------------------------------
        | Upload Error
        |--------------------------------------------------------------------------
        */

        if (
            ($file['error']
                ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'The uploaded file could not be processed.';

            header(
                'Location: /attendance'
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Extension
        |--------------------------------------------------------------------------
        */

        $originalName =
            (string) (
                $file['name']
                ?? ''
            );

        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );

        $allowedExtensions = [
            'xlsx',
            'xls',
            'csv'
        ];

        if (
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'Please upload an XLSX, XLS, or CSV file.';

            header(
                'Location: /attendance'
            );

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Import Preview
        |--------------------------------------------------------------------------
        */

        try {

            $importer =
                new NGTecoExcelImporter();

            $preview =
                $importer->preview(
                    $file['tmp_name']
                );

            if (
                !is_array($preview)
            ) {

                throw new \RuntimeException(
                    'The importer returned an invalid preview.'
                );
            }

            $preview['file_name'] =
                $originalName;

            $_SESSION[
                'attendance_import_preview'
            ] =
                $preview;

            $_SESSION[
                'attendance_upload_success'
            ] =
                'File uploaded successfully. Review the preview below before confirming the import.';

            header(
                'Location: /attendance'
            );

            exit;

        } catch (
            \Throwable $e
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'Upload failed: ' .
                $e->getMessage();

            header(
                'Location: /attendance'
            );

            exit;
        }
    }


    /**
     * Confirm attendance import.
     */
    public function confirmImport(): void
    {
        Auth::requireLogin();

        $preview =
            $_SESSION[
                'attendance_import_preview'
            ]
            ?? null;

        if (
            !is_array($preview)
            ||
            empty($preview)
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'No attendance preview found. Please upload the file again.';

            header(
                'Location: /attendance'
            );

            exit;
        }

        try {

            $importer =
                new NGTecoExcelImporter();

            $result =
                $importer->import(
                    $preview
                );

            unset(
                $_SESSION[
                    'attendance_import_preview'
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Imported Count
            |--------------------------------------------------------------------------
            */

            $importedCount =
                null;

            if (
                is_array($result)
            ) {

                if (
                    isset(
                        $result['imported']
                    )
                ) {

                    $importedCount =
                        (int) $result[
                            'imported'
                        ];

                } elseif (
                    isset(
                        $result['imported_count']
                    )
                ) {

                    $importedCount =
                        (int) $result[
                            'imported_count'
                        ];
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Success Message
            |--------------------------------------------------------------------------
            */

            if (
                $importedCount !== null
            ) {

                $_SESSION[
                    'attendance_upload_success'
                ] =
                    $importedCount .
                    ' attendance record' .
                    (
                        $importedCount === 1
                            ? ''
                            : 's'
                    ) .
                    ' imported successfully.';

            } else {

                $_SESSION[
                    'attendance_upload_success'
                ] =
                    'Attendance imported successfully.';
            }

        } catch (
            \Throwable $e
        ) {

            $_SESSION[
                'attendance_upload_error'
            ] =
                'Import failed: ' .
                $e->getMessage();
        }

        header(
            'Location: /attendance'
        );

        exit;
    }


    /**
     * Cancel attendance import.
     */
    public function cancelImport(): void
    {
        Auth::requireLogin();

        unset(
            $_SESSION[
                'attendance_import_preview'
            ],

            $_SESSION[
                'attendance_upload_success'
            ],

            $_SESSION[
                'attendance_upload_error'
            ],

            $_SESSION[
                'attendance_import_success'
            ],

            $_SESSION[
                'attendance_import_error'
            ]
        );

        header(
            'Location: /attendance'
        );

        exit;
    }


    /**
     * Check if a date is a rest day.
     */
    private function isRestDay(
        string $date,
        array $restDays = []
    ): bool {

        $timestamp =
            strtotime($date);

        if (
            $timestamp === false
        ) {




            return false;
        }

        $weekday =
            strtolower(
                date(
                    'l',
                    $timestamp
                )
            );

        foreach (
            $restDays
            as $restDay
        ) {

            $restDay =
                strtolower(
                    trim(
                        (string) $restDay
                    )
                );

            if (
                $restDay === $weekday
                ||
                substr(
                    $restDay,
                    0,
                    3
                )
                ===
                substr(
                    $weekday,
                    0,
                    3
                )
            ) {

                return true;
            }
        }

        return false;
    }
}
