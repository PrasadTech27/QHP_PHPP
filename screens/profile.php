<?php
// STRICT CLEAN AJAX GATEWAY (HTML Layout Render Aina Break Aipokunda Handle Chestundhi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_generate_otp'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'User not logged in']);
        exit();
    }

    $ajax_user_id = $_SESSION['user_id'];
    $target_email = trim($_POST['target_email']);
    $type = trim($_POST['otp_type']);

    if ($type === 'email_change') {
        $check_dup = $conn->query("SELECT id FROM users WHERE email = '$target_email' AND id != $ajax_user_id LIMIT 1");
        if ($check_dup && $check_dup->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'error' => 'This email is already registered with another account!'
            ]);
            exit();
        }
    }

    if (!empty($target_email) && filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        $conn->query("DELETE FROM temp_otps WHERE email = '$target_email' AND otp_type = '$type'");
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $stmt = $conn->prepare("INSERT INTO temp_otps (email, otp_code, otp_type, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $target_email, $otp, $type, $expires_at);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'otp_code' => $otp, 
                'email' => $target_email
            ]);
            exit();
        }
    }

    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP']);
    exit();
}

if (!isset($_SESSION['user_id'])) { 
    echo "<script>window.location.href='index.php?page=login';</script>"; 
    exit(); 
}

$user_id = $_SESSION['user_id'];
$toast_message = "";

if (!function_exists('verifyOTP')) {
    function verifyOTP($email, $input_otp, $type = 'signup') {
        global $conn;
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("SELECT * FROM temp_otps WHERE email = ? AND otp_type = ? AND expires_at >= ? ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("sss", $email, $type, $now);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($row = $res->fetch_assoc()) {
            if (trim($row['otp_code']) === trim($input_otp)) {
                $conn->query("DELETE FROM temp_otps WHERE id = " . $row['id']);
                return true;
            }
        }
        return false;
    }
}

