<?php
$partner_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$toast_message = "";
$toast_type = "success";

// Ensure columns exist
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Pending'");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS otp INT(4) DEFAULT NULL");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) DEFAULT NULL");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) DEFAULT NULL");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivery_address TEXT DEFAULT NULL");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id_to_process = intval($_POST['order_id'] ?? 0);

    if (isset($_POST['action_accept_order'])) {
        $otp = rand(1000, 9999);
        $stmt = $conn->prepare("UPDATE orders SET status = 'Accepted', otp = ? WHERE id = ?");
        $stmt->bind_param("ii", $otp, $order_id_to_process);
        $stmt->execute();
    }

    if (isset($_POST['action_update_status'])) {
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id_to_process);
        $stmt->execute();
    }

    if (isset($_POST['action_complete_order'])) {
        $entered_otp = intval($_POST['user_otp'] ?? 0);
        $chk_stmt = $conn->prepare("SELECT otp FROM orders WHERE id = ?");
        $chk_stmt->bind_param("i", $order_id_to_process);
        $chk_stmt->execute();
        $res = $chk_stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            if (intval($row['otp']) === $entered_otp) {
                $up_stmt = $conn->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
                $up_stmt->bind_param("i", $order_id_to_process);
                $up_stmt->execute();
                $toast_message = "Order successfully delivered!";
            } else {
                $toast_message = "Incorrect OTP code!";
                $toast_type = "error";
            }
        }
    }
}

