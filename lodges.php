<?php
require_once 'otp_helper.php';

// Fetch available lodges or hotels from database (or define default rooms)
$lodges_query = $conn->query("SHOW TABLES LIKE 'lodges'");
if ($lodges_query->num_rows == 0) {
    // Auto-create lodges table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS lodges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        location VARCHAR(255) NOT NULL,
        price_per_night DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        description TEXT
    )");
    
    // Insert sample lodges
    $conn->query("INSERT INTO lodges (name, location, price_per_night, image_url, description) VALUES 
        ('Grand Palace Lodge', 'City Center, Main Road', 1200.00, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=800&auto=format&fit=crop', 'Deluxe AC Rooms with Free Wi-Fi and 24/7 Room Service.'),
        ('Royal Comfort Stay', 'Station Road, Near Metro', 950.00, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=800&auto=format&fit=crop', 'Affordable and hygienic rooms suitable for family and business travelers.'),
        ('Green Valley Resort', 'Hill View Avenue', 1800.00, 'https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?q=80&w=800&auto=format&fit=crop', 'Scenic views with premium luxury suites and complimentary breakfast.')");
}

$lodges_result = $conn->query("SELECT * FROM lodges ORDER BY id ASC");

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['login_temp_user']) ? $_SESSION['login_temp_user']['id'] : 0);

if ($user_id === 0) {
    echo "<script>window.location.href = 'index.php?page=login';</script>";
    exit();
}

$error_msg = "";
$success_msg = "";

// Handle Lodge Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_lodge'])) {
    $lodge_id    = intval($_POST['lodge_id']);
    $lodge_name  = trim($_POST['lodge_name']);
    $guest_name  = trim($_POST['guest_name']);
    $guest_phone = trim($_POST['guest_phone']);
    $check_in    = trim($_POST['check_in_date']);
    $nights      = intval($_POST['nights']);
    $price_night = floatval($_POST['price_per_night']);
    $pay_method  = trim($_POST['payment_method']);
    
    $total_amount = $price_night * max(1, $nights);

    $formatted_address = "[LODGE BOOKING - " . $lodge_name . "] Check-In: $check_in | Stay: $nights Night(s) | Guest: $guest_name ($guest_phone)";

    // Insert into main orders table so it appears in live orders & delivery/partner panel
    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt_order->bind_param("idss", $user_id, $total_amount, $pay_method, $formatted_address);
    
    if ($stmt_order->execute()) {
        $order_id = $stmt_order->insert_id;

        $item_title = $lodge_name . " (" . $nights . " Night(s))";
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
        $stmt_item->bind_param("isdi", $order_id, $item_title, $price_night, $nights);
        $stmt_item->execute();

        $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'QHP Lodge Desk', '9123456789', 16.82820000, 81.89610000)");

        echo "<script>window.location.href='index.php?page=booking_confirmation&order_id=$order_id';</script>";
        exit();
    } else {
        $error_msg = "Failed to book lodge room. Please try again.";
    }
}

$user_info = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$default_name = $user_info['full_name'] ?? '';
$default_phone = $user_info['phone'] ?? '';
?>

