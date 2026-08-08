<?php
require_once 'otp_helper.php';

// Auto-create trip planners table if it doesn't exist and insert sample packages
$conn->query("CREATE TABLE IF NOT EXISTS trip_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    price_per_person DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description TEXT
)");

$check_trips = $conn->query("SELECT COUNT(*) as total FROM trip_packages");
if ($check_trips && $check_trips->fetch_assoc()['total'] == 0) {
    $conn->query("INSERT INTO trip_packages (package_name, destination, duration, price_per_person, image_url, description) VALUES 
        ('Goa Beach Escape', 'Goa, India', '3 Days / 2 Nights', 4500.00, 'https://images.unsplash.com/photo-1512343879784-a960bf40e7f2?q=80&w=800&auto=format&fit=crop', 'Enjoy sun-kissed beaches, water sports, and beachside nightlife.'),
        ('Kashmir Paradise Tour', 'Srinagar & Gulmarg', '5 Days / 4 Nights', 12500.00, 'https://images.unsplash.com/photo-1595815771614-ade9d652a65d?q=80&w=800&auto=format&fit=crop', 'Experience houseboats, snow peaks, gondola rides, and scenic valleys.'),
        ('Ooty & Kodaikanal Hills', 'Tamil Nadu, India', '4 Days / 3 Nights', 7800.00, 'https://images.unsplash.com/photo-1589182373726-e4f658ab50f0?q=80&w=800&auto=format&fit=crop', 'Explore botanical gardens, boat houses, and mist-covered green hills.')");
}

$trips_result = $conn->query("SELECT * FROM trip_packages ORDER BY id ASC");

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['login_temp_user']) ? $_SESSION['login_temp_user']['id'] : 0);

if ($user_id === 0) {
    echo "<script>window.location.href = 'index.php?page=login';</script>";
    exit();
}

$error_msg = "";

// Handle Trip Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_trip'])) {
    $trip_id     = intval($_POST['trip_id']);
    $trip_name   = trim($_POST['trip_name']);
    $traveler_name = trim($_POST['traveler_name']);
    $traveler_phone= trim($_POST['traveler_phone']);
    $start_date  = trim($_POST['start_date']);
    $travelers   = intval($_POST['traveler_count']);
    $price_person= floatval($_POST['price_per_person']);
    $pay_method  = trim($_POST['payment_method']);
    
    $total_amount = $price_person * max(1, $travelers);

    $formatted_address = "[TRIP PACKAGE - " . $trip_name . "] Start: $start_date | Travelers: $travelers Person(s) | Contact: $traveler_name ($traveler_phone)";

    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt_order->bind_param("idss", $user_id, $total_amount, $pay_method, $formatted_address);
    
    if ($stmt_order->execute()) {
        $order_id = $stmt_order->insert_id;

        $item_title = $trip_name . " (" . $travelers . " Traveler(s))";
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
        $stmt_item->bind_param("isdi", $order_id, $item_title, $price_person, $travelers);
        $stmt_item->execute();

        $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'QHP Travel Desk', '9123456789', 16.82820000, 81.89610000)");

        echo "<script>window.location.href='index.php?page=booking_confirmation&order_id=$order_id';</script>";
        exit();
    } else {
        $error_msg = "Failed to book trip package. Please try again.";
    }
}

$user_info = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$default_name = $user_info['full_name'] ?? '';
$default_phone = $user_info['phone'] ?? '';
?>

