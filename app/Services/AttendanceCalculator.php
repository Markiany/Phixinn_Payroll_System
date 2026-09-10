<?php

namespace App\Services;

class AttendanceCalculator
{
    public const BREAK_ENABLED = true;
    public const BREAK_START = '12:00:00';
    public const BREAK_END = '13:00:00';

    // Shared overtime thresholds used by Attendance and Salary Calculation.
    public const EARLY_OT_MINUTES = 60;
    public const OT_MINUTES = 30;

    public static function evaluate(
        string $date,
        ?string $timeIn,
        ?string $timeOut,
        ?string $scheduleTimeIn,
        ?string $scheduleTimeOut,
        ?string $restDay,
        bool $isHoliday,
        ?string $department = null
    ): array {
        if (!$timeIn) {
            if ($isHoliday) {
                return self::result('Holiday');
            }
            if (self::isRestDay($date, $restDay)) {
                return self::result('Rest-Day');
            }
            return self::result('Absent');
        }

        if (!$timeOut) {
            return self::result(null);
        }

        $actualInTs = self::parseTimestamp($date, $timeIn);
        $actualOutTs = self::parseTimestamp($date, $timeOut);

        if ($actualInTs === null || $actualOutTs === null) {
            return self::result(null);
        }

        if ($actualOutTs <= $actualInTs) {
            $actualOutTs += 86400;
        }

        $workedElapsed = max(0.0, ($actualOutTs - $actualInTs) / 60.0);

        if (!$scheduleTimeIn || !$scheduleTimeOut) {
            $breakMinutes = self::breakOverlapMinutes($actualInTs, $actualOutTs, $date);
            $worked = max(0, $workedElapsed - $breakMinutes);
            return self::result(
                'Present',
                $worked,
                0.0,
                0.0,
                0.0,
                $breakMinutes
            );
        }

        $schedInTs = self::parseTimestamp($date, $scheduleTimeIn);
        $schedOutTs = self::parseTimestamp($date, $scheduleTimeOut);

        if ($schedInTs === null || $schedOutTs === null) {
            $breakMinutes = self::breakOverlapMinutes($actualInTs, $actualOutTs, $date);
            return self::result(
                'Present',
                max(0.0, $workedElapsed - $breakMinutes),
                0.0,
                0.0,
                0.0,
                $breakMinutes
            );
        }

        if ($schedOutTs <= $schedInTs) {
            $schedOutTs += 86400;
        }

        $lateMinutes = $actualInTs > $schedInTs
            ? ($actualInTs - $schedInTs) / 60.0
            : 0.0;

        $earlyMinutes = $actualInTs < $schedInTs
            ? ($schedInTs - $actualInTs) / 60.0
            : 0.0;

        $lateStayMinutes = $actualOutTs > $schedOutTs
            ? ($actualOutTs - $schedOutTs) / 60.0
            : 0.0;

        $allowEarlyOT = true;

        if ($department !== null && trim($department) !== '') {
            $allowEarlyOT = DepartmentOTSettings::isEarlyOTAllowed($department);
        }

        $otFromEarly = $allowEarlyOT && $earlyMinutes >= self::EARLY_OT_MINUTES
            ? $earlyMinutes
            : 0;
        $otFromLateStay = $lateStayMinutes >= self::OT_MINUTES ? $lateStayMinutes : 0;
        $overtimeMinutes = $otFromEarly + $otFromLateStay;

        $breakMinutes = self::breakOverlapMinutes($actualInTs, $actualOutTs, $date);
        $workedMinutes = max(0, $workedElapsed - $breakMinutes);

        // The break is already excluded from worked time.
        // Late is a separate shortage, while undertime is the remaining
        // shortage after accounting for late and qualifying overtime.
        $regularWorkedMinutes = max(0, $workedMinutes - $overtimeMinutes);
        $requiredMinutes = 8 * 60;
        $undertimeMinutes = max(
            0,
            $requiredMinutes - $regularWorkedMinutes - $lateMinutes
        );

        if ($isHoliday) {
            $status = 'Holiday';
        } elseif (self::isRestDay($date, $restDay)) {
            $status = 'Rest-Day';
        } elseif ($lateMinutes > 0) {
            $status = 'Late';
        } else {
            $status = 'Present';
        }

        return self::result(
            $status,
            $workedMinutes,
            $lateMinutes,
            $undertimeMinutes,
            $overtimeMinutes,
            $breakMinutes,
            $earlyMinutes,
            $lateStayMinutes,
            $regularWorkedMinutes
        );
    }

    public static function breakOverlapMinutes(
        int $startTs,
        int $endTs,
        string $date
    ): float {
        if (!self::BREAK_ENABLED || $endTs <= $startTs) {
            return 0;
        }

        $breakStart = strtotime($date . ' ' . self::BREAK_START);
        $breakEnd = strtotime($date . ' ' . self::BREAK_END);

        if ($breakStart === false || $breakEnd === false) {
            return 0;
        }

        $overlapStart = max($startTs, $breakStart);
        $overlapEnd = min($endTs, $breakEnd);

        if ($overlapEnd <= $overlapStart) {
            return 0;
        }

        return ($overlapEnd - $overlapStart) / 60.0;
    }

    private static function isRestDay(string $date, ?string $restDay): bool
    {
        $restDay = trim((string) $restDay);

        if ($restDay === '') {
            return false;
        }

        return strcasecmp($restDay, date('l', strtotime($date))) === 0;
    }

    private static function parseTimestamp(string $date, string $value): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        /*
         * Time-only values must always use the attendance date.
         * Otherwise strtotime('07:00:00') uses today's server date,
         * which can completely break late/undertime/OT calculations.
         */
        if (preg_match('/^\\d{1,2}:\\d{2}(?::\\d{2})?(?:\\s*[AaPp][Mm])?$/', $value)) {
            $timestamp = strtotime($date . ' ' . $value);
            return $timestamp === false ? null : $timestamp;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }

    private static function result(
        ?string $status,
        float $workedMinutes = 0.0,
        float $lateMinutes = 0.0,
        float $undertimeMinutes = 0.0,
        float $overtimeMinutes = 0.0,
        ?float $breakMinutes = null,
        float $earlyMinutes = 0.0,
        float $lateStayMinutes = 0.0,
        ?float $regularMinutes = null
    ): array {
        return [
            'attendance_status' => $status,
            'worked_minutes' => $workedMinutes,
            'regular_minutes' => $regularMinutes ?? max(0.0, $workedMinutes - $overtimeMinutes),
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'early_minutes' => $earlyMinutes,
            'late_stay_minutes' => $lateStayMinutes,
            'break_minutes' => $breakMinutes ?? (self::BREAK_ENABLED ? 60.0 : 0.0),
        ];
    }
}
