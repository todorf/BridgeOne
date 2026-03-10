<?php
require_once __DIR__ . '/config/database.php';

/**
 * @throws Exception
 */
function execute_query(string $sql): mysqli_result|bool {
    $mysqli = db_connection();
    $result = $mysqli->query($sql);

    if ($result === false) {
        throw new Exception("Query error: " . $mysqli->error);
    }

    return $result;
}

/**
 * @throws Exception
 */
function insert_data(string $table, array $data, bool $onDuplicateKeyUpdate = false): void {
    if (empty($data)) {
        throw new Exception("Data is empty");
    }

    $columns = implode(', ', array_keys($data[0]));

    $valuesString = '';
    foreach ($data as $row) {
        $escapedValues = implode(', ', array_map(function($value) {
            return is_numeric($value) ? $value : "'" . $value . "'";
        }, array_values($row)));

        $valuesString .= "(" . $escapedValues . "),";
    }

    $valuesString = rtrim($valuesString, ',');

    if ($onDuplicateKeyUpdate) {
        $onDuplicateKeyUpdate = ' ON DUPLICATE KEY UPDATE ';
        foreach (array_keys($data[0]) as $column) {
            $onDuplicateKeyUpdate .= "`$column`=VALUES(`$column`), ";
        }

        $onDuplicateKeyUpdate = rtrim($onDuplicateKeyUpdate, ', ') . ';';
        $valuesString = rtrim($valuesString, ';');
    }

    $sql = "INSERT INTO `$table` ($columns) VALUES $valuesString" . ($onDuplicateKeyUpdate ? $onDuplicateKeyUpdate : ';');

    execute_query($sql);
}

