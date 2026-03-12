use bridge_one;

CREATE TABLE invoice_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    date_created DATETIME DEFAULT CURRENT_TIMESTAMP
);