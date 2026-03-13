USE bridge_one;

CREATE TABLE invoice_sequence (
    year INT PRIMARY KEY,
    last_invoice_number INT NOT NULL,
    last_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO invoice_sequence (year, last_invoice_number) VALUES (2026, 1);