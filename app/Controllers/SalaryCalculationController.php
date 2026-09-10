<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\AttendanceCalculator;
use Exception;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use ZipArchive;

class SalaryCalculationController
{
    /**
     * ============================================================
     * SALARY CALCULATION CONTROLLER
     * ============================================================
     *
     * FLOW:
     *
     * Employees
     *      ↓
     * Attendance
     *      ↓
     * Salary Calculation
     *      ↓
     * Payroll Run
     *      ↓
     * Payslip
     *
     * No payroll_rules table is required.
     */

    /**
     * ============================================================
     * INDEX
     * ============================================================
     */
    public function index(): void
    {
        Auth::requireLogin();

        $db = Database::connection();
        $user = Auth::user();
        $this->ensureDownloadTrackingColumns($db);

        $runs = $db->query(
            "SELECT
                r.id,
                r.period_start,
                r.period_end,
                r.status,
                r.created_at,
                COALESCE(r.download_count, 0) AS download_count,
                r.last_downloaded_at,

                (
                    SELECT COUNT(*)
                    FROM payroll_history h
                    WHERE h.payroll_run_id = r.id
                ) AS employee_count,

                (
                    SELECT COALESCE(SUM(h.net_pay), 0)
                    FROM payroll_history h
                    WHERE h.payroll_run_id = r.id
                ) AS total_net_pay

             FROM payroll_runs r

             WHERE r.period_type = 'Weekly'

             ORDER BY r.period_start DESC"
        )->fetchAll();

        $today = new \DateTime();
        $defaultFrom = $today->format('Y-m-d');
        $defaultTo = $today->format('Y-m-d');

        // Salary errors are intentionally not displayed on the Salary Calculation page.
        // Keep the session clean so an old transaction error cannot reappear after refresh.
        $error = null;
        $success = $_SESSION['salary_success'] ?? null;

        unset(
            $_SESSION['salary_error'],
            $_SESSION['salary_success']
        );

        require __DIR__ . '/../../resources/views/salary-calculation/index.php';
    }

    /**
     * ============================================================
     * DELETE PAYROLL RUN
     * ============================================================
     */
    public function delete(string $id): void
    {
        Auth::requireRole('admin');

        $db = Database::connection();
        $runId = (int) $id;

        if ($runId <= 0) {
            $_SESSION['salary_error'] = 'Invalid payroll run.';
            header('Location: /salary-calculation');
            exit;
        }

        $run = $this->getRun($db, (string) $runId);

        if (!$run) {
            $_SESSION['salary_error'] = 'Payroll run not found.';
            header('Location: /salary-calculation');
            exit;
        }

        try {
            $db->beginTransaction();

            $deleteHistory = $db->prepare(
                "DELETE FROM payroll_history
                 WHERE payroll_run_id = :run_id"
            );
            $deleteHistory->execute(['run_id' => $runId]);

            $deleteRun = $db->prepare(
                "DELETE FROM payroll_runs
                 WHERE id = :id
                 LIMIT 1"
            );
            $deleteRun->execute(['id' => $runId]);

            if ($deleteRun->rowCount() !== 1) {
                throw new Exception('Hindi ma-delete ang payroll run.');
            }

            $db->commit();

            $_SESSION['salary_success'] =
                'Salary calculation record deleted successfully.';
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $_SESSION['salary_error'] =
                'Failed to delete salary calculation: ' . $e->getMessage();
        }

        header('Location: /salary-calculation');
        exit;
    }


    /**
     * ============================================================
     * DELETE ONE EMPLOYEE PAYROLL LINE
     * ============================================================
     *
     * Deletes only the selected employee's payroll history record
     * from the selected payroll run. The payroll run itself remains.
     */
    public function deleteEmployee(string $id, string $lineId): void
    {
        Auth::requireRole('admin');

        $db = Database::connection();

        $runId = (int) $id;
        $lineId = (int) $lineId;

        if ($runId <= 0 || $lineId <= 0) {
            $_SESSION['salary_error'] =
                'Invalid payroll run or payroll line.';

            header('Location: /salary-calculation');
            exit;
        }

        $run = $this->getRun($db, (string) $runId);

        if (!$run) {
            $_SESSION['salary_error'] =
                'Payroll run not found.';

            header('Location: /salary-calculation');
            exit;
        }

        $lineStmt = $db->prepare(
            "SELECT employee_name
             FROM payroll_history
             WHERE payroll_run_id = :run_id
               AND id = :line_id
             LIMIT 1"
        );

        $lineStmt->execute([
            'run_id' => $runId,
            'line_id' => $lineId,
        ]);

        $line = $lineStmt->fetch();

        if (!$line) {
            $_SESSION['salary_error'] =
                'Employee payroll record not found in this salary calculation.';

            header('Location: /salary-calculation/' . $runId);
            exit;
        }

        try {
            $deleteStmt = $db->prepare(
                "DELETE FROM payroll_history
                 WHERE payroll_run_id = :run_id
                   AND id = :line_id
                 LIMIT 1"
            );

            $deleteStmt->execute([
                'run_id' => $runId,
                'line_id' => $lineId,
            ]);

            if ($deleteStmt->rowCount() !== 1) {
                throw new Exception(
                    'Hindi ma-delete ang employee payroll record.'
                );
            }

            $_SESSION['salary_success'] =
                'Salary calculation for '
                . ($line['employee_name'] ?? 'employee')
                . ' deleted successfully.';
        } catch (Exception $e) {
            $_SESSION['salary_error'] =
                'Failed to delete employee salary calculation: '
                . $e->getMessage();
        }

        $department = trim($_POST['department'] ?? '');

        $redirect = '/salary-calculation/' . $runId;

        if ($department !== '') {
            $redirect .= '?department=' . urlencode($department);
        }

        header('Location: ' . $redirect);
        exit;
    }

    /**
     * ============================================================
     * VIEW PAYROLL RUN
     * ============================================================
     */
    public function view(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $department = trim(
            $_GET['department'] ?? ''
        );

        [
            $run,
            $lines,
            $totals,
            $departments
        ] = $this->loadRunWithLines(
            $db,
            $id,
            $department
        );

        if (!$run) {
            http_response_code(404);
            echo '404 - Payroll run not found';
            return;
        }

        require __DIR__ . '/../../resources/views/salary-calculation/view.php';
    }

    /**
     * ============================================================
     * EXPORT EXCEL
     * ============================================================
     */
    /**
     * View one employee's payroll calculation and attendance for a run.
     */
    public function employee(string $id, string $employeeId): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $run = $this->getRun($db, $id);
        if (!$run) {
            header('Location: /salary-calculation');
            exit;
        }

        $line = $this->getRunLine($db, $id, $employeeId);
        if (!$line) {
            header('Location: /salary-calculation/' . (int)$id);
            exit;
        }

        $stmt = $db->prepare(
            "SELECT
                a.attendance_date,
                a.time_in,
                a.time_out,
                a.worked_minutes,
                a.regular_minutes,
                a.overtime_minutes,
                a.late_minutes,
                a.undertime_minutes,
                CASE
                    WHEN h.id IS NOT NULL THEN 'Holiday'
                    ELSE a.attendance_status
                END AS attendance_status,
                h.holiday_name,
                h.holiday_type
             FROM attendance a
             LEFT JOIN holidays h
                ON h.holiday_date = a.attendance_date
               AND h.is_active = 1
             WHERE (
                    CAST(a.employee_id AS CHAR) = CAST(:employee_id AS CHAR)
                    OR CAST(a.employee_id AS CHAR) = CAST(:employee_code AS CHAR)
                    OR CAST(a.ngteco_user_id AS CHAR) = CAST(:ngteco_user_id AS CHAR)
                )
               AND a.attendance_date BETWEEN :period_start AND :period_end
             ORDER BY a.attendance_date ASC"
        );
        $stmt->execute([
            'employee_id' => $employeeId,
            'employee_code' => $line['employee_code'] ?? '',
            'ngteco_user_id' => $line['ngteco_user_id'] ?? '',
            'period_start' => $run['period_start'],
            'period_end' => $run['period_end'],
        ]);
        $attendance = $stmt->fetchAll();

