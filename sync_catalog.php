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

print_r($rooms);
print_r($rate_plans);

// Insert data into database
insert_data('rooms', $rooms['rooms'], true);
insert_data('rate_plans', $rate_plans, true);
