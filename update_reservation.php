<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/enums/EventType.php';
$config = require __DIR__ . '/config/config.php';
$curlConfig = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];
$api_key = $config['api_key'];

$options = getopt("", ["reservation_id:"]);
$reservation_id = $options['reservation_id'] ?? null;

if (!$reservation_id) {
    echo "Please provide a reservation ID --reservation_id=1234567890\n";
    exit(1);
}

$result = fetch_reservation($base_url, $token, $api_key, $curlConfig, $reservation_id);
if (!$result['success']) {
    echo "Error syncing reservations: " . $result['error'] . "\n";
    exit(1);
}
$reservation = $result['response'];

$mysqli = db_connection();
$exists = check_if_exists($mysqli, 'reservations', 'id_reservations', $reservation_id);

if (!$exists) {
    echo "Fetched reservation not found in local database\n";
    exit(1);
}

$reservation_db = get_rows_by_column($mysqli, 'reservations', 'id_reservations', $reservation_id);
if (empty($reservation_db)) {
    echo "Reservation not found in local database\n";
    exit(1);
}

// Compare payload hash
if (is_reservation_modified($reservation, $reservation_db)) {
    // insert will update the reservation if it exists
    upsert_data($mysqli, 'reservations', [$reservation], true);

    // Log update event
    // TODO: Save only changed fields in old_data and new_data columns in audit_log table
    log_event($mysqli, EventType::UPDATE, ['reservation_id' => $reservation_id]);

    if ($reservation['status'] === 'canceled') {
        log_event($mysqli, EventType::CANCEL, ['reservation_id' => $reservation_id]);
    }

    echo "Reservation updated successfully\n";
    $mysqli->close();
    exit(0);
}

$mysqli->close();

echo "Reservation was not modified\n";
exit(0);