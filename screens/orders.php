<?php
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='index.php?page=login';</script>"; exit(); }

$user_id = $_SESSION['user_id'];

// Fetch Primary Delivery Address Safely
$primary = null;
$check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id AND is_primary = 1 LIMIT 1");
if (!$check_addr || $check_addr->num_rows === 0) {
    $check_addr = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY id DESC LIMIT 1");
}
if ($check_addr && $check_addr->num_rows > 0) {
    $primary = $check_addr->fetch_assoc();
}

$raw_address = '';
if ($primary) {
    if (!empty($primary['address'])) $raw_address = $primary['address'];
    elseif (!empty($primary['full_address'])) $raw_address = $primary['full_address'];
    elseif (!empty($primary['address_line'])) $raw_address = $primary['address_line'];
    elseif (!empty($primary['location'])) $raw_address = $primary['location'];
}

$full_address = !empty($raw_address) ? htmlspecialchars($raw_address) : 'No address details found.';
$address_title = ($primary && !empty($primary['title'])) ? htmlspecialchars($primary['title']) : 'Current Location';

// Handle Order Placement POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_place_order'])) {
    $pay_method  = trim($_POST['payment_method']);
    $grand_total = floatval($_POST['grand_total']);
    $cart_json   = json_decode($_POST['cart_data'], true);

    if (!empty($cart_json) && $grand_total > 0) {
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, payment_method, delivery_address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("idss", $user_id, $grand_total, $pay_method, $full_address);
        
        if ($stmt->execute()) {
            $order_id = $stmt->insert_id;

            // Insert Order Items
            $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, item_name, price, quantity) VALUES (?, ?, ?, ?)");
            foreach ($cart_json as $item) {
                $stmt_item->bind_param("isdi", $order_id, $item['name'], $item['price'], $item['qty']);
                $stmt_item->execute();
            }

            // Create Initial Driver Tracking Data
            $conn->query("INSERT INTO delivery_locations (order_id, driver_name, driver_phone, current_lat, current_lng) VALUES ($order_id, 'Suresh (QHP Rider)', '9123456789', 16.82820000, 81.89610000)");

            echo "<script>
                    localStorage.removeItem('qhp_cart');
                    window.location.href = 'index.php?page=booking_confirmation&order_id=$order_id';
                  </script>";
            exit();
        }
    }
}
?>

