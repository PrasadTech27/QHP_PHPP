<?php
ob_start();
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
require_once 'db.php';

if (!isset($_SESSION['user_id']) && isset($_COOKIE['qhp_user_id']) && !empty($_COOKIE['qhp_user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['qhp_user_id'];
    $_SESSION['user_name'] = $_COOKIE['qhp_user_name'] ?? 'User';
}

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// Dynamic Routing Handler
$page = isset($_GET['page']) ? $_GET['page'] : ($is_logged_in ? 'home' : 'splash');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QHP Customer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background:#f0f4f8; display:flex; min-height: 100vh; overflow-x: hidden; }

        .sidebar-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.4); z-index: 2999; display: none; opacity: 0; transition: opacity 0.3s ease;
        }
        .sidebar-overlay.active { display: block; opacity: 1; }

        .sidebar { 
            width: 260px; height: 100vh; background: #fff; padding: 25px 20px; 
            box-shadow: 4px 0 20px rgba(0,0,0,.1); position: fixed; left: -270px; top: 0;
            display: flex; flex-direction: column; z-index: 3000;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .sidebar.open { left: 0; }
        
        .sidebar .logo { font-size: 26px; font-weight: 800; color: #0D47A1; margin-bottom: 30px; }
        .sidebar .logo span { color: #FF9800; }
        .sidebar ul { list-style: none; }
        .sidebar li a { 
            display: flex; align-items: center; gap: 14px; padding: 14px 18px; border-radius: 12px; 
            margin-bottom: 10px; color: #475569; text-decoration: none; font-weight: 700; font-size: 15px; transition: 0.2s;
        }
        .sidebar li.active a, .sidebar li a:hover { background: #eaf2ff; color: #0D47A1; }

        .main { flex: 1; width: 100%; padding: 0 20px 100px 20px; overflow-y: auto; }

        .topbar { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 16px 0; width: 100%; margin-bottom: 25px;
            background: #f0f4f8;
            border-bottom: 2px solid #cbd5e1;
            box-shadow: 0 4px 10px -4px rgba(0, 0, 0, 0.05);
            position: sticky; top: 0; z-index: 1000;
        }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        
        .menu-toggle-btn {
            background: none; border: none; font-size: 24px; color: #0D47A1; cursor: pointer;
            padding: 6px; display: flex; align-items: center; justify-content: center;
        }

        .location-pill {
            display: flex; align-items: center; gap: 8px; background: #ffffff;
            padding: 8px 18px; border-radius: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid #cbd5e1; font-size: 14px; font-weight: 700; color: #0D47A1;
            cursor: pointer; transition: 0.2s ease;
        }
        .location-pill:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(13,71,161,0.12); }
        .location-pill i { color: #d32f2f; font-size: 15px; }
        .location-text { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .profile-avatar-btn {
            width: 44px; height: 44px; border-radius: 50%; background: #0D47A1; color: #ffffff;
            display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800;
            box-shadow: 0 4px 12px rgba(13,71,161,0.25); border: 2px solid #ffffff; text-decoration: none; cursor: pointer;
        }

        /* CAPSULE FLOATING BOTTOM NAVIGATION BAR (HOME | CART | PROFILE) */
        .bottom-nav-container {
            position: fixed; bottom: 15px; left: 50%; transform: translateX(-50%);
            width: 92%; max-width: 420px; z-index: 2000;
        }
        .capsule-bottom-bar {
            background: #ffffff;
            border-radius: 40px;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(13, 71, 161, 0.15);
            border: 1px solid #e2e8f0;
        }
        .capsule-tab-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 0;
            border-radius: 30px;
            color: #64748b;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            gap: 4px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .capsule-tab-item i { font-size: 18px; }
        
        /* Active Capsule Tab Styling (QHP Theme) */
        .capsule-tab-item.active {
            background: #e0f2fe;
            color: #0D47A1;
        }
        
        /* Divider Line between Tabs */
        .tab-divider {
            width: 1px;
            height: 24px;
            background: #e2e8f0;
        }

        @media (max-width: 480px) {
            .location-text { max-width: 120px; }
            .bottom-nav-container { width: 94%; bottom: 12px; }
        }
    </style>
    <script>
        function openDrawer() {
            const drawer = document.getElementById('sidebarDrawer');
            const overlay = document.getElementById('sidebarOverlay');
            if (drawer) drawer.classList.add('open');
            if (overlay) overlay.classList.add('active');
        }
        function closeDrawer() {
            const drawer = document.getElementById('sidebarDrawer');
            const overlay = document.getElementById('sidebarOverlay');
            if (drawer) drawer.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
        }

        function detectGPSLocation() {
            const locSpan = document.getElementById('txtUserLocation');
            if (!locSpan) return;
            if (navigator.geolocation) {
                locSpan.textContent = "Syncing...";
                navigator.geolocation.getCurrentPosition(pos => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                        .then(res => res.json())
                        .then(data => {
                            const addr = data.address || {};
                            const area = addr.suburb || addr.neighbourhood || addr.village || addr.town || addr.city || "Exact Location";
                            if (locSpan) locSpan.textContent = area;
                        }).catch(() => { if (locSpan) locSpan.textContent = "Live Location"; });
                }, () => { if (locSpan) locSpan.textContent = "Live Location"; });
            } else {
                if (locSpan) locSpan.textContent = "Live Location";
            }
        }

        function updateGlobalNavCartBadge() {
            let cart = JSON.parse(localStorage.getItem('qhp_cart')) || [];
            let totalItems = 0;
            cart.forEach(i => { totalItems += (i.qty || 0); });

            const navBadge = document.getElementById('navCartBadge');
            if (navBadge) {
                if (totalItems > 0) {
                    navBadge.innerText = totalItems;
                    navBadge.style.display = 'inline-block';
                } else {
                    navBadge.style.display = 'none';
                }
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener("DOMContentLoaded", function() {
                detectGPSLocation();
                updateGlobalNavCartBadge();
            });
        } else {
            detectGPSLocation();
            updateGlobalNavCartBadge();
        }
    </script>
</head>
<body>

<?php if ($is_logged_in): ?>
    <!-- Drawer Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeDrawer()"></div>

    <!-- Navigation Drawer Sidebar -->
    <div class="sidebar" id="sidebarDrawer">
        <div class="logo">QHP <span>Customer</span></div>
        <ul>
            <li class="<?= $page === 'home' ? 'active' : '' ?>"><a href="index.php?page=home"><i class="fas fa-home"></i> <span>Dashboard</span></a></li>
            <li class="<?= $page === 'food' ? 'active' : '' ?>"><a href="index.php?page=food"><i class="fas fa-utensils"></i> <span>Food Delivery</span></a></li>
            <li class="<?= $page === 'parcel' ? 'active' : '' ?>"><a href="index.php?page=parcel"><i class="fas fa-box-open"></i> <span>Express Parcel</span></a></li>
            <li class="<?= $page === 'order_history' ? 'active' : '' ?>"><a href="index.php?page=order_history"><i class="fas fa-clock-rotate-left"></i> <span>My Orders</span></a></li>
            <li class="<?= $page === 'orders' ? 'active' : '' ?>"><a href="index.php?page=orders"><i class="fas fa-shopping-cart"></i> <span>Cart</span></a></li>
            <li class="<?= $page === 'profile' ? 'active' : '' ?>"><a href="index.php?page=profile"><i class="fas fa-user"></i> <span>My Profile</span></a></li>
            <li><a href="logout.php" style="color: #d32f2f;"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle-btn" onclick="openDrawer()"><i class="fas fa-bars"></i></button>
                <div class="location-pill" onclick="detectGPSLocation()">
                    <i class="fas fa-location-crosshairs"></i>
                    <span id="txtUserLocation" class="location-text">Detecting Location...</span>
                </div>
            </div>
            <a href="index.php?page=profile" class="profile-avatar-btn">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </a>
        </div>

   <div class="content">
    <?php
    switch ($page) {
        case 'services':
            if (isset($_GET['sub']) && $_GET['sub'] === 'Lodges') {
                echo "<script>window.location.href='index.php?page=lodges';</script>";
                exit();
            }
            if (isset($_GET['sub']) && $_GET['sub'] === 'Car Rentals') {
                echo "<script>window.location.href='index.php?page=car_rentals';</script>";
                exit();
            }
            if (isset($_GET['sub']) && $_GET['sub'] === 'Trip Planners') {
                echo "<script>window.location.href='index.php?page=trip_planners';</script>";
                exit();
            }
            if (isset($_GET['sub']) && ($_GET['sub'] === 'Tourist Guides' || $_GET['sub'] === 'Tourist Guiders')) {
                echo "<script>window.location.href='index.php?page=tourist_guides';</script>";
                exit();
            }
            if (isset($_GET['sub']) && $_GET['sub'] === 'Fancy Stores') {
                echo "<script>window.location.href='index.php?page=fancy_stores';</script>";
                exit();
            }
            include 'services.php';
            break;
        case 'grocery':
            if (isset($_GET['sub']) && $_GET['sub'] === 'Fancy Stores') {
                echo "<script>window.location.href='index.php?page=fancy_stores';</script>";
                exit();
            }
            include 'screens/grocery.php';
            break;
        case 'home':
            if (isset($_GET['sub'])) {
                if ($_GET['sub'] === 'Lodges') {
                    echo "<script>window.location.href='index.php?page=lodges';</script>";
                    exit();
                }
                if ($_GET['sub'] === 'Car Rentals') {
                    echo "<script>window.location.href='index.php?page=car_rentals';</script>";
                    exit();
                }
                if ($_GET['sub'] === 'Trip Planners') {
                    echo "<script>window.location.href='index.php?page=trip_planners';</script>";
                    exit();
                }
                if ($_GET['sub'] === 'Tourist Guides' || $_GET['sub'] === 'Tourist Guiders') {
                    echo "<script>window.location.href='index.php?page=tourist_guides';</script>";
                    exit();
                }
                if ($_GET['sub'] === 'Fancy Stores') {
                    echo "<script>window.location.href='index.php?page=fancy_stores';</script>";
                    exit();
                }
                $sub_val = urlencode($_GET['sub']);
                echo "<script>window.location.href='index.php?page=services&sub={$sub_val}';</script>";
                exit();
            }
            include 'screens/home.php';
            break;
        case 'search': 
        include 'screens/search.php'; 
        break;
        case 'lodges': include 'lodges.php'; break;
        case 'car_rentals': include 'car_rentals.php'; break;
        case 'trip_planners': include 'trip_planners.php'; break;
        case 'tourist_guides': include 'tourist_guides.php'; break;
        case 'fancy_stores': include 'fancy_stores.php'; break;
        case 'partner': include 'services/partner.php'; break;
        case 'admin_groceries': include 'screens/admin_groceries.php'; break;
        case 'admin_medicines': include 'screens/admin_medicines.php'; break;
        case 'medicines': include 'screens/medicine.php'; break;
        case 'admin_restaurants': include 'screens/admin_restaurants.php'; break;
        case 'admin_items': include 'screens/admin_items.php'; break;
        case 'order_tracking': include 'screens/order_tracking.php'; break;
        case 'booking_confirmation': include 'screens/booking_confirmation.php'; break;
        case 'order_history': include 'screens/order_history.php'; break;
        case 'parcel': include 'screens/parcel.php'; break;
        case 'thodu': include 'screens/thodu.php'; break;
        case 'food': include 'screens/food.php'; break;
        case 'orders': include 'screens/orders.php'; break;
        case 'profile': include 'screens/profile.php'; break;
        default: include 'screens/home.php'; break;
    }
    ?>
</div>
    <!-- CAPSULE FLOATING BOTTOM NAVIGATION BAR (HOME | CART | PROFILE) -->
    <div class="bottom-nav-container">
        <nav class="capsule-bottom-bar">
            <a href="index.php?page=home" class="capsule-tab-item <?= ($page==='home' || $page==='food' || $page==='parcel' || $page==='thodu')?'active':'' ?>">
                <i class="fas fa-house"></i>
                <span>Home</span>
            </a>
            
            <div class="tab-divider"></div>

            <a href="index.php?page=orders" class="capsule-tab-item <?= ($page==='orders')?'active':'' ?>" style="position: relative;">
                <i class="fas fa-shopping-bag"></i>
                <span>Cart</span>
                <!-- Cart Badge Added Here -->
                <span id="navCartBadge" style="position: absolute; top: 4px; right: 28%; background: #dc2626; color: #fff; font-size: 10px; font-weight: 800; padding: 1px 5px; border-radius: 50%; display: none;">0</span>
            </a>

            <div class="tab-divider"></div>

            <a href="index.php?page=profile" class="capsule-tab-item <?= ($page==='profile' || $page==='order_history')?'active':'' ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </nav>
    </div>

<?php else: ?>

    <!-- Dynamic Unauthenticated Screen Routing -->
    <?php if ($page === 'splash'): ?>
        <div style="width:100%; min-height:100vh;">
            <?php include 'screens/splash.php'; ?>
        </div>
    <?php else: ?>
        <div style="width:100%; display:flex; justify-content:center; align-items:center; min-height:100vh; padding: 20px;">
            <div style="background:#fff; padding:35px 28px; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.08); width:100%; max-width:420px; border: 1px solid #e2e8f0;">
                <?php
                switch ($page) {
                    case 'signup': include 'screens/signup.php'; break;
                    case 'otp_verify': include 'screens/otp_verify.php'; break;
                    case 'forgot_password': include 'screens/forgot_password.php'; break;
                    case 'reset_password': include 'screens/reset_password.php'; break;
                    case 'login': default: include 'screens/login.php'; break;
                }
                ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>