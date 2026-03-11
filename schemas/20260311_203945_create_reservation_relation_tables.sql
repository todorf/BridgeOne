USE bridge_one;

CREATE TABLE reservations_rooms (
    id_reservations INT,
    id_rooms INT,
    PRIMARY KEY (id_reservations, id_rooms),
    FOREIGN KEY (id_reservations) REFERENCES reservations(id_reservations),
    FOREIGN KEY (id_rooms) REFERENCES rooms(id_rooms)
);

CREATE TABLE reservations_pricing_plans (
    id_reservations INT,
    id_pricing_plans INT,
    PRIMARY KEY (id_reservations, id_pricing_plans),
    FOREIGN KEY (id_reservations) REFERENCES reservations(id_reservations),
    FOREIGN KEY (id_pricing_plans) REFERENCES rate_plans(id_pricing_plans)
);