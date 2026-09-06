<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $sale_id = (int)$_GET['id'];
    $godown_id = $_SESSION['godown_id'];

    try {
        $conn->beginTransaction();

        // ১. আগে চেক করা এই সেলটি এই গোডাউনের কি না
        $check = $conn->prepare("SELECT id FROM sales WHERE id = ? AND godown_id = ?");
        $check->execute([$sale_id, $godown_id]);
        if ($check->rowCount() == 0) { throw new Exception("অনুমতি নেই!"); }

        // ২. স্টক ফেরত দেওয়া (Sale Items থেকে স্টক বের করে আপডেট করা)
        $items_stmt = $conn->prepare("SELECT product_id, variant_id, qty FROM sale_items WHERE sale_id = ?");
        $items_stmt->execute([$sale_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            if ($item['variant_id']) {
                // ভেরিয়েন্ট প্রোডাক্ট স্টক আপডেট
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                     ->execute([$item['qty'], $item['variant_id']]);
            } else {
                // মেইন প্রোডাক্ট স্টক আপডেট
                $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")
                     ->execute([$item['qty'], $item['product_id']]);
            }
        }

        // ৩. ইনভয়েস ডিলিট করা (ডাটাবেসে Cascade থাকলে sale_items অটো ডিলিট হবে)
        $del = $conn->prepare("DELETE FROM sales WHERE id = ? AND godown_id = ?");
        $del->execute([$sale_id, $godown_id]);

        $conn->commit();
        header("Location: sales.php?success=deleted");
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}