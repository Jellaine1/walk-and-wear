<?php
require_once 'includes/config.php';

if (current_user()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = 'Customer Register';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Enter a valid name, email, and password of at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $check = mysqli_prepare($conn, 'SELECT user_id FROM users WHERE email = ? LIMIT 1');
        mysqli_stmt_bind_param($check, 's', $email);
        mysqli_stmt_execute($check);

        if (mysqli_stmt_get_result($check)->num_rows > 0) {
            $error = 'That email is already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')");
            mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $hash);
            mysqli_stmt_execute($stmt);
            login_user(['user_id' => mysqli_insert_id($conn)]);
            header('Location: ' . BASE_URL . '/orders.php');
            exit;
        }
    }
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
        <h1 style="margin-bottom:20px;">Create Customer Account</h1>
        <?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group"><label>Full Name</label><input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"></div>
            <div class="form-group"><label>Password</label><input type="password" name="password" minlength="6" required></div>
            <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" minlength="6" required></div>
            <button type="submit" class="btn btn-full">Register</button>
        </form>
        <p style="margin-top:18px;">Already registered? <a href="<?php echo BASE_URL; ?>/login.php" style="color:var(--accent);font-weight:700;">Log in</a></p>
    </div>
</div>
</body>
</html>
