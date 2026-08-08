<?php
header('Content-Type: application/json');
require_once '../db.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 1;

$stmt = $conn->prepare("SELECT * FROM delivery_locations WHERE order_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$res = $stmt->get_result();

if ($row = $res->fetch_assoc()) {
    echo json_encode([
        'status' => 'success',
        'lat' => floatval($row['current_lat']),
        'lng' => floatval($row['current_lng']),
        'driver_name' => $row['driver_name'],
        'driver_phone' => $row['driver_phone'],
        'order_status' => $row['order_status'],
        'updated_at' => $row['updated_at']
    ]);
} else {
    // Default Fallback Coordinates
    echo json_encode([
        'status' => 'success',
        'lat' => 16.8282,
        'lng' => 81.8961,
        'driver_name' => 'QHP Express Rider',
        'driver_phone' => '9876543210',
        'order_status' => 'On the way'
    ]);
}
?>