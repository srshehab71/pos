<?php
include '../config/db.php';
session_start();

if (isset($_GET['phone'])) {
    $phone = $_GET['phone'];
    $gid = $_SESSION['godown_id'];
    
    $stmt = $conn->prepare("SELECT * FROM customers WHERE phone = ? AND godown_id = ?");
    $stmt->execute([$phone, $gid]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($customer); // ডাটাটি JSON আকারে পাঠাবে
}
?>