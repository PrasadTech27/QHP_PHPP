<?php
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='index.php?page=login';</script>"; exit(); }

// Save Restaurant POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_restaurant'])) {
    $name     = trim($_POST['name']);
    $address  = trim($_POST['address']);
    $lat      = floatval($_POST['lat']);
    $lng      = floatval($_POST['lng']);
    $range_km = floatval($_POST['range_km']);
    $img_url  = trim($_POST['image_url']);

    $stmt = $conn->prepare("INSERT INTO restaurants (name, address, lat, lng, range_km, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssddds", $name, $address, $lat, $lng, $range_km, $img_url);
    if ($stmt->execute()) {
        echo "<script>alert('Restaurant Added with Range Circle!'); window.location.href='index.php?page=admin_restaurants';</script>";
        exit();
    }
}

// Fetch Existing Restaurants
$restaurants = $conn->query("SELECT * FROM restaurants ORDER BY id DESC");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />

<style>
    .admin-card { background:#fff; border-radius:20px; padding:25px; box-shadow:0 8px 25px rgba(15,23,42,0.05); margin-bottom:25px; border:1px solid #e2e8f0; }
    #adminMapPicker { width:100%; height:320px; border-radius:16px; border:1.5px solid #cbd5e1; margin:12px 0; }
    .range-slider-box { background:#f0f6ff; padding:15px; border-radius:12px; border:1px solid #bae6fd; margin-bottom:15px; }
</style>

<div class="admin-card">
    <h3 style="color:#0D47A1; margin-bottom:15px; font-weight:800;"><i class="fas fa-store"></i> Add Restaurant & Coverage Range</h3>

    <form method="POST">
        <input type="hidden" name="action_add_restaurant" value="1">
        
        <div class="form-group">
            <label>Restaurant Name</label>
            <input type="text" name="name" required placeholder="e.g. Paradise Biryani">
        </div>

        <div class="form-group">
            <label>Image URL</label>
            <input type="text" name="image_url" placeholder="https://images.unsplash.com/..." required>
        </div>

        <div class="form-group">
            <label>Pick Restaurant Location on Map</label>
            <div id="adminMapPicker"></div>
        </div>

        <div style="display:flex; gap:12px;">
            <div class="form-group" style="flex:1;">
                <label>Latitude</label>
                <input type="text" name="lat" id="rest_lat" value="16.8282" readonly required>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Longitude</label>
                <input type="text" name="lng" id="rest_lng" value="81.8961" readonly required>
            </div>
        </div>

        <div class="range-slider-box">
            <label style="font-weight:800; color:#0D47A1; font-size:14px; display:flex; justify-content:space-between;">
                <span>Delivery Coverage Range:</span>
                <span id="rangeKmText" style="color:#FF9800; font-size:16px;">5.0 KM</span>
            </label>
            <input type="range" name="range_km" id="range_slider" min="1" max="25" step="0.5" value="5" style="width:100%; margin-top:10px;" oninput="updateRangeCircle(this.value)">
        </div>

        <div class="form-group">
            <label>Full Address Details</label>
            <textarea name="address" id="rest_address" rows="2" required placeholder="Address details..."></textarea>
        </div>

        <button type="submit" class="btn" style="background:#0D47A1; width:100%;"><i class="fas fa-plus"></i> Save Restaurant & Range</button>
    </form>
</div>

<div class="admin-card">
    <h3 style="color:#0D47A1; margin-bottom:15px; font-weight:800;">Active Restaurants</h3>
    <?php while($r = $restaurants->fetch_assoc()): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding:12px 0;">
            <div>
                <strong style="color:#1e293b; font-size:16px;"><?= htmlspecialchars($r['name']) ?></strong>
                <div style="color:#64748b; font-size:13px; margin-top:2px;">
                    <i class="fas fa-location-dot" style="color:#FF9800;"></i> Range: <strong><?= $r['range_km'] ?> KM Circle</strong> | Lat: <?= $r['lat'] ?>, Lng: <?= $r['lng'] ?>
                </div>
            </div>
            <a href="index.php?page=admin_items&rest_id=<?= $r['id'] ?>" class="btn" style="background:#FF9800; font-size:12px; padding:6px 14px;">Add Menu Items</a>
        </div>
    <?php endwhile; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
let adminMap = null;
let restMarker = null;
let rangeCircle = null;

function initAdminMap() {
    adminMap = L.map('adminMapPicker').setView([16.8282, 81.8961], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(adminMap);

    restMarker = L.marker([16.8282, 81.8961], { draggable: true }).addTo(adminMap);

    // Initial 5KM Radius Circle
    rangeCircle = L.circle([16.8282, 81.8961], {
        color: '#0D47A1',
        fillColor: '#FF9800',
        fillOpacity: 0.25,
        radius: 5000 // meters (5 KM)
    }).addTo(adminMap);

    const updateCoords = (lat, lng) => {
        document.getElementById('rest_lat').value = lat;
        document.getElementById('rest_lng').value = lng;
        rangeCircle.setLatLng([lat, lng]);

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
            .then(r => r.json())
            .then(d => {
                document.getElementById('rest_address').value = d.display_name || '';
            });
    };

    adminMap.on('click', e => {
        restMarker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    restMarker.on('dragend', e => {
        const pos = e.target.getLatLng();
        updateCoords(pos.lat, pos.lng);
    });
}

function updateRangeCircle(val) {
    document.getElementById('rangeKmText').innerText = `${val} KM`;
    if (rangeCircle) {
        rangeCircle.setRadius(val * 1000); // meters
    }
}

window.onload = initAdminMap;
</script>