<?php

namespace App\Services;

use App\Helpers\Database;
use PDO;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class NGTecoExcelImporter
{
    private PDO $db;
    /** @var array<string, array<string, mixed>|null> */
    private array $holidayCache = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Preview uploaded NGTeco attendance file.
     */
    public function preview(
        string $path,
        ?string $originalName = null
    ): array {
        $result = $this->parse($path);

        if ($originalName !== null && $originalName !== '') {
            $result['file_name'] = $originalName;
            $result['filename'] = $originalName;
        }

        return $result;
    }

    /**
     * Parse NGTeco Excel/CSV attendance export.
     */
    public function parse(string $path): array
    {
        if (!is_file($path)) {
            throw new \RuntimeException(
                'Import file was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Load Spreadsheet
        |--------------------------------------------------------------------------
        */

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($path);
        } catch (Throwable $e) {
            throw new \RuntimeException(
                'Unable to read the uploaded file: ' .
                $e->getMessage()
            );
        }

        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(
            null,
            true,
            true,
            true
        );

        if (
            !is_array($rows) ||
            empty($rows)
        ) {
            throw new \RuntimeException(
                'The uploaded file appears to be empty.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Detect NGTeco Header
        |--------------------------------------------------------------------------
        |
        | Typical NGTeco export:
        |
        | Row 1 = View Attendance Punch
        | Row 2 = Person ID | Person Name | Punch Date | Attendance record...
        |
        */

        $headerRowIndex = null;
        $headerRow = null;
        $headers = [];

        $maxHeaderScan = min(
            15,
            $this->safeCount($rows)
        );

        foreach ($rows as $rowNumber => $candidateRow) {

            if ($rowNumber > $maxHeaderScan) {
                break;
            }

            if (!is_array($candidateRow)) {
                continue;
            }

            $candidateHeaders = [];

            foreach (
                $candidateRow as $col => $value
            ) {
                $key = $this->normalizeHeader($value);

                if ($key !== '') {
                    $candidateHeaders[$key] = $col;
                }
            }

            $hasId = $this->findHeader(
                $candidateHeaders,
                [
                    'person id',
                    'employee id',
                    'employee code',
                    'personid',
                    'employeeid',
                    'employeecode',
                ]
            ) !== null;

            $hasName = $this->findHeader(
                $candidateHeaders,
                [
                    'person name',
                    'employee name',
                    'name',
                    'personname',
                    'employeename',
                ]
            ) !== null;

            $hasDate = $this->findHeader(
                $candidateHeaders,
                [
                    'punch date',
                    'attendance date',
                    'date',
                    'punchdate',
                    'attendancedate',
                ]
            ) !== null;

            $hasTime = $this->findHeader(
                $candidateHeaders,
                [
                    'attendance record',
                    'time',
                    'punch time',
                    'attendance time',
                    'record',
                    'attendancerecord',
                    'punchtime',
                ]
            ) !== null;

            if (
                $hasId &&
                $hasName &&
                $hasDate &&
                $hasTime
            ) {
                $headerRowIndex = $rowNumber;
                $headerRow = $candidateRow;
                $headers = $candidateHeaders;

                break;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Header Not Found
        |--------------------------------------------------------------------------
        */

        if (
            $headerRowIndex === null ||
            !is_array($headerRow)
        ) {
            $detected = [];

            foreach (
                array_slice(
                    $rows,
                    0,
                    10,
                    true
                ) as $rowNumber => $candidateRow
            ) {
                if (!is_array($candidateRow)) {
                    continue;
                }

                $values = [];

                foreach ($candidateRow as $value) {
                    $value = trim(
                        (string) $value
                    );

                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                if (!empty($values)) {
                    $detected[] =
                        'Row ' .
                        $rowNumber .
                        ': ' .
                        implode(
                            ' | ',
                            array_slice(
                                $values,
                                0,
                                8
                            )
                        );
                }
            }

            throw new \RuntimeException(
                'Unable to detect the NGTeco attendance header row. ' .
                'Expected Person ID, Person Name, Punch Date, and Attendance record. ' .
                'Detected: ' .
                implode(
                    ' || ',
                    $detected
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Required Columns
        |--------------------------------------------------------------------------
        */

        $idColumn = $this->findHeader(
            $headers,
            [
                'person id',
                'employee id',
                'employee code',
                'personid',
                'employeeid',
                'employeecode',
            ]
        );

        $personNameColumn = $this->findHeader(
            $headers,
            [
                'person name',
                'employee name',
                'name',
                'personname',
                'employeename',
            ]
        );

        $dateColumn = $this->findHeader(
            $headers,
            [
                'punch date',
                'attendance date',
                'date',
                'punchdate',
                'attendancedate',
            ]
        );

        $timeColumn = $this->findHeader(
            $headers,
            [
                'attendance record',
                'time',
                'punch time',
                'attendance time',
                'record',
                'attendancerecord',
                'punchtime',
            ]
        );

        if ($idColumn === null) {
            throw new \RuntimeException(
                'Missing required column: Employee ID. ' .
                'Accepted headers are Person ID, Employee ID, or Employee Code. ' .
                'Detected headers: ' .
                implode(
                    ', ',
                    array_keys($headers)
                )
            );
        }

        if ($personNameColumn === null) {
            throw new \RuntimeException(
                'Missing required column: Employee Name.'
            );
        }

        if ($dateColumn === null) {
            throw new \RuntimeException(
                'Missing required column: Date/Punch Date.'
            );
        }

        if ($timeColumn === null) {
            throw new \RuntimeException(
                'Missing required column: Attendance record/Time.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Columns
        |--------------------------------------------------------------------------
        */

        $workCodeColumn = $this->findHeader(
            $headers,
            [
                'work code',
                'workcode',
                'code',
            ]
        );

        $verifyTypeColumn = $this->findHeader(
            $headers,
            [
                'verify type',
                'verification type',
                'verifytype',
                'verificationtype',
            ]
        );

        $timezoneColumn = $this->findHeader(
            $headers,
            [
                'timezone',
                'time zone',
                'time_zone',
            ]
        );

        $sourceColumn = $this->findHeader(
            $headers,
            [
                'source',
                'device source',
                'device',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Data Rows
        |--------------------------------------------------------------------------
        |
        | Header row is removed.
        | Original Excel row numbers are preserved.
        */

        $dataRows = array_slice(
            $rows,
            $headerRowIndex,
            null,
            true
        );

        unset(
            $dataRows[$headerRowIndex]
        );

        /*
        |--------------------------------------------------------------------------
        | Group Punches by Employee ID + Date
        |--------------------------------------------------------------------------
        */

        $grouped = [];
        $skipped = [];

        foreach (
            $dataRows as $index => $row
        ) {
            $line = $index;

            if (!is_array($row)) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Employee ID
            |--------------------------------------------------------------------------
            */

            $sourceEmployeeId =
                $this->normalizeEmployeeId(
                    $row[$idColumn] ?? ''
                );

            /*
            |--------------------------------------------------------------------------
            | Person Name
            |--------------------------------------------------------------------------
            */

            $personName = trim(
                (string) (
                    $row[$personNameColumn]
                    ?? ''
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Date
            |--------------------------------------------------------------------------
            */

            $date =
                $this->normalizeDate(
                    $row[$dateColumn]
                    ?? null
                );

            /*
            |--------------------------------------------------------------------------
            | Time
            |--------------------------------------------------------------------------
            */

            $time =
                $this->normalizeTime(
                    $row[$timeColumn]
                    ?? null
                );

            /*
            |--------------------------------------------------------------------------
            | Work Code
            |--------------------------------------------------------------------------
            */

            $workCode = '0';

            if ($workCodeColumn !== null) {
                $workCode = trim(
                    (string) (
                        $row[$workCodeColumn]
                        ?? ''
                    )
                );

                if ($workCode === '') {
                    $workCode = '0';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Verify Type
            |--------------------------------------------------------------------------
            */

            $verifyType = null;

            if ($verifyTypeColumn !== null) {
                $verifyType = trim(
                    (string) (
                        $row[$verifyTypeColumn]
                        ?? ''
                    )
                );

                if ($verifyType === '') {
                    $verifyType = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | TimeZone
            |--------------------------------------------------------------------------
            */

            $timezone = null;

            if ($timezoneColumn !== null) {
                $timezone = trim(
                    (string) (
                        $row[$timezoneColumn]
                        ?? ''
                    )
                );

                if ($timezone === '') {
                    $timezone = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Device Source
            |--------------------------------------------------------------------------
            */

            $deviceSource = null;

            if ($sourceColumn !== null) {
                $deviceSource = trim(
                    (string) (
                        $row[$sourceColumn]
                        ?? ''
                    )
                );

                if ($deviceSource === '') {
                    $deviceSource = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Completely Blank Row
            |--------------------------------------------------------------------------
            */

            if (
                $sourceEmployeeId === '' &&
                $personName === '' &&
                $date === null &&
                $time === null
            ) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Invalid Row
            |--------------------------------------------------------------------------
            */

            if (
                $sourceEmployeeId === '' ||
                $date === null ||
                $time === null
            ) {
                $skipped[] =
                    "Row {$line}: missing/invalid Employee ID, Date, or Time.";

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Preserve Original NGTeco Columns
            |--------------------------------------------------------------------------
            */

            $rawData = [];

            foreach (
                $headerRow as $col => $headerValue
            ) {
                $headerName = trim(
                    (string) $headerValue
                );

                $headerName = preg_replace(
                    '/[\x{FEFF}\x{200B}]/u',
                    '',
                    $headerName
                );

                if ($headerName === '') {
                    $headerName =
                        'Column ' . $col;
                }

                $rawData[$headerName] =
                    $row[$col] ?? null;
            }

            /*
            |--------------------------------------------------------------------------
            | Group Key
            |--------------------------------------------------------------------------
            */

            $key =
                $sourceEmployeeId .
                '|' .
                $date;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'source_employee_id' =>
                        $sourceEmployeeId,

                    'person_name' =>
                        $personName,

                    'attendance_date' =>
                        $date,

                    'work_code' =>
                        $workCode,

                    'verify_type' =>
                        $verifyType,

                    'timezone' =>
                        $timezone,

                    'device_source' =>
                        $deviceSource,

                    'punches' =>
                        [],
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Add Punch
            |--------------------------------------------------------------------------
            */

            $grouped[$key]['punches'][] = [
                'time' =>
                    $time,

                'verify_type' =>
                    $verifyType,

                'timezone' =>
                    $timezone,

                'device_source' =>
                    $deviceSource,

                'work_code' =>
                    $workCode,

                'raw' =>
                    $rawData,
            ];

            /*
            |--------------------------------------------------------------------------
            | Preserve First Available Metadata
            |--------------------------------------------------------------------------
            */

            if (
                empty(
                    $grouped[$key]['verify_type']
                ) &&
                !empty($verifyType)
            ) {
                $grouped[$key]['verify_type'] =
                    $verifyType;
            }

            if (
                empty(
                    $grouped[$key]['timezone']
                ) &&
                !empty($timezone)
            ) {
                $grouped[$key]['timezone'] =
                    $timezone;
            }

            if (
                empty(
                    $grouped[$key]['device_source']
                ) &&
                !empty($deviceSource)
            ) {
                $grouped[$key]['device_source'] =
                    $deviceSource;
            }
        }

        if (empty($grouped)) {
            throw new \RuntimeException(
                'No valid attendance rows were found in the uploaded file.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Database
        |--------------------------------------------------------------------------
        */

        $db = Database::connection();

        /*
        |--------------------------------------------------------------------------
        | Exact Employee Lookup
        |--------------------------------------------------------------------------
        */

        $findExact = $db->prepare(
            "SELECT
                id,
                employee_code,
                ngteco_user_id,
                first_name,
                last_name,
                department,
                salary_rate,
                schedule_time_in,
                schedule_time_out,
                rest_day,
                status
             FROM employees
             WHERE employee_code = :employee_code
             LIMIT 1"
        );

        /*
        |--------------------------------------------------------------------------
        | Load Employees
        |--------------------------------------------------------------------------
        */

        $employeeRows = [];

        $employeeQuery = $db->query(
            "SELECT
                id,
                employee_code,
                ngteco_user_id,
                first_name,
                last_name,
                department,
                salary_rate,
                schedule_time_in,
                schedule_time_out,
                rest_day,
                status
             FROM employees
             WHERE employee_code IS NOT NULL
             AND TRIM(employee_code) <> ''"
        );

        if ($employeeQuery) {
            $employeeRows =
                $employeeQuery->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Build Preview
        |--------------------------------------------------------------------------
        */

        $preview = [];

        foreach (
            $grouped as $entry
        ) {
            /*
            |--------------------------------------------------------------------------
            | Get Punches
            |--------------------------------------------------------------------------
            */

            $punches = (
                isset($entry['punches']) &&
                is_array($entry['punches'])
            )
                ? $entry['punches']
                : [];

            /*
            |--------------------------------------------------------------------------
            | Sort Punches
            |--------------------------------------------------------------------------
            */

            usort(
                $punches,
                function ($a, $b) {

                    $timeA = is_array($a)
                        ? ($a['time'] ?? '')
                        : '';

                    $timeB = is_array($b)
                        ? ($b['time'] ?? '')
                        : '';

                    return strcmp(
                        (string) $timeA,
                        (string) $timeB
                    );
                }
            );

            /*
            |--------------------------------------------------------------------------
            | Punch Count
            |--------------------------------------------------------------------------
            */

            $punchCount =
                $this->safeCount(
                    $punches
                );

            /*
            |--------------------------------------------------------------------------
            | Time In / Time Out
            |--------------------------------------------------------------------------
            */

            $timeIn = null;
            $timeOut = null;

            if ($punchCount > 0) {

                $firstPunch =
                    $punches[0];

                if (is_array($firstPunch)) {
                    $timeIn =
                        $firstPunch['time']
                        ?? $firstPunch['datetime']
                        ?? null;
                } else {
                    $timeIn =
                        (string) $firstPunch;
                }

                /*
                |--------------------------------------------------------------------------
                | Single Punch
                |--------------------------------------------------------------------------
                |
                | Time In = Time Out
                |
                */

                if ($punchCount === 1) {

                    $timeOut = null;

                } else {

                    $lastPunch =
                        $punches[
                            $punchCount - 1
                        ];

                    if (is_array($lastPunch)) {
                        $timeOut =
                            $lastPunch['time']
                            ?? $lastPunch['datetime']
                            ?? null;
                    } else {
                        $timeOut =
                            (string) $lastPunch;
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Employee Lookup
            |--------------------------------------------------------------------------
            */

            $employee = null;

            /*
            |--------------------------------------------------------------------------
            | 1. Exact Match
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | NGTeco 0883
            | Employee 0883
            |
            */

            $sourceId = trim(
                (string) ($entry['source_employee_id'] ?? '')
            );

            foreach ($employeeRows as $candidate) {
                $candidateNgteco = trim(
                    (string) ($candidate['ngteco_user_id'] ?? '')
                );
                $candidateCode = trim(
                    (string) ($candidate['employee_code'] ?? '')
                );

                if (
                    $sourceId !== ''
                    && (
                        ($candidateNgteco !== '' && strcasecmp($candidateNgteco, $sourceId) === 0)
                        || ($candidateCode !== '' && strcasecmp($candidateCode, $sourceId) === 0)
                    )
                ) {
                    $employee = $candidate;
                    break;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 2. Normalized Numeric Match
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | NGTeco = 83
            | Employee = 0883
            |
            | Both normalize to:
            |
            | 83
            |
            */

            if (!$employee) {
                $employee =
                    $this->findEmployeeByNormalizedCode(
                        $employeeRows,
                        (string) ($entry['source_employee_id'] ?? '')
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Employee Name
            |--------------------------------------------------------------------------
            */

            $employeeName =
                trim(
                    (string) (
                        $entry['person_name']
                        ?? ''
                    )
                );

            if ($employee) {

                $employeeName =
                    trim(
                        ($employee['first_name'] ?? '') .
                        ' ' .
                        ($employee['last_name'] ?? '')
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Metrics
            |--------------------------------------------------------------------------
            */

            if (
                empty($timeIn) ||
                !is_string($timeIn)
            ) {

                $timeIn = null;
                $timeOut = null;

                $metrics = [
                    'worked_minutes' =>
                        0,

                    'regular_minutes' =>
                        0,

                    'overtime_minutes' =>
                        0,

                    'late_minutes' =>
                        0,

                    'undertime_minutes' =>
                        0,

                    'attendance_status' =>
                        null,
                ];

            } else {

                if (
                    empty($timeOut) ||
                    !is_string($timeOut)
                ) {
                    $timeOut = null;
                }

                $metrics =
                    $this->calculateMetrics(
                        (string)
                            $entry['attendance_date'],

                        (string)
                            $timeIn,

                        (string)
                            $timeOut,

                        (string) (
                            $employee['schedule_time_in']
                            ?? ''
                        ),

                        (string) (
                            $employee['schedule_time_out']
                            ?? ''
                        ),

                        (string) (
                            $employee['rest_day']
                            ?? ''
                        ),
                        (string) (
                            $employee['department']
                            ?? ''
                        )
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Automatic Holiday Detection
            |--------------------------------------------------------------------------
            | Match the attendance date against the active holidays table.
            | No manual Holiday selection is required.
            */

            $holiday = $this->findActiveHolidayByDate(
                (string) $entry['attendance_date']
            );

            if ($holiday !== null && $timeOut !== null) {
                $metrics['attendance_status'] = 'Holiday';
            }

            /*
            |--------------------------------------------------------------------------
            | Complete Punch History
            |--------------------------------------------------------------------------
            */

            $punchHistory = [];

            foreach (
                $punches as $punch
            ) {
                if (!is_array($punch)) {
                    continue;
                }

                $punchHistory[] = [
                    'time' =>
                        $punch['time']
                        ?? null,

                    'verify_type' =>
                        $punch['verify_type']
                        ?? null,

                    'timezone' =>
                        $punch['timezone']
                        ?? null,

                    'device_source' =>
                        $punch['device_source']
                        ?? null,

                    'work_code' =>
                        $punch['work_code']
                        ?? null,

                    'raw' =>
                        $punch['raw']
                        ?? [],
                ];
            }

            /*
            |--------------------------------------------------------------------------
            | Complete NGTeco Data
            |--------------------------------------------------------------------------
            */

            $rawNgtecoData = [
                'person_id' =>
                    $entry['source_employee_id'],

                'person_name' =>
                    $entry['person_name'],

                'punch_date' =>
                    $entry['attendance_date'],

                'verify_type' =>
                    $entry['verify_type'],

                'timezone' =>
                    $entry['timezone'],

                'source' =>
                    $entry['device_source'],

                'work_code' =>
                    $entry['work_code'],

                'punch_count' =>
                    $punchCount,

                'punches' =>
                    $punchHistory,
            ];

            /*
            |--------------------------------------------------------------------------
            | Preview Row
            |--------------------------------------------------------------------------
            */

            $preview[] = [
                /*
                 * Employee Code shown in UI.
                 */
                'employee_id' =>
                    $employee['employee_code']
                    ?? $entry['source_employee_id'],

                /*
                 * Internal employees.id.
                 */
                'employee_db_id' =>
                    $employee['id']
                    ?? null,

                /*
                 * Employee name.
                 */
                'employee_name' =>
                    $employeeName,

                /*
                 * Attendance date.
                 */
                'attendance_date' =>
                    $entry['attendance_date'],

                'holiday_name' =>
                    $holiday['holiday_name']
                    ?? null,

                'holiday_type' =>
                    $holiday['holiday_type']
                    ?? null,

                /*
                 * Time In.
                 */
                'time_in' =>
                    $timeIn,

                /*
                 * Time Out.
                 */
                'time_out' =>
                    $timeOut,

                /*
                 * Work Code.
                 */
                'work_code' =>
                    $entry['work_code'],

                /*
                 * Punch count.
                 */
                'punch_count' =>
                    $punchCount,

                /*
                 * Internal NGTeco Person ID.
                 */
                'ngteco_user_id' =>
                    $entry['source_employee_id'],

                /*
                 * Verify Type.
                 */
                'ngteco_verify_type' =>
                    $entry['verify_type'],

                /*
                 * TimeZone.
                 */
                'ngteco_timezone' =>
                    $entry['timezone'],

                /*
                 * Device Source.
                 */
                'ngteco_device_source' =>
                    $entry['device_source'],

                /*
                 * Complete raw data.
                 */
                'ngteco_raw_data' =>
                    $rawNgtecoData,

                /*
                 * Metrics.
                 */
                'worked_minutes' =>
                    $metrics['worked_minutes'],

                'regular_minutes' =>
                    $metrics['regular_minutes']
                    ?? max(
                        0,
                        (int) $metrics['worked_minutes'] -
                        (int) $metrics['overtime_minutes']
                    ),

                'late_minutes' =>
                    $metrics['late_minutes'],

                'undertime_minutes' =>
                    $metrics['undertime_minutes'],

                'overtime_minutes' =>
                    $metrics['overtime_minutes'],

                'attendance_status' =>
                    $metrics['attendance_status'],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Matched / Unmatched
        |--------------------------------------------------------------------------
        */

        $matched = 0;
        $unmatched = 0;

        foreach (
            $preview as $row
        ) {
            if (
                !empty(
                    $row['employee_db_id']
                )
            ) {
                $matched++;
            } else {
                $unmatched++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Return Preview
        |--------------------------------------------------------------------------
        */

        return [
            'rows' =>
                $preview,

            'preview_rows' =>
                $preview,

            'skipped' =>
                $skipped,

            'invalid_rows' =>
                $this->safeCount(
                    $skipped
                ),

            'total_punch_rows' =>
                array_sum(
                    array_column(
                        $preview,
                        'punch_count'
                    )
                ),

            'total_days' =>
                $this->safeCount(
                    $preview
                ),

            'matched' =>
                $matched,

            'unmatched' =>
                $unmatched,

            'id_header_used' =>
                $this->findOriginalHeaderName(
                    $headerRow,
                    $idColumn
                ),
        ];
    }

    /**
     * Import confirmed preview rows into attendance.
     */
    public function import(
        array $preview
    ): array {
        $db =
            Database::connection();

        $this->ensureAttendanceMetadataColumns(
            $db
        );

        $db->beginTransaction();

        $processed = 0;
        $skipped = 0;

        try {

            /*
            |--------------------------------------------------------------------------
            | Attendance Insert / Update
            |--------------------------------------------------------------------------
            */

            $stmt = $db->prepare(
                "INSERT INTO attendance
                (
                    ngteco_user_id,
                    ngteco_verify_type,
                    ngteco_timezone,
                    ngteco_device_source,
                    ngteco_raw_data,
                    employee_id,
                    attendance_date,
                    time_in,
                    time_out,
                    worked_minutes,
                    regular_minutes,
                    overtime_minutes,
                    late_minutes,
                    undertime_minutes,
                    attendance_status,
                    source,
                    sync_timestamp
                )
                VALUES
                (
                    :uid,
                    :verify_type,
                    :timezone,
                    :device_source,
                    :raw_data,
                    :employee_id,
                    :date,
                    :time_in,
                    :time_out,
                    :worked,
                    :regular,
                    :ot,
                    :late,
                    :undertime,
                    :status,
                    'ngteco_excel',
                    NOW()
                )
                ON DUPLICATE KEY UPDATE

                    employee_id =
                        VALUES(employee_id),

                    ngteco_user_id =
                        VALUES(ngteco_user_id),

                    ngteco_verify_type =
                        VALUES(ngteco_verify_type),

                    ngteco_timezone =
                        VALUES(ngteco_timezone),

                    ngteco_device_source =
                        VALUES(ngteco_device_source),

                    ngteco_raw_data =
                        VALUES(ngteco_raw_data),

                    time_in =
                        VALUES(time_in),

                    time_out =
                        VALUES(time_out),

                    worked_minutes =
                        VALUES(worked_minutes),

                    regular_minutes =
                        VALUES(regular_minutes),

                    overtime_minutes =
                        VALUES(overtime_minutes),

                    late_minutes =
                        VALUES(late_minutes),

                    undertime_minutes =
                        VALUES(undertime_minutes),

                    attendance_status =
                        VALUES(attendance_status),

                    source =
                        'ngteco_excel',

                    sync_timestamp =
                        NOW()"
            );

            /*
            |--------------------------------------------------------------------------
            | Process Rows
            |--------------------------------------------------------------------------
            */

            $rows =
                $preview['rows']
                ?? [];

            if (!is_array($rows)) {
                $rows = [];
            }

            foreach (
                $rows as $row
            ) {

                /*
                |--------------------------------------------------------------------------
                | Skip Unmatched
                |--------------------------------------------------------------------------
                */

                if (
                    empty(
                        $row['employee_db_id']
                    )
                ) {
                    $skipped++;

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Regular Minutes
                |--------------------------------------------------------------------------
                */

                $regular =
                    max(
                        0,
                        (int) (
                            $row['worked_minutes']
                            ?? 0
                        ) -
                        (int) (
                            $row['overtime_minutes']
                            ?? 0
                        )
                    );

                /*
                |--------------------------------------------------------------------------
                | Time In
                |--------------------------------------------------------------------------
                */

                $timeIn = null;

                if (
                    !empty(
                        $row['time_in']
                    )
                ) {
                    $timeIn =
                        $row['attendance_date'] .
                        ' ' .
                        $row['time_in'];
                }

                /*
                |--------------------------------------------------------------------------
                | Time Out
                |--------------------------------------------------------------------------
                */

                $timeOut = null;

                if (
                    !empty(
                        $row['time_out']
                    )
                ) {
                    $timeOut =
                        $row['attendance_date'] .
                        ' ' .
                        $row['time_out'];
                }

                /*
                |--------------------------------------------------------------------------
                | Raw JSON
                |--------------------------------------------------------------------------
                */

                $rawData =
                    $row['ngteco_raw_data']
                    ?? [];

                $rawJson =
                    json_encode(
                        $rawData,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );

                if ($rawJson === false) {
                    $rawJson = '{}';
                }

                /*
                |--------------------------------------------------------------------------
                | Automatic Holiday Detection
                |--------------------------------------------------------------------------
                | Re-check at confirmation time so a newly added holiday is
                | still recognized even if the preview was opened earlier.
                */

                $holiday = $this->findActiveHolidayByDate(
                    (string) ($row['attendance_date'] ?? '')
                );

                $attendanceStatus =
                    array_key_exists('attendance_status', $row)
                        ? trim((string) $row['attendance_status'])
                        : null;

                if ($attendanceStatus === '') {
                    $attendanceStatus = null;
                }

                if ($holiday !== null && $timeOut !== null) {
                    $attendanceStatus = 'Holiday';
                }

                /*
                |--------------------------------------------------------------------------
                | Execute
                |--------------------------------------------------------------------------
                */

                $stmt->execute([
                    'uid' =>
                        $row['ngteco_user_id']
                        ?? null,

                    'verify_type' =>
                        $row['ngteco_verify_type']
                        ?? null,

                    'timezone' =>
                        $row['ngteco_timezone']
                        ?? null,

                    'device_source' =>
                        $row['ngteco_device_source']
                        ?? null,

                    'raw_data' =>
                        $rawJson,

                    'employee_id' =>
                        $row['employee_db_id'],

                    'date' =>
                        $row['attendance_date'],

                    'time_in' =>
                        $timeIn,

                    'time_out' =>
                        $timeOut,

                    'worked' =>
                        (int) (
                            $row['worked_minutes']
                            ?? 0
                        ),

                    'regular' =>
                        $regular,

                    'ot' =>
                        (int) (
                            $row['overtime_minutes']
                            ?? 0
                        ),

                    'late' =>
                        (int) (
                            $row['late_minutes']
                            ?? 0
                        ),

                    'undertime' =>
                        (int) (
                            $row['undertime_minutes']
                            ?? 0
                        ),

                    'status' =>
                        $attendanceStatus,
                ]);

                $processed++;
            }

            $db->commit();

            return [
                'processed' =>
                    $processed,

                'skipped' =>
                    $skipped,
            ];

        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Normalize Employee ID.
     *
     * Excel may return:
     * 883
     * 883.0
     * "883"
     */
    private function normalizeEmployeeId(
        $value
    ): string {
        if (
            $value === null ||
            $value === ''
        ) {
            return '';
        }

        if (is_numeric($value)) {

            $numeric =
                (float) $value;

            if (
                floor($numeric) ===
                $numeric
            ) {
                return (string) (
                    (int) $numeric
                );
            }
        }

        return trim(
            (string) $value
        );
    }

    /**
     * Normalize header.
     */
    private function normalizeHeader(
        $value
    ): string {
        $key =
            (string) $value;

        $key = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $key
        );

        $key = preg_replace(
            '/[\x{FEFF}\x{200B}\x{00A0}]/u',
            ' ',
            $key
        );

        $key =
            strtolower(
                trim($key)
            );

        $key = preg_replace(
            '/[\s_\-]+/',
            ' ',
            $key
        );

        return trim($key);
    }

    /**
     * Find header column.
     */
    private function findHeader(
        array $headers,
        array $aliases
    ): ?string {
        foreach (
            $aliases as $alias
        ) {
            $normalized =
                $this->normalizeHeader(
                    $alias
                );

            if (
                isset(
                    $headers[$normalized]
                )
            ) {
                return
                    $headers[$normalized];
            }
        }

        return null;
    }

    /**
     * Original header name.
     */
    private function findOriginalHeaderName(
        array $headerRow,
        ?string $column
    ): ?string {
        if ($column === null) {
            return null;
        }

        if (
            !array_key_exists(
                $column,
                $headerRow
            )
        ) {
            return null;
        }

        return trim(
            (string) $headerRow[$column]
        );
    }

    /**
     * Find employee using normalized numeric Employee ID.
     *
     * Examples:
     *
     * NGTeco: 83
     * Employee: 0883
     *
     * Both become:
     * 83
     */
    private function findEmployeeByNormalizedCode(
        array $employees,
        string $sourceEmployeeId
    ): ?array {
        $sourceNormalized =
            $this->normalizeNumericEmployeeCode(
                $sourceEmployeeId
            );

        if ($sourceNormalized === '') {
            return null;
        }

        $matches = [];

        foreach (
            $employees as $employee
        ) {
            $employeeCode =
                trim(
                    (string) (
                        $employee['employee_code']
                        ?? ''
                    )
                );

            if ($employeeCode === '') {
                continue;
            }

            $employeeNormalized =
                $this->normalizeNumericEmployeeCode(
                    $employeeCode
                );

            if (
                $employeeNormalized ===
                $sourceNormalized
            ) {
                $matches[] =
                    $employee;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Only accept one match.
        |--------------------------------------------------------------------------
        */

        if (
            $this->safeCount($matches) === 1
        ) {
            return $matches[0];
        }

        return null;
    }

    /**
     * Normalize numeric Employee Code.
     *
     * 83    -> 83
     * 083   -> 83
     * 0883  -> 883
     * 00883 -> 883
     */
    private function normalizeNumericEmployeeCode(
        string $value
    ): string {
        $value =
            trim($value);

        if ($value === '') {
            return '';
        }

        /*
        |--------------------------------------------------------------------------
        | Only numeric IDs are normalized.
        |--------------------------------------------------------------------------
        */

        if (
            !preg_match(
                '/^\d+$/',
                $value
            )
        ) {
            return '';
        }

        /*
        |--------------------------------------------------------------------------
        | Remove leading zeroes.
        |--------------------------------------------------------------------------
        */

        $normalized =
            ltrim(
                $value,
                '0'
            );

        if ($normalized === '') {
            return '0';
        }

        return $normalized;
    }

    /**
     * Normalize Excel date.
     */
    private function normalizeDate(
        $value
    ): ?string {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Excel Serial Date
        |--------------------------------------------------------------------------
        */

        if (is_numeric($value)) {
            try {
                return
                    ExcelDate::excelToDateTimeObject(
                        $value
                    )->format('Y-m-d');
            } catch (Throwable) {
                // Continue below.
            }
        }

        $text =
            trim(
                (string) $value
            );

        if ($text === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Supported Date Formats
        |--------------------------------------------------------------------------
        */

        $formats = [
            'm/d/Y',
            'n/j/Y',
            'm/d/y',
            'n/j/y',
            'Y-m-d',
            'd/m/Y',
            'd/m/y',
            'Y/m/d',
            'm-d-Y',
            'd-m-Y',
        ];

        foreach (
            $formats as $format
        ) {
            $dt =
                \DateTime::createFromFormat(
                    $format,
                    $text
                );

            if (
                $dt &&
                $dt->format($format) ===
                $text
            ) {
                return
                    $dt->format('Y-m-d');
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        $ts =
            strtotime($text);

        if ($ts === false) {
            return null;
        }

        return date(
            'Y-m-d',
            $ts
        );
    }

    /**
     * Normalize Excel time.
     */
    private function normalizeTime(
        $value
    ): ?string {
        if (
            $value === null ||
            $value === ''
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Excel Time Fraction
        |--------------------------------------------------------------------------
        |
        | 0.5 = 12:00:00
        |
        */

        if (
            is_numeric($value) &&
            (float) $value < 1
        ) {
            $seconds =
                (int) round(
                    (float) $value *
                    86400
                );

            $seconds =
                $seconds % 86400;

            return
                gmdate(
                    'H:i:s',
                    $seconds
                );
        }

        $text =
            trim(
                (string) $value
            );

        if ($text === '') {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Supported Time Formats
        |--------------------------------------------------------------------------
        */

        $formats = [
            'H:i:s',
            'H:i',
            'G:i:s',
            'G:i',
            'g:i:s A',
            'g:i A',
            'h:i:s A',
            'h:i A',
        ];

        foreach (
            $formats as $format
        ) {
            $dt =
                \DateTime::createFromFormat(
                    $format,
                    $text
                );

            if ($dt) {
                return
                    $dt->format(
                        'H:i:s'
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        $ts =
            strtotime($text);

        if ($ts === false) {
            return null;
        }

        return date(
            'H:i:s',
            $ts
        );
    }

    /**
     * Find an active holiday matching an attendance date.
     */
    private function findActiveHolidayByDate(string $date): ?array
    {
        $date = trim($date);

        if ($date === '') {
            return null;
        }

        if (array_key_exists($date, $this->holidayCache)) {
            return $this->holidayCache[$date];
        }

        $stmt = $this->db->prepare(
            "SELECT holiday_name, holiday_type
             FROM holidays
             WHERE holiday_date = :holiday_date
               AND is_active = 1
             ORDER BY id ASC
             LIMIT 1"
        );

        $stmt->execute([
            'holiday_date' => $date,
        ]);

        $holiday = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->holidayCache[$date] =
            is_array($holiday)
                ? $holiday
                : null;

        return $this->holidayCache[$date];
    }

    /**
     * Calculate attendance metrics.
     */
    private function calculateMetrics(
        string $date,
        string $timeIn,
        ?string $timeOut,
        ?string $scheduleIn,
        ?string $scheduleOut,
        ?string $restDay,
        ?string $department = null
    ): array {
        $isRestDay = trim((string) ($restDay ?? '')) !== ''
            && strcasecmp(trim((string) $restDay), date('l', strtotime($date))) === 0;

        return AttendanceCalculator::evaluate(
            $scheduleIn,
            $scheduleOut,
            $date,
            $timeIn !== '' ? $timeIn : null,
            $timeOut,
            $isRestDay,
            false,
            $department
        );
    }

    /**
     * Safely count countable values.
     */
    private function safeCount(
        mixed $value
    ): int {
        return is_countable($value)
            ? count($value)
            : 0;
    }

    /**
     * Ensure attendance metadata columns exist.
     */
    private function ensureAttendanceMetadataColumns(
        PDO $db
    ): void {

        $columns =
            $db->query(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'attendance'
                   AND COLUMN_NAME IN (
                        'ngteco_verify_type',
                        'ngteco_timezone',
                        'ngteco_device_source',
                        'ngteco_raw_data'
                   )"
            )->fetchAll(
                PDO::FETCH_COLUMN
            );

        $definitions = [
            'ngteco_verify_type' =>
                "VARCHAR(50) NULL AFTER ngteco_user_id",

            'ngteco_timezone' =>
                "VARCHAR(20) NULL AFTER ngteco_verify_type",

            'ngteco_device_source' =>
                "VARCHAR(100) NULL AFTER ngteco_timezone",

            'ngteco_raw_data' =>
                "JSON NULL AFTER ngteco_device_source",
        ];

        $previous = null;

        foreach (
            $definitions as
            $column => $definition
        ) {

            /*
            |--------------------------------------------------------------------------
            | Already Exists
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $column,
                    $columns,
                    true
                )
            ) {
                $previous =
                    $column;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Adjust AFTER clause
            |--------------------------------------------------------------------------
            */

            if ($previous !== null) {
                $definition =
                    preg_replace(
                        '/\s+AFTER\s+\S+$/',
                        " AFTER {$previous}",
                        $definition
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Add Column
            |--------------------------------------------------------------------------
            */

            $db->exec(
                "ALTER TABLE attendance
                 ADD COLUMN {$column} {$definition}"
            );

            $previous =
                $column;
        }

        // Time In-only attendance needs a real NULL status.
        // Empty strings are invalid for an ENUM status column in MySQL.
        $statusColumn = $db->query(
            "SHOW COLUMNS FROM attendance LIKE 'attendance_status'"
        )->fetch(PDO::FETCH_ASSOC);

        if (is_array($statusColumn) && !empty($statusColumn['Type'])) {
            $statusType = (string) $statusColumn['Type'];
            $nullable = strtoupper((string) ($statusColumn['Null'] ?? 'NO'));

            if ($nullable !== 'YES') {
                $db->exec(
                    "ALTER TABLE attendance
                     MODIFY COLUMN attendance_status {$statusType} NULL DEFAULT NULL"
                );
            }
        }
    }
}