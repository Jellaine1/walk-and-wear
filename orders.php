<?php
require_once 'includes/config.php';
require_customer();
$page_title = 'My Orders';
$customer = current_user();

$stmt = mysqli_prepare($conn, "SELECT order_id, total_amount, status, carrier_name, tracking_number, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt, 'i', $customer['user_id']);
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);
$map_stmt = mysqli_prepare($conn, "SELECT order_id, status, created_at FROM orders WHERE user_id = ? AND status <> 'cancelled' ORDER BY created_at DESC");
mysqli_stmt_bind_param($map_stmt, 'i', $customer['user_id']);
mysqli_stmt_execute($map_stmt);
$map_orders = mysqli_stmt_get_result($map_stmt);
$item_stmt = mysqli_prepare($conn, "SELECT oi.quantity, oi.size, oi.price, p.name FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ?");

$tracking_steps = [
    'pending' => ['Order placed', 'Your order has been received.'],
    'confirmed' => ['Confirmed', 'The shop has confirmed your order.'],
    'preparing' => ['Preparing', 'Your items are being prepared.'],
    'ready_for_delivery' => ['Ready for Delivery', 'Your order is packed and ready to leave the shop.'],
    'out_for_delivery' => ['Out for Delivery', 'Your order is on its way to the delivery address.'],
    'delivered' => ['Delivered', 'Your order has been delivered.'],
];
$enable_delivery_map = true;

include 'includes/header.php';
?>

<main class="container order-tracking-page">
    <h1 class="section-title">My Orders</h1>
    <p class="order-tracking-intro">Check the latest delivery status for your orders.</p>

    <section class="live-order-map-panel" aria-label="Delivery map">
        <div class="live-order-map-heading">
            <div>
                <h3>Shop to delivery route</h3>
                <p class="live-order-map-state" id="order-map-state" role="status" aria-live="polite">
                    <?php echo mysqli_num_rows($map_orders) > 0 ? 'Choose an order to view its road route.' : 'A delivery route will appear after you place an order.'; ?>
                </p>
            </div>
            <?php if (mysqli_num_rows($map_orders) > 0): ?>
                <label class="map-order-picker">Order
                    <select id="map-order-select" aria-label="Choose order to track">
                        <?php while ($map_order = mysqli_fetch_assoc($map_orders)): ?>
                            <option value="<?php echo (int)$map_order['order_id']; ?>" data-status="<?php echo htmlspecialchars($map_order['status']); ?>">Order #<?php echo (int)$map_order['order_id']; ?> · <?php echo date('M j, Y', strtotime($map_order['created_at'])); ?></option>
                        <?php endwhile; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <div class="live-order-map" id="order-map" style="display:block" aria-label="Fixed route from the shop to your delivery address"></div>
        <div class="delivery-map-legend"><span><i class="map-key map-key-shop">S</i>Shop</span><span><i class="map-key map-key-customer">C</i>Delivery address</span></div>
    </section>

    <?php if (mysqli_num_rows($orders) === 0): ?>
        <div class="order-empty">
            <h2>No orders yet</h2>
            <p>Your orders will appear here after checkout.</p>
            <a href="<?php echo BASE_URL; ?>/index.php#shop" class="btn btn-small">Browse the shop</a>
        </div>
    <?php else: ?>
        <div class="order-list">
            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                <?php
                $status = $order['status'];
                $is_cancelled = $status === 'cancelled';
                $current_step = array_search($status, array_keys($tracking_steps), true);
                mysqli_stmt_bind_param($item_stmt, 'i', $order['order_id']);
                mysqli_stmt_execute($item_stmt);
                $items = mysqli_stmt_get_result($item_stmt);
                ?>
                <article class="order-card">
                    <div class="order-card-heading">
                        <div>
                            <p class="order-eyebrow">Order #<?php echo (int)$order['order_id']; ?></p>
                            <h2><?php echo date('M j, Y', strtotime($order['created_at'])); ?></h2>
                        </div>
                        <strong class="order-total">₱<?php echo number_format((float)$order['total_amount'], 2); ?></strong>
                    </div>

                    <div class="order-status-summary<?php echo $is_cancelled ? ' is-cancelled' : ''; ?>">
                        <?php echo $is_cancelled ? 'Order cancelled' : htmlspecialchars($tracking_steps[$status][0] ?? 'Order placed'); ?>
                    </div>

                    <?php if ($is_cancelled): ?>
                        <p class="order-cancelled-note">This order will not be shipped.</p>
                    <?php else: ?>
                        <ol class="tracking-steps" aria-label="Parcel delivery progress">
                            <?php $step_index = 0; foreach ($tracking_steps as $step_status => $step): ?>
                                <?php $step_complete = $step_index < $current_step || $step_index === $current_step; ?>
                                <li class="tracking-step<?php echo $step_complete ? ' is-complete' : ''; ?><?php echo $step_index === $current_step ? ' is-current' : ''; ?>">
                                    <span class="tracking-step-marker" aria-hidden="true"></span>
                                    <span class="tracking-step-name"><?php echo htmlspecialchars($step[0]); ?></span>
                                    <?php if ($step_index === $current_step): ?>
                                        <span class="tracking-step-detail"><?php echo htmlspecialchars($step[1]); ?></span>
                                    <?php endif; ?>
                                </li>
                                <?php $step_index++; endforeach; ?>
                        </ol>
                    <?php endif; ?>

                    <?php if ($order['carrier_name'] || $order['tracking_number']): ?>
                        <div class="shipment-details">
                            <?php if ($order['carrier_name']): ?>
                                <div><span>Courier</span><strong><?php echo htmlspecialchars($order['carrier_name']); ?></strong></div>
                            <?php endif; ?>
                            <?php if ($order['tracking_number']): ?>
                                <div><span>Tracking number</span><strong><?php echo htmlspecialchars($order['tracking_number']); ?></strong></div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($status === 'out_for_delivery' || $status === 'delivered'): ?>
                        <p class="shipment-pending">Courier tracking details have not been added yet.</p>
                    <?php endif; ?>

                    <details class="order-items-details">
                        <summary>Items in this order</summary>
                        <ul>
                            <?php while ($item = mysqli_fetch_assoc($items)): ?>
                                <li>
                                    <span><?php echo htmlspecialchars($item['name']); ?><?php echo $item['size'] ? ' · Size ' . htmlspecialchars($item['size']) : ''; ?> × <?php echo (int)$item['quantity']; ?></span>
                                    <strong>₱<?php echo number_format((float)$item['price'] * (int)$item['quantity'], 2); ?></strong>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    </details>
                </article>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?php echo BASE_URL; ?>/js/live_order_map.js"></script>
<script>
const orderSelect = document.getElementById('map-order-select');
const mapElement = document.getElementById('order-map');
const stateElement = document.getElementById('order-map-state');
const mapState = WalkWearDeliveryMap.createMap(mapElement);
if (orderSelect) {
    async function showSelectedOrderRoute() {
        const orderId = orderSelect.value;
        stateElement.textContent = `Loading order #${orderId}...`;
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>/api/order_location.php?order_id=${encodeURIComponent(orderId)}`, { credentials: 'same-origin', cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Could not load this order.');
            await WalkWearDeliveryMap.showRoute(mapState, data.shop, data.customer, stateElement);
        } catch (error) {
            stateElement.textContent = error.message || 'Could not load the delivery route.';
        }
    }

    orderSelect.addEventListener('change', showSelectedOrderRoute);
    showSelectedOrderRoute();
}
window.setTimeout(() => mapState.map.invalidateSize(), 50);
</script>

<?php include 'includes/footer.php'; ?>
