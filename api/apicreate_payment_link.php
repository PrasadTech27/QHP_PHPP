<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit();
}

// Get JSON post data
$data = json_decode(file_get_contents('php://input'), true);
$amount = isset($data['amount']) ? floatval($data['amount']) : 0;

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid amount']);
    exit();
}

// Razorpay Test API Credentials
$key_id = 'rzp_test_TAcBXbDlDMvc29';
$key_secret = 'YOUR_RAZORPAY_KEY_SECRET'; // Fill your Razorpay Key Secret here if available, or keep blank for demo

$api_url = "https://api.razorpay.com/v1/payment_links";

// Amount converted to paise (₹100 = 10000 paise)
$payload = [
    'amount' => intval($amount * 100),
    'currency' => 'INR',
    'accept_partial' => false,
    'description' => 'QHP SuperApp Food Order Payment',
    'customer' => [
        'name' => 'Revanth Reddy',
        'email' => 'revanth@example.com',
        'contact' => '+919876543210'
    ],
    'notify' => [
        'sms' => false,
        'email' => false
    ],
    'reminder_enable' => false,
    'callback_url' => 'http://localhost:8080/qhp_app/index.php?page=booking_confirmation',
    'callback_method' => 'get'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_USERPWD, $key_id . ':' . $key_secret);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$res_data = json_decode($response, true);

if (isset($res_data['short_url'])) {
    echo json_encode([
        'success' => true,
        'payment_url' => $res_data['short_url'],
        'payment_id' => $res_data['id']
    ]);
} else {
    // Fallback Demo Dynamic UPI Intent URL generator for local testing
    $upi_id = "revanth@upi"; // Your Demo UPI ID
    $upi_url = "upi://pay?pa={$upi_id}&pn=QHP%20SuperApp&am={$amount}&cu=INR";
    echo json_encode([
        'success' => true,
        'payment_url' => $upi_url,
        'is_fallback' => true
    ]);
}
