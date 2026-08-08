<?php
$order_id = intval($_GET['order_id'] ?? 0);

// Fetch order details along with restaurant name
$res = $conn->query("
    SELECT o.*, r.name as restaurant_name 
    FROM orders o 
    LEFT JOIN restaurants r ON o.restaurant_id = r.id 
    WHERE o.id = $order_id
");
$order = $res ? $res->fetch_assoc() : null;

if (!$order) {
    echo "<div style='padding:30px; text-align:center;'><h3>Order not found!</h3><a href='index.php?page=order_history'>Back to Orders</a></div>";
    exit();
}

$status = trim($order['status'] ?? 'Pending');
$restaurant_name = !empty($order['restaurant_name']) ? $order['restaurant_name'] : 'Food Order';

// Fetch order items
$items_res = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
?>

<div style="font-family:'Segoe UI', sans-serif; padding-bottom:90px; max-width:800px; margin:0 auto;">
    <!-- Top Bar Header -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
        <div>
            <h3 style="color:#0D47A1; font-weight:800; margin:0 0 4px 0;">Live Tracking: #QHP-<?= $order_id ?> (<?= htmlspecialchars($restaurant_name) ?>)</h3>
            <p style="font-size:12px; color:#64748b; margin:0;">Placed on: <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
        </div>
        <a href="index.php?page=order_history" style="background:#f1f5f9; color:#0D47A1; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:800; text-decoration:none; border:1px solid #cbd5e1;">Back to History</a>
    </div>

    <!-- Status Badge Card -->
    <div style="background:#ffffff; border-radius:16px; padding:16px; border:1px solid #e2e8f0; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:12px; color:#64748b; display:block; font-weight:700;">Current Delivery Status</span>
            <strong style="font-size:16px; color:#0D47A1; text-transform:uppercase;"><?= htmlspecialchars($status) ?></strong>
        </div>
        <div style="font-size:24px; color:#0284c7;">
            <i class="fas fa-motorcycle"></i>
        </div>
    </div>

    <!-- Delivery Address Details -->
    <div style="background:#f8fafc; border-radius:14px; padding:14px; border:1px solid #cbd5e1; margin-bottom:15px; font-size:13px; color:#334155;">
        <i class="fas fa-location-dot" style="color:#0D47A1; margin-right:6px;"></i>
        <strong>Delivery Address:</strong> <?= htmlspecialchars($order['delivery_address'] ?? 'Primary Location') ?>
    </div>

    <!-- Show Verification OTP Code to User -->
    <?php if (!empty($order['otp']) && strtolower($status) !== 'delivered' && strtolower($status) !== 'cancelled'): ?>
        <div style="background:#fff7ed; border:2px dashed #f97316; border-radius:14px; padding:12px; text-align:center; margin-bottom:15px;">
            <span style="font-size:11px; font-weight:700; color:#9a3412; text-transform:uppercase; display:block;">Give this 4-digit code to the delivery partner:</span>
            <h2 style="color:#c2410c; letter-spacing:6px; margin:4px 0 0 0; font-size:26px;"><?= $order['otp'] ?></h2>
        </div>
    <?php endif; ?>

    <!-- Detailed Order Items List -->
    <div style="background:#ffffff; border-radius:16px; padding:16px; border:1px solid #e2e8f0; margin-bottom:20px;">
        <div style="font-size:13px; font-weight:800; color:#0D47A1; margin-bottom:10px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Order Summary</div>
        <?php while($item = $items_res->fetch_assoc()): ?>
            <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569; margin-bottom:6px;">
                <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                <strong>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
            </div>
        <?php endwhile; ?>
        <div style="border-top:1px solid #f1f5f9; margin-top:10px; padding-top:10px; display:flex; justify-content:space-between; font-weight:800; color:#1e293b;">
            <span>Total Amount Paid</span>
            <span style="color:#0D47A1;">₹<?= number_format($order['total_amount'], 2) ?></span>
        </div>
    </div>

    <!-- Mappls Map Container -->
    <div style="font-size:14px; font-weight:800; color:#0D47A1; margin-bottom:8px;"><i class="fas fa-map-marked-alt"></i> Live Partner Route Map</div>
    <div id="map" style="width: 100%; height: 420px; border-radius: 16px; border: 2px solid #cbd5e1;"></div>
</div>

<!-- Load Mappls Web Maps JS SDK using your key -->
<script src="https://apis.mappls.com/advancedmaps/api/ckvdfemrlgxddxfilrdpsxzdvbporcqzharr/map_sdk?layer=vector&v=3.0"></script>

<script>
(function() {
    let map;
    let marker;

    function initMapplsMap() {
        let mapContainer = document.getElementById('map');
        if (!mapContainer) return;

        let initialLat = <?= !empty($order['latitude']) ? floatval($order['latitude']) : 16.8282; ?>;
        let initialLng = <?= !empty($order['longitude']) ? floatval($order['longitude']) : 81.8961; ?>;

        try {
            if (typeof mappls !== 'undefined' && mappls.Map) {
                map = new mappls.Map('map', {
                    center: { lat: initialLat, lng: initialLng },
                    zoom: 15
                });

                marker = new mappls.Marker({
                    map: map,
                    position: { lat: initialLat, lng: initialLng },
                    fitbound: true
                });

                setInterval(fetchPartnerLocation, 5000);
            } else {
                // Retry if SDK script is still downloading
                setTimeout(initMapplsMap, 300);
            }
        } catch (e) {
            console.log("Map init error:", e);
        }
    }

    function fetchPartnerLocation() {
        fetch('services/get_location.php?order_id=<?= $order_id ?>')
            .then(res => res.json())
            .then(data => {
                if (data.latitude && data.longitude) {
                    let newPos = { lat: parseFloat(data.latitude), lng: parseFloat(data.longitude) };
                    if (marker && marker.setPosition) {
                        marker.setPosition(newPos);
                    }
                    if (map && map.panTo) {
                        map.panTo(newPos);
                    }
                }
            }).catch(err => console.log('Location fetch error: ', err));
    }

    // Trigger initialization immediately
    if (document.readyState === 'complete') {
        initMapplsMap();
    } else {
        window.addEventListener('load', initMapplsMap);
        setTimeout(initMapplsMap, 500); // Fallback timer
    }
})();
</script>