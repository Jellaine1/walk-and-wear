<?php
require_once '../includes/config.php';
require_seller();
$page_title = 'Seller Dashboard';

$seller_user = current_user();
$_SESSION['seller_order_csrf'] ??= bin2hex(random_bytes(32));
if (!empty($_SESSION['seller_order_notice'])) {
    $order_notice = $_SESSION['seller_order_notice'];
    unset($_SESSION['seller_order_notice']);
} else {
    $order_notice = '';
}
if (!empty($_SESSION['seller_order_error'])) {
    $order_error = $_SESSION['seller_order_error'];
    unset($_SESSION['seller_order_error']);
} else {
    $order_error = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_tracking') {
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled'];
    $csrf_token = $_POST['csrf_token'] ?? '';
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $carrier_name = trim($_POST['carrier_name'] ?? '');
    $tracking_number = trim($_POST['tracking_number'] ?? '');

    if (!hash_equals($_SESSION['seller_order_csrf'], $csrf_token)) {
        $_SESSION['seller_order_error'] = 'Security check failed. Please try again.';
    } elseif (!$order_id || !in_array($status, $valid_statuses, true)) {
        $_SESSION['seller_order_error'] = 'Please select a valid order status.';
    } elseif (strlen($carrier_name) > 100 || strlen($tracking_number) > 100) {
        $_SESSION['seller_order_error'] = 'Courier and tracking number must be 100 characters or fewer.';
    } else {
        $update_order = mysqli_prepare($conn, "UPDATE orders SET status = ?, carrier_name = NULLIF(?, ''), tracking_number = NULLIF(?, '') WHERE order_id = ?");
        mysqli_stmt_bind_param($update_order, 'sssi', $status, $carrier_name, $tracking_number, $order_id);
        if (mysqli_stmt_execute($update_order) && mysqli_stmt_affected_rows($update_order) >= 0) {
            $_SESSION['seller_order_notice'] = 'Order #' . $order_id . ' delivery update saved. The customer can see it in My Orders.';
        } else {
            $_SESSION['seller_order_error'] = 'The order could not be updated. Please try again.';
        }
    }

    header('Location: ' . BASE_URL . '/seller/index.php#orders');
    exit;
}

$order_stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        SUM(status = 'pending') AS new_orders,
        SUM(status IN ('confirmed', 'preparing', 'ready_for_delivery')) AS orders_to_process,
        SUM(status = 'out_for_delivery') AS orders_for_delivery,
        SUM(status = 'delivered') AS completed_orders,
        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() AND status <> 'cancelled' THEN total_amount ELSE 0 END), 0) AS todays_sales,
        COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END), 0) AS total_sales
    FROM orders
