<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/enums/EventType.php';
require_once __DIR__ . '/enums/WebhookOperations.php';

/**
 * Validates identifier (table/column name) to prevent SQL injection.
 */
function validate_identifier(string $name): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new Exception("Invalid identifier: $name");
    }
}

/**
 * @throws JsonException
 */
function normalize_bind_value(mixed $value): mixed
{
    if ($value === '' || $value === null) {
        return 'NULL';
    }

    if (is_array($value)) {
        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException("Error encoding JSON: " . $e->getMessage());
        }
    }

    return $value;
}

/**
 * @throws Exception
 */
function insert_data(
    mysqli $mysqli,
    string $table,
    array $data,
    bool $onDuplicateKeyUpdate = false
): void {
    if (empty($data)) {
        throw new Exception("Data is empty");
    }

    validate_identifier($table);

    $first_key = array_key_first($data);
    $columnNames = array_keys($data[$first_key]);

    foreach ($columnNames as $col) {
        validate_identifier($col);
    }

    $columns = implode(', ', array_map(fn($c) => "`$c`", $columnNames));
    $placeholdersPerRow = '(' . implode(', ', array_fill(0, count($columnNames), '?')) . ')';
    $placeholders = implode(', ', array_fill(0, count($data), $placeholdersPerRow));

    $params = [];
    foreach ($data as $row) {
        foreach ($columnNames as $col) {
            $params[] = normalize_bind_value($row[$col] ?? null);
        }
    }

    $sql = "INSERT INTO `$table` ($columns) VALUES $placeholders";

    if ($onDuplicateKeyUpdate) {
        $updateParts = [];
        foreach ($columnNames as $col) {
            $updateParts[] = "`$col`=VALUES(`$col`)";
        }
        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
    }

    $sql .= ';';

    $mysqli->autocommit(FALSE);

    try {
        $mysqli->begin_transaction();
        $mysqli->execute_query($sql, $params);
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    }

    $mysqli->autocommit(TRUE);
}

/**
 * @throws Exception
 */
function insert_related_data(mysqli $mysqli, array $data): void
{
    if (empty($data)) {
        throw new Exception("Data is empty");
    }

    $rooms = [];
    $pricing_plans = [];

    foreach ($data as $row) {
        foreach ($row['rooms'] as $room) {
            $rooms[] = [
                'id_reservations' => $row['id_reservations'],
                'id_rooms' => $room['id_rooms'],
            ];
        }

        foreach ($row['pricing_plan'] as $pricing_plan) {
            $pricing_plans[] = [
                'id_reservations' => $row['id_reservations'],
                'id_pricing_plans' => $pricing_plan['id_pricing_plans'],
            ];
        }
    }

    insert_data($mysqli, 'reservations_rooms', $rooms, true);
    insert_data($mysqli, 'reservations_pricing_plans', $pricing_plans, true);
}

function check_if_exists(mysqli $mysqli, string $table, string $column, string $value): bool
{
    $sql = "SELECT EXISTS(SELECT 1 FROM `$table` WHERE `$column` = ?)";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $value);
    $stmt->execute();

    $result = $stmt->get_result();
    return $result->fetch_row()[0] > 0;
}

function get_rows_by_column(
    mysqli $mysqli,
    string $table,
    string $column,
    string $value,
    string $row = '*'
): array {
    $sql = "SELECT $row FROM `$table` WHERE `$column` = ?";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $value);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function log_event(
    mysqli $mysqli,
    EventType|WebhookOperations $event_type,
    array $event_data,
    array $old_data = [],
    array $new_data = []
): void {
    $sql = "INSERT INTO audit_log (event_type, event_data, old_data, new_data, payload_hash) VALUES (?, ?, ?, ?, ?)";

    $event_type_value = $event_type->value;
    $event_data_json = !empty($event_data) ? json_encode($event_data, JSON_THROW_ON_ERROR) : null;
    $old_data_json = !empty($old_data) ? json_encode($old_data, JSON_THROW_ON_ERROR) : null;
    $new_data_json = !empty($new_data) ? json_encode($new_data, JSON_THROW_ON_ERROR) : null;
    $payload_hash = !empty($event_data_json) ? hash('sha256', $event_data_json) : null;

    if (check_if_exists($mysqli, 'audit_log', 'payload_hash', $payload_hash)) {
        return;
    }

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sssss', $event_type_value, $event_data_json, $old_data_json, $new_data_json, $payload_hash);
    $stmt->execute();

    $stmt->close();
}

function update_invoice_sequence(mysqli $mysqli, int $year, int $last_invoice_number): void
{
    $mysqli->begin_transaction();

    // Lock the row for update
    $stmt = $mysqli->prepare("SELECT `last_invoice_number` FROM `invoice_sequence` WHERE `year` = ? FOR UPDATE");
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $stmt->close();

    // Update invoice_sequence
    $update = $mysqli->prepare("UPDATE `invoice_sequence` SET `last_invoice_number` = ? WHERE `year` = ?");
    $update->bind_param('ii', $last_invoice_number, $year);
    $update->execute();

    $mysqli->commit();
}
