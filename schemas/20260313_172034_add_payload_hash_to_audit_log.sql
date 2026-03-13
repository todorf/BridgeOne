USE bridge_one;

ALTER TABLE `audit_log`
    ADD COLUMN `payload_hash` CHAR(64) AFTER `new_data`;