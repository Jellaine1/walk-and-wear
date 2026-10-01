<?php
require_once '../includes/config.php';
require_admin();
$page_title = 'Admin Dashboard';

$admin_user = current_user();
$_SESSION['store_location_csrf'] ??= bin2hex(random_bytes(32));
$order_notice = '';
$order_error = '';
$_SESSION['order_tracking_csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_order_tracking') {
    $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled'];
    $csrf_token = $_POST['csrf_token'] ?? '';
    $order_id = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    $carrier_name = trim($_POST['carrier_name'] ?? '');
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    if (!hash_equals($_SESSION['order_tracking_csrf'], $csrf_token)) {
        $order_error = 'Security check failed. Please reload the page and try again.';
    } elseif (!$order_id || !in_array($status, $valid_statuses, true)) {
        $order_error = 'Please select a valid order status.';
    } elseif (strlen($carrier_name) > 100 || strlen($tracking_number) > 100) {
        $order_error = 'Courier and tracking number must be 100 characters or fewer.';
    } else {
        $update_order = mysqli_prepare($conn, 'UPDATE orders SET status = ?, carrier_name = NULLIF(?, \'\'), tracking_number = NULLIF(?, \'\') WHERE order_id = ?');
        mysqli_stmt_bind_param($update_order, 'sssi', $status, $carrier_name, $tracking_number, $order_id);
        if (mysqli_stmt_execute($update_order) && mysqli_stmt_affected_rows($update_order) >= 0) {
            $order_notice = 'Order #' . $order_id . ' tracking details updated.';
        } else {
            $order_error = 'The order could not be updated. Please try again.';
        }
    }
}

