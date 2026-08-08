<?php
session_start();
include '../db.php'; // నీ డేటాబేస్ కనెక్షన్ ఫైల్ పేరు ఇక్కడ ఇవ్వు

if (isset($_POST['lat']) && isset($_POST['lng'])) {
    $lat = floatval($_POST['lat']);
    $lng = floatval($_POST['lng']);

    $stmt = $conn->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE status IN ('Accepted', 'On the Way', 'Reached')");
    $stmt->bind_param("dd", $lat, $lng);
    $stmt->execute();
}
?>