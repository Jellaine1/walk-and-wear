<?php
require_once 'includes/config.php';

if (current_user()) {
    redirect_for_role(current_user());
}

$page_title = 'Customer Login';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND role = 'customer' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    $error = 'Invalid email or password.';
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
        <h1 style="margin-bottom:20px;">Customer Log In</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
            <button type="submit" class="btn btn-full">Log In</button>
        </form>
        <p style="margin-top:18px;">No account yet? <a href="<?php echo BASE_URL; ?>/register.php" style="color:var(--accent);font-weight:700;">Register here</a></p>
        <p style="margin-top:10px;"><a href="<?php echo BASE_URL; ?>/admin/login.php">Admin Login</a> | <a href="<?php echo BASE_URL; ?>/seller/login.php">Seller Login</a></p>
        <p style="margin-top:10px;"><a href="<?php echo BASE_URL; ?>/index.php">Back to shop</a></p>
    </div>
</div>
</body>
</html>
