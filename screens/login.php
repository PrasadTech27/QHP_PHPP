<?php
require_once 'otp_helper.php';

// Automatically detect if user is already logged in
if (isset($_SESSION['user_id'])) {
    echo "<script>window.location.href = 'index.php?page=home';</script>";
    exit();
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['login_temp_user'] = [
                    'id' => $user['id'],
                    'full_name' => $user['full_name'],
                    'email' => $email
                ];

                generateAndSendOTP($email, 'login');
                $encoded_email = urlencode($email);
                session_write_close();
                echo "<script>window.location.href = 'index.php?page=otp_verify&type=login&email={$encoded_email}';</script>";
                exit();
            } else {
                $error_msg = "Incorrect Password! Please try again.";
            }
        } else {
            $error_msg = "No account found with this email address!";
        }
    } else {
        $error_msg = "Please fill in all required fields!";
    }
}
?>

<style>
    .login-wrapper {
        padding: 5px 0;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    
    /* Header Styles */
    .brand-header {
        text-align: center;
        margin-bottom: 25px;
    }
    .brand-logo-text {
        font-size: 28px;
        font-weight: 800;
        color: #0D47A1;
        margin-bottom: 6px;
        letter-spacing: -0.5px;
    }
    .brand-logo-text span {
        color: #FF9800;
    }
    .brand-sub-text {
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
    }

    /* Form & Input Field Styles */
    .field-group {
        text-align: left;
        margin-bottom: 18px;
    }
    .field-group label {
        display: block;
        font-size: 13px;
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .input-box-relative {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-box-relative i.icon-prefix {
        position: absolute;
        left: 15px;
        color: #94a3b8;
        font-size: 16px;
    }
    .input-box-relative input {
        width: 100%;
        padding: 13px 15px 13px 44px;
        border: 1.5px solid #cbd5e1;
        border-radius: 12px;
        font-size: 14px;
        color: #1e293b;
        font-weight: 500;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .input-box-relative input::placeholder {
        color: #8a99ad;
        font-weight: 500;
    }
    .input-box-relative input:focus {
        border-color: #0D47A1;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.1);
    }
    .icon-toggle-pass {
        position: absolute;
        right: 15px;
        color: #94a3b8;
        cursor: pointer;
        font-size: 16px;
    }

    /* Forgot Password Link */
    .forgot-pass-link {
        display: block;
        text-align: right;
        font-size: 13px;
        font-weight: 800;
        color: #0D47A1;
        text-decoration: none;
        margin-top: -4px;
        margin-bottom: 22px;
    }

    /* Submit Button */
    .btn-submit-login {
        width: 100%;
        padding: 14px;
        background: #0D47A1;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(13, 71, 161, 0.25);
        transition: background 0.2s ease, transform 0.1s ease;
    }
    .btn-submit-login:active {
        transform: scale(0.98);
    }

    /* Footer Text */
    .footer-signup-text {
        margin-top: 25px;
        text-align: center;
        font-size: 14px;
        color: #64748b;
        font-weight: 500;
    }
    .footer-signup-text a {
        color: #0D47A1;
        font-weight: 800;
        text-decoration: none;
    }
</style>

<div class="login-wrapper">
    <!-- Centered Header -->
    <div class="brand-header">
        <div class="brand-logo-text">QHP <span>Customer</span></div>
        <div class="brand-sub-text">Welcome back! Access your workspace</div>
    </div>

    <?php if(!empty($error_msg)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:18px; border:1px solid #fca5a5; text-align:left;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error_msg ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="index.php?page=login">
        <!-- Email Input -->
        <div class="field-group">
            <label>Email Address</label>
            <div class="input-box-relative">
                <i class="fas fa-envelope icon-prefix"></i>
                <input type="email" name="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" placeholder="Enter your email" required>
            </div>
        </div>

        <!-- Password Input -->
        <div class="field-group">
            <label>Password</label>
            <div class="input-box-relative">
                <i class="fas fa-lock icon-prefix"></i>
                <input type="password" id="txtUserPassword" name="password" placeholder="Enter your password" required>
                <i class="fas fa-eye icon-toggle-pass" id="btnToggleEye" onclick="togglePassView()"></i>
            </div>
        </div>

        <!-- Forgot Link -->
        <a href="index.php?page=forgot_password" class="forgot-pass-link">Forgot Password?</a>

        <!-- Button -->
        <button type="submit" class="btn-submit-login">Verify Details & Get OTP</button>
    </form>

    <!-- Footer Signup Link -->
    <div class="footer-signup-text">
        New to QHP? <a href="index.php?page=signup">Create Account</a>
    </div>
</div>

<script>
    function togglePassView() {
        const input = document.getElementById('txtUserPassword');
        const eyeIcon = document.getElementById('btnToggleEye');
        
        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.classList.remove('fa-eye');
            eyeIcon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            eyeIcon.classList.remove('fa-eye-slash');
            eyeIcon.classList.add('fa-eye');
        }
    }
</script>