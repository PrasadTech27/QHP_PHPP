<?php
require_once 'otp_helper.php';

// Auto-create car rentals table if it doesn't exist and insert demo cars
$conn->query("CREATE TABLE IF NOT EXISTS car_rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    features VARCHAR(255) NOT NULL
)");

$check_cars = $conn->query("SELECT COUNT(*) as total FROM car_rentals");
if ($check_cars && $check_cars->fetch_assoc()['total'] == 0) {
    $conn->query("INSERT INTO car_rentals (car_name, category, price_per_day, image_url, features) VALUES 
        ('Maruti Swift', 'Hatchback', 1500.00, 'https://images.unsplash.com/photo-1541899481282-d53bffe3c355?q=80&w=800&auto=format&fit=crop', '5 Seater • Manual • AC • Petrol'),
        ('Toyota Innova Crysta', 'SUV', 3200.00, 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?q=80&w=800&auto=format&fit=crop', '7 Seater • Diesel • AC • Spacious'),
        ('Honda City', 'Sedan', 2200.00, 'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?q=80&w=800&auto=format&fit=crop', '5 Seater • Automatic • AC • Luxury Comfort')");
}

$cars_result = $conn->query("SELECT * FROM car_rentals ORDER BY id ASC");

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['login_temp_user']) ? $_SESSION['login_temp_user']['id'] : 0);

if ($user_id === 0) {
    echo "<script>window.location.href = 'index.php?page=login';</script>";
    exit();
}

$error_msg = "";

// Handle Car Rental Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_car'])) {
    $car_id      = intval($_POST['car_id']);
    $car_name    = trim($_POST['car_name']);
    $renter_name = trim($_POST['renter_name']);
    $renter_phone= trim($_POST['renter_phone']);
    $start_date  = trim($_POST['start_date']);
    $days        = intval($_POST['rental_days']);
    $price_day   = floatval($_POST['price_per_day']);
    $pay_method  = trim($_POST['payment_method']);
    
    $total_amount = $price_day * max(1, $days);

    $formatted_address = "[CAR RENTAL - " . $car_name . "] Start Date: $start_date | Duration: $days Day(s) | Renter: $renter_name ($renter_phone)";

    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt_order->bind_param("idss", $user_id, $total_amount, $pay_method, $formatted_address);
    
    if ($stmt_order->execute()) {
        $order_id = $stmt_order->insert_id;

        $item_title = $car_name . " Rental (" . $days . " Day(s))";
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
        $stmt_item->bind_param("isdi", $order_id, $item_title, $price_day, $days);
        $stmt_item->execute();

        $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'QHP Fleet Desk', '9123456789', 16.82820000, 81.89610000)");

        echo "<script>window.location.href='index.php?page=booking_confirmation&order_id=$order_id';</script>";
        exit();
    } else {
        $error_msg = "Failed to book car rental. Please try again.";
    }
}

$user_info = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$default_name = $user_info['full_name'] ?? '';
$default_phone = $user_info['phone'] ?? '';
?>

