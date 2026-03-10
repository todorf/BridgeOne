<?php
require_once __DIR__ . '/functions.php';
$config = require __DIR__ . '/config/config.php';
$curlConifg = require __DIR__ . '/config/curl.php';

$base_url = $config['api_base_url'];
$token = $config['api_token'];

$rooms = sync_rooms($base_url, $token, $curlConifg);
$rate_plans = sync_rate_plans($base_url, $token, $curlConifg);

print_r($rooms);
print_r($rate_plans);