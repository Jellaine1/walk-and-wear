-- Import products from the supplied "walk wear 2" folder.
-- List prices are in PHP. Stock and EU size ranges are school-project defaults.
SET NAMES utf8mb4;

USE walk_and_wear;

UPDATE products
SET name = 'Revel RD™ Chelsea Women''s Waterproof Boot',
        image = 'images/catalog/Revel RD™ Chelsea Women''s Waterproof Boot.avif'
WHERE name LIKE 'Revel RD%'
    AND image LIKE 'images/catalog/Revel RD%';

INSERT INTO categories (name)
SELECT 'Sports & Court'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE name = 'Sports & Court');

INSERT INTO products (category_id, name, description, price, stock, image, brand, size_available)
SELECT category.category_id, source_product.name, source_product.description,
       source_product.price, 20, source_product.image, source_product.brand,
       source_product.size_available
FROM (
    SELECT 'Running' AS category_name, 'Cloudultra Pro' AS name, 'On trail-running shoe.' AS description, 15490.00 AS price, 'images/catalog/Cloudultra Pro.avif' AS image, 'On' AS brand, '38,39,40,41,42,43' AS size_available
    UNION ALL SELECT 'Sandals', 'Dizzle Heeled Slides', 'CLN low-heel slide sandals.', 1499.00, 'images/catalog/Dizzle Heeled Slides.jpg', 'CLN', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Sandals', 'Gelline Heeled Sandals', 'CLN square-toe heeled sandals.', 1499.00, 'images/catalog/Gelline Heeled Sandals.webp', 'CLN', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Running', 'Glycerin GTS 23', 'Brooks supportive road-running shoe.', 9395.00, 'images/catalog/Glycerin GTS 23.webp', 'Brooks', '38,39,40,41,42,43'
    UNION ALL SELECT 'Casual', 'Reebok Unisex Hammer Street', 'Reebok unisex lifestyle sneaker.', 4595.00, 'images/catalog/Reebok Unisex Hammer Street.webp', 'Reebok', '38,39,40,41,42,43'
    UNION ALL SELECT 'Sports & Court', 'Reebok Unisex Phase Evo', 'Reebok unisex tennis shoe.', 6995.00, 'images/catalog/Reebok Unisex Phase Evo.webp', 'Reebok', '38,39,40,41,42,43'
    UNION ALL SELECT 'Casual', 'Reebok Unisex Workout Plus', 'Reebok unisex classic training sneaker.', 5595.00, 'images/catalog/Reebok Unisex Workout Plus.webp', 'Reebok', '38,39,40,41,42,43'
    UNION ALL SELECT 'Casual', 'Reebok Women Club C Revenge Vintage', 'Reebok women''s low-top vintage sneaker.', 4795.00, 'images/catalog/Reebok Women Club C Revenge Vintage.webp', 'Reebok', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Casual', 'Reebok Women Freestyle Lo', 'Reebok women''s low-cut sneaker.', 4595.00, 'images/catalog/Reebok Women Freestyle Lo.webp', 'Reebok', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Sports & Court', 'Reebok Women Nano Court', 'Reebok women''s court-training shoe.', 5795.00, 'images/catalog/Reebok Women Nano Court.webp', 'Reebok', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Boots', 'Dr. Martens 2976 BEX Smooth Leather Chelsea Boots', 'Dr. Martens 2976 BEX smooth leather Chelsea boots.', 12995.00, 'images/catalog/2976 BEX SMOOTH LEATHER CHEALSEA BOOTS.webp', 'Dr. Martens', '38,39,40,41,42,43'
    UNION ALL SELECT 'Boots', 'Black Leather Zip-Up Borg Lined Boots', 'Black leather zip-up boots with a warm borg lining.', 3995.00, 'images/catalog/Black Leather Zip-Up Borg Lined Boots.webp', 'Walk & Wear', '38,39,40,41,42,43'
    UNION ALL SELECT 'Sandals', 'Montclair Heel Sandals', 'Strappy heeled sandals with a clear block heel.', 1999.00, 'images/catalog/Montclair Heel Sandals.webp', 'CLN', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Sports & Court', 'Nike GP Challenge 1.5', 'Nike court shoe designed for tennis and court training.', 5995.00, 'images/catalog/Nike GP Challenge 1.5.avif', 'Nike', '38,39,40,41,42,43'
    UNION ALL SELECT 'Sports & Court', 'Nike Vapor Pro 3', 'Nike lightweight tennis shoe built for quick court movement.', 6495.00, 'images/catalog/Nike Vapor Pro 3.avif', 'Nike', '38,39,40,41,42,43'
    UNION ALL SELECT 'Boots', 'REBEL Black Hawk S3 Combat Boot', 'REBEL Black Hawk S3 rugged combat boots.', 4995.00, 'images/catalog/REBEL Black Hawk S3 Combat Boot.webp', 'REBEL', '38,39,40,41,42,43'
    UNION ALL SELECT 'Boots', 'Revel RD™ Chelsea Women''s Waterproof Boot', 'Revel RD™ women''s waterproof Chelsea boot.', 8995.00, 'images/catalog/Revel RD™ Chelsea Women''s Waterproof Boot.avif', 'Revel', '35,36,37,38,39,40,41'
    UNION ALL SELECT 'Sandals', 'Sandugo Sinai Adventure Sandals', 'Sandugo adventure sandals with adjustable straps and a grippy sole.', 1495.00, 'images/catalog/Sandugo Sinai Adventure Sandals.jpg', 'Sandugo', '38,39,40,41,42,43'
    UNION ALL SELECT 'Sports & Court', 'THE ROGER Pro 3', 'On THE ROGER Pro 3 court shoe.', 9995.00, 'images/catalog/THE ROGER Pro 3.avif', 'On', '38,39,40,41,42,43'
    UNION ALL SELECT 'Basketball', 'Ja 2 EP A''Two ''A''Pink Shoe''', 'Nike Ja 2 EP basketball shoe in the A''Two ''A''Pink Shoe'' colorway.', 7495.00, 'images/catalog/A''Two ''A''Pink Shoe'' EP.avif', 'Nike', '40,41,42,43,44,45'
    UNION ALL SELECT 'Basketball', 'LeBron XXI Basketball Shoe - Men''s', 'Nike LeBron XXI men''s basketball shoe.', 11995.00, 'images/catalog/NBA Nike Lebron XXI Basketball Shoe - Mens.avif', 'Nike', '40,41,42,43,44,45'
) AS source_product
JOIN categories AS category ON category.name = source_product.category_name
LEFT JOIN products AS existing ON existing.name = source_product.name
WHERE existing.product_id IS NULL;

SELECT ROW_COUNT() AS products_added;