<!-- EasyQRCode CDN for rendering Instant Dynamic QR -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .lodge-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 800px; margin: 0 auto; }
    .lodge-banner { background: linear-gradient(135deg, #0D47A1, #1976D2); color: #fff; border-radius: 22px; padding: 24px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(13,71,161,0.2); }
    
    .lodge-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    @media(min-width: 600px) { .lodge-card { flex-direction: row; } }
    
    .lodge-img { width: 100%; height: 200px; object-fit: cover; }
    @media(min-width: 600px) { .lodge-img { width: 260px; height: auto; } }

    .lodge-content { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .btn-book-lodge { background: #0D47A1; color: #fff; border: none; padding: 10px 20px; border-radius: 14px; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(13,71,161,0.2); text-align: center; }

    /* Modal Styling */
    .booking-modal { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
    .booking-modal-card { background:#fff; width:90%; max-width:420px; border-radius:24px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; text-align: left; }
    .input-box-group { margin-bottom: 14px; }
    .input-box-group label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .input-box-group input, .input-box-group select { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font-size: 14px; font-weight: 600; outline: none; box-sizing: border-box; }

    /* Payment Cards */
    .pay-method-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: 0.2s ease; }
    .pay-method-card:hover, .pay-method-card.selected { border-color: #0D47A1; background: #f0f6ff; }
    .pay-method-card input { accent-color: #0D47A1; }

    /* QR Popup */
    .qr-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 10000; }
    .qr-modal-card { background: #ffffff; width: 90%; max-width: 400px; border-radius: 24px; padding: 25px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
</style>

<div class="lodge-wrapper">
    <!-- Banner -->
    <div class="lodge-banner">
        <h2 style="margin:0 0 6px; font-size:22px; font-weight:800;"><i class="fas fa-hotel" style="margin-right:8px;"></i> Lodge & Room Bookings</h2>
        <p style="margin:0; font-size:13px; opacity:0.9;">Book verified AC rooms, luxury suites, and comfortable stays instantly.</p>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13px;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <!-- Lodges List -->
    <div>
        <?php while($lodge = $lodges_result->fetch_assoc()): ?>
            <div class="lodge-card">
                <img src="<?= htmlspecialchars($lodge['image_url']) ?>" class="lodge-img" alt="Lodge">
                <div class="lodge-content">
                    <div>
                        <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin:0 0 4px;"><?= htmlspecialchars($lodge['name']) ?></h3>
                        <div style="font-size:12px; color:#64748b; margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#dc2626; margin-right:4px;"></i> <?= htmlspecialchars($lodge['location']) ?></div>
                        <p style="font-size:13px; color:#475569; margin:0 0 12px; line-height:1.4;"><?= htmlspecialchars($lodge['description']) ?></p>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:12px;">
                        <div>
                            <span style="font-size:11px; color:#64748b; display:block;">Starting from</span>
                            <strong style="font-size:17px; color:#0D47A1;">₹<?= number_format($lodge['price_per_night'], 2) ?> <span style="font-size:11px; color:#64748b; font-weight:normal;">/ night</span></strong>
                        </div>
                        <button type="button" onclick="openLodgeModal(<?= $lodge['id'] ?>, '<?= htmlspecialchars(addslashes($lodge['name'])) ?>', <?= $lodge['price_per_night'] ?>)" class="btn-book-lodge">
                            Book Room <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Booking Details Modal -->
<div class="booking-modal" id="lodgeModal">
    <div class="booking-modal-card">
        <h3 style="color:#0D47A1; font-weight:800; margin-top:0; margin-bottom:4px;" id="modalLodgeTitle">Book Room</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Price per night: <strong style="color:#0D47A1;" id="modalPriceDisplay">₹0</strong></p>

        <form method="POST" id="lodgeBookingForm">
            <input type="hidden" name="book_lodge" value="1">
            <input type="hidden" name="lodge_id" id="modalLodgeId">
            <input type="hidden" name="lodge_name" id="modalLodgeName">
            <input type="hidden" name="price_per_night" id="modalPriceVal">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="COD">

            <div class="input-box-group">
                <label>Guest Full Name</label>
                <input type="text" name="guest_name" value="<?= htmlspecialchars($default_name) ?>" required placeholder="e.g. Revanth Reddy">
            </div>

            <div class="input-box-group">
                <label>Phone Number</label>
                <input type="text" name="guest_phone" value="<?= htmlspecialchars($default_phone) ?>" required placeholder="e.g. 9876543210">
            </div>

            <div class="input-box-group">
                <label>Check-In Date</label>
                <input type="date" name="check_in_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="input-box-group">
                <label>Number of Nights</label>
                <input type="number" name="nights" id="modalNights" value="1" min="1" max="30" required onchange="calculateLodgeTotal()">
            </div>

            <div class="input-box-group">
                <label>Select Payment Option</label>
                
                <label class="pay-method-card selected" onclick="selectLodgePayment(this, 'COD')">
                    <input type="radio" name="pay_radio" value="COD" checked>
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;">Pay at Lodge / COD</strong>
                        <span style="font-size:11px; color:#64748b;">Pay cash or UPI upon arrival</span>
                    </div>
                </label>

                <label class="pay-method-card" onclick="selectLodgePayment(this, 'RAZORPAY_DYNAMIC_QR')">
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
                <button type="button" onclick="initiateLodgeCheckout()" style="flex:1; background:#0D47A1; color:#fff; border:none; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">Confirm Booking</button>
                <button type="button" onclick="closeLodgeModal()" style="background:#f1f5f9; color:#475569; border:none; padding:12px 16px; border-radius:12px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic UPI QR Popup Modal -->
<div class="qr-modal-overlay" id="lodgeQrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="lodgeQrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="lodgeQrAmountDisplay">₹0</span>
        </div>

        <button type="button" onclick="confirmLodgeQRPayment()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Booking
        </button>
        <button type="button" onclick="closeLodgeQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let currentPricePerNight = 0;

function openLodgeModal(id, name, price) {
    document.getElementById('modalLodgeId').value = id;
    document.getElementById('modalLodgeName').value = name;
    document.getElementById('modalLodgeTitle').innerText = name;
    document.getElementById('modalPriceVal').value = price;
    document.getElementById('modalPriceDisplay').innerText = '₹' + price;
    currentPricePerNight = price;
    
    calculateLodgeTotal();
    document.getElementById('lodgeModal').style.display = 'flex';
}

function closeLodgeModal() {
    document.getElementById('lodgeModal').style.display = 'none';
}

function calculateLodgeTotal() {
    let nights = parseInt(document.getElementById('modalNights').value) || 1;
    let total = currentPricePerNight * nights;
    document.getElementById('modalTotalDisplay').innerText = '₹' + total.toFixed(2);
}

function selectLodgePayment(element, methodValue) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById('selectedPaymentMethod').value = methodValue;
}

function initiateLodgeCheckout() {
    const form = document.getElementById('lodgeBookingForm');
    const name = form.querySelector('[name="guest_name"]').value.trim();
    const phone = form.querySelector('[name="guest_phone"]').value.trim();

    if (!name || !phone) {
        alert('Please fill in your guest name and phone number.');
        return;
    }

    const payMethod = document.getElementById('selectedPaymentMethod').value;
    let nights = parseInt(document.getElementById('modalNights').value) || 1;
    let totalAmount = (currentPricePerNight * nights).toFixed(2);

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('lodgeModal').style.display = 'none';
        document.getElementById('lodgeQrcodeBox').innerHTML = '';
        document.getElementById('lodgeQrAmountDisplay').innerText = '₹' + totalAmount;

        const dynamicUpiUrl = `upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=${totalAmount}&cu=INR`;

        new QRCode(document.getElementById("lodgeQrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('lodgeQrModal').style.display = 'flex';
    } else {
        form.submit();
    }
}

function closeLodgeQrModal() {
    document.getElementById('lodgeQrModal').style.display = 'none';
    document.getElementById('lodgeModal').style.display = 'flex';
}

function confirmLodgeQRPayment() {
    document.getElementById('lodgeBookingForm').submit();
}
</script>