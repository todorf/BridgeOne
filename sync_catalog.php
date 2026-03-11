<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
$config = require __DIR__ . '/config/config.php';
$curlConifg = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];
$api_key = $config['api_key'];

$rooms = sync_rooms($base_url, $token, $api_key, $curlConifg);
$rate_plans = sync_rate_plans($base_url, $token, $api_key, $curlConifg);

// Generate slugs
$rooms['rooms'] = generate_slugs($rooms['rooms'], 'HS', 'id_rooms', 'name');
$rate_plans = generate_slugs($rate_plans, 'RP', 'id_pricing_plans', 'first_meal');

// Insert data into database
$mysqli = db_connection();
insert_data($mysqli, 'rooms', $rooms['rooms'], true);
insert_data($mysqli, 'rate_plans', $rate_plans, true);

$mysqli->close();

echo "Sync completed successfully";
exit(0);