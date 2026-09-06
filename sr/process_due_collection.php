<?php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: due_collection.php");
    exit();
}

try {
    $gid = $_SESSION['godown_id'];
    $uid = $_SESSION['user_id'];
    
    $phone = $_POST['customer_phone'];
    $name = $_POST['customer_name'];
    $address = $_POST['customer_address'];
    $amount = (float)$_POST['collection_amount'];
    $note = $_POST['note'] ?? '';

    if ($amount <= 0) {
        throw new Exception("আদায়ের পরিমাণ অবশ্যই ০ থেকে বেশি হতে হবে!");
    }

    $conn->beginTransaction();

    // কালেকশন এন্ট্রি (Total 0 থাকবে, Paid Amount এ টাকা বসবে)
    $stmt = $conn->prepare("INSERT INTO sales (
        godown_id, user_id, customer_name, customer_phone, customer_address, 
        total_amount, payable_amount, paid_amount, due_amount
    ) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)");

    // due_amount কে মাইনাস ফিগারে দিচ্ছি যাতে SUM করলে বাকী কমে যায়
    $due_effect = -$amount;
    $log_name = $name . " - (বাকী আদায়)";

    $stmt->execute([$gid, $uid, $log_name, $phone, $address, $amount, $due_effect]);
    
    $sale_id = $conn->lastInsertId();
    $conn->commit();

    header("Location: sales_history.php?success=1");
    exit();

} catch (Exception $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    echo "<div style='color:red; font-weight:bold; text-align:center; margin-top:50px;'>এরর: " . $e->getMessage() . "</div>";
    echo "<br><center><a href='due_collection.php'>পিছনে যান</a></center>";
}