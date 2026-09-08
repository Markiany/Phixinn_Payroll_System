<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\Database;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();

        $db = Database::connection();

        /*
         * -------------------------------------------------------------
         * Dashboard date range
         *
         * The selected range is shared by all dashboard sections that
         * are date-based: attendance cards, attendance graph, holidays,
         * and birthdays.
         *
         * Default: current month.
         * -------------------------------------------------------------
         */
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $dateFrom = trim($_GET['date_from'] ?? $monthStart);
        $dateTo = trim($_GET['date_to'] ?? $today);

        if (!$this->isValidDate($dateFrom)) {
            $dateFrom = $monthStart;
        }

        if (!$this->isValidDate($dateTo)) {
            $dateTo = $today;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        /*
         * -------------------------------------------------------------
         * Active employees
         * -------------------------------------------------------------
         */
        $totalEmployees = (int) $db->query(
            "SELECT COUNT(*) AS c
             FROM employees
             WHERE status = 'Active'"
        )->fetch()['c'];

        /*
         * -------------------------------------------------------------
         * Attendance statistics for the selected range.
         * -------------------------------------------------------------
         */
        $stmt = $db->prepare(
            "SELECT
                COALESCE(SUM(attendance_status = 'Present'), 0) AS present,
                COALESCE(SUM(attendance_status = 'Absent'), 0) AS absent,
                COALESCE(SUM(attendance_status = 'Late'), 0) AS late,
                COALESCE(SUM(overtime_minutes > 0), 0) AS overtime
             FROM attendance
             WHERE attendance_date BETWEEN :date_from AND :date_to"
        );

        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $attendanceStats = $stmt->fetch() ?: [];

        $presentCount = (int) ($attendanceStats['present'] ?? 0);
        $absentCount = (int) ($attendanceStats['absent'] ?? 0);
        $lateCount = (int) ($attendanceStats['late'] ?? 0);
        $overtimeCount = (int) ($attendanceStats['overtime'] ?? 0);

        /*
         * -------------------------------------------------------------
         * Latest payroll run
         * -------------------------------------------------------------
         */
        $currentRun = $db->query(
            "SELECT *
             FROM payroll_runs
             ORDER BY period_start DESC
             LIMIT 1"
        )->fetch();

        /*
         * -------------------------------------------------------------
         * Holidays in the selected dashboard range
         * -------------------------------------------------------------
         */
        $stmt = $db->prepare(
            "SELECT
                id,
                holiday_name,
                holiday_date,
                holiday_type,
                description
             FROM holidays
             WHERE is_active = 1
               AND holiday_date BETWEEN :date_from AND :date_to
             ORDER BY holiday_date ASC, holiday_name ASC"
        );

        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);

        $holidays = $stmt->fetchAll();

        /*
         * -------------------------------------------------------------
         * Birthdays in the selected dashboard range
         *
         * Birthdays repeat every year, so only month/day are compared.
         * Employees are returned once even when the selected range
         * crosses a year boundary.
         * -------------------------------------------------------------
         */
        $stmt = $db->query(
            "SELECT
                id,
                employee_code,
                first_name,
                last_name,
                birthdate
             FROM employees
             WHERE birthdate IS NOT NULL
               AND status <> 'Terminated'
             ORDER BY MONTH(birthdate) ASC,
                      DAY(birthdate) ASC,
                      last_name ASC,
                      first_name ASC"
        );

        $allBirthdays = $stmt->fetchAll();
        $birthdays = [];

        foreach ($allBirthdays as $birthday) {
            $birthdate = $birthday['birthdate'] ?? null;

            if (!$birthdate) {
                continue;
            }

            $monthDay = date('m-d', strtotime($birthdate));

            if ($this->monthDayIsWithinRange(
                $monthDay,
                $dateFrom,
                $dateTo
            )) {
                $birthdays[] = $birthday;
            }
        }

        /*
         * Keep birthday display ordered by month/day.
         */
        usort(
            $birthdays,
            function (array $a, array $b): int {
                $aKey = date(
                    'm-d',
                    strtotime((string) $a['birthdate'])
                );

                $bKey = date(
                    'm-d',
                    strtotime((string) $b['birthdate'])
                );

                return strcmp($aKey, $bKey)
                    ?: strcasecmp(
                        (string) ($a['last_name'] ?? ''),
                        (string) ($b['last_name'] ?? '')
                    );
            }
        );

        $stats = [
            'total_employees' => $totalEmployees,
            'present_today'   => $presentCount,
            'absent_today'    => $absentCount,
            'late_today'      => $lateCount,
            'overtime_today'  => $overtimeCount,
            'current_period'  => $currentRun
                ? "{$currentRun['period_start']} to {$currentRun['period_end']}"
                : 'No payroll run yet',
            'payroll_status'  => $currentRun['status'] ?? 'N/A',
        ];

        $attendanceChart = [
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
            'overtime' => $overtimeCount,
            'total' => $presentCount + $absentCount + $lateCount,
        ];

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/dashboard/index.php';
    }

    private function monthDayIsWithinRange(
        string $monthDay,
        string $dateFrom,
        string $dateTo
    ): bool {
        $from = date('m-d', strtotime($dateFrom));
        $to = date('m-d', strtotime($dateTo));

        if ($from <= $to) {
            return $monthDay >= $from && $monthDay <= $to;
        }

        /*
         * Cross-year range, e.g. Dec 20 -> Jan 10.
         */
        return $monthDay >= $from || $monthDay <= $to;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false &&
            $parsed->format('Y-m-d') === $date;
    }
}