<!-- EasyQRCode CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .trip-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 800px; margin: 0 auto; }
    .trip-banner { background: linear-gradient(135deg, #0284c7, #0369a1); color: #fff; border-radius: 22px; padding: 24px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(2,132,199,0.2); }
    
    .trip-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    @media(min-width: 600px) { .trip-card { flex-direction: row; } }
    
    .trip-img { width: 100%; height: 200px; object-fit: cover; }
    @media(min-width: 600px) { .trip-img { width: 260px; height: auto; } }

    .trip-content { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .btn-book-trip { background: #0D47A1; color: #fff; border: none; padding: 10px 20px; border-radius: 14px; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(13,71,161,0.2); text-align: center; text-decoration: none; }

    .booking-modal { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
    .booking-modal-card { background:#fff; width:90%; max-width:420px; border-radius:24px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; text-align: left; }
    .input-box-group { margin-bottom: 14px; }
    .input-box-group label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .input-box-group input, .input-box-group select { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font-size: 14px; font-weight: 600; outline: none; box-sizing: border-box; }

    .pay-method-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: 0.2s ease; }
    .pay-method-card:hover, .pay-method-card.selected { border-color: #0D47A1; background: #f0f6ff; }
    .pay-method-card input { accent-color: #0D47A1; }

    .qr-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 10000; }
    .qr-modal-card { background: #ffffff; width: 90%; max-width: 400px; border-radius: 24px; padding: 25px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
</style>

<div class="trip-wrapper">
    <div class="trip-banner">
        <h2 style="margin:0 0 6px; font-size:22px; font-weight:800;"><i class="fas fa-plane-departure" style="margin-right:8px;"></i> Tour & Trip Planners</h2>
        <p style="margin:0; font-size:13px; opacity:0.9;">Explore curated holiday packages, scenic destinations, and guided tours.</p>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13px;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div>
        <?php while($trip = $trips_result->fetch_assoc()): ?>
            <div class="trip-card">
                <img src="<?= htmlspecialchars($trip['image_url']) ?>" class="trip-img" alt="Trip">
                <div class="trip-content">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin:0;"><?= htmlspecialchars($trip['package_name']) ?></h3>
                            <span style="font-size:11px; font-weight:800; background:#e0f2fe; color:#0369a1; padding:3px 10px; border-radius:10px;"><?= htmlspecialchars($trip['duration']) ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b; margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#dc2626; margin-right:4px;"></i> <?= htmlspecialchars($trip['destination']) ?></div>
                        <p style="font-size:13px; color:#475569; margin:0 0 12px; line-height:1.4;"><?= htmlspecialchars($trip['description']) ?></p>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:12px;">
                        <div>
                            <span style="font-size:11px; color:#64748b; display:block;">Package Price</span>
                            <strong style="font-size:17px; color:#0D47A1;">₹<?= number_format($trip['price_per_person'], 2) ?> <span style="font-size:11px; color:#64748b; font-weight:normal;">/ person</span></strong>
                        </div>
                        <button type="button" onclick="openTripModal(<?= $trip['id'] ?>, '<?= htmlspecialchars(addslashes($trip['package_name'])) ?>', <?= $trip['price_per_person'] ?>)" class="btn-book-trip">
                            Book Trip <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Booking Modal -->
<div class="booking-modal" id="tripModal">
    <div class="booking-modal-card">
        <h3 style="color:#0D47A1; font-weight:800; margin-top:0; margin-bottom:4px;" id="modalTripTitle">Book Package</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Price per person: <strong style="color:#0D47A1;" id="modalPriceDisplay">₹0</strong></p>

        <form method="POST" id="tripBookingForm">
            <input type="hidden" name="book_trip" value="1">
            <input type="hidden" name="trip_id" id="modalTripId">
            <input type="hidden" name="trip_name" id="modalTripName">
            <input type="hidden" name="price_per_person" id="modalPriceVal">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="COD">

            <div class="input-box-group">
                <label>Lead Traveler Full Name</label>
                <input type="text" name="traveler_name" value="<?= htmlspecialchars($default_name) ?>" required placeholder="e.g. Revanth Reddy">
            </div>

            <div class="input-box-group">
                <label>Phone Number</label>
                <input type="text" name="traveler_phone" value="<?= htmlspecialchars($default_phone) ?>" required placeholder="e.g. 9876543210">
            </div>

            <div class="input-box-group">
                <label>Tour Start Date</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="input-box-group">
                <label>Number of Travelers</label>
                <input type="number" name="traveler_count" id="modalTravelers" value="1" min="1" max="50" required onchange="calculateTripTotal()">
            </div>

            <div class="input-box-group">
                <label>Select Payment Option</label>
                
                <label class="pay-method-card selected" onclick="selectTripPayment(this, 'COD')">
                    <input type="radio" name="pay_radio" value="COD" checked>
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;">Pay at Office / COD</strong>
                        <span style="font-size:11px; color:#64748b;">Pay cash or UPI upon booking confirmation</span>
                    </div>
                </label>

                <label class="pay-method-card" onclick="selectTripPayment(this, 'RAZORPAY_DYNAMIC_QR')">
                    <input type="radio" name="pay_radio" value="RAZORPAY_DYNAMIC_QR">
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;"><i class="fas fa-qrcode" style="color:#0D47A1; margin-right:4px;"></i> Scan Dynamic UPI QR</strong>
                        <span style="font-size:11px; color:#64748b;">Scan & Pay Exact Amount via GPay/PhonePe</span>
                    </div>
                </label>
            </div>

            <div style="background:#f8fafc; padding:10px 14px; border-radius:10px; font-weight:800; color:#1e293b; display:flex; justify-content:space-between; margin-bottom:15px;">
                <span>Total Amount:</span>
                <span id="modalTotalDisplay" style="color:#0D47A1;">₹0</span>
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" onclick="initiateTripCheckout()" style="flex:1; background:#0D47A1; color:#fff; border:none; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">Confirm Trip Booking</button>
                <button type="button" onclick="closeTripModal()" style="background:#f1f5f9; color:#475569; border:none; padding:12px 16px; border-radius:12px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- QR Popup Modal -->
<div class="qr-modal-overlay" id="tripQrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="tripQrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="tripQrAmountDisplay">₹0</span>
        </div>

        <button type="button" onclick="confirmTripQRPayment()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Booking
        </button>
        <button type="button" onclick="closeTripQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let currentPricePerPerson = 0;

function openTripModal(id, name, price) {
    document.getElementById('modalTripId').value = id;
    document.getElementById('modalTripName').value = name;
    document.getElementById('modalTripTitle').innerText = name;
    document.getElementById('modalPriceVal').value = price;
    document.getElementById('modalPriceDisplay').innerText = '₹' + price;
    currentPricePerPerson = price;
    
    calculateTripTotal();
    document.getElementById('tripModal').style.display = 'flex';
}

function closeTripModal() {
    document.getElementById('tripModal').style.display = 'none';
}

function calculateTripTotal() {
    let travelers = parseInt(document.getElementById('modalTravelers').value) || 1;
    let total = currentPricePerPerson * travelers;
    document.getElementById('modalTotalDisplay').innerText = '₹' + total.toFixed(2);
}

function selectTripPayment(element, methodValue) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById('selectedPaymentMethod').value = methodValue;
}

function initiateTripCheckout() {
    const form = document.getElementById('tripBookingForm');
    const name = form.querySelector('[name="traveler_name"]').value.trim();
    const phone = form.querySelector('[name="traveler_phone"]').value.trim();

    if (!name || !phone) {
        alert('Please fill in your name and phone number.');
        return;
    }

    const payMethod = document.getElementById('selectedPaymentMethod').value;
    let travelers = parseInt(document.getElementById('modalTravelers').value) || 1;
    let totalAmount = (currentPricePerPerson * travelers).toFixed(2);

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('tripModal').style.display = 'none';
        document.getElementById('tripQrcodeBox').innerHTML = '';
        document.getElementById('tripQrAmountDisplay').innerText = '₹' + totalAmount;

        const dynamicUpiUrl = `upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=${totalAmount}&cu=INR`;

        new QRCode(document.getElementById("tripQrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('tripQrModal').style.display = 'flex';
    } else {
        form.submit();
    }
}

function closeTripQrModal() {
    document.getElementById('tripQrModal').style.display = 'none';
    document.getElementById('tripModal').style.display = 'flex';
}

function confirmTripQRPayment() {
    document.getElementById('tripBookingForm').submit();
}
</script>