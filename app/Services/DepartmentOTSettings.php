<?php

namespace App\Services;

use App\Helpers\Database;
use PDO;

class DepartmentOTSettings
{
    private const TABLE = 'department_ot_settings';

    public static function ensureTable(?PDO $db = null): void
    {
        $db = $db ?? Database::connection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS department_ot_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                department_name VARCHAR(100) NOT NULL,
                department_code VARCHAR(50) NULL,
                allow_early_ot TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_department_name (department_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function syncFromEmployees(?PDO $db = null): array
    {
        $db = $db ?? Database::connection();
        self::ensureTable($db);

        $departments = $db->query("
            SELECT DISTINCT TRIM(department) AS department_name
            FROM employees
            WHERE department IS NOT NULL
              AND TRIM(department) <> ''
            ORDER BY department_name ASC
        ")->fetchAll(PDO::FETCH_COLUMN);

        $knownCodes = [
            'HR Manager' => 'HRMANAGER',
            'Secretary' => 'SEC',
            'Utility' => 'UT',
            'Warehouse Man' => 'WM',
            'Belt' => 'BE',
            'IT' => 'IT',
            'RTS' => 'RTS',
            'Stock' => 'SG',
            'Packer' => 'PA',
            'Picker' => 'PI',
            'Phixinn' => 'DEFAULT',
        ];

        $stmt = $db->prepare("
            INSERT INTO department_ot_settings
                (department_name, department_code, allow_early_ot, created_at, updated_at)
            VALUES
                (:name, :code, :allow, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                department_code = COALESCE(NULLIF(VALUES(department_code), ''), department_code),
                updated_at = NOW()
        ");

        foreach ($departments as $department) {
            $department = trim((string) $department);

            if ($department === '') {
                continue;
            }

            // New departments are disabled by default. Existing saved settings
            // are preserved by the ON DUPLICATE KEY UPDATE clause above.
            $allowEarlyOT = false;

            $stmt->execute([
                ':name' => $department,
                ':code' => $knownCodes[$department] ?? null,
                ':allow' => $allowEarlyOT ? 1 : 0,
            ]);
        }

        return self::all($db);
    }

    public static function all(?PDO $db = null): array
    {
        $db = $db ?? Database::connection();
        self::ensureTable($db);

        return $db->query("
            SELECT id, department_name, department_code, allow_early_ot
            FROM department_ot_settings
            ORDER BY department_name ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function isEarlyOTAllowed(string $department, ?PDO $db = null): bool
    {
        $db = $db ?? Database::connection();
        self::ensureTable($db);

        $department = trim($department);

        if ($department === '') {
            return false;
        }

        $stmt = $db->prepare("
            SELECT allow_early_ot
            FROM department_ot_settings
            WHERE LOWER(TRIM(department_name)) = LOWER(TRIM(:department))
            LIMIT 1
        ");

        $stmt->execute([':department' => $department]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return (int) $row['allow_early_ot'] === 1;
    }

    public static function update(
        int $id,
        string $departmentName,
        ?string $departmentCode,
        bool $allowEarlyOT,
        ?PDO $db = null
    ): void {
        $db = $db ?? Database::connection();
        self::ensureTable($db);

        $stmt = $db->prepare("
            UPDATE department_ot_settings
            SET
                department_name = :name,
                department_code = :code,
                allow_early_ot = :allow,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':name' => trim($departmentName),
            ':code' => $departmentCode !== null && trim($departmentCode) !== ''
                ? trim($departmentCode)
                : null,
            ':allow' => $allowEarlyOT ? 1 : 0,
            ':id' => $id,
        ]);
    }
    /**
     * Save Morning / Early OT permissions for all synced departments at once.
     * Only department IDs that are present in the current settings table can be
     * enabled. All other synced departments are explicitly disabled.
     */
    public static function updateBulk(array $allowedIds, ?PDO $db = null): void
    {
        $db = $db ?? Database::connection();
        self::ensureTable($db);

        $allowedIds = array_values(array_unique(array_filter(
            array_map('intval', $allowedIds),
            static fn (int $id): bool => $id > 0
        )));

        $rows = self::all($db);

        $stmt = $db->prepare("
            UPDATE department_ot_settings
            SET allow_early_ot = :allow, updated_at = NOW()
            WHERE id = :id
        ");

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $stmt->execute([
                ':allow' => in_array($id, $allowedIds, true) ? 1 : 0,
                ':id' => $id,
            ]);
        }
    }

}
