<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
    session_set_cookie_params([
        'lifetime' => 86400 * 30,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None'
    ]);
    session_start();
}

$_SESSION = array();
unset($_COOKIE['qhp_user_id']);
unset($_COOKIE['qhp_user_name']);

$cookie_opts = [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
];

setcookie('qhp_user_id', '', $cookie_opts);
setcookie('qhp_user_name', '', $cookie_opts);

if (ini_get("session.use_cookies")) {
    setcookie(session_name(), '', $cookie_opts);
}

session_destroy();
session_write_close();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Logging out...</title>
</head>
<body>
<script>
    document.cookie = "qhp_user_id=; path=/; max-age=0; SameSite=None; Secure";
    document.cookie = "qhp_user_name=; path=/; max-age=0; SameSite=None; Secure";
    document.cookie = "PHPSESSID=; path=/; max-age=0; SameSite=None; Secure";
    try {
        localStorage.removeItem('qhp_user_id');
        localStorage.removeItem('qhp_user_name');
    } catch(e) {}
    window.location.href = 'index.php?page=login';
</script>
</body>
</html>