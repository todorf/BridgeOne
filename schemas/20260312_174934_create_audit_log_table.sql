use bridge_one;

CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(30),
    event_data JSON,
    old_data JSON,
    new_data JSON,
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP
);