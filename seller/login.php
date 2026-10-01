<?php
require_once '../includes/config.php';

$user = current_user();
if ($user && $user['role'] === 'seller') {
    header('Location: ' . BASE_URL . '/seller/index.php');
    exit;
}
if ($user) {
    redirect_for_role($user);
}

$page_title = 'Seller Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND role = 'seller' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $seller = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($seller && password_verify($password, $seller['password'])) {
        login_user($seller);
        header('Location: ' . BASE_URL . '/seller/index.php');
        exit;
    }

    $error = 'Invalid seller email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $page_title; ?> | Walk & Wear</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body style="background:var(--cream);">
<div class="container" style="max-width:520px; padding-top:60px;">
    <div class="form-box">
        <h1 style="margin-bottom:20px;">Seller Log In</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Seller Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn btn-full">Log In</button>
        </form>
        <p style="margin-top:18px;"><a href="<?php echo BASE_URL; ?>/login.php">Customer Login</a> | <a href="<?php echo BASE_URL; ?>/admin/login.php">Admin Login</a></p>
        <p style="margin-top:10px;"><a href="<?php echo BASE_URL; ?>/index.php">Back to shop</a></p>
    </div>
</div>
</body>
</html>
