<?php
require_once 'includes/config.php';
require_customer();
$page_title = 'Checkout';
$enable_delivery_map = true;

$customer = current_user();

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: " . BASE_URL . "/cart.php");
    exit;
}

$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? $customer['full_name']);
    $email = trim($_POST['email'] ?? $customer['email']);
    $phone = trim($_POST['phone'] ?? $customer['phone']);
    $address = trim($_POST['address'] ?? $customer['address']);
    $latitude = filter_var($_POST['delivery_latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($_POST['delivery_longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $location_confirmed = ($_POST['delivery_confirmed'] ?? '') === '1';

    if ($name === '' || $email === '' || $phone === '' || $address === '') {
        $error = 'Please enter your full name, email, phone number, and delivery address.';
    } elseif ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        $error = 'Choose your delivery location on the map before placing the order.';
    } elseif (!$location_confirmed) {
        $error = 'Confirm the selected delivery location before placing the order.';
    } else {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO orders (user_id, customer_name, customer_email, customer_address, customer_phone, total_amount, delivery_latitude, delivery_longitude, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            mysqli_stmt_bind_param($stmt, "issssddd", $customer['user_id'], $name, $email, $address, $phone, $total, $latitude, $longitude);
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);

            $item_stmt = mysqli_prepare($conn, "INSERT INTO order_items (order_id, product_id, size, quantity, price) VALUES (?, ?, ?, ?, ?)");
            foreach ($cart as $item) {
                mysqli_stmt_bind_param($item_stmt, "iisid", $order_id, $item['product_id'], $item['size'], $item['quantity'], $item['price']);
                mysqli_stmt_execute($item_stmt);

                // reduce stock
                $stock_stmt = mysqli_prepare($conn, "UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE product_id = ?");
                mysqli_stmt_bind_param($stock_stmt, "ii", $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($stock_stmt);
            }

            mysqli_commit($conn);
            unset($_SESSION['cart']);
            $success = true;
            $order_number = $order_id;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = 'Something went wrong while placing your order. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="container">
    <h2 class="section-title">Checkout</h2>

    <?php if ($success): ?>
        <div class="form-box">
            <div class="alert alert-success">
                🎉 Order #<?php echo $order_number; ?> placed successfully! We'll contact you soon to confirm delivery.
            </div>
            <a href="<?php echo BASE_URL; ?>/orders.php" class="btn btn-full" style="margin-bottom:12px;">Track My Order</a>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-full">Continue Shopping</a>
        </div>
    <?php else: ?>
        <div class="form-box">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <p style="margin-bottom:20px;font-weight:700;">Order Total: ₱<?php echo number_format($total, 2); ?></p>

            <form method="POST">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $customer['full_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? $customer['email']); ?>">
                </div>
                <div class="form-group">
                    <label for="checkout-phone">Phone Number *</label>
                    <input type="tel" id="checkout-phone" name="phone" autocomplete="tel" required value="<?php echo htmlspecialchars($_POST['phone'] ?? $customer['phone']); ?>">
                </div>
                <div class="form-group">
                    <label for="delivery-address">Delivery Address *</label>
                    <textarea id="delivery-address" name="address" rows="3" autocomplete="street-address" required><?php echo htmlspecialchars($_POST['address'] ?? $customer['address']); ?></textarea>
                </div>
                <div class="delivery-location-section">
                    <div class="delivery-location-heading">
                        <div><strong>Select location on map</strong><p>Search your address, then adjust the pin if needed.</p></div>
                        <button class="btn btn-small" type="button" id="find-delivery-address">Find address</button>
                    </div>
                    <p class="delivery-map-hint" id="delivery-map-state" role="status" aria-live="polite">Enter your address, find it on the map, or tap the map to place the pin.</p>
                    <div class="delivery-location-map" id="delivery-location-map" aria-label="Select your fixed delivery location on the map"></div>
                    <input type="hidden" name="delivery_latitude" id="delivery-latitude" value="<?php echo htmlspecialchars($_POST['delivery_latitude'] ?? ''); ?>">
                    <input type="hidden" name="delivery_longitude" id="delivery-longitude" value="<?php echo htmlspecialchars($_POST['delivery_longitude'] ?? ''); ?>">
                    <input type="hidden" name="delivery_confirmed" id="delivery-confirmed" value="<?php echo htmlspecialchars($_POST['delivery_confirmed'] ?? ''); ?>">
                    <div class="delivery-location-confirmation" id="delivery-location-confirmation" hidden>
                        <div><strong>Delivery Address</strong><p id="delivery-address-preview"></p><span>Location selected</span></div>
                        <button class="btn btn-small" type="button" id="confirm-delivery-address">Confirm Address</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-full" id="place-order-button" disabled>Place Order</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if (!$success): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
const deliveryMap = L.map('delivery-location-map').setView([17.0269105, 121.6308091], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
}).addTo(deliveryMap);

const latitudeInput = document.getElementById('delivery-latitude');
const longitudeInput = document.getElementById('delivery-longitude');
const confirmedInput = document.getElementById('delivery-confirmed');
const mapState = document.getElementById('delivery-map-state');
const addressInput = document.getElementById('delivery-address');
const checkoutForm = document.querySelector('.form-box form');
const confirmButton = document.getElementById('confirm-delivery-address');
const confirmPanel = document.getElementById('delivery-location-confirmation');
const placeOrderButton = document.getElementById('place-order-button');
let deliveryMarker = null;
let locatedAddress = addressInput.value.trim();
let lookupPromise = null;

function setDeliveryPoint(point) {
    if (!deliveryMarker) {
        const icon = L.divIcon({
            className: 'delivery-map-marker',
            html: '<span style="--marker-color:#2768a8">C</span>',
            iconSize: [34, 34],
            iconAnchor: [17, 17]
        });
        deliveryMarker = L.marker(point, { icon, draggable: true }).addTo(deliveryMap);
        deliveryMarker.on('dragend', () => setDeliveryPoint(deliveryMarker.getLatLng()));
    } else {
        deliveryMarker.setLatLng(point);
    }
    latitudeInput.value = Number(point.lat).toFixed(7);
    longitudeInput.value = Number(point.lng).toFixed(7);
    locatedAddress = addressInput.value.trim();
    confirmedInput.value = '';
    placeOrderButton.disabled = true;
    document.getElementById('delivery-address-preview').textContent = locatedAddress;
    confirmPanel.hidden = false;
    confirmButton.disabled = false;
    confirmButton.textContent = 'Confirm Address';
    mapState.textContent = `Location selected at ${Number(point.lat).toFixed(5)}, ${Number(point.lng).toFixed(5)}. Confirm this pin to continue.`;
}

async function locateDeliveryAddress() {
    const address = addressInput.value.trim();
    if (!address) return false;
    if (locatedAddress === address && latitudeInput.value && longitudeInput.value) return true;
    if (lookupPromise) {
        try {
            await lookupPromise;
        } catch (error) {}
    }
    if (locatedAddress === address && latitudeInput.value && longitudeInput.value) return true;

    mapState.textContent = 'Searching for this address...';
    lookupPromise = (async () => {
        const queryParts = [address];
        if (!/\bsan manuel\b/i.test(address)) queryParts.push('San Manuel');
        if (!/\bisabela\b/i.test(address)) queryParts.push('Isabela');
        if (!/\bphilippines\b/i.test(address)) queryParts.push('Philippines');
        const parameters = new URLSearchParams({
            SingleLine: queryParts.join(', '),
            f: 'json',
            outFields: 'Addr_type,PlaceName',
            maxLocations: '1'
        });
        const response = await fetch(`https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?${parameters}`);
        if (!response.ok) throw new Error('Address search is unavailable right now. You can tap the map to place the pin.');
        const result = await response.json();
        if (addressInput.value.trim() !== address) return false;
        const match = result.candidates?.[0];
        if (!match || match.score < 50) return false;

        const point = L.latLng(Number(match.location.y), Number(match.location.x));
        setDeliveryPoint(point);
        deliveryMap.setView(point, match.attributes.Addr_type === 'Locality' ? 14 : 16);
        mapState.textContent = `Address found: ${match.address}. Confirm the pin below, or adjust it on the map.`;
        return true;
    })();

    try {
        const located = await lookupPromise;
        if (addressInput.value.trim() !== address) return false;
        if (!located) {
            mapState.textContent = 'Address not found. Add a street or landmark, or tap the map to place the pin.';
        }
        return located;
    } catch (error) {
        mapState.textContent = error.message;
        return false;
    } finally {
        lookupPromise = null;
    }
}

addressInput.addEventListener('input', () => {
    if (locatedAddress === addressInput.value.trim()) return;
    latitudeInput.value = '';
    longitudeInput.value = '';
    confirmedInput.value = '';
    locatedAddress = '';
    placeOrderButton.disabled = true;
    confirmPanel.hidden = true;
    if (deliveryMarker) {
        deliveryMap.removeLayer(deliveryMarker);
        deliveryMarker = null;
    }
    mapState.textContent = 'Address changed. Find the new address or select its location on the map.';
});
document.getElementById('find-delivery-address').addEventListener('click', locateDeliveryAddress);
deliveryMap.on('click', event => setDeliveryPoint(event.latlng));
if (latitudeInput.value && longitudeInput.value && locatedAddress === addressInput.value.trim()) {
    const wasConfirmed = confirmedInput.value === '1';
    const savedPoint = L.latLng(Number(latitudeInput.value), Number(longitudeInput.value));
    deliveryMap.setView(savedPoint, 16);
    setDeliveryPoint(savedPoint);
    if (wasConfirmed) {
        confirmedInput.value = '1';
        placeOrderButton.disabled = false;
        confirmButton.disabled = true;
        confirmButton.textContent = 'Address Confirmed';
        mapState.textContent = 'Delivery location confirmed.';
    }
}
window.setTimeout(() => deliveryMap.invalidateSize(), 50);

checkoutForm.addEventListener('submit', async event => {
    if (confirmedInput.value === '1' && latitudeInput.value && longitudeInput.value && locatedAddress === addressInput.value.trim()) return;
    event.preventDefault();
    if (!latitudeInput.value || !longitudeInput.value || locatedAddress !== addressInput.value.trim()) {
        await locateDeliveryAddress();
    }
    mapState.textContent = 'Review the selected pin and confirm your delivery address before placing the order.';
    confirmPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

confirmButton.addEventListener('click', () => {
    if (!latitudeInput.value || !longitudeInput.value || !addressInput.value.trim() || locatedAddress !== addressInput.value.trim()) {
        mapState.textContent = 'Choose a map location for the current delivery address first.';
        return;
    }
    confirmedInput.value = '1';
    placeOrderButton.disabled = false;
    confirmButton.disabled = true;
    confirmButton.textContent = 'Address Confirmed';
    mapState.textContent = 'Delivery location confirmed. You can now place the order.';
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
