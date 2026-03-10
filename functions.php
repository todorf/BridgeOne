<?php

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function sync_rooms(string $base_url, string $token, array $curlConifg): array
{
    $endpoint = '/api/room/data/available_rooms';
    $data = [
        "token" => $token,
        "id_properties" => "93",
        "key" => "574eb98879eb28d03b21e8a5c1a21259a9a5c85f",
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
function sync_rate_plans(string $base_url, string $token, array $curlConifg): array
{
    $endpoint = '/api/pricingPlan/data/pricing_plans';
    $data = [
        "token" => $token,
        "id_properties" => "93",
        "key" => "574eb98879eb28d03b21e8a5c1a21259a9a5c85f",
    ];

    return curl_post_request($base_url, $endpoint, $token, $data, $curlConifg);;
}

/**
 * @throws Exception
 * @throws JsonException
 * @return array
 */
function curl_post_request(string $base_url, string $endpoint, string $token, array $postData, array $curlConifg): array
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $base_url . $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
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

function generate_slugs(array $data, string $prefix, string $id_column, string $suffix_column): array
{
    foreach ($data as &$item) {
        $id = $item[$id_column] ?? '';
        $suffix = $item[$suffix_column] ?? '';
        $item['slug'] = generate_slug($prefix, $id, $suffix);
    }

    return $data;
}

function generate_slug(string $prefix, string $id, string $suffix): string
{
    return  $prefix . '-' . $id . '-' . $suffix;
}