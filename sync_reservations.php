<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
$config = require __DIR__ . '/config/config.php';
$curlConfig = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];
$api_key = $config['api_key'];

$options = getopt("", ["from:", "to:"]);
$fromDate = $options['from'] ?? null;
$toDate = $options['to'] ?? null;

$id_properties = 93;

if (!$fromDate || !$toDate) {
    echo "Please provide start and end date in YYYY-MM-DD format --from=YYYY-MM-DD --to=YYYY-MM-DD\n";
    exit(1);
}

$result = sync_reservations(
    $base_url,
    $token,
    $api_key,
    $curlConfig,
    $id_properties,
    $fromDate,
    $toDate,
);
if (!$result['success']) {
    echo "Error syncing reservations: " . $result['error'] . "\n";
    exit(1);
}
$reservations = $result['response'];

$result = sync_rate_plans($base_url, $token, $api_key, $curlConfig, $id_properties);
if (!$result['success']) {
    echo "Error syncing rate plans: " . $result['error'] . "\n";
    exit(1);
}
$pricing_plans = $result['response'];

// Generate lock_id
$reservations = generate_slugs($reservations, 'LOCK', 'id_reservations', 'date_arrival', 'lock_id');
$reservations = map_pricing_plans_to_reservations($pricing_plans, $reservations);
$reservations = generate_payload_hashes($reservations);

// Insert data into database
$mysqli = db_connection();
upsert_data($mysqli, 'reservations', $reservations, true);
upsert_data($mysqli, 'rate_plans', $pricing_plans, true);

foreach ($reservations as $reservation) {
    if (empty($reservation['rooms'])) {
        continue;
    }

    $rooms = array_map(function ($room) {
        return [
            'id_rooms' => $room['id_rooms'],
            'name' => $room['name'],
            'id_room_types' => $room['id_room_types'],
        ];
    }, $reservation['rooms']);

    upsert_data($mysqli, 'rooms', $rooms, true);
}

insert_related_data($mysqli, $reservations);

$mysqli->close();

echo "Sync completed successfully";
exit(0);