$product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products"))['total'];
$order_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM orders"))['total'];
$customer_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'customer'"))['total'];
$low_stock_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM products WHERE stock <= 5"))['total'];
$products = mysqli_query($conn, "SELECT * FROM products ORDER BY created_at DESC");
$low_stock_products = mysqli_query($conn, "SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC");
$orders = mysqli_query($conn, "SELECT * FROM orders ORDER BY created_at DESC");
$shop_location = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT latitude, longitude FROM store_map_location WHERE store_id = 1 LIMIT 1'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin | Walk & Wear</title>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<style>
    .admin-wrap { max-width:1180px; margin:0 auto; padding:42px 24px 70px; }
    .admin-header { display:flex; justify-content:space-between; align-items:center; gap:20px; margin-bottom:28px; }
    .admin-header h1 { margin-bottom:4px; }
    .admin-kicker { color:var(--accent); font-size:.78rem; font-weight:800; text-transform:uppercase; letter-spacing:.08em; }
    .admin-actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .admin-link { color:var(--gray); font-weight:700; font-size:.9rem; }
    .admin-stats { display:grid; grid-template-columns:repeat(4, 1fr); gap:16px; margin-bottom:38px; }
    .admin-stat { background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:20px; }
    .admin-stat-label { color:var(--gray); font-size:.82rem; font-weight:700; }
    .admin-stat-value { display:block; font-size:2rem; line-height:1.1; margin-top:7px; }
    .admin-section { margin-top:38px; }
    .section-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; }
    .section-heading h2 { margin:0; }
    .section-heading label { display:flex; align-items:center; gap:8px; color:var(--gray); font-size:.85rem; font-weight:700; }
    #admin-order-status-filter { padding:8px 10px; border:1px solid var(--border); border-radius:6px; background:#fff; font:inherit; }
    .table-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:var(--radius); }
    .admin-table { width:100%; min-width:700px; border-collapse:collapse; background:#fff; }
    .orders-table { min-width:1320px; }
    .admin-table th, .admin-table td { padding:12px; border-bottom:1px solid var(--border); text-align:left; font-size:.9rem; }
    .admin-table th { background:#101010; color:#fff; }
    .admin-table tr:last-child td { border-bottom:0; }
    .tracking-form { display:grid; grid-template-columns:130px 145px 160px auto; align-items:center; gap:8px; min-width:480px; }
    .tracking-form select, .tracking-form input { min-width:0; width:100%; padding:8px; border:1px solid var(--border); border-radius:6px; font:inherit; }
    .tracking-form .btn { white-space:nowrap; }
    .admin-map-state { margin-top:6px; color:var(--gray); font-size:.78rem; }
    .admin-map-panel { margin:0 0 22px; padding:14px; border:1px solid var(--border); border-radius:8px; background:#fff; }
    .admin-map-controls { display:flex; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:10px; }
    .admin-map-details { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:12px; margin:10px 0; }
    .admin-map-details div { min-width:0; }
    .admin-map-details span { display:block; color:var(--gray); font-size:.75rem; font-weight:700; text-transform:uppercase; }
    .admin-map-details strong { display:block; overflow-wrap:anywhere; }
    .admin-order-map { width:100%; height:340px; margin:0; border-radius:6px; background:#e9eeea; }
    .shop-pin-map { width:100%; height:320px; margin:10px 0; border-radius:6px; background:#e9eeea; }
    .shop-location-search { display:flex; align-items:center; gap:8px; width:min(100%, 620px); }
    .shop-location-search input { flex:1; min-width:0; padding:9px 10px; border:1px solid var(--border); border-radius:6px; font:inherit; }
    .store-pin-status { color:var(--gray); font-size:.8rem; }
    .order-feedback { padding:12px 16px; margin-bottom:16px; border-radius:8px; font-weight:700; }
    .order-feedback-success { background:#e3f6e8; color:#1e7b34; }
    .order-feedback-error { background:#fde5e3; color:var(--accent-dark); }
    .stock-low { color:var(--accent); font-weight:800; }
    .empty-state { color:var(--gray); background:#fff; border:1px solid var(--border); border-radius:var(--radius); padding:22px; }
    @media (max-width: 760px) {
        .admin-wrap { padding:28px 16px 50px; }
        .admin-header { align-items:flex-start; flex-direction:column; }
        .admin-stats { grid-template-columns:repeat(2, 1fr); }
        .section-heading { align-items:flex-start; flex-direction:column; }
        .admin-map-controls { align-items:flex-start; flex-direction:column; }
        .admin-map-details { grid-template-columns:1fr; }
        .shop-location-search { align-items:stretch; flex-direction:column; }
    }
</style>
</head>
<body style="background:var(--cream);padding-left:0;">

<div class="admin-wrap">
    <div class="admin-header">
        <div>
            <div class="admin-kicker">Walk & Wear Management</div>
            <h1>Admin Dashboard</h1>
            <p style="color:var(--gray);">Welcome, <?php echo htmlspecialchars($admin_user['full_name']); ?>.</p>
        </div>
        <div class="admin-actions">
            <a href="<?php echo BASE_URL; ?>/index.php" class="admin-link">View shop</a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="admin-link">Log out</a>
            <button type="button" class="btn btn-small" id="set-shop-location">Set Shop Location</button>
            <a href="<?php echo BASE_URL; ?>/admin/add_product.php" class="btn btn-small">+ Add Product</a>
        </div>
    </div>
    <p class="store-pin-status" id="store-pin-status" role="status" aria-live="polite" style="margin-top:-18px;margin-bottom:20px;">Shop location: <?php echo $shop_location ? 'District #3, San Manuel, Isabela (' . htmlspecialchars(number_format((float)$shop_location['latitude'], 6) . ', ' . number_format((float)$shop_location['longitude'], 6)) . ')' : 'not set'; ?></p>
    <section class="admin-map-panel" id="shop-location-panel" hidden aria-label="Set fixed shop location">
        <div class="admin-map-controls">
            <strong>Fixed shop location</strong>
            <div class="shop-location-search">
                <input type="search" id="shop-location-query" placeholder="Search shop address or landmark" aria-label="Search shop address">
                <button type="button" class="btn btn-small" id="search-shop-location">Search</button>
            </div>
            <button type="button" class="btn btn-small" id="save-shop-location" disabled>Save Shop Pin</button>
        </div>
        <p class="admin-map-state" id="shop-location-state" role="status" aria-live="polite">Search for the shop address or click the map to place its fixed pin.</p>
        <div class="shop-pin-map" id="shop-pin-map" data-lat="<?php echo $shop_location ? htmlspecialchars((string)$shop_location['latitude']) : ''; ?>" data-lng="<?php echo $shop_location ? htmlspecialchars((string)$shop_location['longitude']) : ''; ?>" aria-label="Fixed shop location map"></div>
    </section>

    <div class="admin-stats">
        <div class="admin-stat"><span class="admin-stat-label">Total products</span><strong class="admin-stat-value"><?php echo $product_count; ?></strong></div>
        <div class="admin-stat"><span class="admin-stat-label">Total orders</span><strong class="admin-stat-value"><?php echo $order_count; ?></strong></div>
        <div class="admin-stat"><span class="admin-stat-label">Customers</span><strong class="admin-stat-value"><?php echo $customer_count; ?></strong></div>
        <div class="admin-stat"><span class="admin-stat-label">Low stock items</span><strong class="admin-stat-value<?php echo $low_stock_count > 0 ? ' stock-low' : ''; ?>"><?php echo $low_stock_count; ?></strong></div>
    </div>

    <?php if ($low_stock_count > 0): ?>
    <section class="admin-section">
        <div class="section-heading"><h2>Low stock</h2><span class="admin-kicker">Needs attention</span></div>
        <div class="table-wrap"><table class="admin-table">
            <tr><th>Product</th><th>Brand</th><th>Stock left</th><th>Price</th></tr>
            <?php while ($p = mysqli_fetch_assoc($low_stock_products)): ?>
            <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['brand']); ?></td><td class="stock-low"><?php echo $p['stock']; ?></td><td>₱<?php echo number_format($p['price'], 2); ?></td></tr>
            <?php endwhile; ?>
        </table></div>
    </section>
    <?php endif; ?>

    <section class="admin-section">
        <div class="section-heading"><h2>Products</h2><span style="color:var(--gray);font-size:.9rem;">Manage your catalog</span></div>
        <div class="table-wrap"><table class="admin-table">
        <tr><th>ID</th><th>Name</th><th>Price</th><th>Stock</th><th>Brand</th><th>Action</th></tr>
        <?php while ($p = mysqli_fetch_assoc($products)): ?>
        <tr>
            <td>#<?php echo $p['product_id']; ?></td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td>₱<?php echo number_format($p['price'], 2); ?></td>
            <td><?php echo $p['stock']; ?></td>
            <td><?php echo htmlspecialchars($p['brand']); ?></td>
            <td><a href="<?php echo BASE_URL; ?>/admin/delete_product.php?id=<?php echo $p['product_id']; ?>" onclick="return confirm('Delete this product?')" style="color:#ff3b30;font-weight:700;">Delete</a></td>
        </tr>
        <?php endwhile; ?>
        </table></div>
    </section>

    <section class="admin-section">
        <div class="section-heading"><h2>Orders</h2><label>Filter status
            <select id="admin-order-status-filter" aria-label="Filter orders by delivery status">
                <option value="all">All statuses</option>
                <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready_for_delivery' => 'Ready for Delivery', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $filter_status => $filter_label): ?>
                    <option value="<?php echo $filter_status; ?>"><?php echo $filter_label; ?></option>
                <?php endforeach; ?>
            </select>
        </label></div>
        <?php if ($order_notice): ?><p class="order-feedback order-feedback-success" role="status"><?php echo htmlspecialchars($order_notice); ?></p><?php endif; ?>
        <?php if ($order_error): ?><p class="order-feedback order-feedback-error" role="alert"><?php echo htmlspecialchars($order_error); ?></p><?php endif; ?>
        <section class="admin-map-panel" id="admin-route-panel" hidden aria-label="Selected order delivery route">
            <div class="admin-map-controls"><strong id="admin-route-heading">Order delivery route</strong><button type="button" class="btn btn-small" id="admin-route-close">Close map</button></div>
            <div class="admin-map-details" id="admin-route-details"></div>
            <p class="admin-map-state" id="admin-route-state" role="status" aria-live="polite">Choose an order to view its road route.</p>
            <div class="admin-order-map" id="admin-order-map" aria-label="Fixed route from shop to customer"></div>
        </section>
        <div class="table-wrap"><table class="admin-table orders-table">
        <tr><th>Order ID</th><th>Customer</th><th>Shop</th><th>Delivery Address</th><th>Order Status</th><th>Date</th><th>View Map</th><th>Update status</th></tr>
        <?php while ($o = mysqli_fetch_assoc($orders)): ?>
        <tr data-order-status="<?php echo htmlspecialchars($o['status']); ?>">
            <td>#<?php echo $o['order_id']; ?></td>
            <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
            <td>Walk & Wear</td>
            <td><?php echo htmlspecialchars($o['customer_address'] ?? 'Address unavailable'); ?></td>
            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $o['status']))); ?></td>
            <td><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
            <td><button type="button" class="btn btn-small admin-view-map" data-order-id="<?php echo (int)$o['order_id']; ?>">View Map</button></td>
            <td>
                <form method="POST" class="tracking-form">
                    <input type="hidden" name="action" value="update_order_tracking">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['order_tracking_csrf']); ?>">
                    <input type="hidden" name="order_id" value="<?php echo (int)$o['order_id']; ?>">
                    <select name="status" aria-label="Status for order #<?php echo (int)$o['order_id']; ?>">
                        <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'ready_for_delivery' => 'Ready for Delivery', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $status_value => $status_label): ?>
                            <option value="<?php echo $status_value; ?>"<?php echo $o['status'] === $status_value ? ' selected' : ''; ?>><?php echo $status_label; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="carrier_name" maxlength="100" placeholder="Courier" aria-label="Courier for order #<?php echo (int)$o['order_id']; ?>" value="<?php echo htmlspecialchars($o['carrier_name'] ?? ''); ?>">
                    <input type="text" name="tracking_number" maxlength="100" placeholder="Tracking number" aria-label="Tracking number for order #<?php echo (int)$o['order_id']; ?>" value="<?php echo htmlspecialchars($o['tracking_number'] ?? ''); ?>">
                    <button type="submit" class="btn btn-small">Save</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
        </table></div>
    </section>
</div>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?php echo BASE_URL; ?>/js/live_order_map.js"></script>
<script>
const routePanel = document.getElementById('admin-route-panel');
const routeState = document.getElementById('admin-route-state');
const routeDetails = document.getElementById('admin-route-details');
const orderMap = WalkWearDeliveryMap.createMap(document.getElementById('admin-order-map'));

function addRouteDetail(label, value) {
    const detail = document.createElement('div');
    const title = document.createElement('span');
    const content = document.createElement('strong');
    title.textContent = label;
    content.textContent = value || 'Not provided';
    detail.append(title, content);
    routeDetails.append(detail);
}

async function openOrderRoute(orderId) {
    routePanel.hidden = false;
    document.getElementById('admin-route-heading').textContent = `Order #${orderId} delivery route`;
    routeState.textContent = 'Loading order details...';
    routeDetails.replaceChildren();
    routePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.setTimeout(() => orderMap.map.invalidateSize(), 50);
    try {
        const response = await fetch(`<?php echo BASE_URL; ?>/api/order_location.php?order_id=${encodeURIComponent(orderId)}`, { credentials: 'same-origin', cache: 'no-store' });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'Could not load this order.');
        addRouteDetail('Customer', data.customer?.name);
        addRouteDetail('Shop', data.shop?.address || data.shop?.name || 'Walk & Wear');
        addRouteDetail('Delivery address', data.address);
        addRouteDetail('Order status', data.status.replaceAll('_', ' '));
        await WalkWearDeliveryMap.showRoute(orderMap, data.shop, data.customer, routeState);
        window.setTimeout(() => orderMap.map.invalidateSize(), 50);
    } catch (error) {
        routeState.textContent = error.message || 'Could not load the delivery route.';
    }
}

document.querySelectorAll('.admin-view-map').forEach(button => {
    button.addEventListener('click', () => openOrderRoute(button.dataset.orderId));
});
document.getElementById('admin-route-close').addEventListener('click', () => { routePanel.hidden = true; });

const orderStatusFilter = document.getElementById('admin-order-status-filter');
orderStatusFilter.addEventListener('change', () => {
    document.querySelectorAll('tr[data-order-status]').forEach(row => {
        row.hidden = orderStatusFilter.value !== 'all' && row.dataset.orderStatus !== orderStatusFilter.value;
    });
});

const shopLocationPanel = document.getElementById('shop-location-panel');
const shopMapElement = document.getElementById('shop-pin-map');
const shopLocationState = document.getElementById('shop-location-state');
const saveShopButton = document.getElementById('save-shop-location');
const existingLatitude = Number(shopMapElement.dataset.lat);
const existingLongitude = Number(shopMapElement.dataset.lng);
const defaultShopPoint = [17.0269105, 121.6308091];
const shopMap = L.map(shopMapElement, { scrollWheelZoom: false }).setView(
    Number.isFinite(existingLatitude) && shopMapElement.dataset.lat ? [existingLatitude, existingLongitude] : defaultShopPoint,
    shopMapElement.dataset.lat ? 16 : 13
);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(shopMap);
let shopMarker = null;
let selectedShopPoint = null;

function setShopPoint(point) {
    selectedShopPoint = point;
    if (!shopMarker) {
        shopMarker = L.marker(point, { draggable: true }).addTo(shopMap);
        shopMarker.on('dragend', () => setShopPoint(shopMarker.getLatLng()));
    } else {
        shopMarker.setLatLng(point);
    }
    saveShopButton.disabled = false;
    shopLocationState.textContent = `Selected shop pin: ${Number(point.lat).toFixed(6)}, ${Number(point.lng).toFixed(6)}. Save to use it for all order routes.`;
}

if (shopMapElement.dataset.lat && shopMapElement.dataset.lng) {
    setShopPoint(L.latLng(existingLatitude, existingLongitude));
    saveShopButton.disabled = true;
    shopLocationState.textContent = 'Current fixed shop pin. Click the map or search to change it.';
}
shopMap.on('click', event => setShopPoint(event.latlng));

document.getElementById('set-shop-location').addEventListener('click', () => {
    shopLocationPanel.hidden = !shopLocationPanel.hidden;
    if (!shopLocationPanel.hidden) window.setTimeout(() => shopMap.invalidateSize(), 50);
});

document.getElementById('search-shop-location').addEventListener('click', async () => {
    const query = document.getElementById('shop-location-query').value.trim();
    if (!query) {
        shopLocationState.textContent = 'Enter a shop address or landmark to search.';
        return;
    }
    shopLocationState.textContent = 'Searching for the shop address...';
    const parameters = new URLSearchParams({ SingleLine: `${query}, San Manuel, Isabela, Philippines`, f: 'json', maxLocations: '1' });
    try {
        const response = await fetch(`https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?${parameters}`);
        if (!response.ok) throw new Error('Address search is unavailable. Click the map to place the shop pin.');
        const result = await response.json();
        const match = result.candidates?.[0];
        if (!match || match.score < 50) throw new Error('Shop address not found. Try a nearby landmark or click the map.');
        const point = L.latLng(Number(match.location.y), Number(match.location.x));
        setShopPoint(point);
        shopMap.setView(point, 17);
        shopLocationState.textContent = `Found ${match.address}. Confirm the pin on the map, then save it.`;
    } catch (error) {
        shopLocationState.textContent = error.message || 'Could not search for that address.';
    }
});

saveShopButton.addEventListener('click', async () => {
    if (!selectedShopPoint) return;
    saveShopButton.disabled = true;
    shopLocationState.textContent = 'Saving fixed shop location...';
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/admin/store_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                csrf_token: <?php echo json_encode($_SESSION['store_location_csrf']); ?>,
                latitude: selectedShopPoint.lat,
                longitude: selectedShopPoint.lng
            })
        });
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Could not save the shop pin.');
        document.getElementById('store-pin-status').textContent = `Shop location saved: ${selectedShopPoint.lat.toFixed(6)}, ${selectedShopPoint.lng.toFixed(6)}`;
        shopLocationState.textContent = 'Fixed shop location saved. It will be used for every order route.';
    } catch (error) {
        shopLocationState.textContent = error.message || 'Could not save the shop pin.';
        saveShopButton.disabled = false;
    }
});
</script>
</body>
</html>
