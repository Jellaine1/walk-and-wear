<?php
$cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
$header_user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo isset($page_title) ? $page_title . ' | Walk & Wear' : 'Walk & Wear'; ?></title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
<?php if (!empty($enable_delivery_map)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<?php endif; ?>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">
        <a href="<?php echo BASE_URL; ?>/index.php" class="logo">Walk<span>&</span>Wear</a>
        <nav class="main-nav">
            <a href="<?php echo BASE_URL; ?>/index.php">Home</a>
            <a href="<?php echo BASE_URL; ?>/index.php#shop">Shop</a>
            <a href="<?php echo BASE_URL; ?>/about.php">About</a>
            <a href="<?php echo BASE_URL; ?>/contact.php">Contact</a>
            <?php if ($header_user && $header_user['role'] === 'customer'): ?>
                <a href="<?php echo BASE_URL; ?>/orders.php">My Orders</a>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <?php if ($header_user): ?>
                <span style="margin-right:12px;">Hi, <?php echo htmlspecialchars($header_user['full_name']); ?></span>
                <?php if ($header_user['role'] === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/index.php" style="margin-right:12px;">Admin Dashboard</a>
                <?php elseif ($header_user['role'] === 'seller'): ?>
                    <a href="<?php echo BASE_URL; ?>/seller/index.php" style="margin-right:12px;">Seller Dashboard</a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/logout.php" style="margin-right:12px;">Log out</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php" style="margin-right:12px;">Customer Login</a>
                <a href="<?php echo BASE_URL; ?>/seller/login.php" style="margin-right:12px;">Seller Login</a>
                <a href="<?php echo BASE_URL; ?>/admin/login.php" style="margin-right:12px;">Admin Login</a>
                <a href="<?php echo BASE_URL; ?>/register.php" style="margin-right:12px;">Customer Register</a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>/cart.php" class="cart-link">
                🛒 Cart <span class="cart-count"><?php echo $cart_count; ?></span>
            </a>
        </div>
    </div>
</header>
