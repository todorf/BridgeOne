<?php
require_once __DIR__ . '/functions.php';
$config = require __DIR__ . '/config/config.php';
$curlConifg = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];
$api_key = $config['api_key'];
$options = getopt("", ["from:", "to:"]);

$fromDate = $options['from'] ?? null;
$toDate = $options['to'] ?? null;

if (!$fromDate || !$toDate) {
    echo "Please provide start and end date in YYYY-MM-DD format --from=YYYY-MM-DD --to=YYYY-MM-DD\n";
    exit(1);
}

$reservations = sync_reservations($base_url, $token, $api_key, $curlConifg, $fromDate, $toDate);

$pricing_plans = sync_rate_plans($base_url, $token, $api_key, $curlConifg);
$reservations = map_pricing_plans_to_reservations($pricing_plans, $reservations);

print_r($reservations);


