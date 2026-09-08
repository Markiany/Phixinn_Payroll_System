<?php

namespace App\Helpers;

class AuditLogger
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        $db = Database::connection();

        $stmt = $db->prepare(
            'INSERT INTO audit_log (user_id, action, entity_type, entity_id, before_value, after_value, ip_address)
             VALUES (:user_id, :action, :entity_type, :entity_id, :before_value, :after_value, :ip_address)'
        );

        $stmt->execute([
            'user_id'      => $_SESSION['user_id'] ?? null,
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'before_value' => $before !== null ? json_encode($before) : null,
            'after_value'  => $after !== null ? json_encode($after) : null,
            'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
}
