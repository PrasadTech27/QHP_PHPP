<?php
require_once 'otp_helper.php';

$type = isset($_REQUEST['type']) ? trim($_REQUEST['type']) : '';
if (empty($type)) {
    if (isset($_SESSION['signup_data']['email'])) {
        $type = 'signup';
    } else if (isset($_SESSION['login_temp_user']['email'])) {
        $type = 'login';
    } else if (isset($_SESSION['last_otp_type'])) {
        $type = $_SESSION['last_otp_type'];
    } else {
        $type = 'signup';
    }
}

$target_email = "";
if (isset($_REQUEST['email']) && filter_var($_REQUEST['email'], FILTER_VALIDATE_EMAIL)) {
    $target_email = trim($_REQUEST['email']);
} else if ($type === 'signup' && isset($_SESSION['signup_data']['email'])) {
    $target_email = $_SESSION['signup_data']['email'];
} else if ($type === 'login' && isset($_SESSION['login_temp_user']['email'])) {
    $target_email = $_SESSION['login_temp_user']['email'];
} else if (isset($_SESSION['signup_data']['email'])) {
    $type = 'signup';
    $target_email = $_SESSION['signup_data']['email'];
} else if (isset($_SESSION['login_temp_user']['email'])) {
    $type = 'login';
    $target_email = $_SESSION['login_temp_user']['email'];
} else if (isset($_SESSION['last_otp_email'])) {
    $target_email = $_SESSION['last_otp_email'];
}

// Handle Resend
if (isset($_GET['resend']) && $_GET['resend'] == '1' && !empty($target_email)) {
    generateAndSendOTP($target_email, $type);
    $resend_msg = "A new OTP code has been sent to your email!";
}

// Fallback Safety Check
if (empty($target_email)) {
    echo "<div style='text-align:center; padding:25px 15px; font-family:sans-serif;'>
            <div style='font-size:42px; color:#dc2626; margin-bottom:12px;'><i class='fas fa-clock-rotate-left'></i></div>
            <h3 style='color:#dc2626; font-size:20px; font-weight:800; margin-bottom:8px;'>Session Expired</h3>
            <p style='color:#64748b; font-size:14px; margin-bottom:24px; line-height:1.5;'>Your verification session was not found or timed out. Please register or login again.</p>
            <div style='display:flex; gap:10px; justify-content:center;'>
                <a href='index.php?page=signup' style='display:inline-block; padding:12px 20px; background:#0D47A1; color:#ffffff; font-weight:800; border-radius:10px; text-decoration:none; font-size:14px;'>Go to Signup</a>
                <a href='index.php?page=login' style='display:inline-block; padding:12px 20px; background:#f1f5f9; color:#334155; font-weight:800; border-radius:10px; text-decoration:none; font-size:14px;'>Go to Login</a>
            </div>
          </div>";
    return;
}

