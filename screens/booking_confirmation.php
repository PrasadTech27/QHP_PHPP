<?php
if (!isset($_SESSION['user_id'])) { echo "<script>window.location.href='index.php?page=login';</script>"; exit(); }

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$user_id = $_SESSION['user_id'];

// Fetch Order Details
$order = $conn->query("SELECT * FROM orders WHERE id = $order_id AND user_id = $user_id LIMIT 1")->fetch_assoc();

if (!$order) {
    echo "<div style='padding:30px; text-align:center;'>Order not found!</div>";
    exit();
}

$items = $conn->query("SELECT * FROM order_items WHERE order_id = $order_id");
?>

<style>
    .confirm-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding: 20px 10px 80px; text-align: center; }
    .success-card {
        background: #ffffff; border-radius: 24px; padding: 30px 20px;
        border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.05); max-width: 500px; margin: 0 auto;
    }
    .check-circle {
        width: 70px; height: 70px; background: #dcfce7; color: #15803d; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 15px;
    }
    .order-id-badge {
        background: #f1f5f9; color: #0D47A1; padding: 6px 16px; border-radius: 20px;
        font-weight: 800; font-size: 13px; display: inline-block; margin-bottom: 20px;
    }
    .item-list-box { text-align: left; background: #f8fafc; border-radius: 16px; padding: 15px; margin: 20px 0; border: 1px solid #e2e8f0; }
    .btn-track {
        background: #0D47A1; color: #ffffff; padding: 14px 28px; border-radius: 16px;
        text-decoration: none; font-weight: 800; font-size: 15px; display: block; width: 100%; box-sizing: border-box;
    }
</style>

<div class="confirm-wrapper">
    <div class="success-card">
        <div class="check-circle"><i class="fas fa-check"></i></div>
        <h2 style="color:#1e293b; margin:0 0 6px;">Booking Confirmed!</h2>
        <p style="color:#64748b; font-size:13px; margin:0 0 15px;">Your order has been placed successfully and sent to the kitchen.</p>
        
        <div class="order-id-badge">Order ID: #QHP-<?= $order['id'] ?></div>

        <div class="item-list-box">
            <strong style="color:#1e293b; font-size:14px; display:block; margin-bottom:10px;">Order Summary</strong>
            <?php while($item = $items->fetch_assoc()): ?>
                <div style="display:flex; justify-content:space-between; font-size:13px; color:#475569; margin-bottom:6px;">
                    <span><?= htmlspecialchars($item['item_name']) ?> x <?= $item['quantity'] ?></span>
                    <strong>₹<?= $item['price'] * $item['quantity'] ?></strong>
                </div>
            <?php endwhile; ?>
            <hr style="border:none; border-top:1px dashed #cbd5e1; margin:10px 0;">
            <div style="display:flex; justify-content:space-between; font-size:15px; color:#1e293b; font-weight:800;">
                <span>Total Paid</span>
                <span style="color:#0D47A1;">₹<?= $order['total_amount'] ?> (<?= htmlspecialchars($order['payment_method']) ?>)</span>
            </div>
        </div>

        <a href="index.php?page=order_tracking&order_id=<?= $order['id'] ?>" class="btn-track">
            Track Order Live <i class="fas fa-motorcycle" style="margin-left:8px;"></i>
        </a>
    </div>
</div>