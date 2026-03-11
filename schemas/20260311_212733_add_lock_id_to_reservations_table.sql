USE bridge_one;

ALTER TABLE reservations
    ADD COLUMN `lock_id` VARCHAR(255) NULL AFTER `id_reservations`;