// Handle OTP Verification Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

    if (!empty($input_otp) && verifyOTP($target_email, $input_otp, $type)) {
        $safe_email = $conn->real_escape_string($target_email);

        if ($type === 'signup') {
            if (isset($_SESSION['signup_data']) && !empty($_SESSION['signup_data']['email'])) {
                $d = $_SESSION['signup_data'];
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, is_verified) VALUES (?, ?, ?, ?, 1) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), phone=VALUES(phone), password=VALUES(password), is_verified=1");
                $stmt->bind_param("ssss", $d['full_name'], $d['email'], $d['phone'], $d['password']);
                $stmt->execute();
            } else {
                // If session signup data was cleared, set is_verified = 1 for existing user row
                $conn->query("UPDATE users SET is_verified = 1 WHERE email = '$safe_email'");
            }

            $u_res = $conn->query("SELECT id, full_name FROM users WHERE email = '$safe_email'");
            if ($u_row = $u_res->fetch_assoc()) {
                $_SESSION['user_id'] = $u_row['id'];
                $_SESSION['user_name'] = $u_row['full_name'];
            }
            unset($_SESSION['signup_data']);
            unset($_SESSION['debug_last_otp']);
        } else {
            $u_res = $conn->query("SELECT id, full_name FROM users WHERE email = '$safe_email'");
            if ($u_row = $u_res->fetch_assoc()) {
                $_SESSION['user_id'] = $u_row['id'];
                $_SESSION['user_name'] = $u_row['full_name'];
            } else if (isset($_SESSION['login_temp_user']['id'])) {
                $_SESSION['user_id'] = $_SESSION['login_temp_user']['id'];
                $_SESSION['user_name'] = $_SESSION['login_temp_user']['full_name'];
            }
            unset($_SESSION['login_temp_user']);
            unset($_SESSION['debug_last_otp']);
        }

        $final_uid = $_SESSION['user_id'] ?? 0;
        $final_uname = $_SESSION['user_name'] ?? 'User';

        if ($final_uid > 0) {
            setcookie('qhp_user_id', (string)$final_uid, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);
            setcookie('qhp_user_name', (string)$final_uname, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);
        }

        session_write_close();

        $safe_name_js = addslashes($final_uname);
        echo "<script>
            document.cookie = 'qhp_user_id={$final_uid}; path=/; max-age=2592000; SameSite=None; Secure';
            document.cookie = 'qhp_user_name={$safe_name_js}; path=/; max-age=2592000; SameSite=None; Secure';
            try {
                localStorage.setItem('qhp_user_id', '{$final_uid}');
                localStorage.setItem('qhp_user_name', '{$safe_name_js}');
            } catch(e) {}
            window.location.href='index.php?page=home';
        </script>";
        exit();
    } else {
        $error = "Incorrect or Expired Security OTP Code!";
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>

<style>
    .otp-wrapper {
        text-align: center;
        padding: 10px 0;
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    
    .otp-header h2 {
        font-size: 26px;
        font-weight: 800;
        color: #0D47A1;
        margin-bottom: 6px;
    }
    .otp-header p {
        color: #64748b;
        font-size: 14px;
        margin-bottom: 25px;
    }

    /* 6 Digit Input Boxes Grid */
    .otp-boxes-grid {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 22px;
    }

    .otp-box-digit {
        width: 46px;
        height: 54px;
        border: 2px solid #cbd5e1;
        border-radius: 12px;
        text-align: center;
        font-size: 22px;
        font-weight: 800;
        color: #0D47A1;
        background: #f8fafc;
        outline: none;
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .otp-box-digit:focus {
        border-color: #0D47A1;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(13, 71, 161, 0.15);
    }

    /* Bounce / Lift-up Effect on Success */
    .otp-box-digit.lift-up {
        transform: translateY(-10px);
        box-shadow: 0 10px 20px rgba(34, 197, 94, 0.25);
    }

    /* Green Color Verified Transition */
    .otp-box-digit.verified-green {
        background: #22c55e !important;
        border-color: #22c55e !important;
        color: #ffffff !important;
    }

    /* Green Alert Banner */
    .success-alert-banner {
        display: none;
        background: #dcfce7;
        color: #15803d;
        border: 1.5px solid #86efac;
        padding: 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 800;
        margin-bottom: 20px;
        align-items: center;
        justify-content: center;
        gap: 8px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .btn-verify-submit {
        width: 100%;
        padding: 14px;
        background: #0D47A1;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 800;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(13, 71, 161, 0.2);
        transition: all 0.2s ease;
    }
    .btn-verify-submit:active {
        transform: scale(0.98);
    }
</style>

<div class="otp-wrapper">
    <div class="otp-header">
        <h2>OTP Verification</h2>
        <p>Security code sent to <strong><?= htmlspecialchars($target_email) ?></strong></p>
    </div>

    <div class="success-alert-banner" id="successBanner">
        <i class="fas fa-circle-check" style="font-size: 18px;"></i>
        <span>OTP Verified Successfully!</span>
    </div>

    <?php if(isset($resend_msg)): ?>
        <div style="background:#e0f2fe; color:#0369a1; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:18px; border:1px solid #7dd3fc;">
            <i class="fas fa-paper-plane" style="margin-right:6px;"></i> <?= $resend_msg ?>
        </div>
    <?php endif; ?>



    <?php if(isset($error)): ?>
        <div style="background:#fee2e2; color:#dc2626; padding:10px 14px; border-radius:10px; font-size:13px; font-weight:700; margin-bottom:18px; border:1px solid #fca5a5;">
            <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form id="frmOtpSubmit" method="POST" action="index.php?page=otp_verify&type=<?= urlencode($type) ?>&email=<?= urlencode($target_email) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($target_email) ?>">
        <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
        <input type="hidden" name="otp" id="hiddenOtpInput">

        <div class="otp-boxes-grid">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
            <input type="text" class="otp-box-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autocomplete="off">
        </div>

        <div style="text-align:center; margin-bottom:20px;">
            <span style="font-size:13px; color:#64748b;">Code Expires in: </span>
            <strong id="timerDisplay" style="color:#dc2626; font-size:15px; font-weight:800;">02:00</strong>
        </div>

        <button type="button" class="btn-verify-submit" id="btnTriggerVerify" onclick="triggerOtpAnimation()">Confirm & Proceed</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
        <button id="btnResendOTP" onclick="resendEmailOTP()" disabled style="background:none; border:none; color:#94a3b8; font-weight:800; cursor:not-allowed; font-size:14px;">
            <i class="fas fa-rotate-right"></i> Resend OTP Code
        </button>
    </div>
</div>

<script>
    // EmailJS Engine Trigger
    emailjs.init({ publicKey: "6OYyhfesYKGExGDPb" });

    <?php if(isset($_SESSION['debug_last_otp'])): ?>
        emailjs.send("service_qh1c06y", "template_judy2gg", {
            email: "<?= $target_email ?>",
            otp: "<?= $_SESSION['debug_last_otp'] ?>"
        }).then(res => console.log("EmailJS Sent Successfully!")).catch(err => console.log("EmailJS Error:", err));
    <?php endif; ?>

    // 6-Digit Box Focus Movement
    const digitInputs = document.querySelectorAll('.otp-box-digit');
    const hiddenOtpInput = document.getElementById('hiddenOtpInput');
    const otpForm = document.getElementById('frmOtpSubmit');

    digitInputs.forEach((input, idx) => {
        input.addEventListener('input', (e) => {
            if (input.value.length === 1 && idx < digitInputs.length - 1) {
                digitInputs[idx + 1].focus();
            }
            combineOtpValues();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && input.value === '' && idx > 0) {
                digitInputs[idx - 1].focus();
            }
        });

        // Paste Handling
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData('text').trim();
            if (/^\d{6}$/.test(pastedData)) {
                pastedData.split('').forEach((char, i) => {
                    digitInputs[i].value = char;
                });
                combineOtpValues();
                digitInputs[5].focus();
            }
        });
    });

    function autoFillOTP(code) {
        if (!code || code.length !== 6) return;
        const digits = code.split('');
        digitInputs.forEach((input, i) => {
            if (digits[i]) input.value = digits[i];
        });
        combineOtpValues();
    }

    function combineOtpValues() {
        let fullCode = '';
        digitInputs.forEach(i => fullCode += i.value);
        hiddenOtpInput.value = fullCode;

        // Auto trigger when 6 digits are entered
        if (fullCode.length === 6) {
            triggerOtpAnimation();
        }
    }

    let isSubmitting = false;

    // Lift Up & Green Animation Logic
    function triggerOtpAnimation() {
        if (isSubmitting) return;

        let fullCode = '';
        digitInputs.forEach(i => fullCode += i.value);
        hiddenOtpInput.value = fullCode;

        if (fullCode.length < 6) {
            alert('Please enter all 6 digits of the OTP code.');
            return;
        }

        isSubmitting = true;

        // Sequential Staggered Lift Up Animation for 6 boxes
        digitInputs.forEach((box, index) => {
            setTimeout(() => {
                box.classList.add('lift-up');
            }, index * 80); // 80ms delay per box
        });

        // Transition to Green Color & Show Message
        setTimeout(() => {
            digitInputs.forEach(box => {
                box.classList.remove('lift-up');
                box.classList.add('verified-green');
            });

            // Show Green Banner
            const banner = document.getElementById('successBanner');
            banner.style.display = 'flex';

            // Submit Form to Backend after animation (500ms delay)
            setTimeout(() => {
                otpForm.submit();
            }, 600);

        }, 650);
    }

    // Timer Countdown
    let timeLeft = 120;
    const timerElem = document.getElementById('timerDisplay');
    const resendBtn = document.getElementById('btnResendOTP');

    const countdown = setInterval(() => {
        timeLeft--;
        let mins = Math.floor(timeLeft / 60);
        let secs = timeLeft % 60;
        
        timerElem.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

        if (timeLeft <= 0) {
            clearInterval(countdown);
            timerElem.textContent = "EXPIRED";
            resendBtn.disabled = false;
            resendBtn.style.color = "#FF9800";
            resendBtn.style.cursor = "pointer";
        }
    }, 1000);

    function resendEmailOTP() {
        window.location.href = 'index.php?page=otp_verify&type=<?= urlencode($type) ?>&email=<?= urlencode($target_email) ?>&resend=1';
    }
</script>