<!-- EasyQRCode CDN for rendering Instant Dynamic QR -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    .cart-container { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 90px; }
    
    .cart-card-box {
        background: #ffffff; border-radius: 20px; padding: 20px;
        border: 1px solid #e2e8f0; box-shadow: 0 6px 20px rgba(0,0,0,0.03); margin-bottom: 20px;
    }

    .cart-address-row { display: flex; align-items: center; justify-content: space-between; gap: 15px; }
    .addr-icon-badge {
        width: 44px; height: 44px; border-radius: 14px; background: #e0f2fe; color: #0D47A1;
        display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
    }

    .cart-item-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
    .cart-item-row:last-child { border-bottom: none; }

    .qty-stepper-btn { display: flex; align-items: center; border: 1.5px solid #0D47A1; border-radius: 12px; overflow: hidden; background: #ffffff; }
    .qty-stepper-btn button { background: none; border: none; color: #0D47A1; font-weight: 900; padding: 6px 12px; cursor: pointer; font-size: 14px; }
    .qty-stepper-btn button:hover { background: #e0f2fe; }
    .qty-stepper-btn span { font-size: 13px; font-weight: 800; padding: 0 8px; color: #0D47A1; }

    .bill-item-row { display: flex; justify-content: space-between; font-size: 13px; color: #64748b; margin-bottom: 10px; font-weight: 600; }
    .bill-item-row.grand-total-row { font-size: 16px; font-weight: 900; color: #1e293b; border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 10px; }

    .pay-method-card { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 14px; margin-bottom: 10px; cursor: pointer; transition: 0.2s ease; }
    .pay-method-card:hover, .pay-method-card.selected { border-color: #0D47A1; background: #f0f6ff; }
    .pay-method-card input { accent-color: #0D47A1; }

    .btn-place-order-bottom { width: 100%; padding: 16px; background: #0D47A1; color: #ffffff; border: none; border-radius: 16px; font-size: 16px; font-weight: 800; cursor: pointer; box-shadow: 0 8px 25px rgba(13, 71, 161, 0.3); transition: background 0.2s ease; }

    /* Custom QR Modal Overlay */
    .qr-modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(15, 23, 42, 0.7); display: none; align-items: center; justify-content: center; z-index: 9999;
    }
    .qr-modal-card {
        background: #ffffff; width: 90%; max-width: 400px; border-radius: 24px; padding: 25px; text-align: center;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2); animation: popUp 0.3s ease;
    }
    @keyframes popUp { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>

<div class="cart-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h3 style="color:#0D47A1; font-size:20px; font-weight:800; margin:0;">
            <i class="fas fa-shopping-bag" style="margin-right:8px;"></i> Checkout Cart
        </h3>
        <a href="index.php?page=food" style="color:#FF9800; text-decoration:none; font-size:13px; font-weight:800;">+ Add More Items</a>
    </div>

    <!-- Delivery Address Card -->
    <div class="cart-card-box">
        <div class="cart-address-row">
            <div style="display:flex; align-items:center; gap:14px; overflow:hidden;">
                <div class="addr-icon-badge"><i class="fas fa-location-dot"></i></div>
                <div>
                    <div style="font-size:14px; font-weight:800; color:#1e293b;">Delivering to <strong><?= $address_title ?></strong></div>
                    <div style="font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px;"><?= $full_address ?></div>
                </div>
            </div>
            <a href="index.php?page=profile" style="color:#0D47A1; font-weight:800; font-size:12px; text-decoration:none;">Change</a>
        </div>
    </div>

    <!-- Cart Items List -->
    <div class="cart-card-box">
        <div style="font-size:15px; font-weight:800; color:#1e293b; margin-bottom:14px;">Your Selected Items</div>
        <div id="cartItemsContainer"></div>
    </div>

    <!-- Bill Details Breakdown -->
    <div class="cart-card-box">
        <div style="font-size:15px; font-weight:800; color:#1e293b; margin-bottom:14px;">Bill Details</div>
        
        <div class="bill-item-row">
            <span>Item Total</span>
            <span id="billItemTotal">₹0</span>
        </div>
        <div class="bill-item-row">
            <span id="feeTitleLabel">Delivery & Tax Charge</span>
            <span id="billDeliveryFee">₹0</span>
        </div>
        
        <div class="bill-item-row grand-total-row">
            <span>To Pay</span>
            <span id="billGrandTotal" style="color:#0D47A1;">₹0</span>
        </div>
    </div>

    <!-- Payment Methods & Submit Form -->
    <form method="POST" id="frmCheckoutOrder">
        <input type="hidden" name="action_place_order" value="1">
        <input type="hidden" name="cart_data" id="hiddenCartData">
        <input type="hidden" name="grand_total" id="hiddenGrandTotal">

        <div class="cart-card-box">
            <div style="font-size:15px; font-weight:800; color:#1e293b; margin-bottom:14px;">Select Payment Option</div>
            
            <label class="pay-method-card selected" onclick="selectPaymentOption(this)">
                <input type="radio" name="payment_method" value="COD" checked>
                <div>
                    <strong style="font-size:14px; color:#1e293b; display:block;">Cash on Delivery (COD)</strong>
                    <span style="font-size:12px; color:#64748b;">Pay via cash or UPI upon delivery</span>
                </div>
            </label>

            <label class="pay-method-card" onclick="selectPaymentOption(this)">
                <input type="radio" name="payment_method" value="RAZORPAY_DYNAMIC_QR">
                <div>
                    <strong style="font-size:14px; color:#1e293b; display:block;"><i class="fas fa-qrcode" style="color:#0D47A1; margin-right:4px;"></i> Scan Dynamic UPI QR Code</strong>
                    <span style="font-size:12px; color:#64748b;">Scan & Pay Exact Amount via GPay/PhonePe</span>
                </div>
            </label>
        </div>

        <button type="button" class="btn-place-order-bottom" onclick="initiatePaymentFlow()">
            Proceed to Pay & Place Order <i class="fas fa-arrow-right" style="margin-left:8px;"></i>
        </button>
    </form>
</div>

<!-- Dynamic UPI QR Popup Modal -->
<div class="qr-modal-overlay" id="qrModal">
    <div class="qr-modal-card">
        <h3 style="margin:0 0 4px; color:#1e293b;">Scan & Pay</h3>
        <p style="color:#64748b; font-size:12px; margin-bottom:15px;">Scan using PhonePe, GPay or Paytm</p>
        
        <div id="qrcodeBox" style="display:flex; justify-content:center; margin:15px 0;"></div>

        <div style="background:#f1f5f9; padding:10px; border-radius:12px; font-weight:800; color:#0D47A1; margin-bottom:15px;">
            Amount: <span id="qrAmountDisplay">₹0</span>
        </div>

        <button type="button" onclick="confirmPaymentAndSubmit()" style="background:#16a34a; color:#fff; border:none; padding:12px 20px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; font-size:14px;">
            <i class="fas fa-check-circle" style="margin-right:6px;"></i> Payment Done - Complete Order
        </button>
        <button type="button" onclick="closeQrModal()" style="background:none; border:none; color:#64748b; margin-top:10px; font-weight:700; cursor:pointer; font-size:12px;">Cancel</button>
    </div>
</div>

<script>
let cartItems = [];
let calculatedGrandTotal = 0;

function getChargesFromImageMatrix(km, hasSpecialItem) {
    // If cart has medicine or grocery, fixed tax/delivery charge is ₹59
    if (hasSpecialItem) {
        return { deliveryCharge: 59, platformCharge: 0 };
    }

    // Otherwise use distance matrix for food items
    let dFee = 20, pFee = 5;
    if (km <= 1.0) { dFee = 20; pFee = 5; }
    else if (km <= 2.0) { dFee = 25; pFee = 5; }
    else if (km <= 3.0) { dFee = 30; pFee = 5; }
    else if (km <= 4.0) { dFee = 35; pFee = 7; }
    else if (km <= 5.0) { dFee = 40; pFee = 7; }
    else if (km <= 6.0) { dFee = 45; pFee = 7; }
    else if (km <= 7.0) { dFee = 55; pFee = 10; }
    else if (km <= 8.0) { dFee = 60; pFee = 10; }
    else if (km <= 9.0) { dFee = 65; pFee = 15; }
    else { dFee = 75; pFee = 15; }
    return { deliveryCharge: dFee, platformCharge: pFee };
}

function loadAndRenderCart() {
    cartItems = JSON.parse(localStorage.getItem('qhp_cart')) || [];
    const container = document.getElementById('cartItemsContainer');

    if (!cartItems || cartItems.length === 0) {
        container.innerHTML = `
            <div style="text-align:center; padding:35px 10px; color:#64748b;">
                <i class="fas fa-basket-shopping" style="font-size:42px; color:#cbd5e1; margin-bottom:12px;"></i>
                <h4 style="color:#1e293b; margin-bottom:4px;">Your Cart is Empty!</h4>
                <a href="index.php?page=food" class="btn" style="background:#0D47A1; color:#fff; margin-top:14px; display:inline-block; text-decoration:none; font-size:13px; padding:10px 20px; border-radius:20px; font-weight:800;">Browse Food Section</a>
            </div>`;
        updateBillCalculations(0, 0, false);
        return;
    }

    let html = '';
    let totalItemsSum = 0;
    let maxDistanceKm = 1.0;
    let hasSpecialItem = false;

    cartItems.forEach((item, index) => {
        const itemSum = Number(item.price) * Number(item.qty);
        totalItemsSum += itemSum;
        
        if (item.distance_km && Number(item.distance_km) > maxDistanceKm) {
            maxDistanceKm = Number(item.distance_km);
        }

        // Check if item is medicine or grocery
        if (item.type === 'medicine' || item.type === 'grocery' || (item.name && (item.name.toLowerCase().includes('tablet') || item.name.toLowerCase().includes('capsule')))) {
            hasSpecialItem = true;
        }

        html += `
            <div class="cart-item-row">
                <div>
                    <strong style="font-size:15px; color:#1e293b; display:block; margin-bottom:2px;">${item.name}</strong>
                    <span style="font-size:14px; color:#0D47A1; font-weight:800;">₹${item.price}</span>
                </div>
                <div class="qty-stepper-btn">
                    <button type="button" onclick="changeQuantity(${index}, -1)">-</button>
                    <span>${item.qty}</span>
                    <button type="button" onclick="changeQuantity(${index}, 1)">+</button>
                </div>
            </div>`;
    });

    container.innerHTML = html;
    updateBillCalculations(totalItemsSum, maxDistanceKm, hasSpecialItem);
}

function changeQuantity(index, change) {
    cartItems[index].qty += change;
    if (cartItems[index].qty <= 0) cartItems.splice(index, 1);
    localStorage.setItem('qhp_cart', JSON.stringify(cartItems));
    loadAndRenderCart();
}

function updateBillCalculations(itemSum, distKm, hasSpecialItem) {
    const charges = getChargesFromImageMatrix(distKm, hasSpecialItem);
    const totalFee = itemSum > 0 ? (hasSpecialItem ? charges.deliveryCharge : (charges.deliveryCharge + charges.platformCharge)) : 0;
    
    calculatedGrandTotal = itemSum + totalFee;

    if (hasSpecialItem) {
        document.getElementById('feeTitleLabel').innerText = "Fixed Delivery & Tax (Medicine/Grocery)";
        document.getElementById('billDeliveryFee').innerText = `₹${totalFee}`;
    } 
    else {
        document.getElementById('feeTitleLabel').innerText = `Delivery Charge (${distKm} KM) + Platform Fee`;
        document.getElementById('billDeliveryFee').innerText = `₹${totalFee}`;
    }

    document.getElementById('billItemTotal').innerText = `₹${itemSum}`;
    document.getElementById('billGrandTotal').innerText = `₹${calculatedGrandTotal}`;

    document.getElementById('hiddenCartData').value = JSON.stringify(cartItems);
    document.getElementById('hiddenGrandTotal').value = calculatedGrandTotal;
}

function selectPaymentOption(element) {
    document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    element.querySelector('input').checked = true;
}

function initiatePaymentFlow() {
    if (!cartItems || cartItems.length === 0) {
        alert('Your cart is empty!');
        return;
    }

    const payMethod = document.querySelector('input[name="payment_method"]:checked').value;

    if (payMethod === 'RAZORPAY_DYNAMIC_QR') {
        document.getElementById('qrAmountDisplay').innerText = `₹${calculatedGrandTotal}`;
        document.getElementById('qrcodeBox').innerHTML = '';

        const dynamicUpiUrl = `upi://pay?pa=revanth@upi&pn=QHP%20SuperApp&am=${calculatedGrandTotal}&cu=INR`;

        new QRCode(document.getElementById("qrcodeBox"), {
            text: dynamicUpiUrl,
            width: 180,
            height: 180
        });

        document.getElementById('qrModal').style.display = 'flex';
    } else {
        document.getElementById('frmCheckoutOrder').submit();
    }
}

function closeQrModal() {
    document.getElementById('qrModal').style.display = 'none';
}

function confirmPaymentAndSubmit() {
    document.getElementById('frmCheckoutOrder').submit();
}

document.addEventListener("DOMContentLoaded", loadAndRenderCart);
</script>