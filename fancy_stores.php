<?php
require_once 'otp_helper.php';

// Auto-create fancy stores table if it doesn't exist and insert sample items
$conn->query("CREATE TABLE IF NOT EXISTS fancy_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description TEXT
)");

$check_fancy = $conn->query("SELECT COUNT(*) as total FROM fancy_items");
if ($check_fancy && $check_fancy->fetch_assoc()['total'] == 0) {
    $conn->query("INSERT INTO fancy_items (item_name, category, price, image_url, description) VALUES 
        ('Bridal Makeup Kit', 'Cosmetics', 1250.00, 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?q=80&w=800&auto=format&fit=crop', 'Complete professional cosmetic kit including lipsticks, palettes, and brushes.'),
        ('Designer Handbag', 'Accessories', 890.00, 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?q=80&w=800&auto=format&fit=crop', 'Stylish, durable leather handbag suitable for daily use and parties.'),
        ('Artificial Diamond Necklace', 'Jewellery', 1500.00, 'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?q=80&w=800&auto=format&fit=crop', 'Elegant party-wear necklace set with matching earrings.')");
}

$fancy_result = $conn->query("SELECT * FROM fancy_items ORDER BY id ASC");

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['login_temp_user']) ? $_SESSION['login_temp_user']['id'] : 0);

if ($user_id === 0) {
    echo "<script>window.location.href = 'index.php?page=login';</script>";
    exit();
}

$error_msg = "";

// Handle Fancy Store Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_fancy'])) {
    $item_id     = intval($_POST['item_id']);
    $item_name   = trim($_POST['item_name']);
    $buyer_name  = trim($_POST['buyer_name']);
    $buyer_phone = trim($_POST['buyer_phone']);
    $delivery_addr = trim($_POST['delivery_address']);
    $qty         = intval($_POST['item_qty']);
    $unit_price  = floatval($_POST['unit_price']);
    $pay_method  = trim($_POST['payment_method']);
    
    $total_amount = $unit_price * max(1, $qty);

    $formatted_address = "[FANCY STORE - " . $item_name . "] Addr: $delivery_addr | Contact: $buyer_name ($buyer_phone)";

    $stmt_order = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt_order->bind_param("idss", $user_id, $total_amount, $pay_method, $formatted_address);
    
    if ($stmt_order->execute()) {
        $order_id = $stmt_order->insert_id;

        $item_title = $item_name . " (Fancy Store)";
        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
        $stmt_item->bind_param("isdi", $order_id, $item_title, $unit_price, $qty);
        $stmt_item->execute();

        $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'QHP Fancy Partner', '9123456789', 16.82820000, 81.89610000)");

        echo "<script>window.location.href='index.php?page=booking_confirmation&order_id=$order_id';</script>";
        exit();
    } else {
        $error_msg = "Failed to place fancy store order. Please try again.";
    }
}

$user_info = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$default_name = $user_info['full_name'] ?? '';
$default_phone = $user_info['phone'] ?? '';
?>

