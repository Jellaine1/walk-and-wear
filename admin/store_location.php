<?php
require_once '../includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
if (!hash_equals($_SESSION['store_location_csrf'] ?? '', (string)($payload['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Security check failed. Reload the admin dashboard and try again.']);
    exit;
}

$latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid GPS coordinates.']);
    exit;
}

$admin = current_user();
$stmt = mysqli_prepare($conn, 'INSERT INTO store_map_location (store_id, latitude, longitude, accuracy_m, updated_by, updated_at) VALUES (1, ?, ?, NULL, ?, NOW()) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), accuracy_m = NULL, updated_by = VALUES(updated_by), updated_at = NOW()');
mysqli_stmt_bind_param($stmt, 'ddi', $latitude, $longitude, $admin['user_id']);
if (!mysqli_stmt_execute($stmt)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save the shop location.']);
    exit;
}

echo json_encode(['ok' => true]);
