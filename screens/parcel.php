<?php
if (!isset($_SESSION['user_id'])) { 
    echo "<script>window.location.href='index.php?page=login';</script>"; 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// Safely fetch user profile name/phone depending on available table columns
$autofill_name = $_SESSION['user_name'] ?? '';
$autofill_phone = '';

$profile_check = $conn->query("SHOW COLUMNS FROM users");
$columns = [];
if ($profile_check) {
    while($col = $profile_check->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}

$name_col = in_array('name', $columns) ? 'name' : (in_array('username', $columns) ? 'username' : '');
$phone_col = in_array('phone', $columns) ? 'phone' : (in_array('phone_number', $columns) ? 'phone_number' : '');

if ($name_col || $phone_col) {
    $sel_cols = ($name_col ? $name_col : "''") . ", " . ($phone_col ? $phone_col : "''");
    $user_profile_query = $conn->prepare("SELECT $sel_cols FROM users WHERE id = ?");
    if ($user_profile_query) {
        $user_profile_query->bind_param("i", $user_id);
        $user_profile_query->execute();
        $res = $user_profile_query->get_result();
        if ($row = $res->fetch_array()) {
            if ($name_col && !empty($row[0])) $autofill_name = $row[0];
            if ($phone_col && !empty($row[1])) $autofill_phone = $row[1];
        }
    }
}

// Handle Parcel Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch_parcel'])) {
    $package_size = trim($_POST['package_size'] ?? 'Small Package');
    $vehicle_type = trim($_POST['vehicle_type'] ?? 'Standard Two-Wheeler Bike');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $pickup_address = trim($_POST['pickup_address'] ?? '');
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $delivery_lat = floatval($_POST['delivery_lat'] ?? 0);
    $delivery_lng = floatval($_POST['delivery_lng'] ?? 0);

    if (empty($contact_name) || empty($contact_phone) || empty($pickup_address) || empty($delivery_address) || $delivery_lat == 0) {
        $error_msg = "Please complete all fields and pinpoint both Pickup & Drop locations on the map.";
    } else {
        $formatted_address = "[PARCEL - $package_size] Contact: $contact_name ($contact_phone) | Pickup: $pickup_address | Drop: $delivery_address";
        
        $stmt = $conn->prepare("INSERT INTO orders (user_id, restaurant_id, total_amount, payment_method, delivery_address, latitude, longitude, status, created_at) VALUES (?, 0, 49.00, 'Online/Parcel', ?, ?, ?, 'Pending', NOW())");
        $stmt->bind_param("isdd", $user_id, $formatted_address, $delivery_lat, $delivery_lng);
        
        if ($stmt->execute()) {
            $success_msg = "Express parcel request dispatched successfully!";
            echo "<script>setTimeout(() => { window.location.href='index.php?page=order_history'; }, 1200);</script>";
        } else {
            $error_msg = "Error dispatching request: " . $conn->error;
        }
    }
}
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
    .parcel-wrapper {
        font-family: 'Segoe UI', -apple-system, sans-serif;
        max-width: 750px;
        margin: 0 auto;
        padding-bottom: 90px;
    }

    .parcel-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .parcel-title {
        color: #0D47A1;
        font-size: 20px;
        font-weight: 800;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 800;
        color: #334155;
        text-transform: uppercase;
        margin-bottom: 6px;
        letter-spacing: 0.5px;
    }

    .form-control {
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        font-size: 14px;
        color: #1e293b;
        background: #f8fafc;
        outline: none;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        border-color: #0D47A1;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
    }

    .map-picker-btn {
        background: #0284c7;
        color: #fff;
        border: none;
        padding: 10px 16px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        justify-content: center;
        margin-top: 4px;
    }

    .map-picker-btn.set-done {
        background: #16a34a !important;
    }

    .coord-info {
        font-size: 11px;
        color: #64748b;
        margin-top: 4px;
    }

    .btn-dispatch {
        background: #16a34a;
        color: #ffffff;
        border: none;
        padding: 14px;
        border-radius: 14px;
        font-weight: 800;
        font-size: 15px;
        cursor: pointer;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
    }

    .alert-box { padding: 12px 16px; border-radius: 12px; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
    .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

    /* FULLSCREEN MAP PICKER MODAL */
    .map-modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100vw;
        height: 100vh;
        background: #f0f4f8;
        z-index: 999999;
        flex-direction: column;
    }

    .map-top-nav {
        background: #0D47A1;
        color: #fff;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 1002;
    }

    .map-top-btn {
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 10px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .map-top-btn.confirm {
        background: #16a34a;
    }

    .map-search-bar-container {
        position: absolute;
        top: 70px;
        left: 50%;
        transform: translateX(-50%);
        width: 90%;
        max-width: 550px;
        z-index: 1001;
    }

    .map-search-input-wrap {
        display: flex;
        align-items: center;
        background: #ffffff;
        border-radius: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        padding: 4px 8px 4px 16px;
    }

    .map-search-input {
        flex: 1;
        padding: 10px 0;
        border: none;
        font-size: 14px;
        outline: none;
        background: transparent;
    }

    .map-search-submit-btn {
        background: #0D47A1;
        color: #fff;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Fixed Center Pin Overlay */
    .map-center-pin {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -100%);
        font-size: 38px;
        color: #0D47A1;
        z-index: 1000;
        pointer-events: none;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
    }

    /* GPS Locate Button */
    .map-gps-btn {
        position: absolute;
        bottom: 180px;
        right: 20px;
        background: #ffffff;
        color: #0D47A1;
        border: none;
        width: 48px;
        height: 48px;
        border-radius: 50%;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        z-index: 1001;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    /* Bottom Clean Sheet */
    .map-bottom-sheet {
        position: absolute;
        bottom: 15px;
        left: 50%;
        transform: translateX(-50%);
        width: 92%;
        max-width: 500px;
        background: #ffffff;
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        z-index: 1001;
    }

    .map-save-btn {
        background: #0D47A1;
        color: #fff;
        width: 100%;
        padding: 14px;
        border-radius: 14px;
        font-weight: 800;
        font-size: 15px;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 14px;
    }
</style>

<div class="parcel-wrapper">
    <div class="parcel-card">
        <div class="parcel-title">
            <i class="fas fa-box-open" style="color: #FF9800;"></i> Express Parcel & Document Delivery
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="alert-box alert-success"><i class="fas fa-check-circle"></i> <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert-box alert-error"><i class="fas fa-circle-exclamation"></i> <?= $error_msg ?></div>
        <?php endif; ?>

        <form method="POST" id="parcelDispatchForm">
            <div class="form-group">
                <label class="form-label">Parcel Structural Dimensions</label>
                <select name="package_size" class="form-control" required>
                    <option value="Small Package">Small Package (Fits inside backpack)</option>
                    <option value="Medium Box">Medium Box (Secure carrier crate)</option>
                    <option value="Large Carton">Large Carton (Requires luggage rack)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Select Logistics Fleet</label>
                <select name="vehicle_type" class="form-control" required>
                    <option value="Standard Two-Wheeler Bike">Standard Two-Wheeler Bike</option>
                    <option value="Priority Electric Scooter">Priority Electric Scooter</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Sender / Recipient Full Name</label>
                <input type="text" name="contact_name" class="form-control" value="<?= htmlspecialchars($autofill_name) ?>" placeholder="Enter full name" required>
            </div>

            <div class="form-group">
                <label class="form-label">Operational Contact Phone Number</label>
                <input type="tel" name="contact_phone" class="form-control" value="<?= htmlspecialchars($autofill_phone) ?>" placeholder="Enter phone number" required>
            </div>

            <!-- Pickup & Delivery Map Selectors -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 16px;">
                <div style="background: #f8fafc; padding: 12px; border-radius: 14px; border: 1px solid #e2e8f0;">
                    <label class="form-label" style="color: #16a34a;">Pickup Point</label>
                    <button type="button" id="btnPickupMap" class="map-picker-btn" onclick="openMapPicker('pickup')">
                        <i class="fas fa-map-pin"></i> Set Pickup Pin
                    </button>
                    <div id="pickup-coord-text" class="coord-info">Pin not set</div>
                    <input type="hidden" name="pickup_lat" id="pickup_lat" value="">
                    <input type="hidden" name="pickup_lng" id="pickup_lng" value="">
                </div>

                <div style="background: #f8fafc; padding: 12px; border-radius: 14px; border: 1px solid #e2e8f0;">
                    <label class="form-label" style="color: #0284c7;">Drop Destination</label>
                    <button type="button" id="btnDropMap" class="map-picker-btn" onclick="openMapPicker('delivery')">
                        <i class="fas fa-map-marker-alt"></i> Set Drop Pin
                    </button>
                    <div id="delivery-coord-text" class="coord-info">Pin not set</div>
                    <input type="hidden" name="delivery_lat" id="delivery_lat" value="">
                    <input type="hidden" name="delivery_lng" id="delivery_lng" value="">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Detailed Pickup Text Address</label>
                <textarea name="pickup_address" id="pickup_address_input" class="form-control" rows="2" placeholder="Flat, Building, Street Info here..." required></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Detailed Drop Text Address</label>
                <textarea name="delivery_address" id="delivery_address_input" class="form-control" rows="2" placeholder="Destination landmarks, house code..." required></textarea>
            </div>

            <button type="submit" name="dispatch_parcel" class="btn-dispatch">
                <i class="fas fa-paper-plane"></i> Dispatch Express Freight Request
            </button>
        </form>
    </div>
</div>

<!-- Fullscreen Map Selector UI -->
<div id="mapPickerModal" class="map-modal-overlay">
    <div class="map-top-nav">
        <button type="button" class="map-top-btn" onclick="closeMapPicker()"><i class="fas fa-arrow-left"></i> Back</button>
        <span id="modalNavTitle" style="font-weight:800; font-size:16px;">Pick Location</span>
        <button type="button" class="map-top-btn confirm" onclick="confirmPinLocation()"><i class="fas fa-check"></i> Confirm</button>
    </div>

    <!-- Floating Search Input -->
    <div class="map-search-bar-container">
        <div class="map-search-input-wrap">
            <input type="text" id="mapSearchInput" class="map-search-input" placeholder="Search area, street, landmark...">
            <button type="button" class="map-search-submit-btn" onclick="searchLocation()"><i class="fas fa-arrow-right"></i></button>
        </div>
    </div>

    <!-- Center Fixed Pin -->
    <div class="map-center-pin"><i class="fas fa-map-marker-alt"></i></div>

    <!-- GPS Button -->
    <button type="button" class="map-gps-btn" onclick="locateUserGPS()"><i class="fas fa-crosshairs"></i></button>

    <!-- Map Canvas Container -->
    <div id="pickerMap" style="width:100%; height:100%;"></div>

    <!-- Bottom Clean Sheet -->
    <div class="map-bottom-sheet">
        <div id="bottomSheetTitle" style="font-size: 11px; font-weight: 800; color: #0D47A1; text-transform: uppercase; letter-spacing: 0.5px;">Selected Location</div>
        <div id="selectedAddressDisplay" style="font-size: 13px; font-weight: 700; color: #334155; margin-top: 4px; line-height: 1.4; max-height: 45px; overflow: hidden; text-overflow: ellipsis;">
            Detecting location...
        </div>

        <button type="button" class="map-save-btn" onclick="confirmPinLocation()">
            Save Location <i class="fas fa-arrow-right"></i>
        </button>
    </div>
</div>

<script>
let pickerMap = null;
let activeTargetType = '';
let currentLat = 16.8282;
let currentLng = 81.8961;

function openMapPicker(type) {
    activeTargetType = type;
    document.getElementById('mapPickerModal').style.display = 'flex';
    
    if (type === 'pickup') {
        document.getElementById('modalNavTitle').innerText = 'Set Pickup Point';
        document.getElementById('bottomSheetTitle').innerText = 'Selected Pickup Point';
    } else {
        document.getElementById('modalNavTitle').innerText = 'Set Drop Point';
        document.getElementById('bottomSheetTitle').innerText = 'Selected Drop Point';
    }

    setTimeout(() => {
        if (!pickerMap) {
            pickerMap = L.map('pickerMap', { zoomControl: false }).setView([16.8282, 81.8961], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(pickerMap);

            pickerMap.on('moveend', function() {
                let center = pickerMap.getCenter();
                currentLat = center.lat;
                currentLng = center.lng;
                reverseGeocode(currentLat, currentLng);
            });
        }
        pickerMap.invalidateSize();
        locateUserGPS();
    }, 200);
}

function closeMapPicker() {
    document.getElementById('mapPickerModal').style.display = 'none';
}

function locateUserGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(pos => {
            currentLat = pos.coords.latitude;
            currentLng = pos.coords.longitude;
            if (pickerMap) {
                pickerMap.setView([currentLat, currentLng], 16);
            }
            reverseGeocode(currentLat, currentLng);
        });
    }
}

function reverseGeocode(lat, lng) {
    document.getElementById('selectedAddressDisplay').innerText = "Fetching address...";
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            let addr = data.display_name || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            document.getElementById('selectedAddressDisplay').innerText = addr;
        }).catch(() => {
            document.getElementById('selectedAddressDisplay').innerText = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        });
}

