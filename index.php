<?php
require_once 'includes/config.php';
$page_title = 'Home';

$cat_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name");

if ($cat_filter > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE category_id = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt, "i", $cat_filter);
    mysqli_stmt_execute($stmt);
    $products = mysqli_stmt_get_result($stmt);
} else {
    $products = mysqli_query($conn, "SELECT * FROM products ORDER BY created_at DESC");
}

include 'includes/header.php';
?>

<section class="hero">
    <div class="container">
        <h1>Walk & Wear<span>.</span> Step Into Your Style</h1>
        <p>Fresh drops, everyday comfort, and bold sneaker culture — all in one shop.</p>
        <a href="#shop" class="btn">Shop Now</a>
    </div>
</section>

<div class="container" id="shop">
    <h2 class="section-title">Our Collection</h2>
    <p class="section-sub">Handpicked shoes for every step of your journey</p>

    <div class="category-bar">
            <a href="<?php echo BASE_URL; ?>/index.php" class="category-chip <?php echo $cat_filter === 0 ? 'active' : ''; ?>">All</a>
        <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
            <a href="<?php echo BASE_URL; ?>/index.php?category=<?php echo $cat['category_id']; ?>"
               class="category-chip <?php echo $cat_filter === (int)$cat['category_id'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
        <?php endwhile; ?>
    </div>

    <div class="product-grid">
        <?php if (mysqli_num_rows($products) === 0): ?>
            <p>No products found.</p>
        <?php endif; ?>
        <?php while ($p = mysqli_fetch_assoc($products)): ?>
            <div class="product-card">
                <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['product_id']; ?>">
                    <div class="product-thumb">
                        <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars(product_image_path($p['image'])); ?>"
                             alt="<?php echo htmlspecialchars($p['name']); ?>">
                    </div>
                </a>
                <div class="product-info">
                    <div class="product-brand"><?php echo htmlspecialchars($p['brand']); ?></div>
                    <div class="product-name">
                        <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['product_id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </a>
                    </div>
                    <div class="product-price">₱<?php echo number_format($p['price'], 2); ?></div>
                    <div class="product-stock">
                        <?php echo $p['stock'] > 0 ? $p['stock'] . ' in stock' : 'Out of stock'; ?>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/product.php?id=<?php echo $p['product_id']; ?>" class="btn btn-small btn-full">View Product</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
