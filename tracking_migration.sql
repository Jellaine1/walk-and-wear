-- Run once on an existing Walk & Wear database to enable parcel maps.

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS carrier_name VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS tracking_number VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS delivery_latitude DECIMAL(10,7) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS delivery_longitude DECIMAL(10,7) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS store_map_location (
    store_id TINYINT UNSIGNED PRIMARY KEY,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    accuracy_m DECIMAL(8,2) DEFAULT NULL,
    updated_by INT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_store_map_location_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE CASCADE
);