function searchLocation() {
    let q = document.getElementById('mapSearchInput').value;
    if (!q) return;
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.length > 0) {
                currentLat = parseFloat(data[0].lat);
                currentLng = parseFloat(data[0].lon);
                pickerMap.setView([currentLat, currentLng], 16);
            }
        });
}

function confirmPinLocation() {
    let addressText = document.getElementById('selectedAddressDisplay').innerText;
    if (activeTargetType === 'pickup') {
        document.getElementById('pickup_lat').value = currentLat.toFixed(6);
        document.getElementById('pickup_lng').value = currentLng.toFixed(6);
        document.getElementById('pickup_address_input').value = addressText;
        document.getElementById('pickup-coord-text').innerText = `Lat: ${currentLat.toFixed(4)}, Lng: ${currentLng.toFixed(4)}`;
        
        let btn = document.getElementById('btnPickupMap');
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Location Set Successfully';
        btn.classList.add('set-done');
    } else {
        document.getElementById('delivery_lat').value = currentLat.toFixed(6);
        document.getElementById('delivery_lng').value = currentLng.toFixed(6);
        document.getElementById('delivery_address_input').value = addressText;
        document.getElementById('delivery-coord-text').innerText = `Lat: ${currentLat.toFixed(4)}, Lng: ${currentLng.toFixed(4)}`;
        
        let btn = document.getElementById('btnDropMap');
        btn.innerHTML = '<i class="fas fa-check-circle"></i> Location Set Successfully';
        btn.classList.add('set-done');
    }
    closeMapPicker();
}

// Client-Side Submission Validation Check
document.getElementById('parcelDispatchForm').addEventListener('submit', function(e) {
    let pLat = document.getElementById('pickup_lat').value;
    let dLat = document.getElementById('delivery_lat').value;
    if (!pLat || !dLat) {
        e.preventDefault();
        alert('Please select both Pickup and Drop location pins on the map before submitting!');
    }
});
</script>