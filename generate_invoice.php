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
echo "Invoice payload generated successfully\n";

$invoice_payload_json = json_encode($invoice_payload, JSON_THROW_ON_ERROR);
$invoice_queue_data = [
    'invoice_number' => $invoice_payload['invoice_number'],
    'payload' => $invoice_payload_json,
];

upsert_data($mysqli, 'invoice_queue', [$invoice_queue_data]);
echo "Invoice queued successfully\n";

$payload = [
    'payload' => $invoice_payload_json,
];

$max_attempts = 6;
$retry_delay_base = 2;
$attempt_errors = [];

for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    $result = curl_post_request($base_url, $api_key, $token, '/api/invoice/send', $payload, $curlConfig);

    if ($result['success']) {
        echo "Invoice sent successfully (Attempt $attempt)\n";
        break;
    } else {
        $error_info = [
            'attempt' => $attempt,
            'error' => $result['error'] ?? 'Unknown error',
            'http_status' => $result['http_status'] ?? null,
            'response' => isset($result['response']) ? substr(json_encode($result['response']), 0, 500) : 'No response'
        ];

        // This would be logged to error log for easier debugging
        $attempt_errors[] = $error_info;
    }

    // Avoid overwhelming the API with requests
    if ($attempt < $max_attempts) {
        echo "Sending invoice attempt (Attempt $attempt)\n";
        sleep((int) $retry_delay_base);
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

upsert_data($mysqli, 'invoice_queue', [$invoice_queue_data], true);

$mysqli->close();

echo "Invoice status set to failed\n";
exit(0);