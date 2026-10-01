<?php
require_once 'includes/config.php';
$page_title = 'Your Cart';

// Remove item
if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][$_GET['remove']]);
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

// Update quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $key => $qty) {
        $qty = max(1, (int)$qty);
        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] = $qty;
        }
    }
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$cart_user = current_user();
$can_checkout = $cart_user && $cart_user['role'] === 'customer';
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

include 'includes/header.php';
?>

<div class="container">
    <h2 class="section-title">Your Cart</h2>

    <?php if (empty($cart)): ?>
        <div class="empty-state">
            <p>Your cart is empty.</p>
            <br>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn">Continue Shopping</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Size</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $key => $item): ?>
                        <tr>
                            <td class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['size']); ?></td>
                            <td>₱<?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <input type="number" name="qty[<?php echo $key; ?>]" value="<?php echo $item['quantity']; ?>" min="1" style="width:70px;padding:6px;">
                            </td>
                            <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            <td><a href="<?php echo BASE_URL; ?>/cart.php?remove=<?php echo $key; ?>" class="remove-btn">Remove</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <button type="submit" name="update_cart" class="btn btn-small">Update Cart</button>
        </form>

        <div class="cart-summary">
            <div class="cart-summary-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>
            <div class="cart-summary-row">
                <span>Shipping</span>
                <span>Free</span>
            </div>
            <div class="cart-summary-row cart-summary-total">
                <span>Total</span>
                <span>₱<?php echo number_format($total, 2); ?></span>
            </div>
            <br>
            <?php if ($can_checkout): ?>
                <a href="<?php echo BASE_URL; ?>/checkout.php" class="btn btn-full">Proceed to Checkout</a>
            <?php elseif ($cart_user): ?>
                <div class="alert alert-error">Only customer accounts can place orders.</div>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-full">Back to Shop</a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-full">Customer Login to Checkout</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
