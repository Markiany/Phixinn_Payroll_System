<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;
use App\Services\AttendanceCalculator;
use PDO;
use Throwable;

class EmployeeController
{
    public function index(): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $stmt = $db->query(
            "SELECT
                id,
                employee_code,
                first_name,
                last_name,
                full_name,
                department,
                salary_type,
                salary_rate,
                schedule_time_in,
                schedule_time_out,
                rest_day,
                status,
                effective_date,
                birthdate
             FROM employees
             ORDER BY last_name ASC, first_name ASC"
        );

        $employees = $stmt->fetchAll();

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/employees/index.php';
    }

    public function create(): void
    {
        Auth::requireRole('admin', 'payroll');

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/employees/create.php';
    }

    public function store(): void
    {
        Auth::requireRole('admin', 'payroll');

        $db = Database::connection();

        $employeeCode = trim($_POST['employee_code'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $employmentType = trim($_POST['employment_type'] ?? 'Regular');
        $salaryRate = $_POST['salary_rate'] ?? '0';

        $scheduleTimeIn = trim($_POST['schedule_time_in'] ?? '');
        $scheduleTimeOut = trim($_POST['schedule_time_out'] ?? '');
        $restDay = $_POST['rest_day'] ?? 'Sunday';
        $status = $_POST['status'] ?? 'Active';
        $effectiveDate = trim($_POST['effective_date'] ?? '');

        if ($employeeCode === '') {
            $this->redirectWithError('/employees/create', 'Employee ID is required.');
        }

        if ($firstName === '' || $lastName === '') {
            $this->redirectWithError('/employees/create', 'First Name and Last Name are required.');
        }

        if ($birthdate !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $birthdate);

            if (!$date || $date->format('Y-m-d') !== $birthdate) {
                $this->redirectWithError('/employees/create', 'Birthdate must be a valid date.');
            }
        }

        $stmt = $db->prepare(
            "SELECT id
             FROM employees
             WHERE employee_code = :employee_code
             LIMIT 1"
        );

        $stmt->execute([
            'employee_code' => $employeeCode
        ]);

        if ($stmt->fetch()) {
            $this->redirectWithError('/employees/create', 'Employee ID already exists.');
        }

        $allowedEmploymentTypes = ['Regular', 'Part-Time'];

        if (!in_array($employmentType, $allowedEmploymentTypes, true)) {
            $employmentType = 'Regular';
        }

        $allowedRestDays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
            'None'
        ];

        if (!in_array($restDay, $allowedRestDays, true)) {
            $restDay = 'Sunday';
        }

        $allowedStatuses = ['Active', 'Suspended', 'Terminated'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Active';
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO employees
                    (
                        employee_code,
                        first_name,
                        last_name,
                        birthdate,
                        department,
                        salary_type,
                        salary_rate,
                        schedule_time_in,
                        schedule_time_out,
                        rest_day,
                        status,
                        effective_date
                    )
                 VALUES
                    (
                        :employee_code,
                        :first_name,
                        :last_name,
                        :birthdate,
                        :department,
                        :salary_type,
                        :salary_rate,
                        :schedule_time_in,
                        :schedule_time_out,
                        :rest_day,
                        :status,
                        :effective_date
                    )"
            );

            $stmt->execute([
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => $birthdate !== '' ? $birthdate : null,
                'department' => $department !== '' ? $department : null,
                'salary_type' => $employmentType,
                'salary_rate' => (float) $salaryRate,
                'schedule_time_in' => $scheduleTimeIn !== '' ? $scheduleTimeIn : null,
                'schedule_time_out' => $scheduleTimeOut !== '' ? $scheduleTimeOut : null,
                'rest_day' => $restDay,
                'status' => $status,
                'effective_date' => $effectiveDate !== '' ? $effectiveDate : null,
            ]);

            header('Location: /employees');
            exit;
        } catch (Throwable $e) {
            $this->redirectWithError(
                '/employees/create',
                'Unable to create employee: ' . $e->getMessage()
            );
        }
    }

    public function edit(string $id): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT *
             FROM employees
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $employee = $stmt->fetch();

        if (!$employee) {
            http_response_code(404);
            echo '404 - Employee not found';
            return;
        }

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/employees/edit.php';
    }

    public function update(string $id): void
    {
        Auth::requireRole('admin', 'payroll');

        $db = Database::connection();

        $stmt = $db->prepare(
            "SELECT *
             FROM employees
             WHERE id = :id
             LIMIT 1"
        );

        $stmt->execute(['id' => $id]);
        $existingEmployee = $stmt->fetch();

        if (!$existingEmployee) {
            $this->redirectWithError('/employees', 'Employee not found.');
        }

        $employeeCode = trim(
            $_POST['employee_code'] ?? ($existingEmployee['employee_code'] ?? '')
        );

        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        $birthdate = trim(
            $_POST['birthdate'] ?? ($existingEmployee['birthdate'] ?? '')
        );

        $department = trim($_POST['department'] ?? '');

        $employmentType = trim(
            $_POST['employment_type'] ?? ($existingEmployee['salary_type'] ?? 'Regular')
        );

        $salaryRate = $_POST['salary_rate']
            ?? ($existingEmployee['salary_rate'] ?? '0');

        $scheduleTimeIn = trim(
            $_POST['schedule_time_in']
            ?? ($existingEmployee['schedule_time_in'] ?? '')
        );

        $scheduleTimeOut = trim(
            $_POST['schedule_time_out']
            ?? ($existingEmployee['schedule_time_out'] ?? '')
        );

        $restDay = $_POST['rest_day']
            ?? ($existingEmployee['rest_day'] ?? 'Sunday');

        $status = $_POST['status']
            ?? ($existingEmployee['status'] ?? 'Active');

        $effectiveDate = trim(
            $_POST['effective_date']
            ?? ($existingEmployee['effective_date'] ?? '')
        );

        if ($employeeCode === '') {
            $this->redirectWithError(
                '/employees/' . rawurlencode($id) . '/edit',
                'Employee ID is required.'
            );
        }

        if ($firstName === '' || $lastName === '') {
            $this->redirectWithError(
                '/employees/' . rawurlencode($id) . '/edit',
                'First Name and Last Name are required.'
            );
        }

        if ($birthdate !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $birthdate);

            if (!$date || $date->format('Y-m-d') !== $birthdate) {
                $this->redirectWithError(
                    '/employees/' . rawurlencode($id) . '/edit',
                    'Birthdate must be a valid date.'
                );
            }
        }

        $stmt = $db->prepare(
            "SELECT id
             FROM employees
             WHERE employee_code = :employee_code
               AND id <> :id
             LIMIT 1"
        );

        $stmt->execute([
            'employee_code' => $employeeCode,
            'id' => $id
        ]);

        if ($stmt->fetch()) {
            $this->redirectWithError(
                '/employees/' . rawurlencode($id) . '/edit',
                'Employee ID already exists.'
            );
        }

        $allowedEmploymentTypes = ['Regular', 'Part-Time'];

        if (!in_array($employmentType, $allowedEmploymentTypes, true)) {
            $employmentType = 'Regular';
        }

        $allowedRestDays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
            'None'
        ];

        if (!in_array($restDay, $allowedRestDays, true)) {
            $restDay = 'Sunday';
        }

        $allowedStatuses = ['Active', 'Suspended', 'Terminated'];

        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Active';
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "UPDATE employees
                 SET
                    employee_code = :employee_code,
                    first_name = :first_name,
                    last_name = :last_name,
                    birthdate = :birthdate,
                    department = :department,
                    salary_type = :salary_type,
                    salary_rate = :salary_rate,
                    schedule_time_in = :schedule_time_in,
                    schedule_time_out = :schedule_time_out,
                    rest_day = :rest_day,
                    status = :status,
                    effective_date = :effective_date
                 WHERE id = :id"
            );

            $stmt->execute([
                'employee_code' => $employeeCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => $birthdate !== '' ? $birthdate : null,
                'department' => $department !== '' ? $department : null,
                'salary_type' => $employmentType,
                'salary_rate' => (float) $salaryRate,
                'schedule_time_in' => $scheduleTimeIn !== '' ? $scheduleTimeIn : null,
                'schedule_time_out' => $scheduleTimeOut !== '' ? $scheduleTimeOut : null,
                'rest_day' => $restDay,
                'status' => $status,
                'effective_date' => $effectiveDate !== '' ? $effectiveDate : null,
                'id' => $id,
            ]);

            /*
             * IMPORTANT:
             * When the employee's schedule changes, existing attendance
             * records must be recalculated using the new schedule.
             *
             * This keeps Attendance and Salary Calculation in sync.
             */
            $updatedEmployee = $existingEmployee;
            $updatedEmployee['id'] = $id;
            $updatedEmployee['employee_code'] = $employeeCode;
            $updatedEmployee['ngteco_user_id'] =
                $existingEmployee['ngteco_user_id'] ?? null;
            $updatedEmployee['schedule_time_in'] =
                $scheduleTimeIn !== '' ? $scheduleTimeIn : null;
            $updatedEmployee['schedule_time_out'] =
                $scheduleTimeOut !== '' ? $scheduleTimeOut : null;
            $updatedEmployee['rest_day'] = $restDay;

            $this->recalculateEmployeeAttendance(
                $db,
                $updatedEmployee
            );

            $db->commit();

            header('Location: /employees');
            exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->redirectWithError(
                '/employees/' . rawurlencode($id) . '/edit',
                'Unable to update employee: ' . $e->getMessage()
            );
        }
    }

    public function deactivate(string $id): void
    {
        Auth::requireRole('admin', 'payroll');

        $db = Database::connection();

        $stmt = $db->prepare(
            "UPDATE employees
             SET status = 'Suspended'
             WHERE id = :id"
        );

        $stmt->execute(['id' => $id]);

        header('Location: /employees');
        exit;
    }

    public function delete(string $id): void
    {
        Auth::requireRole('admin');

        $db = Database::connection();

        try {
            $stmt = $db->prepare(
                "DELETE FROM employees
                 WHERE id = :id"
            );

            $stmt->execute(['id' => $id]);

            header('Location: /employees');
            exit;
        } catch (Throwable $e) {
            http_response_code(500);

            echo 'Unable to delete employee: '
                . htmlspecialchars($e->getMessage());
        }
    }

    /**
     * Recalculate all existing attendance records for one employee
     * using the employee's current Schedule Time In / Time Out.
     *
     * This is intentionally limited to attendance calculation fields.
     * It does not alter raw Time In / Time Out punches.
     */
    private function recalculateEmployeeAttendance(
        $db,
        array $employee
    ): void {
        $employeeId = trim((string) ($employee['id'] ?? ''));
        $employeeCode = trim((string) ($employee['employee_code'] ?? ''));
        $ngtecoUserId = trim((string) ($employee['ngteco_user_id'] ?? ''));

        if ($employeeId === '') {
            return;
        }

        /*
         * Use unique PDO parameter names because the same named placeholder
         * must not be reused when native prepares are enabled.
         */
        $stmt = $db->prepare(
            "SELECT
                a.id,
                a.attendance_date,
                a.time_in,
                a.time_out
             FROM attendance a
             WHERE (
                    CAST(a.employee_id AS CHAR) = :employee_id_1
                    OR (
                        :employee_code_1 <> ''
                        AND CAST(a.employee_id AS CHAR) = :employee_code_2
                    )
                    OR (
                        :ngteco_user_id_1 <> ''
                        AND CAST(a.employee_id AS CHAR) = :ngteco_user_id_2
                    )
                    OR (
                        :ngteco_user_id_2_check <> ''
                        AND CAST(a.ngteco_user_id AS CHAR) = :ngteco_user_id_3
                    )
                    OR (
                        :employee_code_3 <> ''
                        AND CAST(a.ngteco_user_id AS CHAR) = :employee_code_4
                    )
                )
             ORDER BY a.attendance_date ASC, a.id ASC"
        );

        $stmt->execute([
            'employee_id_1' => $employeeId,
            'employee_code_1' => $employeeCode,
            'employee_code_2' => $employeeCode,
            'ngteco_user_id_1' => $ngtecoUserId,
            'ngteco_user_id_2' => $ngtecoUserId,
            'ngteco_user_id_2_check' => $ngtecoUserId,
            'ngteco_user_id_3' => $ngtecoUserId,
            'employee_code_3' => $employeeCode,
            'employee_code_4' => $employeeCode,
        ]);

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($records)) {
            return;
        }

        $holidayStmt = $db->prepare(
            "SELECT id
             FROM holidays
             WHERE holiday_date = :holiday_date
               AND is_active = 1
             LIMIT 1"
        );

        $updateStmt = $db->prepare(
            "UPDATE attendance
             SET
                worked_minutes = :worked_minutes,
                late_minutes = :late_minutes,
                undertime_minutes = :undertime_minutes,
                overtime_minutes = :overtime_minutes,
                attendance_status = :attendance_status
             WHERE id = :id
             LIMIT 1"
        );

        foreach ($records as $record) {
            $date = (string) ($record['attendance_date'] ?? '');

            if ($date === '') {
                continue;
            }

            $timeIn = !empty($record['time_in'])
                ? (string) $record['time_in']
                : null;

            $timeOut = !empty($record['time_out'])
                ? (string) $record['time_out']
                : null;

            $holidayStmt->execute([
                'holiday_date' => $date,
            ]);

            $isHoliday = (bool) $holidayStmt->fetchColumn();

            $isRestDay = $this->isRestDay(
                $date,
                [
                    $employee['rest_day'] ?? 'Sunday'
                ]
            );

            $calculated = AttendanceCalculator::evaluate(
                !empty($employee['schedule_time_in'])
                    ? (string) $employee['schedule_time_in']
                    : null,
                !empty($employee['schedule_time_out'])
                    ? (string) $employee['schedule_time_out']
                    : null,
                $date,
                $timeIn,
                $timeOut,
                $isRestDay,
                $isHoliday
            );

            $updateStmt->execute([
                'worked_minutes' =>
                    (int) ($calculated['worked_minutes'] ?? 0),

                'late_minutes' =>
                    (int) ($calculated['late_minutes'] ?? 0),

                'undertime_minutes' =>
                    (int) ($calculated['undertime_minutes'] ?? 0),

                'overtime_minutes' =>
                    (int) ($calculated['overtime_minutes'] ?? 0),

                'attendance_status' =>
                    (string) ($calculated['attendance_status'] ?? 'Present'),

                'id' =>
                    (int) ($record['id'] ?? 0),
            ]);
        }
    }

    /**
     * Check if an attendance date falls on the employee's configured rest day.
     */
    private function isRestDay(
        string $date,
        array $restDays = []
    ): bool {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return false;
        }

        $weekday = strtolower(date('l', $timestamp));

        foreach ($restDays as $restDay) {
            $restDay = strtolower(trim((string) $restDay));

            if (
                $restDay === $weekday
                || substr($restDay, 0, 3) === substr($weekday, 0, 3)
            ) {
                return true;
            }
        }

        return false;
    }

    private function redirectWithError(
        string $url,
        string $message
    ): void {
        $_SESSION['employee_error'] = $message;

        header('Location: ' . $url);
        exit;
    }
}
