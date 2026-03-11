<?php
require_once __DIR__ . '/config/database.php';

/**
 * Validates identifier (table/column name) to prevent SQL injection.
 */
function validate_identifier(string $name): void
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
        throw new Exception("Invalid identifier: $name");
    }
}

function normalize_bind_value(mixed $value): mixed
{
    if ($value === '' || $value === null) {
        return 'NULL';
    }

    if (is_array($value)) {
        return json_encode($value);
    }

    if (is_string($value)) {
        try {
            $dt = new DateTime($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // Not a valid date string, fall through
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