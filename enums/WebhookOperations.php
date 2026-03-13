<?php

enum WebhookOperations: string
{
    case RESERVATION_INSERT = 'reservation_insert';
    case RESERVATION_UPDATE = 'reservation_update';
    case RESERVATION_CANCEL = 'reservation_cancel';

    public static function isValidOperation(string $operation): bool
    {
        return in_array($operation, array_map(fn($op) => $op->value, self::cases()), true);
    }
}