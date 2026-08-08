<?php
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        $res = $conn->query("SELECT email FROM password_resets WHERE token = '$token' ORDER BY id DESC LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $email = $row['email'];
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            
            $conn->query("UPDATE users SET password = '$new_hash' WHERE email = '$email'");
            $conn->query("DELETE FROM password_resets WHERE email = '$email'");

            echo "<script>alert('Password reset successful! Login now.'); window.location.href='index.php?page=login';</script>";
            exit();
        } else { $error = "Invalid or expired reset token!"; }
    } else { $error = "Passwords do not match!"; }
}
?>

<h2 style="color:#0D47A1; margin-bottom: 8px; font-weight:800;">Reset Password</h2>
<p style="color:#64748b; font-size:14px; margin-bottom:20px;">Create a new password for your account</p>

<?php if(isset($error)) echo "<p style='color:#dc2626; margin-bottom:15px; font-size:14px;'>$error</p>"; ?>

<form method="POST">
    <div class="form-group"><label>New Password</label><input type="password" name="new_password" required></div>
    <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" required></div>
    <button type="submit" class="btn" style="background:#FF9800;">Update Password</button>
</form>