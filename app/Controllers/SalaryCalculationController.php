<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;
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

        $error = $_SESSION['salary_error'] ?? null;
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
             WHERE a.employee_id = :employee_id
               AND a.attendance_date BETWEEN :period_start AND :period_end
             ORDER BY a.attendance_date ASC"
        );
        $stmt->execute([
            'employee_id' => $employeeId,
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

        $filename =
            'Payroll_'
            . date(
                'Ymd',
                strtotime($run['period_start'])
            )
            . '_'
            . date(
                'Ymd',
                strtotime($run['period_end'])
            )
            . (
                $department !== ''
                    ? '_' .
                      $this->sanitizeFilename(
                          $department
                      )
                    : ''
            )
            . '.xlsx';

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

        $width = 1400;
        $height = 1650;

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
        $border = imagecolorallocate($image, 226, 232, 240);
        $section = imagecolorallocate($image, 30, 41, 59);
        $green = imagecolorallocate($image, 22, 101, 52);
        $greenBg = imagecolorallocate($image, 220, 252, 231);
        $red = imagecolorallocate($image, 185, 28, 28);
        $light = imagecolorallocate($image, 248, 250, 252);

        imagefill(
            $image,
            0,
            0,
            $white
        );

        $left = 80;
        $right = $width - 80;
        $contentWidth = $right - $left;
        $rowHeight = 48;
        $labelX = $left + 24;
        $valueRight = $right - 24;
        $valueX = $left + 610;
        $y = 70;

        $this->drawPayslipImageText(
            $image,
            $left,
            $y,
            'PHIXINN PAYROLL SYSTEM',
            $black,
            5
        );

        $y += 48;

        $this->drawPayslipImageText(
            $image,
            $left,
            $y,
            'Employee Payslip',
            $slate,
            4
        );

        $y += 52;

        imagerectangle(
            $image,
            $left,
            $y,
            $right,
            $y + 2,
            $border
        );

        $y += 36;

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

        $employeeCode =
            $line['employee_code']
            ?? $line['ngteco_user_id']
            ?? '';

        $infoRows = [
            'Employee Name' =>
                (string) ($line['employee_name'] ?? '-'),
            'Employee Code' =>
                (string) ($employeeCode ?: '-'),
            'Department' =>
                (string) ($line['department'] ?: '-'),
            'Position' =>
                (string) ($line['position'] ?: '-'),
            'Pay Period' =>
                $periodLabel,
            'Status' =>
                (string) ($line['status'] ?? '-'),
        ];

        foreach ($infoRows as $label => $value) {

            $this->drawPayslipImageText(
                $image,
                $labelX,
                $y,
                $label . ':',
                $black,
                4
            );

            $this->drawPayslipImageTextRight(
                $image,
                $valueRight,
                $y,
                $value,
                $slate,
                4
            );

            $y += $rowHeight;
        }

        $y += 18;

        $y =
            $this->drawPayslipImageMoneySection(
                $image,
                $left,
                $right,
                $y,
                'EARNINGS',
                [
                    'Basic Pay' =>
                        (float) (
                            $line['basic_pay'] ?? 0
                        ),
                    'Overtime Pay' =>
                        (float) (
                            $line['overtime_pay'] ?? 0
                        ),
                    'Allowances' =>
                        (float) (
                            $line['allowances'] ?? 0
                        ),
                ],
                $section,
                $white,
                $black,
                $slate,
                $border
            );

        $y += 28;

        $deductionRows = [
            'Late Deduction' =>
                (float) (
                    $line['late_deduction'] ?? 0
                ),
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
            $deductionRows['Absence Deduction'] =
                (float) (
                    $line['absence_deduction'] ?? 0
                );
        }

        if (
            (float) (
                $line['other_deductions'] ?? 0
            ) > 0
        ) {
            $deductionRows['Other Deductions'] =
                (float) (
                    $line['other_deductions'] ?? 0
                );
        }

        $y =
            $this->drawPayslipImageMoneySection(
                $image,
                $left,
                $right,
                $y,
                'DEDUCTIONS',
                $deductionRows,
                $section,
                $white,
                $black,
                $red,
                $border
            );

        $y += 28;

        $grossPay =
            (float) (
                $line['gross_pay'] ?? 0
            );

        $totalDeduction =
            (float) (
                $line['total_deduction'] ?? 0
            );

        $netPay =
            (float) (
                $line['net_pay'] ?? 0
            );

        $summaryRows = [
            'Gross Pay' => $grossPay,
            'Total Deduction' => $totalDeduction,
        ];

        $summaryHeight =
            58 * count($summaryRows)
            + 76;

        imagefilledrectangle(
            $image,
            $left,
            $y,
            $right,
            $y + $summaryHeight,
            $light
        );

        imagerectangle(
            $image,
            $left,
            $y,
            $right,
            $y + $summaryHeight,
            $border
        );

        $summaryY = $y + 18;

        foreach ($summaryRows as $label => $value) {

            $this->drawPayslipImageText(
                $image,
                $labelX,
                $summaryY,
                $label,
                $black,
                4
            );

            $this->drawPayslipImageTextRight(
                $image,
                $valueRight,
                $summaryY,
                '₱' . number_format($value, 2),
                $slate,
                4
            );

            $summaryY += 58;
        }

        imagefilledrectangle(
            $image,
            $left + 1,
            $summaryY,
            $right - 1,
            $summaryY + 75,
            $greenBg
        );

        $this->drawPayslipImageText(
            $image,
            $labelX,
            $summaryY + 20,
            'Total',
            $green,
            5
        );

        $this->drawPayslipImageTextRight(
            $image,
            $valueRight,
            $summaryY + 20,
            '₱' . number_format($netPay, 2),
            $green,
            5
        );

        $y = $summaryY + 105;

        $this->drawPayslipImageText(
            $image,
            $left,
            $y,
            'Generated: ' . date('M j, Y h:i A'),
            $muted,
            2
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
     * Draw a money section with a dark section heading.
     */
    private function drawPayslipImageMoneySection(
        $image,
        int $left,
        int $right,
        int $y,
        string $title,
        array $rows,
        int $sectionColor,
        int $titleColor,
        int $labelColor,
        int $borderColor
    ): int {

        $headerHeight = 48;
        $rowHeight = 48;
        $totalHeight =
            $headerHeight
            + ($rowHeight * count($rows));

        imagefilledrectangle(
            $image,
            $left,
            $y,
            $right,
            $y + $headerHeight,
            $sectionColor
        );

        $this->drawPayslipImageText(
            $image,
            $left + 24,
            $y + 14,
            $title,
            $titleColor,
            4
        );

        $rowY = $y + $headerHeight;

        foreach ($rows as $label => $value) {

            imagefilledrectangle(
                $image,
                $left,
                $rowY,
                $right,
                $rowY + $rowHeight,
                imagecolorallocate(
                    $image,
                    255,
                    255,
                    255
                )
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
                $rowY + 14,
                $label,
                $labelColor,
                4
            );

            $this->drawPayslipImageTextRight(
                $image,
                $right - 24,
                $rowY + 14,
                '₱' . number_format((float) $value, 2),
                $labelColor,
                4
            );

            $rowY += $rowHeight;
        }

        return $y + $totalHeight;
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
             WHERE a.employee_id = :employee_id
               AND a.attendance_date BETWEEN :period_start AND :period_end
             ORDER BY a.attendance_date ASC"
        );

        $stmt->execute([
            'employee_id' => $employee['id'],
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

        return $line ?: null;
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
     * 40-44 minutes = ₱200
     * 45+ minutes = 1 hour deduction
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

        /*
         * ========================================================
         * DAILY RATE
         * ========================================================
         */

        $dailyRate =
            (float) (
                $emp['salary_rate'] ?? 0
            );

        /*
         * 8 regular hours.
         */
        $regularHoursPerDay = 8.0;

        /*
         * Hourly rate is ONLY used when
         * 45 minutes or more late/undertime
         * becomes an automatic 1-hour deduction.
         */
        $hourlyRate =
            $regularHoursPerDay > 0
                ? $dailyRate /
                  $regularHoursPerDay
                : 0;

        /*
         * ========================================================
         * RULES
         * ========================================================
         */

        /*
         * Deduction starts at 10 minutes.
         */
        $lateBracketMinutes = 10.0;

        /*
         * Every 10 minutes = ₱50.
         */
        $lateBracketAmount = 50.0;

        /*
         * 45 minutes or more = 1 hour deduction.
         */
        $autoHourThreshold = 45.0;

        /*
         * Overtime minimum remains 30 minutes.
         */
        $otMinimumMinutes = 30.0;

        $otRegularMultiplier = 1.00;

        $otRestDayMultiplier = 1.30;

        $otHolidayMultiplier = 2.00;

        /*
         * ========================================================
         * TOTALS
         * ========================================================
         */

        $basicPay = 0.0;

        $overtimePay = 0.0;

        $allowances = 0.0;

        $lateDeduction = 0.0;

        $undertimeDeduction = 0.0;

        $absenceDeduction = 0.0;

        $trace = [];

        /*
         * ========================================================
         * PROCESS ATTENDANCE
         * ========================================================
         */

        foreach (
            $attendanceRows as $attendance
        ) {

            $status =
                trim(
                    (string) (
                        $attendance[
                            'attendance_status'
                        ] ?? ''
                    )
                );

            /*
             * Automatic holiday recognition is date-based.
             * The LEFT JOIN in getEmployeeAttendance supplies the holiday
             * details, so salary calculation does not depend on manually
             * setting attendance_status.
             */
            $holidayName =
                trim(
                    (string) (
                        $attendance['holiday_name']
                        ?? ''
                    )
                );

            $holidayType =
                trim(
                    (string) (
                        $attendance['holiday_type']
                        ?? ''
                    )
                );

            if ($holidayName !== '') {
                $status = 'Holiday';
            }

            $lateMinutes =
                max(
                    0,
                    (float) (
                        $attendance[
                            'late_minutes'
                        ] ?? 0
                    )
                );

            $undertimeMinutes =
                max(
                    0,
                    (float) (
                        $attendance[
                            'undertime_minutes'
                        ] ?? 0
                    )
                );

            $overtimeMinutes =
                max(
                    0,
                    (float) (
                        $attendance[
                            'overtime_minutes'
                        ] ?? 0
                    )
                );

            $date =
                $attendance[
                    'attendance_date'
                ] ?? null;

            if (!$date) {
                continue;
            }

            $dayEntry = [
                'date' =>
                    $date,

                'status' =>
                    $status,

                'holiday_name' =>
                    $holidayName !== ''
                        ? $holidayName
                        : null,

                'holiday_type' =>
                    $holidayType !== ''
                        ? $holidayType
                        : null,

                'daily_salary' =>
                    round(
                        $dailyRate,
                        2
                    ),

                'hourly_rate' =>
                    round(
                        $hourlyRate,
                        2
                    ),
            ];

            /*
             * ====================================================
             * BASIC PAY
             * ====================================================
             */

            $dayBasicPay = 0.0;

            if (
                in_array(
                    $status,
                    [
                        'Present',
                        'Late',
                        'Half-Day'
                    ],
                    true
                )
            ) {

                if (
                    $status === 'Half-Day'
                ) {

                    $dayBasicPay =
                        round(
                            $dailyRate / 2,
                            2
                        );

                } else {

                    $dayBasicPay =
                        round(
                            $dailyRate,
                            2
                        );
                }
            }

            $basicPay +=
                $dayBasicPay;

            /*
             * ====================================================
             * ALLOWANCE
             * ====================================================
             */

            $dayAllowance = 0.0;

            if (
                $status === 'Present'
            ) {

                $dayAllowance =
                    (float) (
                        $emp['allowance']
                        ?? 0
                    );
            }

            $allowances +=
                $dayAllowance;

            /*
             * ====================================================
             * LATE DEDUCTION
             * ====================================================
             *
             * 0-9 minutes:
             *      ₱0
             *
             * 10-19 minutes:
             *      ₱50
             *
             * 20-29 minutes:
             *      ₱100
             *
             * 30-39 minutes:
             *      ₱150
             *
             * 40-44 minutes:
             *      ₱200
             *
             * 45+ minutes:
             *      1 hour deduction
             *      = Daily Rate / 8
             */

            $dayLateDeduction = 0.0;

            if ($lateMinutes > 0) {

                if (
                    $lateMinutes >=
                    $autoHourThreshold
                ) {

                    /*
                     * 45 minutes or more
                     * = automatic 1 hour deduction.
                     */
                    $dayLateDeduction =
                        round(
                            $hourlyRate,
                            2
                        );

                } elseif (
                    $lateMinutes >=
                    $lateBracketMinutes
                ) {

                    /*
                     * Every 10 minutes = ₱50.
                     *
                     * ceil() means:
                     *
                     * 10 mins = 1 bracket = ₱50
                     * 19 mins = 1 bracket = ₱50
                     * 20 mins = 2 brackets = ₱100
                     * 29 mins = 2 brackets = ₱100
                     * 30 mins = 3 brackets = ₱150
                     * 40 mins = 4 brackets = ₱200
                     * 44 mins = 4 brackets = ₱200
                     */
                    $brackets =
                        ceil(
                            $lateMinutes /
                            $lateBracketMinutes
                        );

                    $dayLateDeduction =
                        round(
                            $brackets *
                            $lateBracketAmount,
                            2
                        );
                }

                $lateDeduction +=
                    $dayLateDeduction;
            }

            /*
             * ====================================================
             * UNDERTIME
             * ====================================================
             *
             * Same rule as Late:
             *
             * 0-9 minutes  = ₱0
             * 10-19 minutes = ₱50
             * 20-29 minutes = ₱100
             * 30-39 minutes = ₱150
             * 40-44 minutes = ₱200
             * 45+ minutes = 1 hour deduction
             */

            $dayUndertimeDeduction = 0.0;

            if ($undertimeMinutes > 0) {

                if (
                    $undertimeMinutes >=
                    $autoHourThreshold
                ) {

                    /*
                     * 45 minutes or more
                     * = automatic 1 hour deduction.
                     */
                    $dayUndertimeDeduction =
                        round(
                            $hourlyRate,
                            2
                        );

                } elseif (
                    $undertimeMinutes >=
                    $lateBracketMinutes
                ) {

                    /*
                     * Every 10 minutes = ₱50.
                     */
                    $brackets =
                        ceil(
                            $undertimeMinutes /
                            $lateBracketMinutes
                        );

                    $dayUndertimeDeduction =
                        round(
                            $brackets *
                            $lateBracketAmount,
                            2
                        );
                }

                $undertimeDeduction +=
                    $dayUndertimeDeduction;
            }

            /*
             * ====================================================
             * ABSENCE
             * ====================================================
             */

            $dayAbsenceDeduction = 0.0;

            $absenceDeduction +=
                $dayAbsenceDeduction;

            /*
             * ====================================================
             * OVERTIME
             * ====================================================
             */

            $dayOtPay = 0.0;

            $multiplier = 0.0;

            $dayName =
                date(
                    'l',
                    strtotime($date)
                );

            if (
                $overtimeMinutes >=
                $otMinimumMinutes
            ) {

                $multiplier =
                    $otRegularMultiplier;

                /*
                 * Rest Day
                 */
                $employeeRestDay =
                    trim(
                        (string) (
                            $emp['rest_day']
                            ?? ''
                        )
                    );

                if (
                    $employeeRestDay !== '' &&
                    strcasecmp(
                        $employeeRestDay,
                        $dayName
                    ) === 0
                ) {

                    $multiplier =
                        $otRestDayMultiplier;
                }

                /*
                 * Holiday priority.
                 */
                if (
                    $status === 'Holiday'
                ) {

                    $multiplier =
                        $otHolidayMultiplier;
                }

                $dayOtPay =
                    round(
                        (
                            $overtimeMinutes /
                            60
                        )
                        *
                        $hourlyRate
                        *
                        $multiplier,
                        2
                    );

                $overtimePay +=
                    $dayOtPay;
            }

            /*
             * ====================================================
             * DAILY TOTALS
             * ====================================================
             */

            $dayGross =
                round(
                    $dayBasicPay
                    +
                    $dayOtPay
                    +
                    $dayAllowance,
                    2
                );

            $dayDeduction =
                round(
                    $dayLateDeduction
                    +
                    $dayUndertimeDeduction
                    +
                    $dayAbsenceDeduction,
                    2
                );

            $dayNet =
                round(
                    $dayGross
                    -
                    $dayDeduction,
                    2
                );

            /*
             * ====================================================
             * TRACE
             * ====================================================
             */

            $dayEntry[
                'basic_pay'
            ] =
                $dayBasicPay;

            $dayEntry[
                'allowance'
            ] =
                $dayAllowance;

            $dayEntry[
                'late_minutes'
            ] =
                $lateMinutes;

            $dayEntry[
                'late_deduction'
            ] =
                $dayLateDeduction;

            $dayEntry[
                'undertime_minutes'
            ] =
                $undertimeMinutes;

            $dayEntry[
                'undertime_deduction'
            ] =
                $dayUndertimeDeduction;

            $dayEntry[
                'overtime_minutes'
            ] =
                $overtimeMinutes;

            $dayEntry[
                'overtime_multiplier'
            ] =
                $multiplier;

            $dayEntry[
                'overtime_pay'
            ] =
                $dayOtPay;

            $dayEntry[
                'gross_pay'
            ] =
                $dayGross;

            $dayEntry[
                'total_deduction'
            ] =
                $dayDeduction;

            $dayEntry[
                'net_pay'
            ] =
                $dayNet;

            $trace[] =
                $dayEntry;
        }

        /*
         * ========================================================
         * FINAL TOTALS
         * ========================================================
         */

        $grossPay =
            round(
                $basicPay
                +
                $overtimePay
                +
                $allowances,
                2
            );

        $totalDeduction =
            round(
                $lateDeduction
                +
                $undertimeDeduction
                +
                $absenceDeduction,
                2
            );

        $netPay =
            round(
                $grossPay
                -
                $totalDeduction,
                2
            );

        /*
         * Never allow negative net pay.
         */
        if ($netPay < 0) {
            $netPay = 0.0;
        }

        /*
         * Add overall summary to trace.
         */
        $traceSummary = [
            'days_processed' =>
                count($attendanceRows),

            'basic_pay' =>
                round(
                    $basicPay,
                    2
                ),

            'overtime_pay' =>
                round(
                    $overtimePay,
                    2
                ),

            'allowances' =>
                round(
                    $allowances,
                    2
                ),

            'late_deduction' =>
                round(
                    $lateDeduction,
                    2
                ),

            'undertime_deduction' =>
                round(
                    $undertimeDeduction,
                    2
                ),

            'gross_pay' =>
                $grossPay,

            'total_deduction' =>
                $totalDeduction,

            'net_pay' =>
                $netPay,
        ];

        $trace[] = [
            'summary' =>
                $traceSummary
        ];

        return [
            'basic_pay' =>
                round(
                    $basicPay,
                    2
                ),

            'overtime_pay' =>
                round(
                    $overtimePay,
                    2
                ),

            'allowances' =>
                round(
                    $allowances,
                    2
                ),

            'late_deduction' =>
                round(
                    $lateDeduction,
                    2
                ),

            'undertime_deduction' =>
                round(
                    $undertimeDeduction,
                    2
                ),

            'absence_deduction' =>
                round(
                    $absenceDeduction,
                    2
                ),

            'gross_pay' =>
                $grossPay,

            'total_deduction' =>
                $totalDeduction,

            'net_pay' =>
                $netPay,

            'trace' =>
                $trace,
        ];
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
