<?php
declare(strict_types=1);

/**
 * Stores a record of an authenticated user's important system action.
 * Passwords and password hashes must never be sent to this function.
 */
function record_audit(
    string $action,
    string $tableName,
    ?int $recordId = null,
    ?array $oldData = null,
    ?array $newData = null
): void {
    $user = current_user();

    if ($user === null) {
        return;
    }

    $statement = db()->prepare(
        'INSERT INTO audit_logs
            (user_id, action, table_name, record_id, old_data, new_data, ip_address)
         VALUES
            (:user_id, :action, :table_name, :record_id, :old_data, :new_data, :ip_address)'
    );

    $statement->execute([
        'user_id' => $user['user_id'],
        'action' => $action,
        'table_name' => $tableName,
        'record_id' => $recordId,
        'old_data' => $oldData === null ? null : json_encode($oldData, JSON_THROW_ON_ERROR),
        'new_data' => $newData === null ? null : json_encode($newData, JSON_THROW_ON_ERROR),
        'ip_address' => client_ip_address(),
    ]);
}
