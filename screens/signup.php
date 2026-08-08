<?php
require_once 'otp_helper.php';

if (isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'index.php?page=home';</script>";
    exit();
}

$val_name = ""; $val_email = ""; $val_phone = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $val_name  = trim($_POST['full_name']);
    $val_email = trim($_POST['email']);
    $val_phone = trim($_POST['phone']);
    $password  = $_POST['password'];

    if (!empty($val_name) && !empty($val_email) && !empty($val_phone) && !empty($password)) {
        // Check if Email already exists
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 1");
        $checkStmt->bind_param("s", $val_email);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows > 0) {
            $error = "Email address is already registered! Please Login.";
        } else {
            // Store Temporary Registration Cache
            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
            $_SESSION['signup_data'] = [
                'full_name' => $val_name,
                'email' => $val_email,
                'phone' => $val_phone,
                'password' => $hashed_pass
            ];

            // Pre-save into users table as unverified
            $u_stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, is_verified) VALUES (?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), phone=VALUES(phone), password=VALUES(password), is_verified=0");
            $u_stmt->bind_param("ssss", $val_name, $val_email, $val_phone, $hashed_pass);
            $u_stmt->execute();

            generateAndSendOTP($val_email, 'signup');
            $encoded_email = urlencode($val_email);
            session_write_close();
            echo "<script>window.location.href='index.php?page=otp_verify&type=signup&email={$encoded_email}';</script>";
            exit();
        }
    } else { $error = "All form boxes must be filled!"; }
}
?>

<style>
    .signup-wrapper {
        padding: 5px 0;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    .brand-header {
        text-align: center;
        margin-bottom: 22px;
    }
    .brand-logo-text {
        font-size: 26px;
        font-weight: 900;
        color: #0D47A1;
        margin-bottom: 4px;
    }
    .brand-logo-text span { color: #FF9800; }
    .brand-sub-text { color: #64748b; font-size: 13px; font-weight: 600; }

    .field-group {
        text-align: left;
        margin-bottom: 16px;
    }
    .field-group label {
        display: block;
        font-size: 12px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .input-box-relative {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-box-relative i {
        position: absolute;
        left: 14px;
        color: #94a3b8;
        font-size: 15px;
    }
    .input-box-relative input {
        width: 100%;
        padding: 12px 14px 12px 42px;
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        font-size: 14px;
        color: #0f172a;
        font-weight: 600;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .input-box-relative input:focus {
        border-color: #0D47A1;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
    }
    .btn-submit-signup {
        width: 100%;
        padding: 14px;
        background: #0D47A1;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        margin-top: 10px;
        box-shadow: 0 8px 18px rgba(13, 71, 161, 0.2);
        transition: 0.2s ease;
    }
    .btn-submit-signup:hover {
        background: #1565C0;
        transform: translateY(-1px);
    }
</style>

<div class="signup-wrapper">
    <div class="brand-header">
        <div class="brand-logo-text">QHP <span>SuperApp</span></div>
        <div class="brand-sub-text">Create New Account & Verify OTP</div>
    </div>

    <?php if(isset($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:18px; border:1px solid #fca5a5; display:flex; align-items:center; gap:8px;">
            <i class="fas fa-circle-exclamation"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form action="index.php?page=signup" method="POST">
        <div class="field-group">
            <label>Full Identity Name</label>
            <div class="input-box-relative">
                <i class="fas fa-user"></i>
                <input type="text" name="full_name" placeholder="e.g. Rahul Sharma" value="<?= htmlspecialchars($val_name) ?>" required>
            </div>
        </div>

        <div class="field-group">
            <label>Email Address</label>
            <div class="input-box-relative">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="e.g. name@example.com" value="<?= htmlspecialchars($val_email) ?>" required>
            </div>
        </div>

        <div class="field-group">
            <label>Mobile Number</label>
            <div class="input-box-relative">
                <i class="fas fa-phone"></i>
                <input type="tel" name="phone" placeholder="10-Digit Phone Number" value="<?= htmlspecialchars($val_phone) ?>" required>
            </div>
        </div>

        <div class="field-group">
            <label>Password</label>
            <div class="input-box-relative">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Create strong password" required>
            </div>
        </div>

        <button type="submit" class="btn-submit-signup">
            <i class="fas fa-paper-plane" style="margin-right:6px;"></i> Register & Send Security OTP
        </button>
    </form>

    <p style="margin-top:22px; text-align:center; font-size:14px; color:#64748b; font-weight:600;">
        Already registered? <a href="index.php?page=login" style="color:#0D47A1; font-weight:800; text-decoration:none;">Login Here</a>
    </p>
</div>
