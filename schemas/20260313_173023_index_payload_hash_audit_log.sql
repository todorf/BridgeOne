USE bridge_one;

CREATE INDEX `idx_audit_log_payload_hash` ON `audit_log` (`payload_hash`);
