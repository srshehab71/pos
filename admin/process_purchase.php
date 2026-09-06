<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $godown_id = $_SESSION['godown_id'];
    $supplier_id = $_POST['supplier_id'];
    $chalan_no = $_POST['chalan_no'];
    $total_bill = $_POST['total_bill'];
    $paid_amount = $_POST['paid_amount'];
    $due_amount = $_POST['due_amount'];

    try {
        $conn->beginTransaction();

        // ১. মেইন পারচেস রেকর্ড ইনসার্ট করা (স্ট্যাটাস ডিফল্টভাবে 'Ordered' থাকবে)
        $stmt = $conn->prepare("INSERT INTO purchases (godown_id, supplier_id, chalan_no, total_amount, paid_amount, due_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Ordered', NOW())");
        $stmt->execute([$godown_id, $supplier_id, $chalan_no, $total_bill, $paid_amount, $due_amount]);
        $purchase_id = $conn->lastInsertId();

        // ২. আইটেমগুলো লুপ চালিয়ে শুধুমাত্র লিস্ট সেভ করা
        if (isset($_POST['p_ids'])) {
            foreach ($_POST['p_ids'] as $index => $p_id) {
                $type = $_POST['p_types'][$index]; 
                $buy_price = $_POST['p_buy_prices'][$index];
                $sell_price = $_POST['p_sell_prices'][$index];
                $qty = $_POST['p_qtys'][$index];
                $expiry = !empty($_POST['p_expiries'][$index]) ? $_POST['p_expiries'][$index] : null;

                // পারচেস আইটেম ডিটেইলস সেভ করা (এখানে received_qty ডিফল্ট ০ থাকবে)
                $item_stmt = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, item_type, qty, received_qty, purchase_price, sell_price, expiry_date) VALUES (?, ?, ?, ?, 0, ?, ?, ?)");
                $item_stmt->execute([$purchase_id, $p_id, $type, $qty, $buy_price, $sell_price, $expiry]);

                /* 
                   এখানে আগে স্টক আপডেট করার কোড ছিল, 
                   অর্ডার সিস্টেমে এটি মুছে ফেলা হয়েছে। 
                   এখন মাল স্টকে যোগ হবে শুধুমাত্র রিসিভ করার সময়।
                */
            }
        }

        $conn->commit();
        header("Location: purchase_list.php?success=ordered");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("ভুল হয়েছে: " . $e->getMessage());
    }
} else {
    header("Location: purchase.php");
    exit();
}