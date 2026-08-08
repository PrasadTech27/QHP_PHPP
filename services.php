<?php
require_once 'otp_helper.php';

$cat_slug = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$sub_param = isset($_GET['sub']) ? trim($_GET['sub']) : '';

if (empty($cat_slug) && !empty($sub_param)) {
    $sub_map = [
        'AC Technicians' => 'ac_tech',
        'Plumbers' => 'plumber',
        'Carpenters' => 'carpenter',
        'Car Servicing' => 'car_service',
        'Home Beauty' => 'beauty',
        'Tailoring' => 'tailoring',
        'Photography' => 'photography',
        'Event Planners' => 'events'
    ];
    if (isset($sub_map[$sub_param])) {
        $cat_slug = $sub_map[$sub_param];
    }
}

if (empty($cat_slug)) {
    $cat_slug = 'ac_tech';
}

$stmt = $conn->prepare("SELECT * FROM categories WHERE slug = ?");
$stmt->bind_param("s", $cat_slug);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    echo "Category not found!";
    exit();
}

$category_id = $category['id'];
$selected_date = isset($_REQUEST['booking_date']) ? $_REQUEST['booking_date'] : date('Y-m-d');
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['login_temp_user']) ? $_SESSION['login_temp_user']['id'] : 0);

if ($user_id === 0) {
    echo "<script>window.location.href = 'index.php?page=login';</script>";
    exit();
}

$error_msg = "";

// Theme mappings
$theme_configs = [
    'ac_tech'     => ['color' => '#0D47A1', 'bg' => '#eef2ff', 'icon' => 'fa-snowflake'],
    'plumber'     => ['color' => '#0284c7', 'bg' => '#e0f2fe', 'icon' => 'fa-faucet'],
    'carpenter'   => ['color' => '#b45309', 'bg' => '#fef3c7', 'icon' => 'fa-hammer'],
    'car_service' => ['color' => '#475569', 'bg' => '#f1f5f9', 'icon' => 'fa-car'],
    'beauty'      => ['color' => '#db2777', 'bg' => '#fce7f3', 'icon' => 'fa-paint-brush'],
    'tailoring'   => ['color' => '#7c3aed', 'bg' => '#f3e8ff', 'icon' => 'fa-tshirt'],
    'photography' => ['color' => '#ea580c', 'bg' => '#ffedd5', 'icon' => 'fa-camera'],
    'events'      => ['color' => '#16a34a', 'bg' => '#dcfce7', 'icon' => 'fa-glass-cheers']
];
$current_theme = isset($theme_configs[$cat_slug]) ? $theme_configs[$cat_slug] : $theme_configs['ac_tech'];

// Handle Final Booking Form Submission (Creating the Order safely)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_service_booking'])) {
    $slot_number   = intval($_POST['slot_number']);
    $client_name   = trim($_POST['client_name']);
    $client_phone  = trim($_POST['client_phone']);
    $delivery_addr = trim($_POST['delivery_address']);
    $pay_method    = trim($_POST['payment_method']); // COD or RAZORPAY_DYNAMIC_QR
    $price         = 600.00;

    // 1. Check Slot Capacity Limit (Max 10)
    $check_count = $conn->prepare("SELECT COUNT(*) as total FROM slot_bookings WHERE category_id = ? AND booking_date = ? AND slot_number = ?");
    $check_count->bind_param("isi", $category_id, $selected_date, $slot_number);
    $check_count->execute();
    $booked_count = $check_count->get_result()->fetch_assoc()['total'];

    if ($booked_count >= 10) {
        $error_msg = "Sorry! This slot is full (Max 10 members reached).";
    } else {
        try {
            // 2. Try recording Slot Booking (Safe from duplicate entry crash)
            $insert_slot = $conn->prepare("INSERT INTO slot_bookings (user_id, category_id, booking_date, slot_number, amount_paid) VALUES (?, ?, ?, ?, ?)");
            $insert_slot->bind_param("iisis", $user_id, $category_id, $selected_date, $slot_number, $price);
            
            if ($insert_slot->execute()) {
                $formatted_address = "[SERVICE BOOKING - " . $category['name'] . "] Date: $selected_date | Slot: $slot_number | Contact: $client_name ($client_phone) | Addr: $delivery_addr";

                // 3. Insert into main orders table
                $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Pending')");
                $stmt_order->bind_param("idss", $user_id, $price, $pay_method, $formatted_address);
                
                if ($stmt_order->execute()) {
                    $order_id = $stmt_order->insert_id;

                    $item_title = $category['name'] . " Booking (Slot $slot_number)";
                    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, 1)");
                    $stmt_item->bind_param("isd", $order_id, $item_title, $price);
                    $stmt_item->execute();

                    $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'QHP Service Partner', '9123456789', 16.82820000, 81.89610000)");

                    echo "<script>window.location.href='index.php?page=booking_confirmation&order_id=$order_id';</script>";
                    exit();
                }
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error_msg = "You have already booked this time slot for this date!";
            } else {
                $error_msg = "An error occurred while booking. Please try again.";
            }
        }
    }
}

