<?php
session_start();
require_once 'db.php';

// Admin ID 3 set chesanu
$my_admin_id = 2; 

// Security Gatekeeper Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $my_admin_id) {
    echo "<script>alert('Unauthorized Access!'); window.location.href='index.php';</script>";
    exit();
}

// Action: Admin Confirm Delete Account
if (isset($_GET['action_confirm_delete'])) {
    $req_id = intval($_GET['action_confirm_delete']);
    $user_id_to_del = intval($_GET['user_id']);

    // Safe Delete Cascade directly
    $conn->query("DELETE FROM addresses WHERE user_id = $user_id_to_del");
    $conn->query("DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id = $user_id_to_del)");
    $conn->query("DELETE FROM orders WHERE user_id = $user_id_to_del");
    $conn->query("DELETE FROM account_deletion_requests WHERE id = $req_id");
    $conn->query("DELETE FROM users WHERE id = $user_id_to_del");

    echo "<script>alert('Account and related data successfully purged!'); window.location.href='admin_requests.php';</script>";
    exit();
}

// Fetch Statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_requests = $conn->query("SELECT COUNT(*) as count FROM account_deletion_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Fetch Users List Safely
$users_list = $conn->query("SELECT * FROM users ORDER BY id DESC");

// Fetch Deletion Requests List Safely without missing columns
$requests_list = $conn->query("
    SELECT r.id as req_id, r.user_id, r.user_email, r.requested_at, u.full_name, u.phone
    FROM account_deletion_requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.id DESC
");

// Check if a specific user orders are requested to view
$view_user_id = isset($_GET['view_orders_for']) ? intval($_GET['view_orders_for']) : 0;
$user_orders_list = null;
if ($view_user_id > 0) {
    $user_orders_list = $conn->query("SELECT * FROM orders WHERE user_id = $view_user_id ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>QHP Main Admin Control Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }
        body { background:#f0f4f8; padding:20px; }
        .admin-header { background:#0D47A1; color:#fff; padding:20px; border-radius:16px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .tab-btn-row { display:flex; gap:12px; margin-bottom:20px; align-items:center; flex-wrap:wrap; }
        .tab-btn { padding:12px 20px; border-radius:12px; border:none; background:#fff; font-weight:800; color:#0D47A1; cursor:pointer; box-shadow:0 4px 10px rgba(0,0,0,0.05); text-decoration:none; display:inline-block; }
        .tab-btn.active { background:#0D47A1; color:#fff; }
        .data-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 6px 20px rgba(0,0,0,0.05); margin-bottom:20px; }
        table { width:100%; border-collapse:collapse; margin-top:10px; }
        th, td { padding:12px 16px; text-align:left; border-bottom:1px solid #e2e8f0; font-size:14px; }
        th { background:#f8fafc; color:#0D47A1; font-weight:800; }
        .badge-active { background:#dcfce7; color:#166534; padding:4px 10px; border-radius:20px; font-weight:800; font-size:11px; }
        .btn-action-del { background:#dc2626; color:#fff; border:none; padding:8px 14px; border-radius:8px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-view-orders { background:#0D47A1; color:#fff; border:none; padding:8px 14px; border-radius:8px; font-weight:800; cursor:pointer; text-decoration:none; display:inline-block; margin-right:5px; }
    </style>
</head>
<body>

<div class="admin-header">
    <h2><i class="fas fa-user-shield"></i> QHP Main Admin Panel</h2>
    <div>Total Users: <strong><?= $total_users ?></strong> | Delete Requests: <strong><?= $total_requests ?></strong></div>
</div>

<div class="tab-btn-row">
    <button class="tab-btn active" onclick="showTab('usersTab', this)"><i class="fas fa-users"></i> All Platform Users (<?= $total_users ?>)</button>
    <button class="tab-btn" onclick="showTab('requestsTab', this)" id="btnReqTab"><i class="fas fa-user-minus"></i> Account Delete Requests (<?= $total_requests ?>)</button>
    <a href="index.php" class="tab-btn" style="background:#FF9800; color:#fff; margin-left:auto;"><i class="fas fa-arrow-left"></i> Back to App</a>
</div>

<!-- OPTION 1: ALL USERS LIST -->
<div class="data-card" id="usersTab">
    <h3 style="color:#0D47A1; margin-bottom:10px;"><i class="fas fa-users"></i> Registered Users</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Registered Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users_list && $users_list->num_rows > 0): ?>
                <?php while($u = $users_list->fetch_assoc()): 
                    $u_name = !empty($u['full_name']) ? $u['full_name'] : 'User';
                ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u_name) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['phone']) ?></td>
                        <td><?= $u['created_at'] ?? 'N/A' ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No users found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- OPTION 2: DELETION REQUESTS QUEUE -->
<div class="data-card" id="requestsTab" style="display:none;">
    <h3 style="color:#dc2626; margin-bottom:10px;"><i class="fas fa-triangle-exclamation"></i> Deletion Requests Queue</h3>
    <table>
        <thead>
            <tr>
                <th>Request ID</th>
                <th>User Details</th>
                <th>Request Date</th>
                <th>Orders Status Check</th>
                <th>Action / View Orders</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($requests_list && $requests_list->num_rows > 0): ?>
                <?php while($r = $requests_list->fetch_assoc()): 
                    $r_name = !empty($r['full_name']) ? $r['full_name'] : 'User';
                ?>
                    <tr>
                        <td>#REQ-<?= $r['req_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($r_name) ?></strong><br>
                            <small><?= htmlspecialchars($r['user_email']) ?> | <?= htmlspecialchars($r['phone']) ?></small>
                        </td>
                        <td><?= $r['requested_at'] ?></td>
                        <td>
                            <span class="badge-active"><i class="fas fa-search"></i> REVIEW ORDERS BELOW</span>
                        </td>
                        <td>
                            <a href="admin_requests.php?view_orders_for=<?= $r['user_id'] ?>#userOrdersSection" class="btn-view-orders">
                                <i class="fas fa-eye"></i> Check Orders
                            </a>
                            <a href="admin_requests.php?action_confirm_delete=<?= $r['req_id'] ?>&user_id=<?= $r['user_id'] ?>" onclick="return confirm('Confirm permanent account deletion for this user?')" class="btn-action-del">Confirm Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No pending deletion requests.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- USER SPECIFIC ORDERS SECTION -->
    <?php if ($view_user_id > 0): ?>
        <div id="userOrdersSection" style="margin-top:30px; border-top:2px dashed #cbd5e1; padding-top:20px;">
            <h4 style="color:#0D47A1; margin-bottom:12px;"><i class="fas fa-box"></i> Order History for User ID: #<?= $view_user_id ?></h4>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Total Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($user_orders_list && $user_orders_list->num_rows > 0): ?>
                        <?php while($ord = $user_orders_list->fetch_assoc()): ?>
                            <tr>
                                <td>#ORD-<?= $ord['id'] ?></td>
                                <td>₹<?= htmlspecialchars($ord['total_amount'] ?? '0.00') ?></td>
                                <td><?= htmlspecialchars($ord['created_at'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3">This user has no orders placed yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div style="margin-top:15px;">
                <a href="admin_requests.php" class="tab-btn" style="background:#64748b; color:#fff;">Close Details</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function showTab(tabId, btn) {
    document.getElementById('usersTab').style.display = 'none';
    document.getElementById('requestsTab').style.display = 'none';
    document.querySelectorAll('.tab-btn').forEach(b => {
        if(!b.style.backgroundColor) b.classList.remove('active');
    });
    
    document.getElementById(tabId).style.display = 'block';
    if(!btn.style.backgroundColor) btn.classList.add('active');
}

// Automatically open requests tab if viewing specific user orders
<?php if ($view_user_id > 0): ?>
window.addEventListener('DOMContentLoaded', (event) => {
    showTab('requestsTab', document.getElementById('btnReqTab'));
    window.location.hash = '#userOrdersSection';
});
<?php endif; ?>
</script>

</body>
</html>