"));
$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock <= 5"))['total'];
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY created_at DESC");
$low_stock_products = mysqli_query($conn, "SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC");
$orders = mysqli_query($conn, "SELECT o.order_id, o.customer_name, o.customer_email, o.customer_phone, o.customer_address, o.total_amount, o.status, o.carrier_name, o.tracking_number, o.created_at, (SELECT GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = o.order_id) AS items_summary FROM orders o ORDER BY o.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Seller | Walk & Wear</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    .seller-wrap { max-width: 1180px; margin: 0 auto; padding: 42px 24px 70px; }
    .seller-header { display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:28px; }
    .seller-header h1 { margin-bottom:4px; }
    .seller-kicker { color:var(--accent); font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .seller-actions { display:flex; gap:16px; align-items:center; flex-wrap:wrap; }
    .seller-link { color:var(--gray); font-weight:700; font-size:.9rem; }
    .seller-stats { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:38px; }
    .seller-stat { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
    .seller-stat-label { color:var(--gray); font-size:.82rem; font-weight:700; }
    .seller-stat-value { display:block; font-size:2rem; line-height:1.1; margin-top:7px; }
    .seller-section { margin-top:38px; }
    .section-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
    .section-heading h2 { margin:0; }
    .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:var(--radius); }
    .seller-table { width:100%; min-width:680px; border-collapse:collapse; background:#fff; }
  .seller-table th, .seller-table td { padding:12px; border-bottom:1px solid var(--border); text-align:left; font-size:0.9rem; }
  .seller-table th { background:#101010; color:#fff; }
    .seller-table tr:last-child td { border-bottom:0; }
        .seller-orders-table { min-width:1240px; }
        .seller-order-form { display:grid; grid-template-columns:140px 140px 160px auto; align-items:center; gap:8px; min-width:480px; }
        .seller-order-form select, .seller-order-form input { width:100%; min-width:0; padding:8px; border:1px solid var(--border); border-radius:6px; font:inherit; }
        .seller-order-form .btn { white-space:nowrap; }
        .seller-order-feedback { padding:12px 16px; margin-bottom:16px; border-radius:8px; font-weight:700; }
        .seller-order-success { background:#e3f6e8; color:#1e7b34; }
        .seller-order-error { background:#fde5e3; color:var(--accent-dark); }
        .seller-map-panel { margin:0 0 18px; padding:14px; border:1px solid var(--border); border-radius:8px; background:#fff; }
        .seller-map-controls { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:10px; }
        .seller-map-details { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin:10px 0; }
        .seller-map-details div { min-width:0; }
        .seller-map-details span { display:block; color:var(--gray); font-size:.75rem; font-weight:700; text-transform:uppercase; }
        .seller-map-details strong { display:block; overflow-wrap:anywhere; }
        .seller-map-state { margin-top:7px; color:var(--gray); font-size:.78rem; }
        .seller-order-map { width:100%; height:340px; border-radius:6px; background:#e9eeea; }
    .stock-low { color:var(--accent); font-weight:800; }
    @media (max-width: 760px) {
        .seller-wrap { padding:28px 16px 50px; }
        .seller-header { align-items:flex-start; flex-direction:column; }
        .seller-stats { grid-template-columns:1fr; }
        .seller-map-details { grid-template-columns:1fr; }
    }
</style>
</head>
<body style="background:var(--cream);">
<div class="seller-wrap">
    <div class="seller-header">
        <div>
            <div class="seller-kicker">Walk & Wear Inventory</div>
            <h1>Seller Dashboard</h1>
            <p style="color:var(--gray);">Welcome, <?php echo htmlspecialchars($seller_user['full_name']); ?>.</p>
        </div>
        <div class="seller-actions">
            <a href="<?php echo BASE_URL; ?>/index.php" class="seller-link">View shop</a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-small">Log out</a>
        </div>
    </div>

    <div class="seller-stats">
        <div class="seller-stat"><span class="seller-stat-label">New Orders</span><strong class="seller-stat-value"><?php echo (int)$order_stats['new_orders']; ?></strong></div>
        <div class="seller-stat"><span class="seller-stat-label">Orders to Process</span><strong class="seller-stat-value"><?php echo (int)$order_stats['orders_to_process']; ?></strong></div>
        <div class="seller-stat"><span class="seller-stat-label">Orders for Delivery</span><strong class="seller-stat-value"><?php echo (int)$order_stats['orders_for_delivery']; ?></strong></div>
        <div class="seller-stat"><span class="seller-stat-label">Completed Orders</span><strong class="seller-stat-value"><?php echo (int)$order_stats['completed_orders']; ?></strong></div>
        <div class="seller-stat"><span class="seller-stat-label">Today's Sales</span><strong class="seller-stat-value">₱<?php echo number_format((float)$order_stats['todays_sales'], 2); ?></strong></div>
        <div class="seller-stat"><span class="seller-stat-label">Total Sales</span><strong class="seller-stat-value">₱<?php echo number_format((float)$order_stats['total_sales'], 2); ?></strong></div>
    </div>

    <section class="seller-section" id="orders">
        <div class="section-heading"><h2>Customer orders</h2><span style="color:var(--gray);font-size:.9rem;">Update delivery progress and parcel details</span></div>
        <?php if ($order_notice): ?><p class="seller-order-feedback seller-order-success" role="status"><?php echo htmlspecialchars($order_notice); ?></p><?php endif; ?>
        <?php if ($order_error): ?><p class="seller-order-feedback seller-order-error" role="alert"><?php echo htmlspecialchars($order_error); ?></p><?php endif; ?>
        <section class="seller-map-panel" id="seller-map-panel" hidden aria-label="Selected order delivery route">
            <div class="seller-map-controls">
                <strong id="seller-map-heading">Order delivery route</strong>
                <button class="btn btn-small" id="seller-map-close" type="button" aria-label="Close delivery map">Close map</button>
            </div>
            <div class="seller-map-details" id="seller-map-details"></div>
            <p class="seller-map-state" id="seller-map-state" role="status" aria-live="polite">Choose an order to view its road route.</p>
            <div class="seller-order-map" id="seller-order-map" aria-label="Fixed route from shop to customer"></div>
        </section>
        <div class="table-wrap"><table class="seller-table seller-orders-table">
            <tr><th>Order</th><th>Customer</th><th>Delivery Address</th><th>Products</th><th>Total</th><th>Ordered</th><th>View Map</th><th>Update delivery</th></tr>
            <?php if (mysqli_num_rows($orders) === 0): ?>
                <tr><td colspan="8">There are no customer orders yet.</td></tr>
            <?php else: ?>
                <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                <tr>
                    <td>#<?php echo (int)$order['order_id']; ?><br><strong><?php echo htmlspecialchars(ucfirst($order['status'])); ?></strong></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?><br><small><?php echo htmlspecialchars($order['customer_phone'] ?? ''); ?></small></td>
                    <td><?php echo htmlspecialchars($order['customer_address'] ?? 'Address unavailable'); ?></td>
                    <td><?php echo htmlspecialchars($order['items_summary'] ?? 'Items unavailable'); ?></td>
                    <td>₱<?php echo number_format((float)$order['total_amount'], 2); ?></td>
                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                    <td><button type="button" class="btn btn-small seller-view-map" data-order-id="<?php echo (int)$order['order_id']; ?>">View Map</button></td>
                    <td>
                        <form method="POST" class="seller-order-form">
                            <input type="hidden" name="action" value="update_order_tracking">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['seller_order_csrf']); ?>">
                            <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                            <select name="status" aria-label="Status for order #<?php echo (int)$order['order_id']; ?>">
                                <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready_for_delivery' => 'Ready for Delivery', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $status_value => $status_label): ?>
                                    <option value="<?php echo $status_value; ?>"<?php echo $order['status'] === $status_value ? ' selected' : ''; ?>><?php echo $status_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="carrier_name" maxlength="100" placeholder="Courier" aria-label="Courier for order #<?php echo (int)$order['order_id']; ?>" value="<?php echo htmlspecialchars($order['carrier_name'] ?? ''); ?>">
                            <input type="text" name="tracking_number" maxlength="100" placeholder="Tracking number" aria-label="Tracking number for order #<?php echo (int)$order['order_id']; ?>" value="<?php echo htmlspecialchars($order['tracking_number'] ?? ''); ?>">
                            <button type="submit" class="btn btn-small">Save update</button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </table></div>
    </section>

    </div>
<?php if (mysqli_num_rows($orders) > 0): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?php echo BASE_URL; ?>/js/live_order_map.js"></script>
<script>
                const sellerMapPanel = document.getElementById('seller-map-panel');
                const sellerMapState = document.getElementById('seller-map-state');
                const sellerMapDetails = document.getElementById('seller-map-details');
                const sellerMap = WalkWearDeliveryMap.createMap(document.getElementById('seller-order-map'));

                function addMapDetail(label, value) {
                    const detail = document.createElement('div');
                    const title = document.createElement('span');
                    const content = document.createElement('strong');
                    title.textContent = label;
                    content.textContent = value || 'Not provided';
                    detail.append(title, content);
                    sellerMapDetails.append(detail);
                }

                async function openSellerOrderMap(orderId) {
                    sellerMapPanel.hidden = false;
                    document.getElementById('seller-map-heading').textContent = `Order #${orderId} delivery route`;
                    sellerMapState.textContent = 'Loading order details...';
                    sellerMapDetails.replaceChildren();
                    sellerMapPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    window.setTimeout(() => sellerMap.map.invalidateSize(), 50);
                    try {
                        const response = await fetch(`<?php echo BASE_URL; ?>/api/order_location.php?order_id=${encodeURIComponent(orderId)}`, { credentials: 'same-origin', cache: 'no-store' });
                        const data = await response.json();
                        if (!response.ok || !data.ok) throw new Error(data.error || 'Could not load this order.');
                        addMapDetail('Shop', data.shop?.address || data.shop?.name);
                        addMapDetail('Customer', data.customer?.name);
                        addMapDetail('Delivery address', data.address);
                        addMapDetail('Delivery status', data.status.replaceAll('_', ' '));
                        await WalkWearDeliveryMap.showRoute(sellerMap, data.shop, data.customer, sellerMapState);
                        window.setTimeout(() => sellerMap.map.invalidateSize(), 50);
                    } catch (error) {
                        sellerMapState.textContent = error.message || 'Could not load the delivery route.';
                    }
                }

                document.querySelectorAll('.seller-view-map').forEach(button => {
                    button.addEventListener('click', () => openSellerOrderMap(button.dataset.orderId));
                });
                document.getElementById('seller-map-close').addEventListener('click', () => { sellerMapPanel.hidden = true; });
</script>
<?php endif; ?>
</body>
</html>
