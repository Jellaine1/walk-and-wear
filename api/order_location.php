<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$viewer = current_user();
if (!$viewer || !in_array($viewer['role'], ['customer', 'seller', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Customer, seller, or admin login is required.']);
    exit;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'A valid order is required.']);
    exit;
}

$query = 'SELECT o.order_id, o.status, o.customer_name, o.customer_address, o.delivery_latitude, o.delivery_longitude, s.latitude AS shop_latitude, s.longitude AS shop_longitude FROM orders o LEFT JOIN store_map_location s ON s.store_id = 1 WHERE o.order_id = ?';
if ($viewer['role'] === 'customer') {
    $query .= ' AND o.user_id = ?';
    $stmt = mysqli_prepare($conn, $query . ' LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $viewer['user_id']);
} else {
    $stmt = mysqli_prepare($conn, $query . ' LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
}
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'order_id' => (int)$order['order_id'],
    'status' => $order['status'],
    'address' => $order['customer_address'],
    'shop' => $order['shop_latitude'] !== null && $order['shop_longitude'] !== null ? [
        'latitude' => (float)$order['shop_latitude'],
        'longitude' => (float)$order['shop_longitude'],
        'name' => 'Walk & Wear Shop',
        'address' => 'District #3, San Manuel, Isabela',
    ] : null,
    'customer' => $order['delivery_latitude'] !== null && $order['delivery_longitude'] !== null ? [
        'latitude' => (float)$order['delivery_latitude'],
        'longitude' => (float)$order['delivery_longitude'],
        'name' => $order['customer_name'],
        'address' => $order['customer_address'],
    ] : null,
]);
