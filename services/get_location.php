<?php
include '../db.php'; // నీ డేటాబేస్ కనెక్షన్ ఫైల్ పేరు ఇక్కడ ఇవ్వు

$order_id = intval($_GET['order_id'] ?? 0);
$res = $conn->query("SELECT latitude, longitude FROM orders WHERE id = $order_id");
if ($row = $res->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(['latitude' => null, 'longitude' => null]);
}
?>