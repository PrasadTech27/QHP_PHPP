<?php
require_once '../db.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 1;

// Simulate slight movement (+0.0002 deg shift)
$stmt = $conn->prepare("SELECT current_lat, current_lng FROM delivery_locations WHERE order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if ($row) {
    $new_lat = $row['current_lat'] + (rand(-1, 2) * 0.00025);
    $new_lng = $row['current_lng'] + (rand(-1, 2) * 0.00025);

    $upd = $conn->prepare("UPDATE delivery_locations SET current_lat = ?, current_lng = ? WHERE order_id = ?");
    $upd->bind_param("ddi", $new_lat, $new_lng, $order_id);
    $upd->execute();
    echo "Driver Location Shifted to: $new_lat, $new_lng";
} else {
    echo "Order not found";
}
?>