<?php
// =====================================================
// Walk & Wear - Database Configuration
// =====================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'walk_and_wear');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

$document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$project_root = realpath(__DIR__ . '/..');
$base_url = '';
if ($document_root && $project_root && strpos($project_root, $document_root) === 0) {
    $base_url = str_replace('\\', '/', substr($project_root, strlen($document_root)));
}
define('BASE_URL', rtrim($base_url, '/'));

function product_image_path($image)
{
    $image = trim((string)$image);
    $relative_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $image);
    $full_path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative_path;

    return $image !== '' && is_file($full_path) ? $image : 'images/product-placeholder.svg';
}

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// Start session for cart / login handling
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_user()
{
    global $conn;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, phone, address, role FROM users WHERE user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function redirect_for_role($user)
{
    if ($user['role'] === 'admin') {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } elseif ($user['role'] === 'seller') {
        header('Location: ' . BASE_URL . '/seller/index.php');
    } else {
        header('Location: ' . BASE_URL . '/index.php');
    }
    exit;
}

function login_user($user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
}

function logout_user()
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

function require_customer()
{
    $user = current_user();
    if (!$user) {
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
    if ($user['role'] !== 'customer') {
        redirect_for_role($user);
    }
}

function require_admin()
{
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        header("Location: " . BASE_URL . "/admin/login.php");
        exit;
    }
}

function require_seller()
{
    $user = current_user();
    if (!$user || $user['role'] !== 'seller') {
        header("Location: " . BASE_URL . "/seller/login.php");
        exit;
    }
}

?>
