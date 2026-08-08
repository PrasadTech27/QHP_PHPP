<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $token = md5(uniqid(rand(), true));
        $conn->query("INSERT INTO password_resets (email, token) VALUES ('$email', '$token')");

        $_SESSION['reset_token_debug'] = $token;
        $_SESSION['reset_email'] = $email;
        echo "<script>window.location.href='index.php?page=reset_password&token=$token';</script>";
        exit();
    } else {
        $error = "No registered account found with this email!";
    }
}
?>

<h2 style="color:#0D47A1; margin-bottom: 8px; font-weight:800;">Forgot Password</h2>
<p style="color:#64748b; font-size:14px; margin-bottom:20px;">Enter registered email to reset password</p>

<?php if(isset($error)) echo "<p style='color:#dc2626; margin-bottom:15px; font-size:14px;'>$error</p>"; ?>

<form method="POST">
    <div class="form-group"><label>Registered Email</label><input type="email" name="email" required></div>
    <button type="submit" class="btn" style="background:#0D47A1;">Generate Reset Link</button>
</form>

<p style="margin-top:20px; text-align:center; font-size:14px;"><a href="index.php?page=login" style="color:#0D47A1; font-weight:800;">Back to Login</a></p>