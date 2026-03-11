USE bridge_one;

ALTER TABLE `rooms`
    ADD COLUMN `slug` VARCHAR(255) NULL AFTER `id_room_types`;

ALTER TABLE `rate_plans`
    ADD COLUMN `slug` VARCHAR(255) NULL AFTER `locked_price`;