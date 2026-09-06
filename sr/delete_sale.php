<?php
session_start();
include '../config/db.php';

// লগইন এবং রোল চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $sale_id = $_GET['id'];
    $uid = $_SESSION['user_id'];
    $gid = $_SESSION['godown_id'];

    try {
        // ১. ট্রানজেকশন শুরু (যাতে কোনো এরর হলে ডাটা অগোছালো না হয়)
        $conn->beginTransaction();

        // ২. সিকিউরিটি চেক: এই সেলসটি কি আসলেই এই এসআর এবং এই গোডাউনের?
        $check_stmt = $conn->prepare("SELECT id FROM sales WHERE id = ? AND user_id = ? AND godown_id = ?");
        $check_stmt->execute([$sale_id, $uid, $gid]);
        if (!$check_stmt->fetch()) {
            throw new Exception("আপনার এই সেলসটি ডিলেট করার অনুমতি নেই!");
        }

        // ৩. স্টক রিস্টোর (Stock Restore): 
        // এই বিক্রিতে কোন মাল কতটুকু ছিল তা বের করা
        $item_stmt = $conn->prepare("SELECT product_id, qty FROM sale_items WHERE sale_id = ?");
        $item_stmt->execute([$sale_id]);
        $items = $item_stmt->fetchAll();

        foreach ($items as $item) {
            $p_id = $item['product_id'];
            $qty = $item['qty'];

            // প্রোডাক্টের স্টক আবার বাড়িয়ে দেওয়া
            $update_stock = $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ? AND godown_id = ?");
            $update_stock->execute([$qty, $p_id, $gid]);
        }

        // ৪. sale_items টেবিল থেকে ডাটা মোছা (Foreign Key থাকলে অটো মুছে যাওয়ার কথা, তবুও ম্যানুয়ালি করা নিরাপদ)
        $del_items = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
        $del_items->execute([$sale_id]);

        // ৫. মেইন sales টেবিল থেকে ডাটা মোছা
        $del_sale = $conn->prepare("DELETE FROM sales WHERE id = ?");
        $del_sale->execute([$sale_id]);

        // ৬. সব কাজ সফল হলে ট্রানজেকশন কনফার্ম করা
        $conn->commit();

        header("Location: sales_history.php?deleted=1");
        exit();

    } catch (Exception $e) {
        // কোনো ভুল হলে সব কাজ বাতিল (Rollback) করা
        $conn->rollBack();
        echo "ডিলেট করতে সমস্যা হয়েছে: " . $e->getMessage();
    }
} else {
    header("Location: sales_history.php");
}