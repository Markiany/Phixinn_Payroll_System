<?php

namespace App\Services;

/**
 * PayrollRules
 * ============================================================
 * ONE centralized place for every payroll constant: late /
 * undertime deduction brackets, overtime thresholds and
 * multipliers, and holiday pay multipliers per holiday type.
 *
 * Nothing else in the app should hardcode these numbers. If a
 * rate ever needs to change, it changes here once. Every
 * consumer (Salary Calculation page, Excel export, individual
 * payslip, Payslips ZIP) already goes through the single
 * calculation engine in SalaryCalculationController, so a
 * change here is picked up everywhere automatically - there is
 * no second copy of these numbers anywhere to fall out of sync.
 * ============================================================
 */
class PayrollRules
{
    /** Standard working hours in a regular day. Hourly Rate = Daily Rate / this. */
    public const REGULAR_HOURS_PER_DAY = 8.0;

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
    // Multiplier applied to the employee's Daily Rate for the day,
    // depending on the holiday's type and whether they worked it.
    // Standard Philippine holiday-pay conventions:
    //   Regular Holiday:              worked = 200%, unworked = 100% (if eligible)
    //   Special Non-Working Holiday:  worked = 130%, unworked =   0% (no work, no pay)
    // Adjust here if your company's policy differs - nothing else
    // in the app needs to change.
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