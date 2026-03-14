<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/enums/WebhookOperations.php';
require_once __DIR__ . '/enums/EventType.php';
require_once __DIR__ . '/logger.php';

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_rooms(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConfig,
    array $sync_data
): array {
    $endpoint = '/api/room/data/available_rooms';
    $data = [
        "token" => $token,
        "id_properties" => $sync_data['id_properties'] ?? null,
        "key" => $api_key,
        "dfrom" => $sync_data['dfrom'] ?? null,
        "dto" => $sync_data['dto'] ?? null,
        "id_room_types" => $sync_data['id_room_types'] ?? null,
        "id_pricing_plans" => $sync_data['id_pricing_plans'] ?? null
    ];

    return curl_post_request($base_url, $api_key, $token, $endpoint, $data, $curlConfig);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_rate_plans(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConfig,
    int $id_properties
): array
{
    $endpoint = '/api/pricingPlan/data/pricing_plans';
    $data = [
        "token" => $token,
        "id_properties" => $id_properties,
        "key" => $api_key,
    ];

    return curl_post_request($base_url, $api_key, $token, $endpoint, $data, $curlConfig);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_reservations(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConfig,
    int $id_properties,
    string $fromDate,
    string $toDate,
): array {
    $endpoint = '/api/reservation/data/reservations';
    $data = [
        "token" => $token,
        "key" => $api_key,
        "id_properties" => $id_properties,
        "rooms" => [],
        'channels' => [],
        'countries' => [],
        'order_by' => 'date_received',
        "dfrom" => $fromDate,
        "dto" => $toDate,
        'show_rooms' => 1,
    ];

    return curl_post_request($base_url, $api_key, $token, $endpoint, $data, $curlConfig);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function fetch_reservation(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConfig,
    string $reservation_id,
    int $id_properties,
): array {
    $endpoint = '/api/reservation/data/reservation';
    $data = [
        "token" => $token,
        "key" => $api_key,
        "id_properties" => $id_properties,
        "id_reservations" => $reservation_id,
    ];

    return curl_post_request($base_url, $api_key, $token, $endpoint, $data, $curlConfig);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function curl_post_request(
    string $base_url,
    string $api_key,
    string $token,
    string $endpoint,
    array $postData,
    array $curlConfig,
): array {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData, JSON_THROW_ON_ERROR));
    curl_setopt_array($ch, $curlConfig + [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'X-API-KEY: ' . $api_key,
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMsg = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'response' => null,
            'error' => $errorMsg,
        ];
    }

    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        curl_close($ch);
        return [
            'success' => false,
            'response' => null,
            'error' => 'JSON decode error: ' . $e->getMessage(),
        ];
    }

    curl_close($ch);

    return [
        'success' => true,
        'response' => $data,
        'error' => null,
    ];
}

function generate_slugs(
    array $data,
    string $prefix,
    string $id_column,
    string $suffix_column,
    string $slug_name = 'slug'
): array {
    foreach ($data as &$item) {
        $id = $item[$id_column] ?? '';
        $suffix = $item[$suffix_column] ?? '';

        if (empty($id) || empty($suffix)) {
            continue;
        }

        $item[$slug_name] = generate_slug($prefix, $id, $suffix);
    }

    return $data;
}

function generate_slug(string $prefix, string $id, string $suffix): string
{
    return  $prefix . '-' . $id . '-' . $suffix;
}

function map_pricing_plans_to_reservations(array $pricing_plans, array $reservations): array
{
    $pricing_plans_ids = [];

    foreach ($pricing_plans as $pricing_plan) {
        $pricing_plans_ids[$pricing_plan['id_pricing_plans']] = $pricing_plan;
    }

    $reservations = array_filter($reservations, function ($reservation) use ($pricing_plans_ids) {
        return (
            !empty($reservation['id_pricing_plans']) &&
            array_key_exists($reservation['id_pricing_plans'], $pricing_plans_ids)
        );
    });

    foreach ($reservations as &$reservation) {
        $reservation['pricing_plan'][] = $pricing_plans_ids[$reservation['id_pricing_plans']];
    }
    unset($reservation);

    return $reservations;
}

/**
 * @throws JsonException
 * @return array
 */
function generate_payload_hashes(array $data): array
{
    foreach ($data as &$item) {
        if (empty($item)) {
            continue;
        }

        try {
            $item['payload_hash'] = generate_hash($item);
        } catch (JsonException $e) {
            throw new JsonException("Error encoding JSON: " . $e->getMessage());
        }
    }
    unset($item);

    return $data;
}

function generate_hash(array $data, string $hash_algo = 'sha256'): string
{
    return hash($hash_algo, json_encode($data, JSON_THROW_ON_ERROR));
}

function is_reservation_modified(array $reservation, array $reservation_db): bool
{
    if (
        empty($reservation['id_reservations']) ||
        empty($reservation_db['id_reservations'])
    ) {
        return false;
    }

    return (
        $reservation['id_reservations'] === $reservation_db['id_reservations'] &&
        $reservation['payload_hash'] !== $reservation_db['payload_hash']
    );
}

/**
 * @throws Exception
 * @return array
 */
function generate_invoice_payload(mysqli $mysqli, array $reservation): array
{
    $year = date('Y');
    $invoice_sequence = get_rows_by_column($mysqli, 'invoice_sequence', 'year', $year, 'last_invoice_number');
    if (empty($invoice_sequence)) {
        throw new Exception("Invoice sequence not found for year: " . $year);
    }

    $invoice_sequence_number = $invoice_sequence[0]['last_invoice_number'] + 1;
    update_invoice_sequence($mysqli, $year, $invoice_sequence_number);

    $invoice_number = 'HS-INV-' . $year . '-' . str_pad($invoice_sequence_number, 6, '0', STR_PAD_LEFT);
    $line_items = [
        'custom_price' => $reservation['custom_price'],
        'total_price' => $reservation['total_price'],
        'rooms_price' => $reservation['rooms_price'],
        'extras_price' => $reservation['extras_price'],
        'city_tax_price' => $reservation['city_tax_price'],
        'insurance_price' => $reservation['insurance_price'],
        'board_price' => $reservation['board_price'],
        'conference_halls_price' => $reservation['conference_halls_price'],
        'spas_price' => $reservation['spas_price'],
        'custom_tax_price' => $reservation['custom_tax_price'],
    ];

    return [
        'invoice_number' => $invoice_number,
        'reservation_id' => $reservation['id_reservations'],
        'guest_name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
        'arrival_date' => $reservation['date_arrival'],
        'departure_date' => $reservation['date_departure'],
        'line_items' => json_encode($line_items, JSON_THROW_ON_ERROR),
        'total_amount' => $reservation['total_price'],
        'currency' => $reservation['currency'],
    ];
}

function handle_event(mysqli $mysqli, WebhookOperations $event_type, array $event_data): array
{
    switch ($event_type) {
        case WebhookOperations::RESERVATION_INSERT:
            return reservation_insert($mysqli, $event_data);
        case WebhookOperations::RESERVATION_UPDATE:
            return reservation_update($mysqli, $event_data);
        case WebhookOperations::RESERVATION_CANCEL:
            return reservation_cancel($mysqli, $event_data);
        default:
            return ['error' => 'Invalid event type: ' . $event_type];
    }
}

function reservation_insert(mysqli $mysqli, array $event_data): array
{
    try {
        if (!isset($event_data['id_reservations'])) {
            return ['error' => 'Reservation ID is required'];
        }

        if (check_if_exists($mysqli, 'reservations', 'id_reservations', $event_data['id_reservations'])) {
            return ['error' => 'Reservation already exists'];
        }

        upsert_data($mysqli, 'reservations', [$event_data]);
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }

    return ['success' => true];
}

function reservation_update(mysqli $mysqli, array $event_data): array
{
    try {
        if (!isset($event_data['id_reservations'])) {
            return ['error' => 'Reservation ID is required'];
        }

        // If reservation is canceled, log the event
        if (isset($event_data['status']) && $event_data['status'] === 'canceled') {
            log_event($mysqli, EventType::CANCEL, ['reservation_id' => $event_data['id_reservations']]);
        }

        // Update reservation
        upsert_data($mysqli, 'reservations', [$event_data], true);
    } catch (Throwable $e) {
        // We would also save this to log file
        return ['error' => $e->getMessage()];
    }

    return ['success' => true];
}

function reservation_cancel(mysqli $mysqli, array $event_data): array
{
    try {
        if (!isset($event_data['id_reservations'])) {
            return ['error' => 'Reservation ID is required'];
        }

        if (!check_if_exists($mysqli, 'reservations', 'id_reservations', $event_data['id_reservations'])) {
            return ['error' => 'Reservation not found'];
        }

        // Update reservation status to canceled
        $event_data['status'] = 'canceled';
        upsert_data($mysqli, 'reservations', [$event_data], true);

        // Reservation cancelled, log the event
        log_event($mysqli, EventType::CANCEL, ['reservation_id' => $event_data['id_reservations']]);
    } catch (Throwable $e) {
        return ['error' => $e->getMessage()];
    }

    return ['success' => true];
}