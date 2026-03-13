<?php
require_once __DIR__ . '/enums/EventType.php';
require_once __DIR__ . '/enums/WebhookOperations.php';

/**
 * Appends one line to the log file.
 *
 * @param string $log_file_path Absolute path to the log file
 */
function append_event_to_log(
    string $log_file_path,
    EventType|WebhookOperations|DatabaseOperations $event_type,
    array $event_data,
    ?string $description = null
): void {
    $timestamp = date('Y-m-d H:i:s');
    $type = $event_type->value;
    $desc = $description ?? get_event_description($event_type);
    $id = get_reservation_or_external_id($event_data);

    $line = implode("\t", [
        $timestamp,
        $type,
        $desc,
        $id,
    ]) . "\n";

    $dir = dirname($log_file_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($log_file_path, $line, FILE_APPEND | LOCK_EX);
}

function get_event_description(EventType|WebhookOperations $event_type): string
{
    return match ($event_type) {
        WebhookOperations::RESERVATION_INSERT => 'New reservation',
        WebhookOperations::RESERVATION_UPDATE => 'Reservation update',
        WebhookOperations::RESERVATION_CANCEL => 'Reservation cancelled',
        EventType::UPDATE => 'Reservation update',
        EventType::CANCEL => 'Reservation cancelled',
        default => $event_type->value,
    };
}

/**
 * Extracts reservation ID or external ID from event_data.
 */
function get_reservation_or_external_id(array $event_data): string
{
    $flat = isset($event_data['data']) && is_array($event_data['data'])
        ? array_merge($event_data, $event_data['data'])
        : $event_data;

    if (!empty($flat['id_reservations'])) {
        return (string) $flat['id_reservations'];
    }

    if (!empty($flat['reservation_id'])) {
        return (string) $flat['reservation_id'];
    }

    if (!empty($flat['external_id'])) {
        return (string) $flat['external_id'];
    }

    return '';
}
