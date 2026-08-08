<?php
if (!isset($_SESSION['user_id'])) { 
    echo "<script>window.location.href='index.php?page=login';</script>"; 
    exit(); 
}

$user_id = $_SESSION['user_id'];

// Fetch All User Orders with Restaurant Name mapping
$orders_query = $conn->query("
    SELECT o.*, 
    r.name as restaurant_name,
    COUNT(i.id) as total_items_count
    FROM orders o
    LEFT JOIN restaurants r ON o.restaurant_id = r.id
    LEFT JOIN order_items i ON o.id = i.order_id
    WHERE o.user_id = $user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
?>

<style>
    .history-wrapper {
        font-family: 'Segoe UI', -apple-system, sans-serif;
        padding-bottom: 90px;
        max-width: 800px;
        margin: 0 auto;
    }

    .history-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
    }

    .order-history-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 16px;
    }

    .card-top-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    /* Status Badges */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .status-pending { background: #fef3c7; color: #b45309; }
    .status-accepted { background: #e0f2fe; color: #0369a1; }
    .status-ontheway { background: #fae8ff; color: #86198f; }
    .status-reached { background: #ffedd5; color: #c2410c; }
    .status-delivered { background: #dcfce7; color: #15803d; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }

    .item-preview-list {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 14px;
    }
    .item-preview-row {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: #475569;
        margin-bottom: 4px;
    }

    .btn-track-order {
        background: #0284c7;
        color: #ffffff;
        border: none;
        padding: 10px 16px;
        border-radius: 12px;
        font-weight: 800;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
        width: 100%;
        justify-content: center;
        margin-bottom: 12px;
    }
</style>

<div class="history-wrapper">
    <div class="history-header">
        <h3 style="color:#0D47A1; font-size:20px; font-weight:800; margin:0;">
            <i class="fas fa-clock-rotate-left" style="margin-right:8px;"></i> My Orders & Status
        </h3>
        <a href="index.php?page=food" style="color:#FF9800; text-decoration:none; font-size:13px; font-weight:800;">+ Order More</a>
    </div>

    <div id="liveOrdersContainer">
        <?php if ($orders_query && $orders_query->num_rows > 0): ?>
            <?php while($order = $orders_query->fetch_assoc()): 
                $order_id = $order['id'];
                $status = strtolower(trim($order['status'] ?? 'pending'));
                $raw_address = $order['delivery_address'] ?? '';
                
                // Check order type definitions
                $is_parcel = (strpos($raw_address, '[PARCEL') !== false);
                $is_service = (strpos($raw_address, '[SERVICE BOOKING') !== false);
                
                // Check items or titles to properly identify grocery or specialized hubs if missing restaurant name
                $items_res = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
                $first_item_name = '';
                $items_array = [];
                while($it = $items_res->fetch_assoc()) {
                    if (empty($first_item_name)) $first_item_name = $it['item_name'];
                    $items_array[] = $it;
                }
                // Reset pointer or re-query if needed, but we collected into $items_array
                
                $restaurant_name = !empty($order['restaurant_name']) ? $order['restaurant_name'] : '';
                
                if ($is_parcel) {
                    $restaurant_name = 'Express Parcel Delivery';
                } elseif ($is_service) {
                    if (preg_match('/\[SERVICE BOOKING - (.*?)\]/', $raw_address, $matches)) {
                        $restaurant_name = $matches[1];
                    } else {
                        $restaurant_name = 'Professional Home Service';
                    }
                } elseif (empty($restaurant_name)) {
                    // Detect if Grocery or Medicines based on items or address text
                    if (stripos($first_item_name, 'grocery') !== false || stripos($raw_address, 'grocery') !== false) {
                        $restaurant_name = 'Grocery Hub';
                    } elseif (stripos($first_item_name, 'tablet') !== false || stripos($first_item_name, 'capsule') !== false || stripos($raw_address, 'medicine') !== false) {
                        $restaurant_name = 'Pharmacy Hub';
                    } else {
                        $restaurant_name = 'Food & General Order';
                    }
                }
                
                // Format Status display mappings
                $status_class = 'status-pending';
                $status_icon = 'fa-clock';
                $status_text = 'Pending';

                if ($status === 'accepted') {
                    $status_class = 'status-accepted';
                    $status_icon = 'fa-check-circle';
                    $status_text = 'Accepted';
                } elseif ($status === 'on the way' || $status === 'ontheway') {
                    $status_class = 'status-ontheway';
                    $status_icon = 'fa-motorcycle';
                    $status_text = 'On the Way';
                } elseif ($status === 'reached') {
                    $status_class = 'status-reached';
                    $status_icon = 'fa-location-dot';
                    $status_text = 'Reached Doorstep';
                } elseif ($status === 'delivered') {
                    $status_class = 'status-delivered';
                    $status_icon = 'fa-circle-check';
                    $status_text = 'Completed / Delivered';
                } elseif ($status === 'cancelled') {
                    $status_class = 'status-cancelled';
                    $status_icon = 'fa-circle-xmark';
                    $status_text = 'Cancelled';
                }
            ?>
                <div class="order-history-card">
                    <div class="card-top-bar">
                        <div>
                            <strong style="color:#1e293b; font-size:15px; display:block;">
                                <?php if ($is_parcel): ?>
                                    <i class="fas fa-box-open" style="color:#FF9800; margin-right:4px;"></i>
                                <?php elseif ($is_service): ?>
                                    <i class="fas fa-tools" style="color:#0D47A1; margin-right:4px;"></i>
                                <?php else: ?>
                                    <i class="fas fa-shopping-bag" style="color:#16a34a; margin-right:4px;"></i>
                                <?php endif; ?>
                                #QHP-<?= $order_id ?> (<?= htmlspecialchars($restaurant_name) ?>)
                            </strong>
                            <span style="font-size:12px; color:#94a3b8;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                        </div>
                        <span class="status-badge <?= $status_class ?>">
                            <i class="fas <?= $status_icon ?>"></i> <?= $status_text ?>
                        </span>
                    </div>

                    <!-- Details Box Preview -->
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
                        <div style="background: #eff6ff; border: 1.5px solid #bfdbfe; padding: 14px; border-radius: 12px; margin-bottom: 12px; font-size: 13px; color: #1e3a8a;">
                            <div style="font-weight: 800; margin-bottom: 8px; color: #1d4ed8; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-box"></i> Parcel Specification: <span style="color:#0f172a; font-weight:700;"><?= htmlspecialchars($parcel_size) ?></span>
                            </div>
                            <?php if (!empty($contact_info)): ?>
                                <div style="margin-bottom: 6px; font-size: 12px; color: #475569;">
                                    <i class="fas fa-user" style="color: #0284c7; margin-right: 4px;"></i> <strong>Sender/Recipient:</strong> <?= htmlspecialchars($contact_info) ?>
                                </div>
                            <?php endif; ?>
                            <div style="margin-bottom: 6px;">
                                <i class="fas fa-map-pin" style="color: #16a34a; margin-right: 6px;"></i>
                                <strong>Pickup Point:</strong> <?= htmlspecialchars($pickup_pt) ?>
                            </div>
                            <div>
                                <i class="fas fa-location-dot" style="color: #dc2626; margin-right: 6px;"></i>
                                <strong>Drop Destination:</strong> <?= htmlspecialchars($drop_pt) ?>
                            </div>
                        </div>
                    <?php elseif ($is_service): 
                        $service_title_tag = '';
                        $service_meta_info = '';
                        if (preg_match('/\[SERVICE BOOKING - (.*?)\] (.*?)$/', $raw_address, $matches)) {
                            $service_title_tag = $matches[1];
                            $service_meta_info = $matches[2];
                        } else {
                            $service_meta_info = $raw_address;
                        }
                    ?>
                        <div style="background: #f0f6ff; border: 1.5px solid #bae6fd; padding: 14px; border-radius: 12px; margin-bottom: 12px; font-size: 13px; color: #0369a1;">
                            <div style="font-weight: 800; margin-bottom: 6px; color: #0D47A1; display: flex; align-items: center; gap: 6px;">
                                <i class="fas fa-calendar-check"></i> Service Booking: <span style="color:#1e293b; font-weight:700;"><?= htmlspecialchars($service_title_tag) ?></span>
                            </div>
                            <div style="color: #475569; font-size: 12px; line-height: 1.4;">
                                <i class="fas fa-info-circle" style="color: #0284c7; margin-right: 4px;"></i> <?= htmlspecialchars($service_meta_info) ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background: #f1f5f9; padding: 10px; border-radius: 10px; margin-bottom: 12px; font-size: 13px; color: #475569;">
                            <i class="fas fa-location-dot" style="color: #0D47A1; margin-right: 6px;"></i>
                            <strong>Deliver to:</strong> <?= htmlspecialchars($raw_address ?: 'Primary Location') ?>
                        </div>
                    <?php endif; ?>

                    <!-- Track Order Button: Appears ONLY when status is 'on the way' or 'reached' -->
                    <?php if ($status === 'on the way' || $status === 'ontheway' || $status === 'reached'): ?>
                        <a href="index.php?page=order_tracking&order_id=<?= $order_id ?>" class="btn-track-order">
                            <i class="fas fa-map-marked-alt"></i> Track Live Partner Route
                        </a>
                    <?php endif; ?>

                    <!-- Show Verification OTP Code to User when Accepted or On the Way -->
                    <?php if (!empty($order['otp']) && $status !== 'delivered' && $status !== 'cancelled'): ?>
                        <div style="background: #fff7ed; border: 2px dashed #f97316; border-radius: 14px; padding: 12px; text-align: center; margin-bottom: 14px;">
                            <span style="font-size: 11px; font-weight: 700; color: #9a3412; display: block; text-transform: uppercase;">Give this 4-digit code to the partner:</span>
                            <h2 style="color: #c2410c; letter-spacing: 6px; margin: 4px 0 0 0; font-size: 24px;"><?= $order['otp'] ?></h2>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Order Item List -->
                    <?php if (!empty($items_array)): ?>
                        <div class="item-preview-list">
                            <div style="font-size:12px; font-weight:800; color:#0D47A1; margin-bottom:6px;"><?= $is_service ? 'Booked Slots / Details:' : 'Order Items:' ?></div>
                            <?php foreach($items_array as $item): ?>
                                <div class="item-preview-row">
                                    <span><?= htmlspecialchars($item['item_name']) ?> × <?= $item['quantity'] ?></span>
                                    <strong>₹<?= number_format($item['price'] * $item['quantity'], 2) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span style="font-size:12px; color:#64748b; display:block;">Total Paid (<?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?>)</span>
                            <strong style="font-size:16px; color:#0D47A1;">₹<?= number_format($order['total_amount'], 2) ?></strong>
                        </div>

                        <div>
                            <?php if ($status === 'delivered'): ?>
                                <span style="color: #16a34a; font-weight: 800; font-size: 13px;"><i class="fas fa-check-circle"></i> Completed</span>
                            <?php else: ?>
                                <span style="font-size: 12px; color: #64748b; font-weight: 700; background: #f1f5f9; padding: 4px 10px; border-radius: 8px;">Active Order</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:50px 20px; background:#fff; border-radius:20px; border:1px solid #e2e8f0; color:#64748b;">
                <i class="fas fa-receipt" style="font-size:48px; color:#cbd5e1; margin-bottom:14px;"></i>
                <h3 style="color:#1e293b; margin-bottom:6px;">No Orders Placed Yet!</h3>
                <p style="font-size:13px; margin-bottom:18px;">Place your order or book a service to view tracking here.</p>
                <a href="index.php?page=home" style="background:#0D47A1; color:#fff; padding:12px 24px; border-radius:20px; text-decoration:none; font-weight:800; font-size:14px;">Explore Home</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Real-Time Auto Refresh Script -->
<script>
setInterval(function() {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            let parser = new DOMParser();
            let doc = parser.parseFromString(html, 'text/html');
            let newContent = doc.getElementById('liveOrdersContainer');
            if (newContent) {
                document.getElementById('liveOrdersContainer').innerHTML = newContent.innerHTML;
            }
        }).catch(err => console.log('Auto-refresh error: ', err));
}, 5000);
</script>