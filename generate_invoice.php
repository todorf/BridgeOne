<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/enums/InvoiceStatus.php';
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

$mysqli = db_connection();
$reservation = get_rows_by_column($mysqli, 'reservations', 'id_reservations', $reservation_id);
if (empty($reservation)) {
    echo "Reservation not found in local database\n";
    exit(1);
}

$invoice_payload = generate_invoice_payload($mysqli, $reservation[0]);
$invoice_payload_json = json_encode($invoice_payload, JSON_THROW_ON_ERROR);
$invoice_queue_data = [
    'invoice_number' => $invoice_payload['invoice_number'],
    'payload' => $invoice_payload_json,
];

insert_data($mysqli, 'invoice_queue', [$invoice_queue_data]);
$payload = [
    'payload' => $invoice_payload_json,
];

// Try to send invoice first time, then retry 5 times
for ($i = 0; $i < 6; $i++) {
    $result = curl_post_request($base_url, $api_key, $token, '/api/invoice/send', $payload, $curlConfig);
    if ($result['success']) {
        echo "Invoice sent successfully\n";
        break;
    }
}

// Failed, set invoice status to failed
$invoice_queue = get_rows_by_column($mysqli, 'invoice_queue', 'invoice_number', $invoice_payload['invoice_number'], 'id')[0];
$invoice_queue_data = [
    'id' => $invoice_queue['id'],
    'invoice_number' => $invoice_payload['invoice_number'],
    'payload' => $invoice_payload_json,
    'status' => InvoiceStatus::FAILED->value,
];

insert_data($mysqli, 'invoice_queue', [$invoice_queue_data], true);

$mysqli->close();

echo "Invoice status set to failed\n";
exit(0);