        $trace = [];
        if (!empty($line['calculation_trace'])) {
            $decoded = json_decode($line['calculation_trace'], true);
            if (is_array($decoded)) {
                $trace = $decoded;
            }
        }

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/salary-calculation/employee.php';
    }

    public function exportExcel(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();
        $this->ensureDownloadTrackingColumns($db);

        $department = trim(
            $_GET['department'] ?? ''
        );

        [
            $run,
            $lines,
            $totals
        ] = $this->loadRunWithLines(
            $db,
            $id,
            $department
        );

        if (!$run) {
            http_response_code(404);
            echo '404 - Payroll run not found';
            return;
        }

        $periodLabel =
            date(
                'M j',
                strtotime($run['period_start'])
            )
            . ' - '
            . date(
                'M j, Y',
                strtotime($run['period_end'])
            );

        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setTitle(
            'Payroll ' .
            date(
                'Ymd',
                strtotime($run['period_start'])
            )
        );

        /*
         * ========================================================
         * TITLE
         * ========================================================
         */

        $sheet->setCellValue(
            'A1',
            'PHIXINN PAYROLL SYSTEM'
        );

        $sheet->mergeCells('A1:H1');

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(true)
            ->setSize(14);

        $sheet->setCellValue(
            'A2',
            'Salary Calculation - Weekly Payroll Run'
        );

        $sheet->mergeCells('A2:H2');

        $sheet
            ->getStyle('A2')
            ->getFont()
            ->setSize(11)
            ->setItalic(true);

        $subtitle =
            'Period: '
            . $periodLabel
            . ' | Status: '
            . $run['status'];

        if ($department !== '') {
            $subtitle .=
                ' | Department: '
                . $department;
        }

        $sheet->setCellValue(
            'A3',
            $subtitle
        );

        $sheet->mergeCells('A3:H3');

        $sheet
            ->getStyle('A3')
            ->getFont()
            ->setBold(true);

        /*
         * ========================================================
         * HEADER
         * ========================================================
         */

        $headers = [
            'A' => 'Employee',
            'B' => 'Department',
            'C' => 'Basic Pay',
            'D' => 'Overtime Pay',
            'E' => 'Allowances',
            'F' => 'Late Deduction',
            'G' => 'Undertime Deduction',
            'H' => 'Total Deduction',
        ];

        $headerRow = 5;

        foreach ($headers as $column => $label) {
            $sheet->setCellValue(
                "{$column}{$headerRow}",
                $label
            );
        }

        $sheet
            ->getStyle(
                "A{$headerRow}:H{$headerRow}"
            )
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('FFFFFF');

        $sheet
            ->getStyle(
                "A{$headerRow}:H{$headerRow}"
            )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setRGB('1E293B');

        $sheet
            ->getStyle(
                "A{$headerRow}:H{$headerRow}"
            )
            ->getAlignment()
            ->setHorizontal(
                Alignment::HORIZONTAL_CENTER
            );

        /*
         * ========================================================
         * DATA
         * ========================================================
         */

        $row = $headerRow + 1;

        $moneyColumns = [
            'C',
            'D',
            'E',
            'F',
            'G',
            'H'
        ];

        foreach ($lines as $line) {

            $sheet->setCellValue(
                "A{$row}",
                $line['employee_name']
            );

            $sheet->setCellValue(
                "B{$row}",
                $line['department'] ?? '-'
            );

            $sheet->setCellValue(
                "C{$row}",
                (float) ($line['basic_pay'] ?? 0)
            );

            $sheet->setCellValue(
                "D{$row}",
                (float) ($line['overtime_pay'] ?? 0)
            );

            $sheet->setCellValue(
                "E{$row}",
                (float) ($line['allowances'] ?? 0)
            );

            $sheet->setCellValue(
                "F{$row}",
                (float) ($line['late_deduction'] ?? 0)
            );

            $sheet->setCellValue(
                "G{$row}",
                (float) ($line['undertime_deduction'] ?? 0)
            );

            $sheet->setCellValue(
                "H{$row}",
                (float) ($line['total_deduction'] ?? 0)
            );

            $row++;
        }

        /*
         * ========================================================
         * TOTALS
         * ========================================================
         */

        $totalsRow = $row;

        $sheet->setCellValue(
            "A{$totalsRow}",
            'TOTAL'
        );

        $sheet
            ->getStyle("A{$totalsRow}")
            ->getFont()
            ->setBold(true);

        $sheet->setCellValue(
            "C{$totalsRow}",
            (float) ($totals['basic_pay'] ?? 0)
        );

        $sheet->setCellValue(
            "D{$totalsRow}",
            (float) ($totals['overtime_pay'] ?? 0)
        );

        $sheet->setCellValue(
            "E{$totalsRow}",
            (float) ($totals['allowances'] ?? 0)
        );

        $sheet->setCellValue(
            "F{$totalsRow}",
            (float) ($totals['late_deduction'] ?? 0)
        );

        $sheet->setCellValue(
            "G{$totalsRow}",
            (float) ($totals['undertime_deduction'] ?? 0)
        );

        $sheet->setCellValue(
            "H{$totalsRow}",
            (float) ($totals['total_deduction'] ?? 0)
        );

        $sheet
            ->getStyle(
                "A{$totalsRow}:H{$totalsRow}"
            )
            ->getFont()
            ->setBold(true);

        $sheet
            ->getStyle(
                "A{$totalsRow}:H{$totalsRow}"
            )
            ->getBorders()
            ->getTop()
            ->setBorderStyle(
                Border::BORDER_THIN
            );

        foreach ($moneyColumns as $column) {

            $sheet
                ->getStyle(
                    "{$column}" .
                    ($headerRow + 1) .
                    ":{$column}{$totalsRow}"
                )
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }

        foreach (range('A', 'H') as $column) {

            $sheet
                ->getColumnDimension($column)
                ->setAutoSize(true);
        }

        /*
         * ========================================================
         * OUTPUT
         * ========================================================
         */

        /*
         * ========================================================
         * PAYROLL EXCEL FILENAME
         * ========================================================
         *
         * Same month:
         * Payroll Sept 30 - 5 2026.xlsx
         *
         * Different months:
         * Payroll Aug 30, 2026 - Sep 5, 2026.xlsx
         *
         * The filename follows the actual payroll period.
         */

        $periodStartDate =
            new \DateTime(
                (string) $run['period_start']
            );

        $periodEndDate =
            new \DateTime(
                (string) $run['period_end']
            );

        if (
            $periodStartDate->format('Y-m') !==
            $periodEndDate->format('Y-m')
        ) {
            $filename =
                'Payroll '
                . $periodStartDate->format('M j, Y')
                . ' - '
                . $periodEndDate->format('M j, Y')
                . (
                    $department !== ''
                        ? ' - ' .
                          $this->sanitizeFilename(
                              $department
                          )
                        : ''
                )
                . '.xlsx';
        } else {
            $filename =
                'Payroll '
                . $periodStartDate->format('M')
                . ' '
                . $periodStartDate->format('j')
                . ' - '
                . $periodEndDate->format('j')
                . ' '
                . $periodStartDate->format('Y')
                . (
                    $department !== ''
                        ? ' - ' .
                          $this->sanitizeFilename(
                              $department
                          )
                        : ''
                )
                . '.xlsx';
        }

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        header(
            'Cache-Control: max-age=0'
        );

        $this->markPayrollRunDownloaded($db, (int) $run['id']);

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            'php://output'
        );

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);

        exit;
    }

    /**
     * ============================================================
     * PAYSLIP
     * ============================================================
     */
    public function payslip(
        string $id,
        string $employeeId
    ): void {

        Auth::requireLogin();

        $db = Database::connection();
        $this->ensureDownloadTrackingColumns($db);

        $run =
            $this->getRun(
                $db,
                $id
            );

        $line =
            $this->getRunLine(
                $db,
                $id,
                $employeeId
            );

        if (!$run || !$line) {

            http_response_code(404);

            echo '404 - Payslip not found';

            return;
        }

        $spreadsheet =
            $this->buildPayslipSpreadsheet(
                $run,
                $line
            );

        $filename =
            $this->payslipFilename(
                $run,
                $line
            );

        header(
            'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );

        header(
            'Cache-Control: max-age=0'
        );

        $this->markPayrollRunDownloaded($db, (int) $run['id']);

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            'php://output'
        );

        $spreadsheet->disconnectWorksheets();

        unset($spreadsheet);

        exit;
    }

    /**
     * ============================================================
     * DOWNLOAD PAYSLIP AS IMAGE
     * ============================================================
     *
     * Generates the same payslip information as the existing Excel
     * payslip, but renders it as a PNG image. This keeps the existing
     * XLSX download untouched while providing a non-editable image
     * version for sharing/printing.
     *
     * Visual layout only was redesigned to follow the requested
     * reference payslip format: bordered card, boxed pay-period /
     * employee-code header, labeled info grid, a Salary Summary
     * (days worked/absent/holiday - counted from existing attendance
     * records, no new formulas), a Basic Salary section broken down
     * by overtime type (Regular / Rest Day / Holiday - split using
     * the SAME multiplier logic already in computeEmployeePay(), so
     * the three amounts always add up to the existing overtime_pay
     * total), a Gross Pay strip, a Deductions grid, and a prominent
     * Net Pay bar. Every peso amount shown still comes straight from
     * the existing $line array - nothing here changes how basic pay,
     * overtime, deductions, gross pay, or net pay are calculated.
     */
    public function downloadPayslipImage(
        string $id,
        string $employeeId
    ): void {

        Auth::requireLogin();

        $db = Database::connection();
        $this->ensureDownloadTrackingColumns($db);

        $run =
            $this->getRun(
                $db,
                $id
            );

        $line =
            $this->getRunLine(
                $db,
                $id,
                $employeeId
            );

        if (!$run || !$line) {

            http_response_code(404);

            echo '404 - Payslip not found';

            return;
        }

        if (!function_exists('imagecreatetruecolor')) {

            http_response_code(500);

            echo 'Hindi available ang PHP GD extension. I-enable ang GD extension para makapag-download ng payslip bilang image.';

            return;
        }

        $breakdown =
            $this->getPayslipAttendanceBreakdown(
                $db,
                $run,
                $line
            );

        $width = 1400;
        $height = 1260;

        $image =
            imagecreatetruecolor(
                $width,
                $height
            );

        if (!$image) {

            http_response_code(500);

            echo 'Hindi makagawa ng payslip image.';

            return;
        }

        imagealphablending($image, true);
        imagesavealpha($image, false);

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 15, 23, 42);
        $slate = imagecolorallocate($image, 71, 85, 105);
        $muted = imagecolorallocate($image, 100, 116, 139);
        $border = imagecolorallocate($image, 148, 163, 184);
        $lightBorder = imagecolorallocate($image, 226, 232, 240);
        $section = imagecolorallocate($image, 30, 41, 59);
        $lightBar = imagecolorallocate($image, 226, 229, 235);
        $netBar = imagecolorallocate($image, 71, 78, 92);
        $red = imagecolorallocate($image, 185, 28, 28);
        $light = imagecolorallocate($image, 248, 250, 252);

        imagefill(
            $image,
            0,
            0,
            $white
        );

        $left = 60;
        $right = $width - 60;
        $top = 40;

        $labelX = $left + 24;
        $valueRight = $right - 24;
        $y = $top + 30;

        /*
         * ========================================================
         * HEADER: company name / subtitle (left)
         * Pay period + employee code box (right)
         * ========================================================
         */

        $this->drawPayslipImageText(
            $image,
            $left + 20,
            $y,
            'PHIXINN PAYROLL SYSTEM',
            $black,
            5
        );

        $this->drawPayslipImageText(
            $image,
            $left + 20,
            $y + 28,
            'Employee Payslip',
            $slate,
            4
        );

        $periodLabel =
            date(
                'M j',
                strtotime(
                    $run['period_start']
                )
            )
            . ' - '
            . date(
                'M j, Y',
                strtotime(
                    $run['period_end']
                )
            );

        $this->drawPayslipImageTextRight(
            $image,
            $valueRight,
            $y,
            'Payslip for the period of',
            $slate,
            3
        );

        $this->drawPayslipImageTextRight(
            $image,
            $valueRight,
            $y + 18,
            $periodLabel,
            $black,
            4
        );

        $employeeCode =
            $line['employee_code']
            ?? $line['ngteco_user_id']
            ?? '';

        $codeBoxWidth = 260;
        $codeBoxHeight = 34;
        $codeBoxX = $right - $codeBoxWidth;
        $codeBoxY = $y + 42;

        imagerectangle(
            $image,
            $codeBoxX,
            $codeBoxY,
            $right,
            $codeBoxY + $codeBoxHeight,
            $border
        );

        $this->drawPayslipImageTextRight(
            $image,
            $right - 10,
            $codeBoxY + 9,
            (string) ($employeeCode ?: '-'),
            $black,
            4
        );

        $y += 78;

        imagefilledrectangle(
            $image,
            $left,
            $y,
            $right,
            $y + 2,
            $border
        );

        $y += 30;

        /*
         * ========================================================
         * EMPLOYEE INFO GRID (3 columns x 2 rows)
         * ========================================================
         */

        $infoColWidth = (int) (($right - $left) / 3);

        $infoGrid = [
            [
                'EMPLOYEE',
                (string) ($line['employee_name'] ?? '-'),
                'DEPARTMENT',
                (string) ($line['department'] ?: '-'),
                'EMPLOYEE CODE',
                (string) ($employeeCode ?: '-'),
            ],
            [
                'POSITION',
                (string) ($line['position'] ?: '-'),
                'STATUS',
                (string) ($line['status'] ?? '-'),
                'PAY PERIOD',
                $periodLabel,
            ],
        ];

        foreach ($infoGrid as $rowFields) {

            for ($col = 0; $col < 3; $col++) {

                $colX = $left + ($col * $infoColWidth);
                $label = $rowFields[$col * 2];
                $value = $rowFields[($col * 2) + 1];

                $this->drawPayslipImageText(
                    $image,
                    $colX,
                    $y,
                    $label,
                    $muted,
                    2
                );

                imagedashedline(
                    $image,
                    $colX,
                    $y + 22,
                    $colX + $infoColWidth - 24,
                    $y + 22,
                    $border
                );

                $this->drawPayslipImageText(
                    $image,
                    $colX,
                    $y + 28,
                    $value,
                    $black,
                    4
                );
            }

            $y += 66;
        }

        $y += 10;

        /*
         * ========================================================
         * SALARY SUMMARY
         * (counts only - derived from existing attendance records,
         * no change to any pay formula)
         * ========================================================
         */

        $y =
            $this->drawPayslipImageSectionBar(
                $image,
                $left,
                $right,
                $y,
                40,
                'SALARY SUMMARY',
                $lightBar,
                $black,
                null,
                null,
                4
            );

        $summaryCountRows = [
            'Days Worked' =>
                (string) $breakdown['days_worked'],
            'Days Absent / No Work' =>
                (string) $breakdown['days_absent'],
            'Holiday Days' =>
                (string) $breakdown['holiday_days'],
        ];

        $y =
            $this->drawPayslipImageCountRows(
                $image,
                $left,
                $right,
                $y,
                $summaryCountRows,
                36,
                $black,
                $slate,
                $lightBorder
            );

        $y += 16;

        /*
         * ========================================================
         * BASIC SALARY
         * (bar shows the existing basic_pay total, unchanged; the
         * rows below split the existing overtime_pay total into
         * Regular / Rest Day / Holiday OT using the SAME multiplier
         * rule already in computeEmployeePay - the three amounts
         * always sum back to the original overtime_pay value)
         * ========================================================
         */

        $basicPay =
            (float) (
                $line['basic_pay'] ?? 0
            );

        $y =
            $this->drawPayslipImageSectionBar(
                $image,
                $left,
                $right,
                $y,
                48,
                'BASIC SALARY',
                $lightBar,
                $black,
                '₱' . number_format($basicPay, 2),
                $black
            );

        $otRows = [
            'Regular OT (' . number_format($breakdown['regular_ot_hours'], 1) . ' hrs)' =>
                $breakdown['regular_ot_amount'],
            'Allowances' =>
                (float) (
                    $line['allowances'] ?? 0
                ),
        ];

        $y =
            $this->drawPayslipImageMoneyRows(
                $image,
                $left,
                $right,
                $y,
                $otRows,
                40,
                $black,
                $slate,
                $lightBorder,
                $white
            );

        $y += 16;

        $grossPay =
            (float) (
                $line['gross_pay'] ?? 0
            );

        $y =
            $this->drawPayslipImageSectionBar(
                $image,
                $left,
                $right,
                $y,
                52,
                'GROSS PAY',
                $lightBar,
                $black,
                '₱' . number_format($grossPay, 2),
                $black
            );

        $y += 22;

        /*
         * ========================================================
         * DEDUCTIONS (two-column grid, matches reference layout)
         * ========================================================
         */

        $y =
            $this->drawPayslipImageSectionBar(
                $image,
                $left,
                $right,
                $y,
                44,
                'DEDUCTIONS',
                $section,
                $white,
                null,
                null
            );

        $leftDeductions = [
            'Late Deduction' =>
                (float) (
                    $line['late_deduction'] ?? 0
                ),
        ];

        $rightDeductions = [
            'Undertime Deduction' =>
                (float) (
                    $line['undertime_deduction'] ?? 0
                ),
        ];

        if (
            (float) (
                $line['absence_deduction'] ?? 0
            ) > 0
        ) {
            $leftDeductions['Absence Deduction'] =
                (float) (
                    $line['absence_deduction'] ?? 0
                );
        }

        if (
            (float) (
                $line['other_deductions'] ?? 0
            ) > 0
        ) {
            $rightDeductions['Other Deductions'] =
                (float) (
                    $line['other_deductions'] ?? 0
                );
        }

        $y =
            $this->drawPayslipImageTwoColumnRows(
                $image,
                $left,
                $right,
                $y,
                $leftDeductions,
                $rightDeductions,
                44,
                $black,
                $red,
                $lightBorder,
                $white
            );

        $y += 10;

        $totalDeduction =
            (float) (
                $line['total_deduction'] ?? 0
            );

        $this->drawPayslipImageText(
            $image,
            $labelX,
            $y,
            'TOTAL DEDUCTIONS',
            $black,
            4
        );

        $this->drawPayslipImageTextRight(
            $image,
            $valueRight,
            $y,
            '(₱' . number_format($totalDeduction, 2) . ')',
            $red,
            4
        );

        $y += 40;

        /*
         * ========================================================
         * NET PAY
         * ========================================================
         */

        $netPay =
            (float) (
                $line['net_pay'] ?? 0
            );

        $y =
            $this->drawPayslipImageSectionBar(
                $image,
                $left,
                $right,
                $y,
                64,
                'NET PAY',
                $netBar,
                $white,
                '₱' . number_format($netPay, 2),
                $white,
                5
            );

        $y += 40;

        /*
         * ========================================================
         * PREPARED BY / SIGNATURE LINE
         * ========================================================
         */

        $this->drawPayslipImageText(
            $image,
            $left,
            $y,
            'PREPARED BY:',
            $slate,
            3
        );

        imagedashedline(
            $image,
            $left + 140,
            $y + 12,
            $left + 420,
            $y + 12,
            $border
        );

        $y += 40;

        $this->drawPayslipImageText(
            $image,
            $left,
            $y,
            'Generated: ' . date('M j, Y h:i A'),
            $muted,
            2
        );

        /*
         * ========================================================
         * OUTER CARD BORDER
         * ========================================================
         */

        imagerectangle(
            $image,
            $left - 20,
            $top,
            $right + 20,
            $y + 30,
            $lightBorder
        );

        $filename =
            $this->payslipImageFilename(
                $run,
                $line
            );

        $this->markPayrollRunDownloaded(
            $db,
            (int) $run['id']
        );

        header('Content-Type: image/png');
        header(
            'Content-Disposition: attachment; filename="' .
            $filename .
            '"'
        );
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        imagepng(
            $image,
            null,
            6
        );

        imagedestroy($image);

        exit;
    }

    /**
     * ============================================================
     * PAYSLIP ATTENDANCE BREAKDOWN (display-only, additive)
     * ============================================================
     *
     * Computes the Days Worked / Days Absent / Holiday Days counts
     * and the Regular / Rest Day / Holiday overtime split shown on
     * the redesigned payslip image. This re-reads the SAME
     * `employees` and `attendance` tables (via the existing
     * getEmployeeAttendance() helper) and applies the EXACT SAME
     * status checks and overtime multipliers already used in
     * computeEmployeePay() (Present/Late/Half-Day = worked day,
     * Holiday override, 30-minute OT minimum, 1.00x regular /
     * 1.30x rest day / 2.00x holiday multiplier).
     *
     * This does NOT change computeEmployeePay(), does NOT change
     * what gets saved to payroll_history, and does NOT change any
     * peso amount already on the payslip - it only breaks the
     * existing overtime_pay total down by type for display. The sum
     * of regular_ot_amount + rest_ot_amount + holiday_ot_amount will
     * always equal the existing $line['overtime_pay'] value.
     */
    private function getPayslipAttendanceBreakdown(
        PDO $db,
        array $run,
        array $line
    ): array {

        $defaults = [
            'days_worked' => 0,
            'days_absent' => 0,
            'holiday_days' => 0,
            'regular_ot_hours' => 0.0,
            'regular_ot_amount' => 0.0,
            'rest_ot_hours' => 0.0,
            'rest_ot_amount' => 0.0,
            'holiday_ot_hours' => 0.0,
            'holiday_ot_amount' => 0.0,
        ];

        $employeeId =
            $line['employee_id']
            ?? null;

        if (!$employeeId) {
            return $defaults;
        }

        $empStmt =
            $db->prepare(
                "SELECT id, salary_rate, rest_day
                 FROM employees
                 WHERE id = :id
                 LIMIT 1"
            );

        $empStmt->execute([
            'id' => $employeeId,
        ]);

        $emp = $empStmt->fetch();

        if (!$emp) {
            return $defaults;
        }

        $attendanceRows =
            $this->getEmployeeAttendance(
                $db,
                $emp,
                (string) $run['period_start'],
                (string) $run['period_end']
            );

        $dailyRate = (float) ($emp['salary_rate'] ?? 0);
        $hourlyRate = $dailyRate > 0 ? $dailyRate / 8.0 : 0.0;
        $restDay = trim((string) ($emp['rest_day'] ?? ''));

        $daysWorked = 0;
        $daysAbsent = 0;
        $holidayDays = 0;

        $regularOtMinutes = 0.0;
        $regularOtAmount = 0.0;
        $restOtMinutes = 0.0;
        $restOtAmount = 0.0;
        $holidayOtMinutes = 0.0;
        $holidayOtAmount = 0.0;

        foreach ($attendanceRows as $attendance) {

            $status =
                trim(
                    (string) ($attendance['attendance_status'] ?? '')
                );

            $holidayName =
                trim(
                    (string) ($attendance['holiday_name'] ?? '')
                );

            if ($holidayName !== '') {
                $status = 'Holiday';
            }

            if (
                in_array(
                    $status,
                    ['Present', 'Late', 'Half-Day'],
                    true
                )
            ) {
                $daysWorked++;
            } elseif ($status === 'Holiday') {
                $holidayDays++;
            } else {
                $daysAbsent++;
            }

            $overtimeMinutes =
                max(
                    0,
                    (float) ($attendance['overtime_minutes'] ?? 0)
                );

            if ($overtimeMinutes < 30.0) {
                continue;
            }

            $multiplier = 1.00;

            $date = $attendance['attendance_date'] ?? null;

            if ($date) {

                $dayName = date('l', strtotime((string) $date));

                if (
                    $restDay !== '' &&
                    strcasecmp($restDay, $dayName) === 0
                ) {
                    $multiplier = 1.30;
                }
            }

            if ($status === 'Holiday') {
                $multiplier = 2.00;
            }

            $otPay =
                round(
                    ($overtimeMinutes / 60)
                    * $hourlyRate
                    * $multiplier,
                    2
                );

            if ($multiplier === 2.00) {
                $holidayOtMinutes += $overtimeMinutes;
                $holidayOtAmount += $otPay;
            } elseif ($multiplier === 1.30) {
                $restOtMinutes += $overtimeMinutes;
                $restOtAmount += $otPay;
            } else {
                $regularOtMinutes += $overtimeMinutes;
                $regularOtAmount += $otPay;
            }
        }

        return [
            'days_worked' => $daysWorked,
            'days_absent' => $daysAbsent,
            'holiday_days' => $holidayDays,
            'regular_ot_hours' => round($regularOtMinutes / 60, 2),
            'regular_ot_amount' => round($regularOtAmount, 2),
            'rest_ot_hours' => round($restOtMinutes / 60, 2),
            'rest_ot_amount' => round($restOtAmount, 2),
            'holiday_ot_hours' => round($holidayOtMinutes / 60, 2),
            'holiday_ot_amount' => round($holidayOtAmount, 2),
        ];
    }

    /**
     * Draw a payslip text value using PHP GD's built-in fonts.
     */
    private function drawPayslipImageText(
        $image,
        int $x,
        int $y,
        string $text,
        int $color,
        int $font = 4
    ): void {

        $text =
            $this->normalizePayslipImageText(
                $text
            );

        imagestring(
            $image,
            $font,
            $x,
            $y,
            $text,
            $color
        );
    }

    /**
     * Draw right-aligned text using PHP GD's built-in fonts.
     */
    private function drawPayslipImageTextRight(
        $image,
        int $rightX,
        int $y,
        string $text,
        int $color,
        int $font = 4
    ): void {

        $text =
            $this->normalizePayslipImageText(
                $text
            );

        $textWidth =
            imagefontwidth($font)
            * strlen($text);

        $x =
            max(
                10,
                $rightX - $textWidth
            );

        imagestring(
            $image,
            $font,
            $x,
            $y,
            $text,
            $color
        );
    }

    /**
     * Draw a full-width section bar (used for SALARY SUMMARY /
     * BASIC SALARY / DEDUCTIONS headers, the GROSS PAY strip, and
     * the final NET PAY strip). Optionally draws a right-aligned
     * value on the same bar.
     */
    private function drawPayslipImageSectionBar(
        $image,
        int $left,
        int $right,
        int $y,
        int $height,
        string $title,
        int $bgColor,
        int $titleColor,
        ?string $rightValue = null,
        ?int $rightColor = null,
        int $font = 4
    ): int {

        imagefilledrectangle(
            $image,
            $left,
            $y,
            $right,
            $y + $height,
            $bgColor
        );

        $textY = $y + (int) (($height - 8) / 2) - 4;

        $this->drawPayslipImageText(
            $image,
            $left + 24,
            $textY,
            $title,
            $titleColor,
            $font
        );

        if ($rightValue !== null) {

            $this->drawPayslipImageTextRight(
                $image,
                $right - 24,
                $textY,
                $rightValue,
                $rightColor ?? $titleColor,
                $font
            );
        }

        return $y + $height;
    }

    /**
     * Draw a single-column list of label/amount rows, each in its
     * own bordered row (used for the Basic Salary OT breakdown and
     * the Earnings-style rows).
     */
    private function drawPayslipImageMoneyRows(
        $image,
        int $left,
        int $right,
        int $y,
        array $rows,
        int $rowHeight,
        int $labelColor,
        int $valueColor,
        int $borderColor,
        int $bgColor
    ): int {

        $rowY = $y;

        foreach ($rows as $label => $value) {

            imagefilledrectangle(
                $image,
                $left,
                $rowY,
                $right,
                $rowY + $rowHeight,
                $bgColor
            );

            imagerectangle(
                $image,
                $left,
                $rowY,
                $right,
                $rowY + $rowHeight,
                $borderColor
            );

            $this->drawPayslipImageText(
                $image,
                $left + 24,
                $rowY + 12,
                $label,
                $labelColor,
                4
            );

            $this->drawPayslipImageTextRight(
                $image,
                $right - 24,
                $rowY + 12,
                '₱' . number_format((float) $value, 2),
                $valueColor,
                4
            );

            $rowY += $rowHeight;
        }

        return $rowY;
    }

    /**
     * Draw a single-column list of label/count rows with a thin
     * dashed underline instead of a boxed border - used for the
     * SALARY SUMMARY counts (Days Worked / Days Absent / Holiday
     * Days), which are not peso amounts.
     */
    private function drawPayslipImageCountRows(
        $image,
        int $left,
        int $right,
        int $y,
        array $rows,
        int $rowHeight,
        int $labelColor,
        int $valueColor,
        int $borderColor
    ): int {

        $rowY = $y;

        foreach ($rows as $label => $value) {

            $this->drawPayslipImageText(
                $image,
                $left + 24,
                $rowY + 8,
                $label,
                $labelColor,
                4
            );

            $this->drawPayslipImageTextRight(
                $image,
                $right - 24,
                $rowY + 8,
                (string) $value,
                $valueColor,
                4
            );

            imagedashedline(
                $image,
                $left,
                $rowY + $rowHeight - 2,
                $right,
                $rowY + $rowHeight - 2,
                $borderColor
            );

            $rowY += $rowHeight;
        }

        return $rowY;
    }

    /**
     * Draw two side-by-side columns of label/amount rows sharing the
     * same row height (used for the DEDUCTIONS grid, matching the
     * reference design's left/right deduction columns).
     */
    private function drawPayslipImageTwoColumnRows(
        $image,
        int $left,
        int $right,
        int $y,
        array $leftRows,
        array $rightRows,
        int $rowHeight,
        int $labelColor,
        int $valueColor,
        int $borderColor,
        int $bgColor
    ): int {

        $midX = (int) (($left + $right) / 2);
        $rowCount = max(count($leftRows), count($rightRows));

        $leftLabels = array_keys($leftRows);
        $leftValues = array_values($leftRows);
        $rightLabels = array_keys($rightRows);
        $rightValues = array_values($rightRows);

        imagefilledrectangle(
            $image,
            $left,
            $y,
            $right,
            $y + ($rowHeight * $rowCount),
            $bgColor
        );

        imagerectangle(
            $image,
            $left,
            $y,
            $right,
            $y + ($rowHeight * $rowCount),
            $borderColor
        );

        imagedashedline(
            $image,
            $midX,
            $y,
            $midX,
            $y + ($rowHeight * $rowCount),
            $borderColor
        );

        for ($i = 0; $i < $rowCount; $i++) {

            $rowY = $y + ($i * $rowHeight);

            if (isset($leftLabels[$i])) {

                $this->drawPayslipImageText(
                    $image,
                    $left + 24,
                    $rowY + 14,
                    $leftLabels[$i],
                    $labelColor,
                    4
                );

                $this->drawPayslipImageTextRight(
                    $image,
                    $midX - 24,
                    $rowY + 14,
                    '₱' . number_format((float) $leftValues[$i], 2),
                    $valueColor,
                    4
                );
            }

            if (isset($rightLabels[$i])) {

                $this->drawPayslipImageText(
                    $image,
                    $midX + 24,
                    $rowY + 14,
                    $rightLabels[$i],
                    $labelColor,
                    4
                );

                $this->drawPayslipImageTextRight(
                    $image,
                    $right - 24,
                    $rowY + 14,
                    '₱' . number_format((float) $rightValues[$i], 2),
                    $valueColor,
                    4
                );
            }
        }

        return $y + ($rowHeight * $rowCount);
    }

    /**
     * Built-in GD fonts do not reliably render UTF-8 text. Convert
     * unsupported characters to close ASCII equivalents so names and
     * labels remain readable without requiring a server-side TTF file.
     */
    private function normalizePayslipImageText(
        string $text
    ): string {

        $text =
            preg_replace(
                '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',
                '',
                $text
            ) ?? '';

        if (
            function_exists('iconv')
        ) {
            $converted =
                @iconv(
                    'UTF-8',
                    'ASCII//TRANSLIT//IGNORE',
                    $text
                );

            if ($converted !== false) {
                return $converted;
            }
        }

        return
            preg_replace(
                '/[^\x20-\x7E]/',
                '?',
                $text
            ) ?? '';
    }


    /**
     * Filename for the PNG payslip download.
     */
    private function payslipImageFilename(
        array $run,
        array $line
    ): string {

        $name =
            $this->sanitizeFilename(
                (string) (
                    $line['employee_name']
                    ?? 'Unknown'
                )
            );

        return
            'Payslip_'
            . $name
            . '_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_start']
                )
            )
            . '_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_end']
                )
            )
            . '.png';
    }

    /**
     * ============================================================
     * PAYSLIPS ZIP
     * ============================================================
     */
    public function payslipsZip(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();
        $this->ensureDownloadTrackingColumns($db);

        $department =
            trim(
                $_GET['department'] ?? ''
            );

        [
            $run,
            $lines
        ] = $this->loadRunWithLines(
            $db,
            $id,
            $department
        );

        if (!$run) {

            http_response_code(404);

            echo '404 - Payroll run not found';

            return;
        }

        if (empty($lines)) {

            http_response_code(404);

            echo 'Walang employee data na ma-e-export para sa filter na ito.';

            return;
        }

        if (!class_exists(ZipArchive::class)) {

            http_response_code(500);

            echo 'Ang PHP zip extension (ext-zip) ay hindi naka-enable sa server na ito.';

            return;
        }

        $tempDir =
            sys_get_temp_dir()
            . '/payslips_'
            . uniqid();

        if (!mkdir($tempDir, 0777, true)) {

            http_response_code(500);

            echo 'Hindi makagawa ng temporary directory.';

            return;
        }

        $zipName =
            'Payslips_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_start']
                )
            )
            . '_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_end']
                )
            )
            . (
                $department !== ''
                    ? '_' .
                      $this->sanitizeFilename(
                          $department
                      )
                    : ''
            )
            . '.zip';

        $zipPath =
            $tempDir . '/' . $zipName;

        $zip =
            new ZipArchive();

        $zipResult =
            $zip->open(
                $zipPath,
                ZipArchive::CREATE |
                ZipArchive::OVERWRITE
            );

        if ($zipResult !== true) {

            @rmdir($tempDir);

            http_response_code(500);

            echo 'Hindi mabuksan o magawa ang ZIP file.';

            return;
        }

        $usedNames = [];

        try {

            foreach ($lines as $line) {

                $spreadsheet =
                    $this->buildPayslipSpreadsheet(
                        $run,
                        $line
                    );

                $entryName =
                    $this->payslipFilename(
                        $run,
                        $line
                    );

                if (isset($usedNames[$entryName])) {

                    $entryName =
                        $this->payslipFilename(
                            $run,
                            $line,
                            true
                        );
                }

                $originalEntryName =
                    $entryName;

                $counter = 2;

                while (
                    isset(
                        $usedNames[$entryName]
                    )
                ) {

                    $pathInfo =
                        pathinfo(
                            $originalEntryName
                        );

                    $entryName =
                        $pathInfo['filename']
                        . '_'
                        . $counter
                        . '.'
                        . (
                            $pathInfo['extension']
                            ?? 'xlsx'
                        );

                    $counter++;
                }

                $usedNames[$entryName] = true;

                $tmpFile =
                    $tempDir
                    . '/'
                    . uniqid('ps_')
                    . '.xlsx';

                $writer =
                    new Xlsx(
                        $spreadsheet
                    );

                $writer->save(
                    $tmpFile
                );

                $zip->addFile(
                    $tmpFile,
                    $entryName
                );

                $spreadsheet
                    ->disconnectWorksheets();

                unset($spreadsheet);
            }

            $zip->close();

            if (!file_exists($zipPath)) {

                throw new Exception(
                    'ZIP file was not created.'
                );
            }

            $this->markPayrollRunDownloaded($db, (int) $run['id']);

            header(
                'Content-Type: application/zip'
            );

            header(
                'Content-Disposition: attachment; filename="' .
                $zipName .
                '"'
            );

            header(
                'Content-Length: ' .
                filesize($zipPath)
            );

            header(
                'Cache-Control: max-age=0'
            );

            readfile($zipPath);

        } finally {

            foreach (
                glob(
                    $tempDir . '/*'
                ) ?: []
                as $file
            ) {

                @unlink($file);
            }

            @rmdir($tempDir);
        }

        exit;
    }

    /**
     * ============================================================
     * GENERATE SALARY CALCULATION
     * ============================================================
     */
    public function generate(): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $user = Auth::user();

        $periodStart = trim(
            $_POST['period_start'] ?? ''
        );

        $periodEnd = trim(
            $_POST['period_end'] ?? ''
        );

        /*
         * ========================================================
         * VALIDATE DATE RANGE
         * ========================================================
         */

        if (
            !$periodStart ||
            !$periodEnd ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodStart) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodEnd)
        ) {
            $_SESSION['salary_error'] =
                'Pumili ng valid na From Date at To Date.';

            header('Location: /salary-calculation');
            exit;
        }

        $startDt = \DateTime::createFromFormat('!Y-m-d', $periodStart);
        $endDt = \DateTime::createFromFormat('!Y-m-d', $periodEnd);

        if (
            !$startDt ||
            !$endDt ||
            $startDt->format('Y-m-d') !== $periodStart ||
            $endDt->format('Y-m-d') !== $periodEnd
        ) {
            $_SESSION['salary_error'] =
                'Invalid From Date or To Date.';

            header('Location: /salary-calculation');
            exit;
        }

        if ($startDt > $endDt) {
            $_SESSION['salary_error'] =
                'Ang From Date ay hindi dapat mas late kaysa To Date.';

            header('Location: /salary-calculation');
            exit;
        }

        /*
         * ========================================================
         * EXISTING RUN
         * ========================================================
         *
         * A week may be generated again.
         *
         * If the run already exists, we refresh its payroll
         * history instead of blocking the user with "Already
         * Generated".
         */

        $existingRunStmt = $db->prepare(
            "SELECT id
             FROM payroll_runs
             WHERE period_type = 'Weekly'
               AND period_start = :period_start
               AND period_end = :period_end
             LIMIT 1"
        );

        $existingRunStmt->execute([
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        $existingRun = $existingRunStmt->fetch();

        try {

            /*
             * ====================================================
             * GET ACTIVE EMPLOYEES
             * ====================================================
             */

            $employees =
                $db->query(
                    "SELECT *
                     FROM employees
                     WHERE status = 'Active'
                     ORDER BY full_name ASC"
                )->fetchAll();

            if (empty($employees)) {

                throw new Exception(
                    'Walang Active employees na maaaring i-calculate.'
                );
            }

            /*
             * ====================================================
             * BEGIN TRANSACTION
             * ====================================================
             */

            $db->beginTransaction();

            /*
             * ====================================================
             * CREATE OR REFRESH PAYROLL RUN
             * ====================================================
             */

            if ($existingRun) {
                $runId = (int) $existingRun['id'];

                /*
                 * Re-generation must always reflect the latest
                 * Attendance data.
                 */
                $deleteHistory = $db->prepare(
                    "DELETE FROM payroll_history
                     WHERE payroll_run_id = :run_id"
                );
                $deleteHistory->execute(['run_id' => $runId]);

                $updateRun = $db->prepare(
                    "UPDATE payroll_runs
                     SET period_end = :period_end,
                         status = 'Draft',
                         generated_by = :generated_by,
                         created_at = NOW()
                     WHERE id = :id"
                );
                $updateRun->execute([
                    'period_end' => $periodEnd,
                    'generated_by' => $user['id'] ?? null,
                    'id' => $runId,
                ]);
            } else {
                $insertRun = $db->prepare(
                    "INSERT INTO payroll_runs
                    (
                        period_type,
                        period_start,
                        period_end,
                        status,
                        generated_by,
                        created_at
                    )
                    VALUES
                    (
                        'Weekly',
                        :period_start,
                        :period_end,
                        'Draft',
                        :generated_by,
                        NOW()
                    )"
                );

                $insertRun->execute([
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'generated_by' => $user['id'] ?? null,
                ]);

                $runId = (int) $db->lastInsertId();
            }

            /*
             * ====================================================
             * PREPARE PAYROLL HISTORY
             * ====================================================
             */

            $insertLine =
                $db->prepare(
                    "INSERT INTO payroll_history
                    (
                        payroll_run_id,
                        employee_id,
                        ngteco_user_id,
                        employee_name,

                        basic_pay,
                        overtime_pay,
                        allowances,

                        late_deduction,
                        undertime_deduction,

                        absence_deduction,
                        other_deductions,

                        gross_pay,
                        total_deduction,
                        net_pay,

                        status,
                        calculation_trace,
                        created_at
                    )
                    VALUES
                    (
                        :payroll_run_id,
                        :employee_id,
                        :ngteco_user_id,
                        :employee_name,

                        :basic_pay,
                        :overtime_pay,
                        :allowances,

                        :late_deduction,
                        :undertime_deduction,

                        :absence_deduction,
                        :other_deductions,

                        :gross_pay,
                        :total_deduction,
                        :net_pay,

                        'Draft',
                        :calculation_trace,
                        NOW()
                    )"
                );

            /*
             * ====================================================
             * PROCESS EACH EMPLOYEE
             * ====================================================
             */

            foreach ($employees as $employee) {

                /*
                 * ------------------------------------------------
                 * GET ATTENDANCE
                 * ------------------------------------------------
                 */

                $attendanceRows =
                    $this->getEmployeeAttendance(
                        $db,
                        $employee,
                        $periodStart,
                        $periodEnd
                    );

                /*
                 * ------------------------------------------------
                 * COMPUTE
                 * ------------------------------------------------
                 */

                $computed =
                    $this->computeEmployeePay(
                        $employee,
                        $attendanceRows
                    );

                /*
                 * ------------------------------------------------
                 * NGTeco USER ID
                 * ------------------------------------------------
                 */

                $ngtecoUserId =
                    $this->resolveNgtecoUserId(
                        $employee
                    );

                /*
                 * ------------------------------------------------
                 * INSERT
                 * ------------------------------------------------
                 */

                $insertLine->execute([
                    'payroll_run_id' =>
                        $runId,

                    'employee_id' =>
                        $employee['id'],

                    'ngteco_user_id' =>
                        $ngtecoUserId,

                    'employee_name' =>
                        $employee['full_name'],

                    'basic_pay' =>
                        $computed['basic_pay'],

                    'overtime_pay' =>
                        $computed['overtime_pay'],

                    'allowances' =>
                        $computed['allowances'],

                    'late_deduction' =>
                        $computed['late_deduction'],

                    'undertime_deduction' =>
                        $computed['undertime_deduction'],

                    'absence_deduction' =>
                        $computed['absence_deduction'],

                    'other_deductions' =>
                        0,

                    'gross_pay' =>
                        $computed['gross_pay'],

                    'total_deduction' =>
                        $computed['total_deduction'],

                    'net_pay' =>
                        $computed['net_pay'],

                    'calculation_trace' =>
                        json_encode(
                            $computed['trace'],
                            JSON_UNESCAPED_UNICODE |
                            JSON_UNESCAPED_SLASHES
                        ),
                ]);
            }

            /*
             * ====================================================
             * COMMIT
             * ====================================================
             */

            $db->commit();

            $_SESSION['salary_success'] =
                "Nagawa ang Payroll Run para sa "
                . "{$periodStart} - {$periodEnd}.";

        } catch (Exception $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $_SESSION['salary_error'] =
                'Error: ' . $e->getMessage();
        }

        header(
            'Location: /salary-calculation'
        );

        exit;
    }

    /**
     * ============================================================
     * GET EMPLOYEE ATTENDANCE
     * ============================================================
     */
        /**
     * ============================================================
     * GET EMPLOYEE ATTENDANCE
     * ============================================================
     */
    private function getEmployeeAttendance(
        PDO $db,
        array $employee,
        string $periodStart,
        string $periodEnd
    ): array {
        /*
         * Match attendance using the same identity fallbacks as the
         * Attendance module. Imported NGTeco rows may identify an employee
         * by internal ID, employee code, or NGTeco user ID.
         */
        $stmt = $db->prepare(
            "SELECT
                a.*,
                CASE
                    WHEN h.id IS NOT NULL THEN 'Holiday'
                    ELSE a.attendance_status
                END AS attendance_status,
                h.holiday_name,
                h.holiday_type
             FROM attendance a
             LEFT JOIN holidays h
                ON h.holiday_date = a.attendance_date
               AND h.is_active = 1
             WHERE (
                    CAST(a.employee_id AS CHAR) = CAST(:employee_id AS CHAR)
                    OR CAST(a.employee_id AS CHAR) = CAST(:employee_code AS CHAR)
                    OR CAST(a.ngteco_user_id AS CHAR) = CAST(:ngteco_user_id AS CHAR)
                )
               AND a.attendance_date BETWEEN :period_start AND :period_end
             ORDER BY a.attendance_date ASC"
        );

        $stmt->execute([
            'employee_id' => $employee['id'],
            'employee_code' => $employee['employee_code'] ?? '',
            'ngteco_user_id' => $employee['ngteco_user_id'] ?? '',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * ============================================================
     * GET TABLE COLUMNS
     * ============================================================
     */
    private function getTableColumns(
        PDO $db,
        string $table
    ): array {

        $allowedTables = [
            'attendance',
            'employees',
            'payroll_runs',
            'payroll_history',
        ];

        if (
            !in_array(
                $table,
                $allowedTables,
                true
            )
        ) {

            throw new Exception(
                'Invalid table name.'
            );
        }

        $stmt =
            $db->query(
                "SHOW COLUMNS FROM `{$table}`"
            );

        $columns = [];

        foreach (
            $stmt->fetchAll(PDO::FETCH_ASSOC)
            as $column
        ) {

            if (
                isset(
                    $column['Field']
                )
            ) {

                $columns[] =
                    $column['Field'];
            }
        }

        return $columns;
    }

    /**
     * ============================================================
     * LOAD PAYROLL RUN + LINES
     * ============================================================
     */
    private function loadRunWithLines(
        $db,
        string $id,
        string $department = ''
    ): array {

        $run =
            $this->getRun(
                $db,
                $id
            );

        if (!$run) {

            return [
                null,
                [],
                [],
                []
            ];
        }

        $sql =
            "SELECT
                h.*,
                e.department AS department,
                e.position AS position,
                e.employee_code AS employee_code

             FROM payroll_history h

             LEFT JOIN employees e
                ON e.id = h.employee_id

             WHERE h.payroll_run_id = :id";

        $params = [
            'id' =>
                $id
        ];

        if ($department !== '') {

            $sql .=
                " AND e.department = :department";

            $params['department'] =
                $department;
        }

        $sql .=
            " ORDER BY h.employee_name ASC";

        $stmt =
            $db->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );

        $lines =
            $stmt->fetchAll();

        /*
         * ========================================================
         * CALCULATION AVAILABILITY
         * ========================================================
         *
         * Mark an employee as calculable only when at least one
         * attendance record in this payroll period has BOTH
         * Time In and Time Out.
         *
         * This is used by the view so employees with no completed
         * attendance remain blank instead of showing ₱0.00.
         */
        $attendanceStateStmt =
            $db->prepare(
                "SELECT
                    employee_id,
                    MAX(
                        CASE
                            WHEN time_in IS NOT NULL
                             AND TRIM(time_in) <> ''
                             AND time_out IS NOT NULL
                             AND TRIM(time_out) <> ''
                            THEN 1
                            ELSE 0
                        END
                    ) AS has_complete,
                    MAX(
                        CASE
                            WHEN time_in IS NOT NULL
                             AND TRIM(time_in) <> ''
                             AND (
                                 time_out IS NULL
                                 OR TRIM(time_out) = ''
                             )
                            THEN 1
                            ELSE 0
                        END
                    ) AS has_incomplete
                 FROM attendance
                 WHERE attendance_date BETWEEN :period_start AND :period_end
                   AND employee_id IS NOT NULL
                 GROUP BY employee_id"
            );

        $attendanceStateStmt->execute([
            'period_start' => $run['period_start'],
            'period_end' => $run['period_end'],
        ]);

        $attendanceStates = [];

        foreach (
            $attendanceStateStmt->fetchAll(PDO::FETCH_ASSOC)
            as $state
        ) {
            $attendanceStates[
                (int) $state['employee_id']
            ] = $state;
        }

        foreach ($lines as &$line) {
            $employeeId = (int) ($line['employee_id'] ?? 0);
            $state = $attendanceStates[$employeeId] ?? null;

            $line['has_calculable_attendance'] =
                (bool) ($state['has_complete'] ?? false);

            $line['has_incomplete_attendance'] =
                (bool) ($state['has_incomplete'] ?? false);
        }

        unset($line);

        $deptStmt =
            $db->prepare(
                "SELECT DISTINCT
                    e.department

                 FROM payroll_history h

                 LEFT JOIN employees e
                    ON e.id = h.employee_id

                 WHERE h.payroll_run_id = :id
                   AND e.department IS NOT NULL
                   AND e.department <> ''

                 ORDER BY e.department ASC"
            );

        $deptStmt->execute([
            'id' =>
                $id
        ]);

        $departments =
            $deptStmt->fetchAll(
                PDO::FETCH_COLUMN
            );

        $totals = [
            'basic_pay' => 0.0,
            'overtime_pay' => 0.0,
            'allowances' => 0.0,
            'late_deduction' => 0.0,
            'undertime_deduction' => 0.0,
            'absence_deduction' => 0.0,
            'other_deductions' => 0.0,
            'gross_pay' => 0.0,
            'total_deduction' => 0.0,
            'net_pay' => 0.0,
        ];

        foreach ($lines as $line) {

            foreach (
                array_keys($totals)
                as $key
            ) {

                $totals[$key] +=
                    (float) (
                        $line[$key] ?? 0
                    );
            }
        }

        foreach (
            $totals as $key => $value
        ) {

            $totals[$key] =
                round(
                    $value,
                    2
                );
        }

        return [
            $run,
            $lines,
            $totals,
            $departments
        ];
    }

    /**
     * ============================================================
     * GET RUN
     * ============================================================
     */
    private function getRun(
        $db,
        string $id
    ): ?array {

        $stmt =
            $db->prepare(
                "SELECT *
                 FROM payroll_runs
                 WHERE id = :id
                 LIMIT 1"
            );

        $stmt->execute([
            'id' =>
                $id
        ]);

        $run =
            $stmt->fetch();

        return $run ?: null;
    }

    /**
     * ============================================================
     * GET RUN LINE
     * ============================================================
     */
    private function getRunLine(
        $db,
        string $runId,
        string $employeeId
    ): ?array {

        $stmt =
            $db->prepare(
                "SELECT
                    h.*,
                    e.department AS department,
                    e.position AS position,
                    e.employee_code AS employee_code

                 FROM payroll_history h

                 LEFT JOIN employees e
                    ON e.id = h.employee_id

                 WHERE h.payroll_run_id = :run_id
                   AND h.employee_id = :employee_id

                 LIMIT 1"
            );

        $stmt->execute([
            'run_id' =>
                $runId,

            'employee_id' =>
                $employeeId,
        ]);

        $line =
            $stmt->fetch();

        if (!$line) {
            return null;
        }

        /*
         * ========================================================
         * CALCULATION AVAILABILITY
         * ========================================================
         *
         * Individual salary details/payslips are considered
         * calculable only when at least one attendance record in
         * the payroll period has both Time In and Time Out.
         */
        $runStmt =
            $db->prepare(
                "SELECT period_start, period_end
                 FROM payroll_runs
                 WHERE id = :run_id
                 LIMIT 1"
            );

        $runStmt->execute([
            'run_id' => $runId,
        ]);

        $runDates = $runStmt->fetch();

        $line['has_calculable_attendance'] = false;
        $line['has_incomplete_attendance'] = false;

        if ($runDates) {
            $attendanceStateStmt =
                $db->prepare(
                    "SELECT
                        MAX(
                            CASE
                                WHEN time_in IS NOT NULL
                                 AND TRIM(time_in) <> ''
                                 AND time_out IS NOT NULL
                                 AND TRIM(time_out) <> ''
                                THEN 1
                                ELSE 0
                            END
                        ) AS has_complete,
                        MAX(
                            CASE
                                WHEN time_in IS NOT NULL
                                 AND TRIM(time_in) <> ''
                                 AND (
                                     time_out IS NULL
                                     OR TRIM(time_out) = ''
                                 )
                                THEN 1
                                ELSE 0
                            END
                        ) AS has_incomplete
                     FROM attendance
                     WHERE employee_id = :employee_id
                       AND attendance_date BETWEEN :period_start AND :period_end"
                );

            $attendanceStateStmt->execute([
                'employee_id' => $line['employee_id'],
                'period_start' => $runDates['period_start'],
                'period_end' => $runDates['period_end'],
            ]);

            $state = $attendanceStateStmt->fetch();

            $line['has_calculable_attendance'] =
                (bool) ($state['has_complete'] ?? false);

            $line['has_incomplete_attendance'] =
                (bool) ($state['has_incomplete'] ?? false);
        }

        return $line;
    }

    /**
     * ============================================================
     * COMPUTE EMPLOYEE PAY
     * ============================================================
     *
     * Rules:
     *
     * DAILY SALARY
     * ----------------------------
     * employees.salary_rate
     *
     * REGULAR HOURS
     * ----------------------------
     * 8 hours/day
     *
     * LATE
     * ----------------------------
     * 0-9 minutes  = ₱0
     * 10-19 minutes = ₱50
     * 20-29 minutes = ₱100
     * 30-39 minutes = ₱150
     * 40+ minutes = 1 full hourly-rate deduction
     *
     * UNDERTIME
     * ----------------------------
     * Same deduction rule as Late.
     *
     * OVERTIME
     * ----------------------------
     * Minimum 30 minutes
     *
     * Regular Day = 1.00x
     * Rest Day    = 1.30x
     * Holiday     = 2.00x
     */
    private function computeEmployeePay(
        array $emp,
        array $attendanceRows
    ): array {
        $dailyRate = (float) ($emp['salary_rate'] ?? 0);
        $regularHoursPerDay = 8.0;
        $hourlyRate = $regularHoursPerDay > 0
            ? $dailyRate / $regularHoursPerDay
            : 0.0;

        /*
         * FINAL TIME-DEDUCTION RULE
         *
         * Complete hours are charged at the hourly rate.
         * Remaining minutes:
         * 0-9   = ₱0
         * 10-19 = ₱50
         * 20-29 = ₱100
         * 30-39 = ₱150
         * 40-59 = 1 hourly rate
         *
         * Example at ₱695/day (₱86.875/hour):
         * 3h34m = 3 x ₱86.875 + ₱150 = ₱410.63
         */
        $otMinimumMinutes = 30.0;

        $otRegularMultiplier = 1.00;
        $otRestDayMultiplier = 1.30;
        $otHolidayMultiplier = 2.00;

        $basicPay = 0.0;
        $overtimePay = 0.0;
        $allowances = 0.0;
        $lateDeduction = 0.0;
        $undertimeDeduction = 0.0;
        $absenceDeduction = 0.0;
        $trace = [];

        foreach ($attendanceRows as $attendance) {
            $date = $attendance['attendance_date'] ?? null;
            if (!$date) {
                continue;
            }

            $timeInValue = trim((string) ($attendance['time_in'] ?? ''));
            $timeOutValue = trim((string) ($attendance['time_out'] ?? ''));

            /*
             * Time In without Time Out is intentionally not calculable yet.
             * Attendance remains visible and will be calculated after the
             * next payroll regeneration once Time Out is available.
             */
            if ($timeInValue === '' || $timeOutValue === '') {
                continue;
            }

            $timeInTs = $this->parseAttendanceTimestamp($date, $timeInValue);
            $timeOutTs = $this->parseAttendanceTimestamp($date, $timeOutValue);

            if ($timeInTs === null || $timeOutTs === null) {
                continue;
            }

            $scheduleInValue = trim((string) ($emp['schedule_time_in'] ?? ''));
            $scheduleOutValue = trim((string) ($emp['schedule_time_out'] ?? ''));

            $scheduleInTs = null;
            $scheduleOutTs = null;

            if ($scheduleInValue !== '') {
                $parsed = strtotime((string) $date . ' ' . $scheduleInValue);
                if ($parsed !== false) {
                    $scheduleInTs = $parsed;
                }
            }

            if ($scheduleOutValue !== '') {
                $parsed = strtotime((string) $date . ' ' . $scheduleOutValue);
                if ($parsed !== false) {
                    $scheduleOutTs = $parsed;
                }
            }

            /* Support an overnight schedule without hardcoding a shift. */
            if ($scheduleInTs !== null && $scheduleOutTs !== null && $scheduleOutTs <= $scheduleInTs) {
                $scheduleOutTs += 86400;
            }

            /* If the actual Time Out crossed midnight, move it to the next day. */
            if ($scheduleInTs !== null && $scheduleOutTs !== null && $timeOutTs < $timeInTs) {
                $timeOutTs += 86400;
            }

            $status = trim((string) ($attendance['attendance_status'] ?? ''));
            $holidayName = trim((string) ($attendance['holiday_name'] ?? ''));
            $holidayType = trim((string) ($attendance['holiday_type'] ?? ''));

            if ($holidayName !== '') {
                $status = 'Holiday';
            }

            /*
             * If a complete attendance record has no stored status, derive
             * it from the actual Time In / Time Out. This prevents valid
             * attendance from becoming ₱0.00 just because the imported
             * status is blank.
             */

            $employeeRestDay = trim((string) ($emp['rest_day'] ?? ''));
            $dayNameForRules = date('l', strtotime((string) $date));
            $isRestDay = $employeeRestDay !== ''
                && strcasecmp($employeeRestDay, $dayNameForRules) === 0;
            $isHoliday = $holidayName !== '';

            /*
             * AttendanceCalculator::evaluate() has one shared signature
             * used by both Attendance and Salary Calculation:
             *
             * evaluate(
             *     date,
             *     timeIn,
             *     timeOut,
             *     scheduleTimeIn,
             *     scheduleTimeOut,
             *     restDay,
             *     isHoliday,
             *     department
             * )
             *
             * IMPORTANT: pass the actual rest-day name, not the boolean
             * result of the rest-day check. The previous call used the
             * arguments in the wrong order, which caused Salary Calculation
             * to calculate a different/invalid attendance result than the
             * Attendance module.
             */
            $metrics = AttendanceCalculator::evaluate(
                (string) $date,
                $timeInValue,
                $timeOutValue,
                $scheduleInValue !== '' ? $scheduleInValue : null,
                $scheduleOutValue !== '' ? $scheduleOutValue : null,
                $employeeRestDay !== '' ? $employeeRestDay : null,
                $isHoliday,
                (string) ($emp['department'] ?? '')
            );

            $lateMinutes = (float) ($metrics['late_minutes'] ?? 0);
            $undertimeMinutes = (float) ($metrics['undertime_minutes'] ?? 0);
            $overtimeMinutes = (float) ($metrics['overtime_minutes'] ?? 0);
            $earlyMinutes = 0.0;
            $lateStayMinutes = 0.0;

            if ($scheduleInTs !== null && $timeInTs < $scheduleInTs) {
                $earlyMinutes = round(($scheduleInTs - $timeInTs) / 60, 2);
            }
            if ($scheduleOutTs !== null && $timeOutTs > $scheduleOutTs) {
                $lateStayMinutes = round(($timeOutTs - $scheduleOutTs) / 60, 2);
            }

            $status = trim((string) ($metrics['attendance_status'] ?? ''));
            if ($holidayName !== '') {
                $status = 'Holiday';
            }

            $dayEntry = [
                'date' => $date,
                'status' => $status,
                'holiday_name' => $holidayName !== '' ? $holidayName : null,
                'holiday_type' => $holidayType !== '' ? $holidayType : null,
                'time_in' => $timeInValue,
                'time_out' => $timeOutValue,
                'schedule_time_in' => $scheduleInValue !== '' ? $scheduleInValue : null,
                'schedule_time_out' => $scheduleOutValue !== '' ? $scheduleOutValue : null,
                'daily_salary' => round($dailyRate, 2),
                'hourly_rate' => round($hourlyRate, 2),
            ];

            /*
             * Derive a missing status only after Time In and Time Out are
             * both valid. Existing explicit statuses are preserved.
             */
            if ($status === '') {
                $status = $lateMinutes > 0 ? 'Late' : 'Present';
                $dayEntry['status'] = $status;
            }

            /* BASIC PAY */
            $dayBasicPay = 0.0;

            if (in_array($status, ['Present', 'Late', 'Half-Day'], true)) {
                if ($status === 'Half-Day') {
                    $dayBasicPay = round($dailyRate / 2, 2);
                } else {
                    $dayBasicPay = round($dailyRate, 2);
                }
            }

            $basicPay += $dayBasicPay;

            /* ALLOWANCE - intentionally unchanged. */
            $dayAllowance = 0.0;
            if ($status === 'Present') {
                $dayAllowance = (float) ($emp['allowance'] ?? 0);
            }
            $allowances += $dayAllowance;

            /* LATE DEDUCTION */
            $dayLateDeduction = $this->calculateTimeDeduction(
                $lateMinutes,
                $hourlyRate
            );
            $lateDeduction += $dayLateDeduction;

            /* UNDERTIME DEDUCTION */
            $dayUndertimeDeduction = $this->calculateTimeDeduction(
                $undertimeMinutes,
                $hourlyRate
            );
            $undertimeDeduction += $dayUndertimeDeduction;

            /* ABSENCE DEDUCTION remains zero under the current rule. */
            $dayAbsenceDeduction = 0.0;
            $absenceDeduction += $dayAbsenceDeduction;

            /* OVERTIME */
            $dayOtPay = 0.0;
            $multiplier = 0.0;
            $dayName = date('l', strtotime((string) $date));
            $employeeRestDay = trim((string) ($emp['rest_day'] ?? ''));

            if ($overtimeMinutes >= $otMinimumMinutes) {
                $multiplier = $otRegularMultiplier;

                if (
                    $employeeRestDay !== '' &&
                    strcasecmp($employeeRestDay, $dayName) === 0
                ) {
                    $multiplier = $otRestDayMultiplier;
                }

                if ($status === 'Holiday') {
                    $multiplier = $otHolidayMultiplier;
                }

                $dayOtPay = round(
                    ($overtimeMinutes / 60) * $hourlyRate * $multiplier,
                    2
                );

                $overtimePay += $dayOtPay;
            }

            $dayGross = round(
                $dayBasicPay + $dayOtPay + $dayAllowance,
                2
            );

            /* Total Deduction is display-only: Late + Undertime only. */
            $dayDeduction = round(
                $dayLateDeduction + $dayUndertimeDeduction,
                2
            );

            $dayNet = round($dayGross - $dayDeduction, 2);
            if ($dayNet < 0) {
                $dayNet = 0.0;
            }

            $dayEntry['basic_pay'] = $dayBasicPay;
            $dayEntry['allowance'] = $dayAllowance;
            $dayEntry['early_minutes'] = $earlyMinutes;
            $dayEntry['late_stay_minutes'] = $lateStayMinutes;
            $dayEntry['late_minutes'] = $lateMinutes;
            $dayEntry['late_deduction'] = $dayLateDeduction;
            $dayEntry['undertime_minutes'] = $undertimeMinutes;
            $dayEntry['undertime_deduction'] = $dayUndertimeDeduction;
            $dayEntry['overtime_minutes'] = $overtimeMinutes;
            $dayEntry['overtime_multiplier'] = $multiplier;
            $dayEntry['overtime_pay'] = $dayOtPay;
            $dayEntry['gross_pay'] = $dayGross;
            $dayEntry['total_deduction'] = $dayDeduction;
            $dayEntry['net_pay'] = $dayNet;

            $trace[] = $dayEntry;
        }

        $grossPay = round(
            $basicPay + $overtimePay + $allowances,
            2
        );

        /* Final formula: Gross Pay - Late Deduction - Undertime Deduction. */
        $totalDeduction = round(
            $lateDeduction + $undertimeDeduction,
            2
        );

        $netPay = round(
            $grossPay - $totalDeduction,
            2
        );

        if ($netPay < 0) {
            $netPay = 0.0;
        }

        $trace[] = [
            'summary' => [
                'days_processed' => count($trace),
                'basic_pay' => round($basicPay, 2),
                'overtime_pay' => round($overtimePay, 2),
                'allowances' => round($allowances, 2),
                'late_deduction' => round($lateDeduction, 2),
                'undertime_deduction' => round($undertimeDeduction, 2),
                'total_deduction' => $totalDeduction,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay,
            ],
        ];

        return [
            'basic_pay' => round($basicPay, 2),
            'overtime_pay' => round($overtimePay, 2),
            'allowances' => round($allowances, 2),
            'late_deduction' => round($lateDeduction, 2),
            'undertime_deduction' => round($undertimeDeduction, 2),
            'absence_deduction' => round($absenceDeduction, 2),
            'gross_pay' => $grossPay,
            'total_deduction' => $totalDeduction,
            'net_pay' => $netPay,
            'trace' => $trace,
        ];
    }

    /**
     * Parse an attendance timestamp whether the database stores a TIME
     * value or a full DATETIME value.
     */
    private function parseAttendanceTimestamp(
        string $date,
        string $value
    ): ?int {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            $timestamp = strtotime($date . ' ' . $value);
        }

        return $timestamp === false ? null : $timestamp;
    }

    /**
     * Apply the cumulative Late / Undertime deduction rule.
     */
    private function calculateTimeDeduction(
        float $minutes,
        float $hourlyRate
    ): float {
        $minutes = max(0.0, $minutes);
        $hourlyRate = max(0.0, $hourlyRate);

        if ($minutes <= 0 || $hourlyRate <= 0) {
            return 0.0;
        }

        $completeHours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes - ($completeHours * 60);

        $deduction = $completeHours * $hourlyRate;

        if ($remainingMinutes >= 40) {
            $deduction += $hourlyRate;
        } elseif ($remainingMinutes >= 30) {
            $deduction += 150.0;
        } elseif ($remainingMinutes >= 20) {
            $deduction += 100.0;
        } elseif ($remainingMinutes >= 10) {
            $deduction += 50.0;
        }

        return round($deduction, 2);
    }

    /**
     * ============================================================
     * RESOLVE NGTeco USER ID
     * ============================================================
     */
    private function resolveNgtecoUserId(
        array $employee
    ): ?string {

        $possibleFields = [
            'ngteco_user_id',
            'ngteco_person_id',
            'ngteco_employee_id',
            'employee_code',
            'employee_id',
        ];

        foreach (
            $possibleFields as $field
        ) {

            if (
                array_key_exists(
                    $field,
                    $employee
                )
            ) {

                $value =
                    trim(
                        (string) (
                            $employee[$field]
                            ?? ''
                        )
                    );

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Ensure download tracking exists on installations created
     * before repeat-download support was added.
     */
    private function ensureDownloadTrackingColumns(PDO $db): void
    {
        $columns = $db->query(
            "SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'payroll_runs'
               AND COLUMN_NAME IN ('download_count', 'last_downloaded_at')"
        )->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('download_count', $columns, true)) {
            $db->exec(
                "ALTER TABLE payroll_runs
                 ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0
                 AFTER approved_at"
            );
        }

        if (!in_array('last_downloaded_at', $columns, true)) {
            $db->exec(
                "ALTER TABLE payroll_runs
                 ADD COLUMN last_downloaded_at DATETIME NULL
                 AFTER download_count"
            );
        }
    }

    private function markPayrollRunDownloaded(PDO $db, int $runId): void
    {
        $stmt = $db->prepare(
            "UPDATE payroll_runs
             SET download_count = download_count + 1,
                 last_downloaded_at = NOW()
             WHERE id = :id"
        );

        $stmt->execute(['id' => $runId]);
    }

    /**
     * ============================================================
     * BUILD PAYSLIP
     * ============================================================
     */
    private function buildPayslipSpreadsheet(
        array $run,
        array $line
    ): Spreadsheet {

        $periodLabel =
            date(
                'M j',
                strtotime(
                    $run['period_start']
                )
            )
            . ' - '
            . date(
                'M j, Y',
                strtotime(
                    $run['period_end']
                )
            );

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet->getActiveSheet();

        $sheet->setTitle(
            'Payslip'
        );

        /*
         * ========================================================
         * HEADER
         * ========================================================
         */

        $sheet->setCellValue(
            'A1',
            'PHIXINN PAYROLL SYSTEM'
        );

        $sheet->mergeCells(
            'A1:B1'
        );

        $sheet
            ->getStyle('A1')
            ->getFont()
            ->setBold(true)
            ->setSize(14);

        $sheet->setCellValue(
            'A2',
            'Employee Payslip'
        );

        $sheet->mergeCells(
            'A2:B2'
        );

        $sheet
            ->getStyle('A2')
            ->getFont()
            ->setItalic(true)
            ->setSize(11);

        /*
         * ========================================================
         * EMPLOYEE INFORMATION
         * ========================================================
         */

        $employeeCode =
            $line['employee_code']
            ?? $line['ngteco_user_id']
            ?? '';

        $info = [

            'Employee Name' =>
                $line['employee_name'],

            'Employee Code' =>
                $employeeCode,

            'Department' =>
                $line['department']
                ?: '-',

            'Position' =>
                $line['position']
                ?: '-',

            'Pay Period' =>
                $periodLabel,

            'Status' =>
                $line['status'],
        ];

        $row = 4;

        foreach (
            $info as $label => $value
        ) {

            $sheet->setCellValue(
                "A{$row}",
                $label . ':'
            );

            $sheet
                ->getStyle(
                    "A{$row}"
                )
                ->getFont()
                ->setBold(true);

            $sheet->setCellValue(
                "B{$row}",
                $value
            );

            $row++;
        }

        /*
         * ========================================================
         * EARNINGS
         * ========================================================
         */

        $row++;

        $row =
            $this->writePayslipSection(
                $sheet,
                $row,
                'EARNINGS',
                [

                    'Basic Pay' =>
                        (float) (
                            $line['basic_pay']
                            ?? 0
                        ),

                    'Overtime Pay' =>
                        (float) (
                            $line['overtime_pay']
                            ?? 0
                        ),

                    'Allowances' =>
                        (float) (
                            $line['allowances']
                            ?? 0
                        ),
                ]
            );

        /*
         * ========================================================
         * DEDUCTIONS
         * ========================================================
         */

        $row++;

        $deductionRows = [

            'Late Deduction' =>
                (float) (
                    $line['late_deduction']
                    ?? 0
                ),

            'Undertime Deduction' =>
                (float) (
                    $line['undertime_deduction']
                    ?? 0
                ),
        ];

        if (
            (float) (
                $line['absence_deduction']
                ?? 0
            ) > 0
        ) {

            $deductionRows[
                'Absence Deduction'
            ] =
                (float) (
                    $line['absence_deduction']
                    ?? 0
                );
        }

        if (
            (float) (
                $line['other_deductions']
                ?? 0
            ) > 0
        ) {

            $deductionRows[
                'Other Deductions'
            ] =
                (float) (
                    $line['other_deductions']
                    ?? 0
                );
        }

        $row =
            $this->writePayslipSection(
                $sheet,
                $row,
                'DEDUCTIONS',
                $deductionRows
            );

        /*
         * ========================================================
         * SUMMARY
         * ========================================================
         */

        $row++;

        $summary = [

            'Gross Pay' =>
                (float) (
                    $line['gross_pay']
                    ?? 0
                ),

            'Total Deduction' =>
                (float) (
                    $line['total_deduction']
                    ?? 0
                ),

            'Total' =>
                (float) (
                    $line['net_pay']
                    ?? 0
                ),
        ];

        foreach (
            $summary as $label => $value
        ) {

            $sheet->setCellValue(
                "A{$row}",
                $label
            );

            $sheet->setCellValue(
                "B{$row}",
                $value
            );

            $sheet
                ->getStyle(
                    "B{$row}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0.00'
                );

            $sheet
                ->getStyle(
                    "A{$row}:B{$row}"
                )
                ->getFont()
                ->setBold(true);

            if (
                $label === 'Total'
            ) {

                $sheet
                    ->getStyle(
                        "A{$row}:B{$row}"
                    )
                    ->getFont()
                    ->setSize(12);

                $sheet
                    ->getStyle(
                        "A{$row}:B{$row}"
                    )
                    ->getFill()
                    ->setFillType(
                        Fill::FILL_SOLID
                    )
                    ->getStartColor()
                    ->setRGB(
                        'DCFCE7'
                    );
            }

            $row++;
        }

        /*
         * ========================================================
         * COLUMN WIDTH
         * ========================================================
         */

        $sheet
            ->getColumnDimension('A')
            ->setWidth(26);

        $sheet
            ->getColumnDimension('B')
            ->setWidth(22);

        return $spreadsheet;
    }

    /**
     * ============================================================
     * WRITE PAYSLIP SECTION
     * ============================================================
     */
    private function writePayslipSection(
        $sheet,
        int $row,
        string $title,
        array $rows
    ): int {

        $sheet->setCellValue(
            "A{$row}",
            $title
        );

        $sheet->mergeCells(
            "A{$row}:B{$row}"
        );

        $sheet
            ->getStyle(
                "A{$row}:B{$row}"
            )
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB(
                'FFFFFF'
            );

        $sheet
            ->getStyle(
                "A{$row}:B{$row}"
            )
            ->getFill()
            ->setFillType(
                Fill::FILL_SOLID
            )
            ->getStartColor()
            ->setRGB(
                '1E293B'
            );

        $row++;

        foreach (
            $rows as $label => $value
        ) {

            $sheet->setCellValue(
                "A{$row}",
                $label
            );

            $sheet->setCellValue(
                "B{$row}",
                $value
            );

            $sheet
                ->getStyle(
                    "B{$row}"
                )
                ->getNumberFormat()
                ->setFormatCode(
                    '#,##0.00'
                );

            $row++;
        }

        return $row;
    }

    /**
     * ============================================================
     * PAYSLIP FILENAME
     * ============================================================
     */
    private function payslipFilename(
        array $run,
        array $line,
        bool $withCode = false
    ): string {

        $name =
            $this->sanitizeFilename(
                $line['employee_name']
            );

        if ($withCode) {

            $code =
                $this->sanitizeFilename(
                    (string) (
                        $line['employee_code']
                        ??
                        $line['ngteco_user_id']
                        ??
                        ''
                    )
                );

            if ($code !== '') {

                $name .=
                    "_{$code}";

            } else {

                $name .=
                    '_' .
                    $line['employee_id'];
            }
        }

        return
            'Payslip_'
            . $name
            . '_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_start']
                )
            )
            . '_'
            . date(
                'Ymd',
                strtotime(
                    $run['period_end']
                )
            )
            . '.xlsx';
    }

    /**
     * ============================================================
     * SANITIZE FILENAME
     * ============================================================
     */
    private function sanitizeFilename(
        string $value
    ): string {

        $value =
            preg_replace(
                '/[^A-Za-z0-9\-_ ]/',
                '',
                $value
            ) ?? '';

        $value =
            str_replace(
                ' ',
                '_',
                trim($value)
            );

        return
            $value !== ''
                ? $value
                : 'Unknown';
    }

    /**
     * ============================================================
     * BUILD WEEK OPTIONS
     * ============================================================
     */
    private function buildWeekOptions(
        $db,
        int $pastWeeks = 8
    ): array {

        $suggested =
            $this->suggestNextWeek(
                $db
            );

        $suggestedStart =
            new \DateTime(
                $suggested['start']
            );

        $existing =
            $db->query(
                "SELECT period_start
                 FROM payroll_runs
                 WHERE period_type = 'Weekly'"
            )->fetchAll(
                PDO::FETCH_COLUMN
            );

        $existing =
            array_map(
                'strval',
                $existing
            );

        $options = [];

        for (
            $i = 0;
            $i <= $pastWeeks;
            $i++
        ) {

            $start =
                (clone $suggestedStart)
                ->modify(
                    "-{$i} weeks"
                );

            $end =
                (clone $start)
                ->modify(
                    '+6 days'
                );

            $startStr =
                $start->format(
                    'Y-m-d'
                );

            $endStr =
                $end->format(
                    'Y-m-d'
                );

            $options[] = [

                'start' =>
                    $startStr,

                'end' =>
                    $endStr,

                'label' =>
                    $start->format(
                        'M j'
                    )
                    . ' - '
                    . $end->format(
                        'M j, Y'
                    )
                    . (
                        $i === 0
                            ? ' (Suggested)'
                            : ''
                    ),

                'exists' =>
                    in_array(
                        $startStr,
                        $existing,
                        true
                    ),
            ];
        }

        return $options;
    }

    /**
     * ============================================================
     * SUGGEST NEXT WEEK
     * ============================================================
     */
    private function suggestNextWeek(
        $db
    ): array {

        $lastEnd =
            $db->query(
                "SELECT
                    MAX(period_end) AS last_end

                 FROM payroll_runs

                 WHERE period_type IN
                    ('Daily', 'Weekly')"
            )->fetch()['last_end']
            ?? null;

        if ($lastEnd) {

            $candidate =
                new \DateTime(
                    $lastEnd
                );

            $candidate->modify(
                '+1 day'
            );

            $daysSinceSunday =
                (int) $candidate->format(
                    'w'
                );

            $start =
                (clone $candidate)
                ->modify(
                    "-{$daysSinceSunday} days"
                );

        } else {

            $today =
                new \DateTime();

            $daysSinceSunday =
                (int) $today->format(
                    'w'
                );

            $start =
                (clone $today)
                ->modify(
                    "-{$daysSinceSunday} days"
                );
        }

        $end =
            (clone $start)
            ->modify(
                '+6 days'
            );

        return [

            'start' =>
                $start->format(
                    'Y-m-d'
                ),

            'end' =>
                $end->format(
                    'Y-m-d'
                ),
        ];
    }
}