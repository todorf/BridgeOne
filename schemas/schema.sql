CREATE DATABASE bridge_one;
USE bridge_one;

CREATE TABLE rooms (
    id_rooms INT PRIMARY KEY,
    name VARCHAR(255),
    id_room_types INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE rate_plans (
    id_pricing_plans INT PRIMARY KEY,
    id_properties INT,
    name VARCHAR(255),
    id_board_names INT,
    id_policies INT,
    id_restriction_plans INT,
    id_boards INT,
    booking_engine TINYINT,
    description TEXT,
    type VARCHAR(50),
    copy_periods INT,
    variation_type INT,
    variation_amount DECIMAL(10,2),
    parent_id INT,
    first_meal VARCHAR(100),
    date_created DATETIME,
    prices_per_person_active TINYINT,
    locked_price TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);