<!-- EasyQRCode CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .rental-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 800px; margin: 0 auto; }
    .rental-banner { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; border-radius: 22px; padding: 24px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(15,23,42,0.2); }
    
    .car-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    @media(min-width: 600px) { .car-card { flex-direction: row; } }
    
    .car-img { width: 100%; height: 200px; object-fit: cover; }
    @media(min-width: 600px) { .car-img { width: 260px; height: auto; } }

    .car-content { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .btn-book-car { background: #0D47A1; color: #fff; border: none; padding: 10px 20px; border-radius: 14px; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(13,71,161,0.2); text-align: center; text-decoration: none; }

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

<div class="rental-wrapper">
    <div class="rental-banner">
        <h2 style="margin:0 0 6px; font-size:22px; font-weight:800;"><i class="fas fa-car-side" style="margin-right:8px;"></i> Car Rentals & Self Drive</h2>
        <p style="margin:0; font-size:13px; opacity:0.9;">Rent well-maintained cars for city trips, family outings, and long drives.</p>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13px;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div>
        <?php while($car = $cars_result->fetch_assoc()): ?>
            <div class="car-card">
                <img src="<?= htmlspecialchars($car['image_url']) ?>" class="car-img" alt="Car">
                <div class="car-content">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin:0;"><?= htmlspecialchars($car['car_name']) ?></h3>
                            <span style="font-size:11px; font-weight:800; background:#e0f2fe; color:#0369a1; padding:3px 10px; border-radius:10px;"><?= htmlspecialchars($car['category']) ?></span>
                        </div>
                        <div style="font-size:12px; color:#64748b; margin-bottom:12px;"><i class="fas fa-circle-info" style="color:#0284c7; margin-right:4px;"></i> <?= htmlspecialchars($car['features']) ?></div>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:12px;">
                        <div>
                            <span style="font-size:11px; color:#64748b; display:block;">Rental Rate</span>
                            <strong style="font-size:17px; color:#0D47A1;">₹<?= number_format($car['price_per_day'], 2) ?> <span style="font-size:11px; color:#64748b; font-weight:normal;">/ day</span></strong>
                        </div>
                        <button type="button" onclick="openCarModal(<?= $car['id'] ?>, '<?= htmlspecialchars(addslashes($car['car_name'])) ?>', <?= $car['price_per_day'] ?>)" class="btn-book-car">
                            Rent Car <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Booking Modal -->
<div class="booking-modal" id="carModal">
    <div class="booking-modal-card">
        <h3 style="color:#0D47A1; font-weight:800; margin-top:0; margin-bottom:4px;" id="modalCarTitle">Rent Car</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Price per day: <strong style="color:#0D47A1;" id="modalPriceDisplay">₹0</strong></p>

        <form method="POST" id="carBookingForm">
            <input type="hidden" name="book_car" value="1">
            <input type="hidden" name="car_id" id="modalCarId">
            <input type="hidden" name="car_name" id="modalCarName">
            <input type="hidden" name="price_per_day" id="modalPriceVal">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="COD">

            <div class="input-box-group">
                <label>Renter Full Name</label>
                <input type="text" name="renter_name" value="<?= htmlspecialchars($default_name) ?>" required placeholder="e.g. Revanth Reddy">
            </div>

            <div class="input-box-group">
                <label>Phone Number</label>
                <input type="text" name="renter_phone" value="<?= htmlspecialchars($default_phone) ?>" required placeholder="e.g. 9876543210">
            </div>

            <div class="input-box-group">
                <label>Trip Start Date</label>
                <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="input-box-group">
                <label>Rental Duration (Days)</label>
                <input type="number" name="rental_days" id="modalDays" value="1" min="1" max="30" required onchange="calculateCarTotal()">
            </div>

            <div class="input-box-group">
                <label>Select Payment Option</label>
                
                <label class="pay-method-card selected" onclick="selectCarPayment(this, 'COD')">
                    <input type="radio" name="pay_radio" value="COD" checked>
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;">Pay at Pickup / COD</strong>
                        <span style="font-size:11px; color:#64748b;">Pay cash or UPI when picking up car</span>
                    </div>
                </label>

                <label class="pay-method-card" onclick="selectCarPayment(this, 'RAZORPAY_DYNAMIC_QR')">
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
                <button type="button" onclick="initiateCarCheckout()" style="flex:1; background:#0D47A1; color:#fff; border:none; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">Confirm Car Rental</button>
                <button type="button" onclick="closeCarModal()" style="background:#f1f5f9; color:#475569; border:none; padding:12px 16px; border-radius:12px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- QR Popup Modal -->
<div class="qr-modal-overlay" id="carQrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="carQrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="carQrAmountDisplay">₹0</span>
        </div>

        <button type="button" onclick="confirmCarQRPayment()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Booking
        </button>
        <button type="button" onclick="closeCarQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let currentPricePerDay = 0;

function openCarModal(id, name, price) {
    document.getElementById('modalCarId').value = id;
    document.getElementById('modalCarName').value = name;
    document.getElementById('modalCarTitle').innerText = name;
    document.getElementById('modalPriceVal').value = price;
    document.getElementById('modalPriceDisplay').innerText = '₹' + price;
    currentPricePerDay = price;
    
    calculateCarTotal();
    document.getElementById('carModal').style.display = 'flex';
}

function closeCarModal() {
    document.getElementById('carModal').style.display = 'none';
}

function calculateCarTotal() {
    let days = parseInt(document.getElementById('modalDays').value) || 1;
    let total = currentPricePerDay * days;
    document.getElementById('modalTotalDisplay').innerText = '₹' + total.toFixed(2);
}

function selectCarPayment(element, methodValue) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById('selectedPaymentMethod').value = methodValue;
}

function initiateCarCheckout() {
    const form = document.getElementById('carBookingForm');
    const name = form.querySelector('[name="renter_name"]').value.trim();
    const phone = form.querySelector('[name="renter_phone"]').value.trim();

    if (!name || !phone) {
        alert('Please fill in your name and phone number.');
        return;
    }

    const payMethod = document.getElementById('selectedPaymentMethod').value;
    let days = parseInt(document.getElementById('modalDays').value) || 1;
    let totalAmount = (currentPricePerDay * days).toFixed(2);

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('carModal').style.display = 'none';
        document.getElementById('carQrcodeBox').innerHTML = '';
        document.getElementById('carQrAmountDisplay').innerText = '₹' + totalAmount;

        const dynamicUpiUrl = `upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=${totalAmount}&cu=INR`;

        new QRCode(document.getElementById("carQrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('carQrModal').style.display = 'flex';
    } else {
        form.submit();
    }
}

function closeCarQrModal() {
    document.getElementById('carQrModal').style.display = 'none';
    document.getElementById('carModal').style.display = 'flex';
}

function confirmCarQRPayment() {
    document.getElementById('carBookingForm').submit();
}
</script>