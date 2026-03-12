<?php

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_rooms(string $base_url, string $token, string $api_key, array $curlConifg): array
{
    $endpoint = '/api/room/data/available_rooms';
    $data = [
        "token" => $token,
        "id_properties" => "93",
        "key" => $api_key,
        "dfrom" => "2025-02-01",
        "dto" => "2025-02-20",
        "id_room_types" => 170,
        "id_pricing_plans" => 370
    ];

    return curl_post_request($base_url, $endpoint, $token, $data, $curlConifg);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_rate_plans(string $base_url, string $token, string $api_key, array $curlConifg): array
{
    $endpoint = '/api/pricingPlan/data/pricing_plans';
    $data = [
        "token" => $token,
        "id_properties" => "93",
        "key" => $api_key,
    ];

    return curl_post_request($base_url, $endpoint, $token, $data, $curlConifg);;
}

function sync_reservations(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConifg,
    string $fromDate,
    string $toDate,
): array {
    $endpoint = '/api/reservation/data/reservations';
    $data = [
        "token" => $token,
        "key" => $api_key,
        "id_properties" => 93,
        "rooms" => [],
        'channels' => [],
        'countries' => [],
        'order_by' => 'date_received',
        "dfrom" => $fromDate,
        "dto" => $toDate,
        'show_rooms' => 1,
    ];

    return curl_post_request($base_url, $endpoint, $token, $data, $curlConifg);
}

function get_reservation(
    string $base_url,
    string $token,
    string $api_key,
    array $curlConifg,
    string $reservation_id,
): array {
    $endpoint = '/api/reservation/data/reservation';
    $data = [
        "token" => $token,
        "key" => $api_key,
        "id_properties" => 93,
        "id_reservations" => $reservation_id,
    ];

    return curl_post_request($base_url, $endpoint, $token, $data, $curlConifg);
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function curl_post_request(
    string $base_url,
    string $endpoint,
    string $token,
    array $postData,
    array $curlConifg,
): array {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData, JSON_THROW_ON_ERROR));
    curl_setopt_array($ch, $curlConifg + [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception('Error:' . curl_error($ch));
        exit;
    }

    try{
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new Exception('Error:' . $e->getMessage());
    }

    curl_close($ch);

    return $data;
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

function generate_payload_hash(array $data): array
{
    foreach ($data as &$item) {
        if (empty($item)) {
            continue;
        }

        try {
            $item['payload_hash'] = hash('sha256', json_encode($item, JSON_THROW_ON_ERROR));
        } catch (JsonException $e) {
            throw new Exception("Error encoding JSON: " . $e->getMessage());
        }
    }
    unset($item);

    return $data;
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