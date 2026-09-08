<?php

namespace App\Services;

/**
 * AttendanceCalculator
 * ============================================================
 * Turns a raw NGTeco punch (time-in / time-out) into the fields
 * the rest of the system relies on: attendance_status,
 * worked_minutes, late_minutes, undertime_minutes and
 * overtime_minutes.
 *
 * Used by AttendanceController::sync() right after each day's
 * punches are paired, so every attendance row written to the
 * database already carries a correct status and a correct
 * minute breakdown. Salary Calculation then only has to read
 * these columns - it never re-derives them from raw punches,
 * which keeps the whole pipeline (Attendance -> Salary
 * Calculation -> Excel -> Payslip) consistent.
 * ============================================================
 */
class AttendanceCalculator
{
    /**
     * @param string|null $scheduleTimeIn  employee's scheduled start, 'HH:MM[:SS]' (e.g. from employees.schedule_time_in), or null if not set
     * @param string|null $scheduleTimeOut employee's scheduled/expected end, 'HH:MM[:SS]', or null if not set
     * @param string      $date            'Y-m-d' attendance date
     * @param string|null $timeIn          actual clock-in as a full 'Y-m-d H:i:s', or null if no punch that day
     * @param string|null $timeOut         actual clock-out as a full 'Y-m-d H:i:s', or null if only one punch that day
     * @param bool        $isRestDay       whether $date is this employee's configured rest day
     * @param bool        $isHoliday       whether $date is in the holiday calendar (Settings)
     *
     * @return array{attendance_status:string, worked_minutes:int, late_minutes:int, undertime_minutes:int, overtime_minutes:int}
     */
    public static function evaluate(
        ?string $scheduleTimeIn,
        ?string $scheduleTimeOut,
        string $date,
        ?string $timeIn,
        ?string $timeOut,
        bool $isRestDay,
        bool $isHoliday
    ): array {
        // ------------------------------------------------------
        // No punch at all that day - nothing to compute, just
        // classify which kind of "no work" day this is. Salary
        // Calculation is what decides whether it's paid (a
        // holiday can still be paid even with no punch).
        // ------------------------------------------------------
        if (!$timeIn) {
            if ($isHoliday) {
                return self::result('Holiday');
            }

            if ($isRestDay) {
                return self::result('Rest-Day');
            }

            return self::result('Absent');
        }

        $workedMinutes = 0;
        if ($timeOut) {
            $workedMinutes = max(0, (int) round((strtotime($timeOut) - strtotime($timeIn)) / 60));
        }

        // ------------------------------------------------------
        // No schedule on file for this employee yet - we can
        // still record the punch, we just cannot tell if it was
        // late/undertime/OT without a scheduled time to compare
        // against.
        // ------------------------------------------------------
        if (!$scheduleTimeIn || !$scheduleTimeOut) {
            return self::result($timeOut ? 'Present' : 'Half-Day', $workedMinutes);
        }

        $schedInTs  = strtotime($date . ' ' . $scheduleTimeIn);
        $schedOutTs = strtotime($date . ' ' . $scheduleTimeOut);
        $actualInTs = strtotime($timeIn);
        $actualOutTs = $timeOut ? strtotime($timeOut) : null;

        // ---- Late: actual time-in after scheduled time-in ----
        $lateMinutes = $actualInTs > $schedInTs
            ? (int) round(($actualInTs - $schedInTs) / 60)
            : 0;

        // ---- Undertime: left before scheduled time-out ----
        $undertimeMinutes = ($actualOutTs !== null && $actualOutTs < $schedOutTs)
            ? (int) round(($schedOutTs - $actualOutTs) / 60)
            : 0;

        // ---- OT source 1: early arrival before scheduled time-in.
        //      Only counts once the accumulated early time reaches
        //      the minimum - then the WHOLE amount counts, not just
        //      the excess over the minimum. ----
        $earlyMinutes = $actualInTs < $schedInTs
            ? (int) round(($schedInTs - $actualInTs) / 60)
            : 0;
        $otFromEarly = $earlyMinutes >= PayrollRules::OVERTIME_MINIMUM_MINUTES ? $earlyMinutes : 0;

        // ---- OT source 2: staying after scheduled time-out.
        //      Same all-or-nothing threshold rule. ----
        $lateStayMinutes = ($actualOutTs !== null && $actualOutTs > $schedOutTs)
            ? (int) round(($actualOutTs - $schedOutTs) / 60)
            : 0;
        $otFromLateStay = $lateStayMinutes >= PayrollRules::OVERTIME_MINIMUM_MINUTES ? $lateStayMinutes : 0;

        $overtimeMinutes = $otFromEarly + $otFromLateStay;

        // ---- Status ----
        if (!$timeOut) {
            $status = 'Half-Day';
        } elseif ($lateMinutes > 0) {
            $status = 'Late';
        } else {
            $status = 'Present';
        }

        return self::result($status, $workedMinutes, $lateMinutes, $undertimeMinutes, $overtimeMinutes);
    }

    private static function result(
        string $status,
        int $workedMinutes = 0,
        int $lateMinutes = 0,
        int $undertimeMinutes = 0,
        int $overtimeMinutes = 0
    ): array {
        return [
            'attendance_status' => $status,
            'worked_minutes'    => $workedMinutes,
            'late_minutes'      => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes'  => $overtimeMinutes,
        ];
    }
}