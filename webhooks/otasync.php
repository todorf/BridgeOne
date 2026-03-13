<?php
require_once __DIR__ . '/../enums/WebhookOperations.php';
require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests are allowed.']);
    exit;
}

// Get the raw POST body
$raw_input = file_get_contents('php://input');

// Try to decode JSON payload
try {
    $data = json_decode($raw_input, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// JSON payload
$event_type = $data['type'] ?? null;
$event_data = $data['data'] ?? null;

if (empty($event_type) || empty($event_data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event type or data']);
    exit;
}

// Validate event type
if (!WebhookOperations::isValidOperation($event_type)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event type']);
    exit;
}

$event_type = WebhookOperations::from($event_type);
$payload_to_hash = ['type' => $event_type->value, 'data' => $event_data];

$mysqli = db_connection();
log_event($mysqli, $event_type, $data, [], [], generate_hash($payload_to_hash));

$result = handle_event($mysqli, $event_type, $event_data);
if (isset($result['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $result['error']]);
    exit;
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'received' => $data,
]);