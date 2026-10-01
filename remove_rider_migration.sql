-- Run once to remove the rider feature and its account/location data.
DROP TABLE IF EXISTS order_live_locations;

SET @rider_fk = (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'rider_id'
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1
);
SET @drop_rider_fk = IF(@rider_fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE orders DROP FOREIGN KEY `', @rider_fk, '`'));
PREPARE remove_rider_fk FROM @drop_rider_fk;
EXECUTE remove_rider_fk;
DEALLOCATE PREPARE remove_rider_fk;

SET @has_rider_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'rider_id'
);
SET @clear_rider_assignment = IF(@has_rider_column > 0, 'UPDATE orders SET rider_id = NULL', 'SELECT 1');
PREPARE clear_rider_orders FROM @clear_rider_assignment;
EXECUTE clear_rider_orders;
DEALLOCATE PREPARE clear_rider_orders;

DELETE FROM users WHERE role = 'rider';
ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'seller') NOT NULL DEFAULT 'customer';

SET @drop_rider_column = IF(@has_rider_column > 0, 'ALTER TABLE orders DROP COLUMN rider_id', 'SELECT 1');
PREPARE remove_rider_column FROM @drop_rider_column;
EXECUTE remove_rider_column;
DEALLOCATE PREPARE remove_rider_column;
