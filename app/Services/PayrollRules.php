<?php

namespace App\Services;

/**
 * PayrollRules
 * ============================================================
 * ONE centralized place for every payroll constant: late /
 * undertime deduction brackets, overtime thresholds and
 * multipliers, holiday pay multipliers, and allowance rules.
 *
 * Nothing else in the app should hardcode these numbers. If a
 * rate ever needs to change, it changes here once.
 * ============================================================
 */
class PayrollRules
{
    /** Standard working hours in a regular day. Hourly Rate = Daily Rate / this. */
    public const REGULAR_HOURS_PER_DAY = 8.0;

    // ------------------------------------------------------------
    // ALLOWANCE RULES
    // ------------------------------------------------------------

    /** Fixed weekly allowance amount for eligible employees. */
    public const DEFAULT_WEEKLY_ALLOWANCE = 500.00;

    /**
     * Determine if employee qualifies for weekly allowance.
     * Rule: Must have 0 late minutes, 0 undertime minutes, and 0 absent days.
     * Even a single minute of late or undertime forfeits the entire allowance.
     */
    public static function calculateAllowance(float|int $lateMinutes, float|int $undertimeMinutes, float|int $absentDays, float $allowanceAmount = self::DEFAULT_WEEKLY_ALLOWANCE): float
    {
        if ($lateMinutes > 0 || $undertimeMinutes > 0 || $absentDays > 0) {
            return 0.00;
        }

        return $allowanceAmount;
    }

    // ------------------------------------------------------------
    // LATE / UNDERTIME
    // ------------------------------------------------------------

    /** Minutes per late/undertime deduction bracket. */
    public const LATE_BRACKET_MINUTES = 10.0;

    /** Pesos deducted per bracket of lateness/undertime. */
    public const LATE_BRACKET_AMOUNT = 50.0;

    /**
     * At/above this many minutes late (or short at time-out), the
     * deduction becomes a flat 1 hour at the hourly rate instead of
     * counting brackets.
     */
    public const AUTO_HOUR_THRESHOLD_MINUTES = 45.0;

    // ------------------------------------------------------------
    // OVERTIME
    // ------------------------------------------------------------

    /**
     * Minimum accumulated early-arrival (before scheduled time-in) or
     * late-stay (after scheduled time-out) minutes needed for that
     * time to count as OT at all. Below this, OT is 0. At/above
     * this, the WHOLE accumulated amount counts (not just the
     * excess over the threshold).
     */
    public const OVERTIME_MINIMUM_MINUTES = 30.0;

    /** OT multiplier on a normal working day. */
    public const OT_REGULAR_MULTIPLIER = 1.00;

    /** OT multiplier on the employee's configured rest day. */
    public const OT_REST_DAY_MULTIPLIER = 1.30;

    /** OT multiplier on any holiday (Regular or Special Non-Working). */
    public const OT_HOLIDAY_MULTIPLIER = 2.00;

    // ------------------------------------------------------------
    // HOLIDAY BASE-DAY PAY
    // ------------------------------------------------------------
    private const HOLIDAY_MULTIPLIERS = [
        'Regular Holiday' => [
            'worked'   => 2.00,
            'unworked' => 1.00,
        ],
        'Special Non-Working Holiday' => [
            'worked'   => 1.30,
            'unworked' => 0.00,
        ],
    ];

    public static function holidayWorkedMultiplier(string $holidayType): float
    {
        return self::HOLIDAY_MULTIPLIERS[$holidayType]['worked'] ?? 1.00;
    }

    public static function holidayUnworkedMultiplier(string $holidayType): float
    {
        return self::HOLIDAY_MULTIPLIERS[$holidayType]['unworked'] ?? 0.00;
    }

    /** All holiday types the system recognizes, in display order (used by the Settings form). */
    public static function holidayTypes(): array
    {
        return array_keys(self::HOLIDAY_MULTIPLIERS);
    }
}