// Fetch orders joined with restaurants, user addresses, and user contact details
$orders_query = $conn->query("
    SELECT o.*, 
           r.name as restaurant_name,
           u.phone as user_phone,
           COALESCE(o.latitude, a.lat, 16.8282) as final_lat, 
           COALESCE(o.longitude, a.lng, 81.8961) as final_lng,
           COALESCE(o.delivery_address, 'Primary Location') as final_address
    FROM orders o
    LEFT JOIN restaurants r ON o.restaurant_id = r.id
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN addresses a ON o.user_id = a.user_id AND a.is_primary = 1
    ORDER BY o.id DESC
");
?>

<!-- OpenLayers CSS & JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css" type="text/css">
<script src="https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js"></script>

<style>
    .partner-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 900px; margin: 0 auto; }
    .partner-card { background: #ffffff; border-radius: 20px; padding: 24px; box-shadow: 0 6px 20px rgba(0,0,0,0.04); margin-bottom: 20px; border: 1px solid #e2e8f0; }
    .partner-title { color: #0D47A1; font-size: 20px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .order-box { background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 16px; padding: 18px; margin-bottom: 15px; }
    .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; }
    .btn-action { background: #16a34a; color: #ffffff; border: none; padding: 10px 18px; border-radius: 12px; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .btn-view-map { background: #0284c7; color: #ffffff; border: none; padding: 10px 16px; border-radius: 12px; font-weight: 800; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .badge-status { background: #e0f2fe; color: #0D47A1; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; }
    .map-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 9999; justify-content: center; align-items: center; padding: 15px; }
    .map-modal-content { background: #ffffff; width: 100%; max-width: 750px; height: 85vh; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; }
    .map-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; background: #0D47A1; color: #fff; }
    .map-modal-close { background: #ef4444; color: #fff; border: none; width: 32px; height: 32px; border-radius: 50%; font-weight: 800; cursor: pointer; }
    .modal-map-box { width: 100% !important; height: 500px !important; flex-grow: 1; }
    .item-list-box { background: #fff; border-radius: 10px; padding: 10px; border: 1px solid #e2e8f0; margin-bottom: 12px; font-size: 13px; color: #334155; }
</style>

<script>
function openRouteMapModal(modalId, targetLat, targetLng, headerTitle, headerBgColor) {
    let modal = document.getElementById(modalId);
    modal.style.display = 'flex';
    
    document.getElementById(modalId + '-title').innerText = headerTitle;
    document.getElementById(modalId + '-header').style.background = headerBgColor;

    setTimeout(function() {
        let containerId = modalId + '-canvas';
        
        if (!targetLat || !targetLng || isNaN(targetLat) || isNaN(targetLng)) {
            targetLat = 16.8282; 
            targetLng = 81.8961;
        }

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                fetchRoadRoute(containerId, modalId, pos.coords.latitude, pos.coords.longitude, targetLat, targetLng);
            }, () => {
                fetchRoadRoute(containerId, modalId, 16.8250, 81.8900, targetLat, targetLng);
            });
        } else {
            fetchRoadRoute(containerId, modalId, 16.8250, 81.8900, targetLat, targetLng);
        }
    }, 250);
}

// Fetch real street routing from OSRM Public API (Google Maps style routing)
function fetchRoadRoute(containerId, modalId, pLat, pLng, tLat, tLng) {
    let osrmUrl = `https://router.project-osrm.org/route/v1/driving/${pLng},${pLat};${tLng},${tLat}?overview=full&geometries=geojson`;

    fetch(osrmUrl)
        .then(res => res.json())
        .then(data => {
            let routeCoords = [];
            if (data.routes && data.routes.length > 0) {
                // Convert OSRM coordinates [lng, lat] to OpenLayers projection
                routeCoords = data.routes[0].geometry.coordinates.map(coord => ol.proj.fromLonLat(coord));
            }
            renderMapWithRoute(containerId, modalId, pLat, pLng, tLat, tLng, routeCoords);
        })
        .catch(() => {
            // Fallback straight line if network error occurs
            let fallbackCoords = [
                ol.proj.fromLonLat([pLng, pLat]),
                ol.proj.fromLonLat([tLng, tLat])
            ];
            renderMapWithRoute(containerId, modalId, pLat, pLng, tLat, tLng, fallbackCoords);
        });
}

function renderMapWithRoute(containerId, modalId, pLat, pLng, tLat, tLng, routeCoords) {
    if (!window['mapObj_' + modalId]) {
        let map = new ol.Map({
            target: containerId,
            layers: [
                new ol.layer.Tile({ source: new ol.source.OSM() })
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([tLng, tLat]),
                zoom: 14
            })
        });

        // Partner Current Location Marker
        let partnerFeature = new ol.Feature({
            geometry: new ol.geom.Point(ol.proj.fromLonLat([pLng, pLat]))
        });
        partnerFeature.setStyle(new ol.style.Style({
            image: new ol.style.Icon({
                anchor: [0.5, 0.5],
                src: 'https://cdn-icons-png.flaticon.com/512/3063/3063823.png',
                scale: 0.08
            })
        }));

        // Target Point Marker (Pickup or Drop)
        let targetFeature = new ol.Feature({
            geometry: new ol.geom.Point(ol.proj.fromLonLat([tLng, tLat]))
        });
        targetFeature.setStyle(new ol.style.Style({
            image: new ol.style.Icon({
                anchor: [0.5, 1],
                src: 'https://cdn-icons-png.flaticon.com/512/684/684908.png',
                scale: 0.08
            })
        }));

        // Street-Snapped Google Maps Style Route Line
        let routeFeature = new ol.Feature({
            geometry: new ol.geom.LineString(routeCoords)
        });
        routeFeature.setStyle(new ol.style.Style({
            stroke: new ol.style.Stroke({ color: '#2563eb', width: 6 })
        }));

        let vectorSource = new ol.source.Vector({
            features: [routeFeature, partnerFeature, targetFeature]
        });

        let vectorLayer = new ol.layer.Vector({ source: vectorSource });
        map.addLayer(vectorLayer);
        window['mapObj_' + modalId] = map;

        // Auto zoom to fit route bounds
        setTimeout(() => {
            map.getView().fit(vectorSource.getExtent(), { padding: [50, 50, 50, 50], maxZoom: 16 });
        }, 100);
    } else {
        let map = window['mapObj_' + modalId];
        map.updateSize();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}
</script>

<div class="partner-wrapper">
    <div class="partner-card">
        <div class="partner-title">
            <i class="fas fa-motorcycle"></i> Delivery Partner Dashboard - Live Control
        </div>

        <div class="orders-list-container">
            <?php if ($orders_query && $orders_query->num_rows > 0): ?>
                <?php while($order = $orders_query->fetch_assoc()): 
                    $order_id = $order['id'];
                    $current_status = trim($order['status'] ?? 'Pending');
                    $raw_address = $order['delivery_address'] ?? '';
                    $is_parcel = (strpos($raw_address, '[PARCEL') !== false);

                    $restaurant_name = !empty($order['restaurant_name']) ? $order['restaurant_name'] : 'Food Order';
                    if ($is_parcel) {
                        $restaurant_name = 'Express Parcel Delivery';
                    }

                    $user_phone = !empty($order['user_phone']) ? $order['user_phone'] : 'Not Provided';
                    
                    $lat = floatval($order['final_lat']);
                    $lng = floatval($order['final_lng']);

                    $pickup_lat = 16.8300;
                    $pickup_lng = 81.8900;
                    $drop_lat = $lat;
                    $drop_lng = $lng;

                    $items_res = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
                ?>
                    <div class="order-box">
                        <div class="order-header">
                            <div>
                                <strong style="font-size: 16px; color: #1e293b;">
                                    <?php if ($is_parcel): ?><i class="fas fa-box-open" style="color:#FF9800;"></i><?php endif; ?>
                                    #QHP-<?= $order_id ?> (<?= htmlspecialchars($restaurant_name) ?>)
                                </strong>
                                <span style="display: block; font-size: 12px; color: #64748b;">Placed on: <?= $order['created_at'] ?? 'Recent' ?></span>
                            </div>
                            <div>
                                <span class="badge-status"><?= htmlspecialchars($current_status) ?></span>
                            </div>
                        </div>

                        <!-- Details Box -->
                        <?php if ($is_parcel): 
                            $parcel_size = 'Standard Package';
                            $contact_info = '';
                            $pickup_pt = '';
                            $drop_pt = '';

                            if (preg_match('/\[PARCEL - (.*?)\] Contact: (.*?) \| Pickup: (.*?) \| Drop: (.*?)$/', $raw_address, $matches)) {
                                $parcel_size = $matches[1];
                                $contact_info = $matches[2];
                                $pickup_pt = $matches[3];
                                $drop_pt = $matches[4];
                            } else {
                                $drop_pt = $raw_address;
                            }
                        ?>
                            <div style="font-size: 14px; color: #334155; margin-bottom: 10px; background: #eff6ff; padding: 14px; border-radius: 12px; border: 1.5px solid #bfdbfe;">
                                <div style="font-weight: 800; margin-bottom: 8px; color: #1d4ed8;">
                                    <i class="fas fa-box"></i> Parcel Specification: <?= htmlspecialchars($parcel_size) ?>
                                </div>
                                <?php if (!empty($contact_info)): ?>
                                    <div style="margin-bottom: 6px; font-size: 13px;">
                                        <i class="fas fa-user" style="color: #0284c7; margin-right: 5px;"></i> <strong>Sender/Recipient:</strong> <?= htmlspecialchars($contact_info) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-bottom: 6px;">
                                    <i class="fas fa-map-pin" style="color: #16a34a; margin-right: 5px;"></i>
                                    <strong>Pickup Point:</strong> <?= htmlspecialchars($pickup_pt) ?>
                                </div>
                                <div style="margin-bottom: 6px;">
                                    <i class="fas fa-location-dot" style="color: #dc2626; margin-right: 5px;"></i>
                                    <strong>Drop Destination:</strong> <?= htmlspecialchars($drop_pt) ?>
                                </div>
                                <div style="font-size: 13px; color: #475569;">
                                    <i class="fas fa-phone" style="color: #16a34a; margin-right: 5px;"></i>
                                    <strong>Phone:</strong> <a href="tel:<?= htmlspecialchars($user_phone) ?>" style="color: #0284c7; text-decoration: none; font-weight: 700;"><?= htmlspecialchars($user_phone) ?></a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 14px; color: #334155; margin-bottom: 10px; background: #fff; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0;">
                                <div style="margin-bottom: 6px;">
                                    <i class="fas fa-location-dot" style="color: #0D47A1; margin-right: 5px;"></i>
                                    <strong>Delivery Address:</strong> <?= htmlspecialchars($raw_address) ?>
                                </div>
                                <div style="margin-bottom: 4px; font-size: 13px; color: #475569;">
                                    <i class="fas fa-phone" style="color: #16a34a; margin-right: 5px;"></i>
                                    <strong>Customer Phone:</strong> <a href="tel:<?= htmlspecialchars($user_phone) ?>" style="color: #0284c7; text-decoration: none; font-weight: 700;"><?= htmlspecialchars($user_phone) ?></a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Items (if restaurant order) -->
                        <?php if (!$is_parcel && $items_res && $items_res->num_rows > 0): ?>
                            <div class="item-list-box">
                                <div style="font-weight: 800; color: #0D47A1; margin-bottom: 6px; font-size: 12px; text-transform: uppercase;">Ordered Items:</div>
                                <?php while($item = $items_res->fetch_assoc()): ?>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 3px;">
                                        <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                                        <strong>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Map Buttons -->
                        <div style="margin-bottom: 14px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <?php if ($is_parcel): ?>
                                <button type="button" class="btn-view-map" style="background: #16a34a;" onclick="openRouteMapModal('pickupRouteModal-<?= $order_id ?>', <?= $pickup_lat ?>, <?= $pickup_lng ?>, 'Best Street Route to Pickup - Order #<?= $order_id ?>', '#16a34a')">
                                    <i class="fas fa-route"></i> Street Route to Pickup
                                </button>
                                <button type="button" class="btn-view-map" style="background: #0284c7;" onclick="openRouteMapModal('dropRouteModal-<?= $order_id ?>', <?= $drop_lat ?>, <?= $drop_lng ?>, 'Best Street Route to Drop - Order #<?= $order_id ?>', '#0284c7')">
                                    <i class="fas fa-route"></i> Street Route to Drop
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-view-map" onclick="openRouteMapModal('dropRouteModal-<?= $order_id ?>', <?= $drop_lat ?>, <?= $drop_lng ?>, 'Best Street Route Map - Order #<?= $order_id ?>', '#0284c7')">
                                    <i class="fas fa-route"></i> View Best Street Route
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Pickup Route Modal -->
                        <?php if ($is_parcel): ?>
                            <div id="pickupRouteModal-<?= $order_id ?>" class="map-modal-overlay">
                                <div class="map-modal-content">
                                    <div id="pickupRouteModal-<?= $order_id ?>-header" class="map-modal-header">
                                        <strong id="pickupRouteModal-<?= $order_id ?>-title"><i class="fas fa-route"></i> Street Route to Pickup</strong>
                                        <button type="button" class="map-modal-close" onclick="closeModal('pickupRouteModal-<?= $order_id ?>')">&times;</button>
                                    </div>
                                    <div id="pickupRouteModal-<?= $order_id ?>-canvas" class="modal-map-box"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Drop Route Modal -->
                        <div id="dropRouteModal-<?= $order_id ?>" class="map-modal-overlay">
                            <div class="map-modal-content">
                                <div id="dropRouteModal-<?= $order_id ?>-header" class="map-modal-header">
                                    <strong id="dropRouteModal-<?= $order_id ?>-title"><i class="fas fa-route"></i> Street Route to Drop</strong>
                                    <button type="button" class="map-modal-close" onclick="closeModal('dropRouteModal-<?= $order_id ?>')">&times;</button>
                                </div>
                                <div id="dropRouteModal-<?= $order_id ?>-canvas" class="modal-map-box"></div>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                            <?php if ($current_status === 'Pending'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action_accept_order" value="1">
                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                    <button type="submit" class="btn-action"><i class="fas fa-check"></i> Accept Order</button>
                                </form>
                            <?php elseif ($current_status === 'Accepted'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action_update_status" value="1">
                                    <input type="hidden" name="new_status" value="On the Way">
                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                    <button type="submit" class="btn-action" style="background: #0284c7;"><i class="fas fa-motorcycle"></i> On the Way</button>
                                </form>
                            <?php elseif ($current_status === 'On the Way'): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action_update_status" value="1">
                                    <input type="hidden" name="new_status" value="Reached">
                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                    <button type="submit" class="btn-action" style="background: #d97706;"><i class="fas fa-location-dot"></i> Reached Doorstep</button>
                                </form>
                            <?php elseif ($current_status === 'Reached'): ?>
                                <form method="POST" style="margin: 0; display:flex; gap:8px;">
                                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                    <input type="number" name="user_otp" placeholder="4-Digit OTP" required style="padding: 8px; border: 1px solid #cbd5e1; border-radius: 8px; width: 110px;">
                                    <button type="submit" name="action_complete_order" class="btn-action"><i class="fas fa-check"></i> Verify</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #16a34a; font-weight: 800; font-size: 13px;"><i class="fas fa-circle-check"></i> Delivered Successfully</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-box-open" style="font-size: 40px; color: #cbd5e1; margin-bottom: 10px;"></i>
                    <p style="font-weight: 700;">No active incoming orders.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>