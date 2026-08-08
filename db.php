<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "qhp_superapp";

// Connect to MySQL server
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Ensure database exists
$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($dbname);

// Ensure essential tables exist
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0");

$conn->query("CREATE TABLE IF NOT EXISTS temp_otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    otp_type VARCHAR(50) DEFAULT 'signup',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    restaurant_id INT DEFAULT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) DEFAULT 'Cash on Delivery',
    delivery_address TEXT,
    status VARCHAR(50) DEFAULT 'Pending',
    otp VARCHAR(10),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS restaurant_id INT DEFAULT NULL");

$conn->query("CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    item_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 1
)");

$conn->query("CREATE TABLE IF NOT EXISTS delivery_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    driver_name VARCHAR(100),
    driver_phone VARCHAR(20),
    current_lat DECIMAL(10,8),
    current_lng DECIMAL(11,8)
)");

$conn->query("CREATE TABLE IF NOT EXISTS subcategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    sub_category_name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-utensils'
)");

// Seed default subcategories matching user structure
$sub_check = $conn->query("SELECT COUNT(*) as cnt FROM subcategories");
if ($sub_check && ($sub_row = $sub_check->fetch_assoc())) {
    // Re-populate to match exact requested list
    $conn->query("TRUNCATE TABLE subcategories");
    $default_subs = [
        ['Food', 'Food Services', 'fa-utensils'],
        ['Grocery', 'Groceries', 'fa-basket-shopping'],
        ['Grocery', 'Supermarkets', 'fa-store'],
        ['Grocery', 'Water Plants', 'fa-glass-water'],
        ['Grocery', 'Fancy Stores', 'fa-gem'],
        ['Parcel', 'Pickup and Delivery Services', 'fa-box-open'],
        ['Medicines', 'Medical Stores', 'fa-clinic-medical'],
        ['Local Services', 'AC Technicians', 'fa-snowflake'],
        ['Local Services', 'Plumbers', 'fa-wrench'],
        ['Local Services', 'Carpenters', 'fa-hammer'],
        ['Local Services', 'Car Servicing', 'fa-car-side'],
        ['Local Services', 'Home Beauty Makers', 'fa-spa'],
        ['Local Services', 'Tailoring', 'fa-scissors'],
        ['Local Services', 'Photography Digitals', 'fa-camera'],
        ['Local Services', 'Event Planners', 'fa-calendar-check'],
        ['Travel & Stay', 'Lodges', 'fa-hotel'],
        ['Travel & Stay', 'Car Rentals', 'fa-car'],
        ['Travel & Stay', 'Trip Planners', 'fa-map-location-dot'],
        ['Travel & Stay', 'Tourist Guiders', 'fa-compass']
    ];
    $stmt = $conn->prepare("INSERT INTO subcategories (category_name, sub_category_name, icon) VALUES (?, ?, ?)");
    foreach ($default_subs as $ds) {
        $stmt->bind_param("sss", $ds[0], $ds[1], $ds[2]);
        $stmt->execute();
    }
}

// Ensure Restaurants table exists
$conn->query("CREATE TABLE IF NOT EXISTS restaurants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    image_url VARCHAR(255),
    address TEXT,
    lat DECIMAL(10,8) DEFAULT 16.8282,
    lng DECIMAL(11,8) DEFAULT 81.8961,
    range_km DECIMAL(5,2) DEFAULT 25.00,
    rating DECIMAL(3,2) DEFAULT 4.5,
    is_active TINYINT(1) DEFAULT 1
)");

// Ensure Food Items table exists
$conn->query("CREATE TABLE IF NOT EXISTS food_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) DEFAULT 'General',
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    description TEXT,
    is_available TINYINT(1) DEFAULT 1
)");

// Ensure Groceries table exists
$conn->query("CREATE TABLE IF NOT EXISTS groceries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    description TEXT,
    is_available TINYINT(1) DEFAULT 1
)");

// Ensure Medicines table exists
$conn->query("CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255),
    description TEXT,
    is_available TINYINT(1) DEFAULT 1
)");

// Ensure Password Resets table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Ensure Lodges table exists
$conn->query("CREATE TABLE IF NOT EXISTS lodges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    price_per_night DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description TEXT
)");

// Ensure Car Rentals table exists
$conn->query("CREATE TABLE IF NOT EXISTS car_rentals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    features VARCHAR(255) NOT NULL
)");

// Ensure Fancy Items table exists
$conn->query("CREATE TABLE IF NOT EXISTS fancy_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description TEXT
)");

// Ensure Tourist Guides table exists
$conn->query("CREATE TABLE IF NOT EXISTS tourist_guides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guide_name VARCHAR(255) NOT NULL,
    languages VARCHAR(255) NOT NULL,
    experience_years INT NOT NULL,
    price_per_day DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    bio TEXT
)");

// Ensure Trip Packages table exists
$conn->query("CREATE TABLE IF NOT EXISTS trip_packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NOT NULL,
    destination VARCHAR(255) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    price_per_person DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    description TEXT
)");

// Seed Restaurants & Food Items if empty
$rest_chk = $conn->query("SELECT COUNT(*) as cnt FROM restaurants");
if ($rest_chk && ($r_row = $rest_chk->fetch_assoc()) && $r_row['cnt'] == 0) {
    $conn->query("INSERT INTO restaurants (name, image_url, address, lat, lng, range_km, rating, is_active) VALUES
    ('Spicy Paradise Biryani', 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?q=80&w=800&auto=format&fit=crop', 'Main Road, Rajahmundry', 16.8282, 81.8961, 30.00, 4.8, 1),
    ('South Tiffins Corner', 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?q=80&w=800&auto=format&fit=crop', 'Station Road, Kovvur', 16.8320, 81.7280, 25.00, 4.6, 1)");
    
    $rid1 = 1; $rid2 = 2;
    $conn->query("INSERT INTO food_items (restaurant_id, item_name, category, price, image_url, description, is_available) VALUES
    ($rid1, 'Special Chicken Dum Biryani', 'Biryani & Rice', 280.00, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?q=80&w=800&auto=format&fit=crop', 'Aromatic Basmati Rice cooked with tender spiced chicken pieces.', 1),
    ($rid1, 'Paneer Butter Masala', 'North & South Indian', 220.00, 'https://images.unsplash.com/photo-1631452180519-c014fe946bc7?q=80&w=800&auto=format&fit=crop', 'Rich creamy cottage cheese curry with Indian spices.', 1),
    ($rid2, 'Ghee Karam Dosa', 'Tiffins & Breakfast', 80.00, 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?q=80&w=800&auto=format&fit=crop', 'Crispy Dosa smeared with pure Ghee and spicy chili paste.', 1),
    ($rid2, 'Idli Sambar (4 Pcs)', 'Tiffins & Breakfast', 50.00, 'https://images.unsplash.com/photo-1589301760014-d929f3979dbc?q=80&w=800&auto=format&fit=crop', 'Steamed rice cakes served with hot lentil sambar.', 1)");
}

// Seed Groceries if empty
$groc_chk = $conn->query("SELECT COUNT(*) as cnt FROM groceries");
if ($groc_chk && ($g_row = $groc_chk->fetch_assoc()) && $g_row['cnt'] == 0) {
    $conn->query("INSERT INTO groceries (name, price, image_url, description, is_available) VALUES
    ('Fortune Sunlite Refined Sunflower Oil (1L)', 145.00, 'https://images.unsplash.com/photo-1610348725531-843dff563e2c?q=80&w=800&auto=format&fit=crop', 'Pure healthy cooking oil for daily cooking.', 1),
    ('Fresh Tomato (1 kg)', 35.00, 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?q=80&w=800&auto=format&fit=crop', 'Farm fresh red juicy tomatoes.', 1),
    ('Heritage Toned Fresh Milk (500ml)', 28.00, 'https://images.unsplash.com/photo-1550583724-b2692b85b150?q=80&w=800&auto=format&fit=crop', 'Pasteurized toned milk rich in calcium.', 1),
    ('Aashirvaad Shuddh Chakki Atta (5kg)', 240.00, 'https://images.unsplash.com/photo-1574316071802-0d684efa7bf5?q=80&w=800&auto=format&fit=crop', '100% pure whole wheat flour.', 1)");
}

// Seed Medicines if empty
$med_chk = $conn->query("SELECT COUNT(*) as cnt FROM medicines");
if ($med_chk && ($m_row = $med_chk->fetch_assoc()) && $m_row['cnt'] == 0) {
    $conn->query("INSERT INTO medicines (name, price, image_url, description, is_available) VALUES
    ('Dolo 650mg Paracetamol Tablets (15 Strip)', 32.00, 'https://images.unsplash.com/photo-1471864190281-a93a3070b6de?q=80&w=800&auto=format&fit=crop', 'Effective relief from fever and mild to moderate pain.', 1),
    ('Dettol Antiseptic Liquid 250ml', 120.00, 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?q=80&w=800&auto=format&fit=crop', 'Trusted protection against germs for first aid and personal hygiene.', 1),
    ('Pampers Baby Dry Diapers (Medium - 20 Pcs)', 399.00, 'https://images.unsplash.com/photo-1519689680058-324335c77eba?q=80&w=800&auto=format&fit=crop', 'Soft and comfortable diapers with 12 hours absorption.', 1)");
}

// Seed Lodges if empty
$lodge_chk = $conn->query("SELECT COUNT(*) as cnt FROM lodges");
if ($lodge_chk && ($l_row = $lodge_chk->fetch_assoc()) && $l_row['cnt'] == 0) {
    $conn->query("INSERT INTO lodges (name, location, price_per_night, image_url, description) VALUES
    ('Grand Palace Lodge', 'City Center, Main Road', 1200.00, 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=800&auto=format&fit=crop', 'Deluxe AC Rooms with Free Wi-Fi and 24/7 Room Service.'),
    ('Royal Comfort Stay', 'Station Road, Near Metro', 950.00, 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?q=80&w=800&auto=format&fit=crop', 'Affordable and hygienic rooms suitable for family and business travelers.')");
}

$conn->query("CREATE TABLE IF NOT EXISTS slot_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    booking_date DATE NOT NULL,
    slot_number INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    address_text TEXT NOT NULL,
    is_primary TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS account_deletion_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_email VARCHAR(100) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Seed a default demo user if no users exist
$check_users = $conn->query("SELECT COUNT(*) as cnt FROM users");
if ($check_users && $check_users->fetch_assoc()['cnt'] == 0) {
    $demo_pass = password_hash("password123", PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (full_name, email, phone, password) VALUES ('John Doe', 'demo@qhp.com', '9876543210', '$demo_pass')");
}

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
?>