$slots = [
    1 => ['time' => '9:00 AM - 11:00 AM', 'label' => 'Slot 1'],
    2 => ['time' => '11:00 AM - 1:00 PM', 'label' => 'Slot 2'],
    3 => ['time' => '2:00 PM - 4:00 PM', 'label' => 'Slot 3'],
    4 => ['time' => '4:00 PM - 6:00 PM', 'label' => 'Slot 4']
];

$user_info = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$default_name = $user_info['full_name'] ?? '';
$default_phone = $user_info['phone'] ?? '';
?>

<!-- EasyQRCode CDN for rendering Instant Dynamic QR -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .unified-service-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 750px; margin: 0 auto; }
    .service-top-banner { background: #ffffff; border-radius: 22px; padding: 24px; border: 1px solid #e2e8f0; box-shadow: 0 6px 20px rgba(0,0,0,0.04); margin-bottom: 20px; display: flex; flex-direction: column; gap: 15px; }
    .category-switcher-pills { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 6px; scrollbar-width: none; }
    .category-switcher-pills::-webkit-scrollbar { display: none; }
    .cat-pill { padding: 8px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; text-decoration: none; white-space: nowrap; border: 1.5px solid #cbd5e1; background: #f8fafc; color: #475569; transition: all 0.2s; }
    .cat-pill.active { background: <?= $current_theme['color'] ?>; color: #fff; border-color: <?= $current_theme['color'] ?>; }
    
    .slot-card { background: #fff; border-radius: 18px; border: 1.5px solid #e2e8f0; padding: 18px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
    .slot-card.full { background: #f8fafc; border-color: #cbd5e1; opacity: 0.75; }
    .btn-book-slot { background: <?= $current_theme['color'] ?>; color: #fff; border: none; padding: 10px 20px; border-radius: 14px; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

    /* Modal Styling matching order history & cart checkout */
    .booking-modal { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
    .booking-modal-card { background:#fff; width:90%; max-width:400px; border-radius:24px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; text-align: center; }
    .input-box-group { margin-bottom: 14px; text-align: left; }
    .input-box-group label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .input-box-group input, .input-box-group textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font-size: 14px; font-weight: 600; outline: none; box-sizing: border-box; }

    /* Unified Payment Cards Styling matching orders.php */
    .pay-method-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: 0.2s ease; text-align: left; }
    .pay-method-card:hover, .pay-method-card.selected { border-color: #0D47A1; background: #f0f6ff; }
    .pay-method-card input { accent-color: #0D47A1; }

    /* QR Popup Styles */
    .qr-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 10000; }
    .qr-modal-card { background: #ffffff; width: 90%; max-width: 400px; border-radius: 24px; padding: 25px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
</style>

<div class="unified-service-wrapper">
    <div class="service-top-banner">
        <div style="display:flex; align-items:center; gap:14px;">
            <div style="width:50px; height:50px; border-radius:16px; background:<?= $current_theme['bg'] ?>; color:<?= $current_theme['color'] ?>; display:flex; align-items:center; justify-content:center; font-size:22px;">
                <i class="fas <?= $current_theme['icon'] ?>"></i>
            </div>
            <div>
                <h2 style="color:#1e293b; font-size:20px; font-weight:800; margin:0 0 2px;"><?= htmlspecialchars($category['name']) ?></h2>
                <p style="color:#64748b; font-size:13px; margin:0;">Fixed Price: <strong style="color:<?= $current_theme['color'] ?>;">₹600</strong> per slot (Max 10 members/slot)</p>
            </div>
        </div>

        <div class="category-switcher-pills">
            <?php
            $cats = $conn->query("SELECT * FROM categories");
            while($c = $cats->fetch_assoc()) {
                $is_active = ($c['slug'] === $cat_slug) ? 'active' : '';
                echo "<a href='index.php?page=services&cat={$c['slug']}&booking_date={$selected_date}' class='cat-pill {$is_active}'>{$c['name']}</a>";
            }
            ?>
        </div>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13px; border:1px solid #fca5a5;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div style="background:#fff; padding:16px 20px; border-radius:18px; border:1px solid #e2e8f0; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:13px; font-weight:800; color:#1e293b;">Select Service Date:</span>
        <form method="GET" action="index.php" style="margin:0;">
            <input type="hidden" name="page" value="services">
            <input type="hidden" name="cat" value="<?= $cat_slug ?>">
            <input type="date" name="booking_date" value="<?= $selected_date ?>" onchange="this.form.submit()" style="padding:8px 12px; border:1.5px solid #cbd5e1; border-radius:12px; font-size:13px; font-weight:600; outline:none; color:#1e293b;">
        </form>
    </div>

    <div style="font-size:16px; font-weight:800; color:#0D47A1; margin-bottom:14px;">Available Time Slots</div>

    <?php foreach($slots as $num => $slot): 
        $count_q = $conn->prepare("SELECT COUNT(*) as total FROM slot_bookings WHERE category_id = ? AND booking_date = ? AND slot_number = ?");
        $count_q->bind_param("isi", $category_id, $selected_date, $num);
        $count_q->execute();
        $booked_users = $count_q->get_result()->fetch_assoc()['total'];
        $is_full = ($booked_users >= 10);
    ?>
        <div class="slot-card <?= $is_full ? 'full' : '' ?>">
            <div>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
                    <span style="font-size:12px; font-weight:800; color:<?= $current_theme['color'] ?>; background:<?= $current_theme['bg'] ?>; padding:2px 8px; border-radius:6px;"><?= $slot['label'] ?></span>
                    <span style="font-size:11px; font-weight:700; color:<?= $is_full ? '#dc2626' : '#16a34a' ?>; background:<?= $is_full ? '#fee2e2' : '#dcfce7' ?>; padding:2px 8px; border-radius:6px;">
                        <?= $is_full ? 'Slot Full (10/10)' : (10 - $booked_users) . ' slots remaining' ?>
                    </span>
                </div>
                <div style="font-size:16px; font-weight:800; color:#1e293b;"><?= $slot['time'] ?></div>
                <div style="font-size:13px; color:#64748b; font-weight:600; margin-top:2px;">Price: ₹600</div>
            </div>

            <div>
                <?php if($is_full): ?>
                    <button disabled class="btn-book-slot" style="background:#cbd5e1; cursor:not-allowed;">Fully Booked</button>
                <?php else: ?>
                    <button type="button" onclick="openBookingDetailsModal(<?= $num ?>)" class="btn-book-slot">Book Now (₹600)</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Details Modal Popup -->
<div class="booking-modal" id="bookingModal">
    <div class="booking-modal-card">
        <h3 style="color:#0D47A1; font-weight:800; margin-top:0; margin-bottom:4px;">Complete Booking Details</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Fixed Service Price: <strong style="color:#0D47A1;">₹600</strong></p>

        <form method="POST" id="serviceBookingForm">
            <input type="hidden" name="confirm_service_booking" value="1">
            <input type="hidden" name="slot_number" id="modalSlotNumber">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="COD">

            <div class="input-box-group">
                <label>Your Full Name</label>
                <input type="text" name="client_name" value="<?= htmlspecialchars($default_name) ?>" required placeholder="e.g. Revanth Reddy">
            </div>

            <div class="input-box-group">
                <label>Phone Number</label>
                <input type="text" name="client_phone" value="<?= htmlspecialchars($default_phone) ?>" required placeholder="e.g. 9876543210">
            </div>

            <div class="input-box-group">
                <label>Delivery / Service Address</label>
                <textarea name="delivery_address" rows="2" required placeholder="House No, Street, Landmark..."></textarea>
            </div>

            <div class="input-box-group">
                <label>Select Payment Option</label>
                
                <label class="pay-method-card selected" onclick="selectPaymentOption(this, 'COD')">
                    <input type="radio" name="pay_radio" value="COD" checked>
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;">Cash on Delivery (COD)</strong>
                        <span style="font-size:11px; color:#64748b;">Pay cash or UPI upon service completion</span>
                    </div>
                </label>

                <label class="pay-method-card" onclick="selectPaymentOption(this, 'RAZORPAY_DYNAMIC_QR')">
                    <input type="radio" name="pay_radio" value="RAZORPAY_DYNAMIC_QR">
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;"><i class="fas fa-qrcode" style="color:#0D47A1; margin-right:4px;"></i> Scan Dynamic UPI QR Code</strong>
                        <span style="font-size:11px; color:#64748b;">Scan & Pay Exact Amount via GPay/PhonePe</span>
                    </div>
                </label>
            </div>

            <div style="display:flex; gap:10px; margin-top:15px;">
                <button type="button" onclick="initiateServiceCheckout()" style="flex:1; background:#0D47A1; color:#fff; border:none; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">Proceed to Pay & Book</button>
                <button type="button" onclick="closeBookingModal()" style="background:#f1f5f9; color:#475569; border:none; padding:12px 16px; border-radius:12px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Dynamic UPI QR Popup Modal matching image layout -->
<div class="qr-modal-overlay" id="qrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="qrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="qrAmountDisplay">₹600</span>
        </div>

        <button type="button" onclick="confirmQRPaymentAndSubmit()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Order
        </button>
        <button type="button" onclick="closeQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let qrGenerated = false;

function openBookingDetailsModal(slotNum) {
    document.getElementById('modalSlotNumber').value = slotNum;
    document.getElementById('bookingModal').style.display = 'flex';
}

function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

function selectPaymentOption(element, methodValue) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById('selectedPaymentMethod').value = methodValue;
}

function initiateServiceCheckout() {
    const form = document.getElementById('serviceBookingForm');
    // Basic validation check
    const name = form.querySelector('[name="client_name"]').value.trim();
    const phone = form.querySelector('[name="client_phone"]').value.trim();
    const addr = form.querySelector('[name="delivery_address"]').value.trim();

    if (!name || !phone || !addr) {
        alert('Please fill in all required booking details.');
        return;
    }

    const payMethod = document.getElementById('selectedPaymentMethod').value;

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('bookingModal').style.display = 'none';
        document.getElementById('qrcodeBox').innerHTML = '';

        const dynamicUpiUrl = "upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=600.00&cu=INR";

        new QRCode(document.getElementById("qrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('qrModal').style.display = 'flex';
    } else {
        form.submit();
    }
}

function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
    document.getElementById('bookingModal').style.display = 'flex';
}

function confirmQRPaymentAndSubmit() {
    document.getElementById('serviceBookingForm').submit();
}
</script>