<!-- EasyQRCode CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .fancy-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; max-width: 800px; margin: 0 auto; }
    .fancy-banner { background: linear-gradient(135deg, #ec4899, #db2777); color: #fff; border-radius: 22px; padding: 24px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(236,72,153,0.2); }
    
    .fancy-card { background: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
    @media(min-width: 600px) { .fancy-card { flex-direction: row; } }
    
    .fancy-img { width: 100%; height: 200px; object-fit: cover; }
    @media(min-width: 600px) { .fancy-img { width: 220px; height: auto; } }

    .fancy-content { padding: 20px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .btn-buy-fancy { background: #0D47A1; color: #fff; border: none; padding: 10px 20px; border-radius: 14px; font-weight: 800; font-size: 13px; cursor: pointer; box-shadow: 0 4px 12px rgba(13,71,161,0.2); text-align: center; text-decoration: none; }

    .booking-modal { position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.7); display:none; align-items:center; justify-content:center; z-index:9999; }
    .booking-modal-card { background:#fff; width:90%; max-width:420px; border-radius:24px; padding:25px; box-shadow:0 20px 40px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; text-align: left; }
    .input-box-group { margin-bottom: 14px; }
    .input-box-group label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .input-box-group input, .input-box-group textarea, .input-box-group select { width: 100%; padding: 12px 14px; border: 1.5px solid #cbd5e1; border-radius: 12px; font-size: 14px; font-weight: 600; outline: none; box-sizing: border-box; }

    .pay-method-card { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 8px; cursor: pointer; transition: 0.2s ease; }
    .pay-method-card:hover, .pay-method-card.selected { border-color: #0D47A1; background: #f0f6ff; }
    .pay-method-card input { accent-color: #0D47A1; }

    .qr-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 10000; }
    .qr-modal-card { background: #ffffff; width: 90%; max-width: 400px; border-radius: 24px; padding: 25px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
</style>

<div class="fancy-wrapper">
    <div class="fancy-banner">
        <h2 style="margin:0 0 6px; font-size:22px; font-weight:800;"><i class="fas fa-gift" style="margin-right:8px;"></i> Fancy Stores & Accessories</h2>
        <p style="margin:0; font-size:13px; opacity:0.9;">Shop cosmetics, fashion accessories, jewellery, and gift items delivered to your doorstep.</p>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:12px 18px; border-radius:14px; margin-bottom:18px; font-weight:700; font-size:13px;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <div>
        <?php while($item = $fancy_result->fetch_assoc()): ?>
            <div class="fancy-card">
                <img src="<?= htmlspecialchars($item['image_url']) ?>" class="fancy-img" alt="Fancy Item">
                <div class="fancy-content">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin:0;"><?= htmlspecialchars($item['item_name']) ?></h3>
                            <span style="font-size:11px; font-weight:800; background:#fce7f3; color:#db2777; padding:3px 10px; border-radius:10px;"><?= htmlspecialchars($item['category']) ?></span>
                        </div>
                        <p style="font-size:13px; color:#475569; margin:0 0 12px; line-height:1.4;"><?= htmlspecialchars($item['description']) ?></p>
                    </div>
                    
                    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid #f1f5f9; padding-top:12px;">
                        <div>
                            <span style="font-size:11px; color:#64748b; display:block;">Price</span>
                            <strong style="font-size:17px; color:#0D47A1;">₹<?= number_format($item['price'], 2) ?></strong>
                        </div>
                        <button type="button" onclick="openFancyModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name'])) ?>', <?= $item['price'] ?>)" class="btn-buy-fancy">
                            Buy Now <i class="fas fa-arrow-right" style="margin-left:4px;"></i>
                        </button>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Order Modal -->
<div class="booking-modal" id="fancyModal">
    <div class="booking-modal-card">
        <h3 style="color:#0D47A1; font-weight:800; margin-top:0; margin-bottom:4px;" id="modalFancyTitle">Complete Order</h3>
        <p style="font-size:13px; color:#64748b; margin-bottom:16px;">Unit Price: <strong style="color:#0D47A1;" id="modalPriceDisplay">₹0</strong></p>

        <form method="POST" id="fancyOrderForm">
            <input type="hidden" name="buy_fancy" value="1">
            <input type="hidden" name="item_id" id="modalItemId">
            <input type="hidden" name="item_name" id="modalItemName">
            <input type="hidden" name="unit_price" id="modalPriceVal">
            <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="COD">

            <div class="input-box-group">
                <label>Your Full Name</label>
                <input type="text" name="buyer_name" value="<?= htmlspecialchars($default_name) ?>" required placeholder="e.g. Revanth Reddy">
            </div>

            <div class="input-box-group">
                <label>Phone Number</label>
                <input type="text" name="buyer_phone" value="<?= htmlspecialchars($default_phone) ?>" required placeholder="e.g. 9876543210">
            </div>

            <div class="input-box-group">
                <label>Delivery Address</label>
                <textarea name="delivery_address" rows="2" required placeholder="House No, Street, Landmark..."></textarea>
            </div>

            <div class="input-box-group">
                <label>Quantity</label>
                <input type="number" name="item_qty" id="modalQty" value="1" min="1" max="20" required onchange="calculateFancyTotal()">
            </div>

            <div class="input-box-group">
                <label>Select Payment Option</label>
                
                <label class="pay-method-card selected" onclick="selectFancyPayment(this, 'COD')">
                    <input type="radio" name="pay_radio" value="COD" checked>
                    <div>
                        <strong style="font-size:13px; color:#1e293b; display:block;">Cash on Delivery (COD)</strong>
                        <span style="font-size:11px; color:#64748b;">Pay cash or UPI upon delivery</span>
                    </div>
                </label>

                <label class="pay-method-card" onclick="selectFancyPayment(this, 'RAZORPAY_DYNAMIC_QR')">
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
                <button type="button" onclick="initiateFancyCheckout()" style="flex:1; background:#0D47A1; color:#fff; border:none; padding:12px; border-radius:12px; font-weight:800; cursor:pointer;">Confirm & Place Order</button>
                <button type="button" onclick="closeFancyModal()" style="background:#f1f5f9; color:#475569; border:none; padding:12px 16px; border-radius:12px; font-weight:800; cursor:pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- QR Popup Modal -->
<div class="qr-modal-overlay" id="fancyQrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="fancyQrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="fancyQrAmountDisplay">₹0</span>
        </div>

        <button type="button" onclick="confirmFancyQRPayment()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Order
        </button>
        <button type="button" onclick="closeFancyQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let currentUnitPrice = 0;

function openFancyModal(id, name, price) {
    document.getElementById('modalItemId').value = id;
    document.getElementById('modalItemName').value = name;
    document.getElementById('modalFancyTitle').innerText = name;
    document.getElementById('modalPriceVal').value = price;
    document.getElementById('modalPriceDisplay').innerText = '₹' + price;
    currentUnitPrice = price;
    
    calculateFancyTotal();
    document.getElementById('fancyModal').style.display = 'flex';
}

function closeFancyModal() {
    document.getElementById('fancyModal').style.display = 'none';
}

function calculateFancyTotal() {
    let qty = parseInt(document.getElementById('modalQty').value) || 1;
    let total = currentUnitPrice * qty;
    document.getElementById('modalTotalDisplay').innerText = '₹' + total.toFixed(2);
}

function selectFancyPayment(element, methodValue) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
    document.getElementById('selectedPaymentMethod').value = methodValue;
}

function initiateFancyCheckout() {
    const form = document.getElementById('fancyOrderForm');
    const name = form.querySelector('[name="buyer_name"]').value.trim();
    const phone = form.querySelector('[name="buyer_phone"]').value.trim();
    const addr = form.querySelector('[name="delivery_address"]').value.trim();

    if (!name || !phone || !addr) {
        alert('Please fill in your name, phone number, and delivery address.');
        return;
    }

    const payMethod = document.getElementById('selectedPaymentMethod').value;
    let qty = parseInt(document.getElementById('modalQty').value) || 1;
    let totalAmount = (currentUnitPrice * qty).toFixed(2);

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('fancyModal').style.display = 'none';
        document.getElementById('fancyQrcodeBox').innerHTML = '';
        document.getElementById('fancyQrAmountDisplay').innerText = '₹' + totalAmount;

        const dynamicUpiUrl = `upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=${totalAmount}&cu=INR`;

        new QRCode(document.getElementById("fancyQrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('fancyQrModal').style.display = 'flex';
    } else {
        form.submit();
    }
}

function closeFancyQrModal() {
    document.getElementById('fancyQrModal').style.display = 'none';
    document.getElementById('fancyModal').style.display = 'flex';
}

function confirmFancyQRPayment() {
    document.getElementById('fancyOrderForm').submit();
}
</script>