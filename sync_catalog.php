<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
$config = require __DIR__ . '/config/config.php';
$curlConfig = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];
$api_key = $config['api_key'];

$sync_data = [
    'id_properties' => 93,
    'dfrom' => '2025-02-01',
    'dto' => '2025-02-20',
    "id_room_types" => 170,
    "id_pricing_plans" => 370
];

$result = sync_rooms($base_url, $token, $api_key, $curlConfig, $sync_data);
if (!$result['success']) {
    echo "Error syncing rooms: " . $result['error'] . "\n";
    exit(1);
}
$rooms = $result['response'];

$result = sync_rate_plans($base_url, $token, $api_key, $curlConfig, $sync_data['id_properties']);
if (!$result['success']) {
    echo "Error syncing rate plans: " . $result['error'] . "\n";
    exit(1);
}
$rate_plans = $result['response'];

// Generate slugs
$rooms['rooms'] = generate_slugs($rooms['rooms'], 'HS', 'id_rooms', 'name');
$rate_plans = generate_slugs($rate_plans, 'RP', 'id_pricing_plans', 'first_meal');

// Insert data into database
$mysqli = db_connection();
upsert_data($mysqli, 'rooms', $rooms['rooms'], true);
upsert_data($mysqli, 'rate_plans', $rate_plans, true);

$mysqli->close();

echo "Sync completed successfully";
exit(0);