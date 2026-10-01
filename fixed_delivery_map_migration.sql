-- Run once on an existing Walk & Wear database to use fixed point-to-point routes.
USE walk_and_wear;

ALTER TABLE orders
    MODIFY status ENUM('pending','processing','shipped','completed','cancelled','confirmed','preparing','ready_for_delivery','out_for_delivery','delivered') NOT NULL DEFAULT 'pending';

UPDATE orders
SET status = CASE status
    WHEN 'processing' THEN 'preparing'
    WHEN 'shipped' THEN 'out_for_delivery'
    WHEN 'completed' THEN 'delivered'
    ELSE status
END;

ALTER TABLE orders
    MODIFY status ENUM('pending','confirmed','preparing','ready_for_delivery','out_for_delivery','delivered','cancelled') NOT NULL DEFAULT 'pending';

DROP TABLE IF EXISTS order_party_locations;

INSERT INTO store_map_location (store_id, latitude, longitude, accuracy_m, updated_by)
SELECT 1, 17.0236651, 121.6307742, NULL, user_id
FROM users
WHERE role = 'admin'
ORDER BY user_id
LIMIT 1
ON DUPLICATE KEY UPDATE store_id = store_map_location.store_id;