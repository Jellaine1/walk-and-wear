<?php
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE product_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $viewer = current_user();
    if ($viewer && $viewer['role'] !== 'customer') {
        redirect_for_role($viewer);
    }

    $size = $_POST['size'] ?? '';
    $qty = max(1, (int)($_POST['quantity'] ?? 1));

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $key = $product['product_id'] . '-' . $size;
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][$key] = [
            'product_id' => $product['product_id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'image' => $product['image'],
            'size' => $size,
            'quantity' => $qty
        ];
    }
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

$page_title = $product['name'];
$sizes = explode(',', $product['size_available']);
$viewer = current_user();
$can_add_to_cart = !$viewer || $viewer['role'] === 'customer';

include 'includes/header.php';
?>

<div class="container">
    <div class="product-detail">
        <div class="product-detail-img">
            <img src="<?php echo BASE_URL; ?>/<?php echo htmlspecialchars(product_image_path($product['image'])); ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>
        <div>
            <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <div class="price">₱<?php echo number_format($product['price'], 2); ?></div>
            <p class="desc"><?php echo htmlspecialchars($product['description']); ?></p>

            <?php if ($can_add_to_cart): ?>
            <form method="POST">
                <div class="size-select">
                    <label>Select Size</label>
                    <div class="size-options">
                        <?php foreach ($sizes as $s): $s = trim($s); ?>
                            <input type="radio" name="size" id="size-<?php echo $s; ?>" value="<?php echo $s; ?>" <?php echo $s === trim($sizes[0]) ? 'checked' : ''; ?>>
                            <label for="size-<?php echo $s; ?>"><?php echo $s; ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="qty-select">
                    <label>Quantity</label>
                    <input type="number" name="quantity" value="1" min="1" max="<?php echo max(1,$product['stock']); ?>">
                </div>

                <button type="submit" name="add_to_cart" class="btn btn-full" <?php echo $product['stock'] <= 0 ? 'disabled' : ''; ?>>
                    <?php echo $product['stock'] > 0 ? 'Add to Cart 🛒' : 'Out of Stock'; ?>
                </button>
            </form>
            <?php else: ?>
                <div class="alert alert-error">Admin accounts cannot place orders.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