$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
$display_name = !empty($user['full_name']) ? $user['full_name'] : (!empty($user['name']) ? $user['name'] : 'User Account');
$user_email = $user['email'] ?? '';
$user_phone = $user['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_profile'])) {
    $edit_name  = trim($_POST['edit_name']);
    $edit_phone = trim($_POST['edit_phone']);

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
    if (!$stmt) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
    }
    
    $stmt->bind_param("ssi", $edit_name, $edit_phone, $user_id);
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $edit_name;
        $toast_message = "Profile details updated successfully!";
        $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
        $display_name = !empty($user['full_name']) ? $user['full_name'] : (!empty($user['name']) ? $user['name'] : 'User Account');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_verify_email_otp'])) {
    $new_email = trim($_POST['verify_new_email']);
    $entered_otp = trim($_POST['entered_email_otp']);
    
    if (verifyOTP($new_email, $entered_otp, 'email_change')) {
        $check_dup = $conn->query("SELECT id FROM users WHERE email = '$new_email' AND id != $user_id LIMIT 1");
        if ($check_dup && $check_dup->num_rows > 0) {
            $toast_message = "Email is already taken by another account!";
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $new_email, $user_id);
            if ($stmt->execute()) {
                $toast_message = "Email address verified & changed successfully!";
                $user['email'] = $new_email;
                $user_email = $new_email;
            }
        }
    } else {
        $toast_message = "Invalid or expired Email OTP!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_delete_account'])) {
    $delete_otp = trim($_POST['entered_delete_otp']);

    if (verifyOTP($user_email, $delete_otp, 'delete_account')) {
        $conn->query("CREATE TABLE IF NOT EXISTS account_deletion_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            user_email VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $check_req = $conn->query("SELECT id FROM account_deletion_requests WHERE user_id = $user_id AND status = 'pending'");
        if ($check_req && $check_req->num_rows === 0) {
            $stmt_req = $conn->prepare("INSERT INTO account_deletion_requests (user_id, user_email, status) VALUES (?, ?, 'pending')");
            $stmt_req->bind_param("is", $user_id, $user_email);
            $stmt_req->execute();
        }
        $toast_message = "Your request has been sent successfully! Account will be deleted in 24 hours.";
    } else {
        $toast_message = "Invalid or expired Security OTP!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_add_address'])) {
    $title = trim($_POST['address_title']);
    $address_line = trim($_POST['address_line']);
    $lat = !empty($_POST['map_lat']) ? floatval($_POST['map_lat']) : 16.8282;
    $lng = !empty($_POST['map_lng']) ? floatval($_POST['map_lng']) : 81.8961;

    $check_first = $conn->query("SELECT id FROM addresses WHERE user_id = $user_id");
    $is_primary = ($check_first && $check_first->num_rows === 0) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO addresses (user_id, title, address_line, lat, lng, is_primary) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        $stmt = $conn->prepare("INSERT INTO addresses (user_id, title, address, lat, lng, is_primary) VALUES (?, ?, ?, ?, ?, ?)");
    }
    
    if ($stmt) {
        $stmt->bind_param("issddi", $user_id, $title, $address_line, $lat, $lng, $is_primary);
        if ($stmt->execute()) {
            $toast_message = "New delivery address saved successfully!";
        }
    }
}

if (isset($_GET['set_primary'])) {
    $addr_id = intval($_GET['set_primary']);
    $conn->query("UPDATE addresses SET is_primary = 0 WHERE user_id = $user_id");
    $conn->query("UPDATE addresses SET is_primary = 1 WHERE id = $addr_id AND user_id = $user_id");
    $toast_message = "Primary location updated!";
}

if (isset($_GET['delete_addr'])) {
    $del_id = intval($_GET['delete_addr']);
    $conn->query("DELETE FROM addresses WHERE id = $del_id AND user_id = $user_id");
    $toast_message = "Address deleted!";
}

$addresses = $conn->query("SELECT * FROM addresses WHERE user_id = $user_id ORDER BY is_primary DESC, id DESC");
?>

<!-- EmailJS SDK CDN -->
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js"></script>
<script type="text/javascript">
   (function(){
      emailjs.init("6OYyhfesYKGExGDPb");
   })();
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />

<style>
    .profile-wrapper { font-family: 'Segoe UI', -apple-system, sans-serif; padding-bottom: 110px; }
    .profile-card { background: #ffffff; border-radius: 20px; padding: 22px; box-shadow: 0 6px 20px rgba(15,23,42,0.04); margin-bottom: 18px; border: 1px solid #e2e8f0; }
    .user-avatar-circle { width: 65px; height: 65px; background: #e0f2fe; color: #0D47A1; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; flex-shrink: 0; border: 2.5px solid #0D47A1; }
    .btn-edit-profile-pill { background: #0D47A1; color: #ffffff; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-top: 8px; box-shadow: 0 4px 12px rgba(13,71,161,0.2); }
    .menu-link-card { background: #ffffff; border-radius: 16px; padding: 14px 18px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; text-decoration: none; color: #1e293b; font-weight: 700; margin-bottom: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); cursor: pointer; }
    .addr-card { background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 16px; margin-bottom: 12px; }
    .addr-card.primary { border-color: #0D47A1; background: #f0f6ff; }
    .badge-primary { background: #0D47A1; color: #ffffff; font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }

    .custom-toast { position: fixed; top: 25px; right: 20px; z-index: 99999; background: #0D47A1; color: #ffffff; padding: 14px 22px; border-radius: 16px; font-weight: 700; font-size: 14px; box-shadow: 0 10px 30px rgba(13, 71, 161, 0.35); display: flex; align-items: center; gap: 10px; border: 1.5px solid #3b82f6; }
    .custom-modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(15, 23, 42, 0.65); display:none; align-items:center; justify-content:center; z-index: 9999; backdrop-filter: blur(4px); }
    .custom-modal-card { background: #ffffff; border-radius: 24px; padding: 26px; width: 90%; max-width: 440px; box-shadow: 0 20px 40px rgba(0,0,0,0.25); border: 1px solid #e2e8f0; }
    .edit-form-input-box { margin-bottom: 16px; }
    .edit-form-input-box label { display: block; font-size: 12px; font-weight: 800; color: #0D47A1; text-transform: uppercase; margin-bottom: 6px; }
    .edit-form-input-box input { width: 100%; padding: 12px 16px; border: 1.5px solid #cbd5e1; border-radius: 14px; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; }

    /* PROFESSIONAL MAP PICKER UI STYLING FIXES */
    .map-modal-full { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; height: 100dvh; background: #0f172a; z-index: 10000; flex-direction: column; overflow: hidden; }
    .map-modal-full.show { display: flex; }
    
    .map-top-bar { height: 65px; background: #0D47A1; padding: 0 20px; display: flex; align-items: center; justify-content: space-between; z-index: 20; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .map-top-bar .title { color: #ffffff; font-size: 17px; font-weight: 800; }
    .map-top-bar button { background: rgba(255,255,255,0.15); border: none; color: #fff; padding: 8px 14px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; }
    
    .map-wrapper { flex: 1; width: 100%; position: relative; }
    #pickerMapFull { width: 100%; height: 100%; z-index: 1; }
    
    .map-search-float { position: absolute; top: 15px; left: 50%; transform: translateX(-50%); width: 92%; max-width: 500px; background: #ffffff; border-radius: 16px; padding: 6px 8px 6px 16px; display: flex; align-items: center; gap: 10px; z-index: 1000; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border: 1.5px solid #e2e8f0; }
    .map-search-float input { flex: 1; border: none; outline: none; font-size: 14px; font-weight: 600; color: #1e293b; background: transparent; }
    .btn-search-go { background: #0D47A1; color: #fff; border: none; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    
    .btn-recenter-gps { position: absolute; bottom: 240px; right: 20px; width: 48px; height: 48px; border-radius: 50%; background: #ffffff; color: #0D47A1; display: flex; align-items: center; justify-content: center; font-size: 18px; border: 2px solid #e2e8f0; cursor: pointer; z-index: 1000; box-shadow: 0 4px 16px rgba(0,0,0,0.15); transition: transform 0.2s; }
    .btn-recenter-gps:active { transform: scale(0.92); }

    .map-bottom-card { position: absolute; bottom: 15px; left: 50%; transform: translateX(-50%); width: 94%; max-width: 500px; background: #ffffff; border-radius: 24px; padding: 20px; z-index: 1000; box-shadow: 0 -10px 30px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; }
    
    .title-tag-row { display: flex; gap: 10px; margin: 12px 0; }
    .tag-chip { flex: 1; padding: 10px; text-align: center; border-radius: 12px; background: #f1f5f9; color: #475569; font-size: 13px; font-weight: 700; cursor: pointer; border: 1.5px solid #e2e8f0; transition: all 0.2s ease; }
    .tag-chip.selected { background: #0D47A1; color: #ffffff; border-color: #0D47A1; box-shadow: 0 4px 12px rgba(13,71,161,0.25); }
    
    .btn-proceed-submit { background: linear-gradient(135deg, #0D47A1, #1565C0); color: #ffffff; border: none; padding: 14px; border-radius: 16px; font-weight: 800; font-size: 15px; width: 100%; cursor: pointer; box-shadow: 0 6px 20px rgba(13,71,161,0.3); display: flex; align-items: center; justify-content: center; gap: 8px; }
</style>

<?php if (!empty($toast_message)): ?>
    <div class="custom-toast" id="userToast">
        <i class="fas fa-circle-check" style="color:#FF9800; font-size:18px;"></i>
        <span><?= htmlspecialchars($toast_message) ?></span>
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('userToast');
            if (toast) toast.style.display = 'none';
        }, 5000);
    </script>
<?php endif; ?>

<div class="profile-wrapper">
    <div class="profile-card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:16px;">
            <div style="display:flex; align-items:center; gap:14px;">
                <div class="user-avatar-circle">
                    <?= strtoupper(substr($display_name, 0, 1)) ?>
                </div>
                <div>
                    <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin:0;"><?= htmlspecialchars($display_name) ?></h3>
                    <p style="color:#64748b; font-size:12px; margin:2px 0;"><?= htmlspecialchars($user['email'] ?? 'No email set') ?></p>
                    <p style="color:#0D47A1; font-size:13px; font-weight:800; margin:0;"><i class="fas fa-phone" style="font-size:11px;"></i> <?= htmlspecialchars($user['phone'] ?? 'N/A') ?></p>
                    
                    <button class="btn-edit-profile-pill" onclick="openEditProfileModal()">
                        <i class="fas fa-pen-to-square"></i> Edit Profile
                    </button>
                </div>
            </div>
            <a href="logout.php" style="color:#dc2626; text-decoration:none; font-size:13px; font-weight:800;"><i class="fas fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>

    <a href="index.php?page=order_history" class="menu-link-card">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-clock-rotate-left" style="color:#0D47A1; font-size:18px;"></i>
            <span>My Orders & Order History</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
    </a>

    <div class="profile-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <h3 style="color:#0D47A1; font-size:17px; font-weight:800; margin:0;">
                <i class="fas fa-location-dot" style="margin-right:6px;"></i> Delivery Addresses
            </h3>
            <button class="btn-edit-profile-pill" onclick="openFullscreenMap()"><i class="fas fa-plus"></i> Add New</button>
        </div>

        <?php if ($addresses && $addresses->num_rows > 0): ?>
            <?php while($addr = $addresses->fetch_assoc()): 
                $addr_text = !empty($addr['address_line']) ? $addr['address_line'] : (!empty($addr['address']) ? $addr['address'] : 'Saved Pin Location');
            ?>
                <div class="addr-card <?= $addr['is_primary'] ? 'primary' : '' ?>">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="font-weight:800; color:#1e293b; font-size:14px; display:flex; align-items:center; gap:8px;">
                            <i class="fas fa-house" style="color:#0D47A1;"></i> <?= htmlspecialchars($addr['title']) ?>
                            <?php if($addr['is_primary']): ?>
                                <span class="badge-primary">Primary</span>
                            <?php endif; ?>
                        </div>
                        <a href="index.php?page=profile&delete_addr=<?= $addr['id'] ?>" onclick="return confirm('Delete address?')" style="color:#dc2626; font-size:13px;"><i class="fas fa-trash"></i></a>
                    </div>

                    <p style="font-size:12px; color:#475569; line-height:1.4; margin-bottom:10px;"><?= htmlspecialchars($addr_text) ?></p>

                    <?php if(!$addr['is_primary']): ?>
                        <a href="index.php?page=profile&set_primary=<?= $addr['id'] ?>" style="font-size:12px; color:#0D47A1; font-weight:700; text-decoration:none;"><i class="far fa-circle-check"></i> Set as Primary</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color:#94a3b8; font-size:13px; text-align:center; padding:15px 0;">No saved addresses yet.</p>
        <?php endif; ?>
    </div>

    <div style="font-size:15px; font-weight:800; color:#0D47A1; margin: 15px 0 10px;">Information & Account</div>

    <div class="menu-link-card" onclick="openInfoModal('privacy')">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-shield-halved" style="color:#0D47A1; font-size:18px;"></i>
            <span>Privacy Policy</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
    </div>

    <div class="menu-link-card" onclick="openInfoModal('terms')">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-file-contract" style="color:#0D47A1; font-size:18px;"></i>
            <span>Terms & Conditions</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
    </div>

    <div class="menu-link-card" onclick="openInfoModal('about')">
        <div style="display:flex; align-items:center; gap:12px;">
            <i class="fas fa-circle-info" style="color:#0D47A1; font-size:18px;"></i>
            <span>About Us</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#cbd5e1; font-size:14px;"></i>
    </div>

    <div class="menu-link-card" id="btnTriggerDeleteAccount" style="border-color:#fee2e2; background:#fff5f5;">
        <div style="display:flex; align-items:center; gap:12px; color:#dc2626;">
            <i class="fas fa-user-slash" style="font-size:18px;"></i>
            <span>Request Account Deletion</span>
        </div>
        <i class="fas fa-chevron-right" style="color:#f87171; font-size:14px;"></i>
    </div>
</div>

<!-- EDIT PROFILE MODAL -->
<div class="custom-modal-overlay" id="editProfileModal">
    <div class="custom-modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="color:#0D47A1; margin:0; font-size:19px; font-weight:800; display:flex; align-items:center; gap:8px;">
                <i class="fas fa-user-pen"></i> Edit Profile Details
            </h3>
            <i class="fas fa-xmark" onclick="closeEditProfileModal()" style="font-size:22px; color:#94a3b8; cursor:pointer;"></i>
        </div>

        <form method="POST" id="frmProfileEdit">
            <input type="hidden" name="action_update_profile" value="1">
            <div class="edit-form-input-box">
                <label><i class="fas fa-user" style="margin-right:4px;"></i> Full Name</label>
                <input type="text" name="edit_name" id="inpEditName" value="<?= htmlspecialchars($display_name) ?>" required>
            </div>
            <div class="edit-form-input-box">
                <label><i class="fas fa-envelope" style="margin-right:4px;"></i> Email Address</label>
                <input type="email" name="edit_email" id="inpEditEmail" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>
            <div class="edit-form-input-box">
                <label><i class="fas fa-phone" style="margin-right:4px;"></i> Phone Number</label>
                <input type="text" name="edit_phone" id="inpEditPhone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
            </div>
            <button type="button" id="btnSaveProfile" onclick="handleProfileSaveClick()" class="btn" style="width:100%; padding:14px; border-radius:16px; background:#0D47A1; color:#fff; border:none; font-size:15px; font-weight:800; cursor:pointer;">
                Save Profile Changes
            </button>
        </form>
    </div>
</div>

<!-- EMAIL OTP MODAL -->
<div class="custom-modal-overlay" id="emailOtpModal">
    <div class="custom-modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="color:#0D47A1; margin:0; font-size:18px; font-weight:800;"><i class="fas fa-envelope-circle-check"></i> Verify Email OTP</h3>
            <i class="fas fa-xmark" onclick="closeEmailOtpModal()" style="font-size:20px; color:#64748b; cursor:pointer;"></i>
        </div>
        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">A 6-digit OTP code has been sent to your new email: <strong id="txtOtpTargetEmail" style="color:#0D47A1;"></strong>.</p>
        <form method="POST">
            <input type="hidden" name="action_verify_email_otp" value="1">
            <input type="hidden" name="verify_new_email" id="hiddenVerifyEmail">
            <div class="edit-form-input-box">
                <label>Enter 6-Digit Email OTP</label>
                <input type="text" name="entered_email_otp" maxlength="6" placeholder="******" required style="text-align:center; font-size:20px; letter-spacing:6px; font-weight:900;">
            </div>
            <button type="submit" class="btn" style="width:100%; padding:14px; border-radius:16px; background:#16a34a; color:#fff; border:none; font-size:15px; font-weight:800; cursor:pointer;">
                Verify OTP & Change Email
            </button>
        </form>
    </div>
</div>

<!-- DELETE ACCOUNT MODAL -->
<div class="custom-modal-overlay" id="deleteOtpModal">
    <div class="custom-modal-card" style="border-color:#fee2e2;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="color:#dc2626; margin:0; font-size:18px; font-weight:800;"><i class="fas fa-triangle-exclamation"></i> Account Deletion Request</h3>
            <i class="fas fa-xmark" onclick="closeDeleteOtpModal()" style="font-size:20px; color:#64748b; cursor:pointer;"></i>
        </div>
        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">Click below to send OTP code to your registered email: <strong style="color:#1e293b;"><?= htmlspecialchars($user_email) ?></strong>.</p>
        <button type="button" id="btnSendDeleteOtp" onclick="sendDeleteAccountOtpNow()" class="btn" style="width:100%; padding:12px; border-radius:14px; background:#FF9800; color:#fff; border:none; font-size:14px; font-weight:800; margin-bottom:15px; cursor:pointer;">
            Send Security Delete OTP
        </button>
        <form method="POST" id="frmConfirmDeleteAcc">
            <input type="hidden" name="action_confirm_delete_account" value="1">
            <div class="edit-form-input-box">
                <label style="color:#dc2626;">6-Digit Security OTP</label>
                <input type="text" name="entered_delete_otp" maxlength="6" placeholder="******" required style="text-align:center; font-size:20px; letter-spacing:6px; font-weight:900; border-color:#f87171;">
            </div>
            <button type="submit" class="btn" style="width:100%; padding:14px; border-radius:16px; background:#dc2626; color:#fff; border:none; font-size:15px; font-weight:800; cursor:pointer;">
                Submit Deletion Request
            </button>
        </form>
    </div>
</div>

<!-- POLICY & ABOUT INFO MODAL -->
<div class="custom-modal-overlay" id="infoModal">
    <div class="custom-modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="color:#0D47A1; margin:0; font-size:18px; font-weight:800;" id="infoModalTitle">Information</h3>
            <i class="fas fa-xmark" onclick="closeInfoModal()" style="font-size:20px; color:#64748b; cursor:pointer;"></i>
        </div>
        <div id="infoModalBody" style="font-size:13px; color:#475569; line-height:1.6; max-height:60vh; overflow-y:auto;"></div>
    </div>
</div>

<!-- FULLSCREEN RESPONSIVE MAP PICKER MODAL (REDESIGNED CLEAN LOOK) -->
<div class="map-modal-full" id="fullMapModal">
    <div class="map-top-bar">
        <button onclick="closeFullscreenMap()"><i class="fas fa-arrow-left"></i> Back</button>
        <div class="title"><i class="fas fa-map-location-dot"></i> Pick Delivery Location</div>
        <button onclick="submitAddressForm()" style="background:#10b981; color:#fff;"><i class="fas fa-check"></i> Confirm</button>
    </div>

    <div class="map-wrapper">
        <div id="pickerMapFull"></div>

        <div class="map-search-float">
            <i class="fas fa-search" style="color:#0D47A1; font-size:15px;"></i>
            <input type="text" id="mapSearchInput" placeholder="Search area, street, landmark...">
            <button class="btn-search-go" onclick="searchLocationMap()"><i class="fas fa-arrow-right"></i></button>
        </div>

        <button class="btn-recenter-gps" onclick="recenterToGPS()"><i class="fas fa-crosshairs"></i></button>

        <div class="map-bottom-card">
            <div style="color:#0D47A1; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Selected Location</div>
            <p id="txtSelectedAddress" style="color:#1e293b; font-size:13px; font-weight:600; margin:6px 0 12px; line-height:1.4; max-height:40px; overflow:hidden; text-overflow:ellipsis;">Fetching location details...</p>

            <div class="title-tag-row">
                <div class="tag-chip selected" onclick="setTag('Home', this)"><i class="fas fa-house"></i> Home</div>
                <div class="tag-chip" onclick="setTag('Office', this)"><i class="fas fa-briefcase"></i> Office</div>
                <div class="tag-chip" onclick="setTag('Other', this)"><i class="fas fa-location-dot"></i> Other</div>
            </div>

            <form id="addressSaveForm" method="POST">
                <input type="hidden" name="action_add_address" value="1">
                <input type="hidden" name="address_title" id="inpAddressTitle" value="Home">
                <input type="hidden" name="address_line" id="inpAddressLine">
                <input type="hidden" name="map_lat" id="inpMapLat">
                <input type="hidden" name="map_lng" id="inpMapLng">

                <button type="submit" class="btn-proceed-submit">
                    Save Location <i class="fas fa-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const originalEmail = "<?= htmlspecialchars($user_email) ?>";

function openEditProfileModal() { document.getElementById('editProfileModal').style.display = 'flex'; }
function closeEditProfileModal() { document.getElementById('editProfileModal').style.display = 'none'; }

function dispatchBrowserEmailJS(email, otpCode, onSuccess, onError) {
    emailjs.send("service_qh1c06y", "template_judy2gg", {
        email: email,
        otp: otpCode
    }).then(function(response) {
        onSuccess(response);
    }, function(error) {
        onError(error);
    });
}

function handleProfileSaveClick() {
    const newEmail = document.getElementById('inpEditEmail').value.trim();
    const btn = document.getElementById('btnSaveProfile');

    if (newEmail !== originalEmail) {
        btn.innerText = "Checking Email...";
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action_generate_otp', '1');
        formData.append('target_email', newEmail);
        formData.append('otp_type', 'email_change');

        fetch('index.php?page=profile', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    btn.innerText = "Sending Email...";
                    dispatchBrowserEmailJS(newEmail, res.otp_code, 
                        function() {
                            btn.innerText = "Save Profile Changes";
                            btn.disabled = false;
                            document.getElementById('txtOtpTargetEmail').innerText = newEmail;
                            document.getElementById('hiddenVerifyEmail').value = newEmail;
                            closeEditProfileModal();
                            document.getElementById('emailOtpModal').style.display = 'flex';
                        }, 
                        function(err) {
                            btn.innerText = "Save Profile Changes";
                            btn.disabled = false;
                            alert("Failed to send Email OTP!");
                        }
                    );
                } else {
                    btn.innerText = "Save Profile Changes";
                    btn.disabled = false;
                    alert(res.error || 'Failed to generate OTP!');
                }
            })
            .catch(err => {
                btn.innerText = "Save Profile Changes";
                btn.disabled = false;
                alert("Server error verifying email!");
            });
    } else {
        document.getElementById('frmProfileEdit').submit();
    }
}

function closeEmailOtpModal() { document.getElementById('emailOtpModal').style.display = 'none'; }

document.addEventListener('DOMContentLoaded', function() {
    const delBtn = document.getElementById('btnTriggerDeleteAccount');
    if (delBtn) {
        delBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('deleteOtpModal').style.display = 'flex';
        });
    }
});

function sendDeleteAccountOtpNow() {
    const targetEmail = "<?= htmlspecialchars($user_email) ?>";
    const btn = document.getElementById('btnSendDeleteOtp');
    btn.innerText = "Generating Security OTP...";
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action_generate_otp', '1');
    formData.append('target_email', targetEmail);
    formData.append('otp_type', 'delete_account');

    fetch('index.php?page=profile', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                btn.innerText = "Sending OTP Email...";
                dispatchBrowserEmailJS(targetEmail, res.otp_code,
                    function() {
                        btn.innerText = "OTP Sent to Your Email!";
                        btn.style.background = "#16a34a";
                    },
                    function(err) {
                        btn.innerText = "Send Security Delete OTP";
                        btn.disabled = false;
                        alert("Failed to send Security Delete OTP!");
                    }
                );
            } else {
                btn.innerText = "Send Security Delete OTP";
                btn.disabled = false;
                alert(res.error || 'Unable to request delete OTP right now.');
            }
        })
        .catch(err => {
            btn.innerText = "Send Security Delete OTP";
            btn.disabled = false;
            alert("Error sending OTP request!");
        });
}

function closeDeleteOtpModal() { document.getElementById('deleteOtpModal').style.display = 'none'; }

function openInfoModal(type) {
    const titleEl = document.getElementById('infoModalTitle');
    const bodyEl = document.getElementById('infoModalBody');

    if (type === 'privacy') {
        titleEl.innerText = "Privacy Policy";
        bodyEl.innerHTML = `<p>At QHP Super App, we value your privacy. Your personal information, saved delivery addresses, and order history are securely encrypted and protected.</p>`;
    } else if (type === 'terms') {
        titleEl.innerText = "Terms & Conditions";
        bodyEl.innerHTML = `<p>By using QHP Super App services, you agree to our terms of delivery & distance matrix charges.</p>`;
    } else if (type === 'about') {
        titleEl.innerText = "About Us";
        bodyEl.innerHTML = `<p><strong>QHP Super App</strong> is your all-in-one local delivery platform bringing food delivery, grocery, and ride services right to your doorstep.</p>`;
    }
    document.getElementById('infoModal').style.display = 'flex';
}
function closeInfoModal() { document.getElementById('infoModal').style.display = 'none'; }

let fullMapInstance = null;
let centerPinMarker = null;

function openFullscreenMap() {
    document.getElementById('fullMapModal').classList.add('show');
    setTimeout(() => {
        if (!fullMapInstance) {
            fullMapInstance = L.map('pickerMapFull', { zoomControl: false }).setView([16.8282, 81.8961], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(fullMapInstance);

            const customIcon = L.divIcon({
                html: `<div style="background:#0D47A1; width:40px; height:40px; border-radius:50% 50% 50% 0; transform:rotate(-45deg); display:flex; align-items:center; justify-content:center; box-shadow:0 6px 16px rgba(13,71,161,0.4); border:3px solid #ffffff;">
                        <i class="fas fa-location-dot" style="transform:rotate(45deg); color:#ffffff; font-size:16px;"></i>
                       </div>`,
                className: '', iconSize: [40, 40], iconAnchor: [20, 40]
            });

            centerPinMarker = L.marker([16.8282, 81.8961], { icon: customIcon, draggable: true }).addTo(fullMapInstance);

            const reverseGeocode = (lat, lng) => {
                document.getElementById('inpMapLat').value = lat;
                document.getElementById('inpMapLng').value = lng;
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(r => r.json())
                    .then(d => {
                        const addrText = d.display_name || `Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}`;
                        document.getElementById('txtSelectedAddress').innerText = addrText;
                        document.getElementById('inpAddressLine').value = addrText;
                    });
            };

            fullMapInstance.on('click', e => {
                centerPinMarker.setLatLng(e.latlng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });

            centerPinMarker.on('dragend', e => {
                const pos = e.target.getLatLng();
                reverseGeocode(pos.lat, pos.lng);
            });

            recenterToGPS();
        }
        fullMapInstance.invalidateSize();
    }, 200);
}

function recenterToGPS() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(p => {
            const lat = p.coords.latitude;
            const lng = p.coords.longitude;
            fullMapInstance.setView([lat, lng], 15);
            centerPinMarker.setLatLng([lat, lng]);
            
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(r => r.json())
                .then(d => {
                    const addrText = d.display_name;
                    document.getElementById('txtSelectedAddress').innerText = addrText;
                    document.getElementById('inpAddressLine').value = addrText;
                    document.getElementById('inpMapLat').value, lat;
                    document.getElementById('inpMapLng').value = lng;
                });
        });
    }
}

function searchLocationMap() {
    const query = document.getElementById('mapSearchInput').value.trim();
    if (query) {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
            .then(r => r.json())
            .then(data => {
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    fullMapInstance.setView([lat, lng], 15);
                    centerPinMarker.setLatLng([lat, lng]);
                    document.getElementById('txtSelectedAddress').innerText = data[0].display_name;
                    document.getElementById('inpAddressLine').value = data[0].display_name;
                    document.getElementById('inpMapLat').value = lat;
                    document.getElementById('inpMapLng').value = lng;
                }
            });
    }
}

function setTag(tagVal, el) {
    document.querySelectorAll('.tag-chip').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('inpAddressTitle').value = tagVal;
}

function submitAddressForm() { document.getElementById('addressSaveForm').submit(); }
function closeFullscreenMap() { document.getElementById('fullMapModal').classList.remove('show'); }
</script>