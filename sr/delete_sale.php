<?php
session_start();
include '../config/db.php';

// লগইন এবং রোল চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php");
    exit();
}

if (isset($_GET['id'])) {
    $sale_id = (int)$_GET['id']; // আইডি নিশ্চিত করতে (int) করা হলো
    $uid = $_SESSION['user_id'];
    $gid = $_SESSION['godown_id'];

    try {
        // ১. ট্রানজেকশন শুরু
        $conn->beginTransaction();

        // ২. সিকিউরিটি চেক: এই সেলসটি কি আসলেই এই এসআর এবং এই গোডাউনের?
        $check_stmt = $conn->prepare("SELECT id FROM sales WHERE id = ? AND user_id = ? AND godown_id = ?");
        $check_stmt->execute([$sale_id, $uid, $gid]);
        
        // fetch() এর বদলে rowCount() ব্যবহার করা হয়েছে (এডমিন কোডের মতো)
        if ($check_stmt->rowCount() == 0) {
            throw new Exception("আপনার এই সেলসটি ডিলেট করার অনুমতি নেই!");
        }

        // ৩. স্টক রিস্টোর (Stock Restore)
        $item_stmt = $conn->prepare("SELECT product_id, variant_id, purchase_item_id, qty FROM sale_items WHERE sale_id = ?");
        $item_stmt->execute([$sale_id]);
        
        // এখানে PDO::FETCH_ASSOC যোগ করা হয়েছে, এটিই মেইন সমাধান
        $items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $restore_qty = (float)$item['qty'];

            // ২.১ ব্যাচ স্টক ফেরত দেওয়া (যদি ব্যাচ আইডি থাকে)
            if (!empty($item['purchase_item_id'])) {
                $conn->prepare("UPDATE purchase_items SET remaining_qty = remaining_qty + ? WHERE id = ?")
                     ->execute([$restore_qty, $item['purchase_item_id']]);
            }

            // ২.২ মেইন স্টক ফেরত দেওয়া (ভেরিয়েন্ট চেক সহ)
            if (!empty($item['variant_id'])) {
                // ভেরিয়েন্ট প্রোডাক্টের স্টক বাড়ানো
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                     ->execute([$restore_qty, $item['variant_id']]);
            } else {
                // সাধারণ প্রোডাক্টের স্টক বাড়ানো
                $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")
                     ->execute([$restore_qty, $item['product_id']]);
            }
        }

        // ৪. sale_items টেবিল থেকে ডাটা মোছা
        $del_items = $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?");
        $del_items->execute([$sale_id]);

        // ৫. মেইন sales টেবিল থেকে ডাটা মোছা
        $del_sale = $conn->prepare("DELETE FROM sales WHERE id = ? AND user_id = ? AND godown_id = ?");
        $del_sale->execute([$sale_id, $uid, $gid]);

        // ৬. সব কাজ সফল হলে ট্রানজেকশন কনফার্ম করা
        $conn->commit();

        header("Location: sales_history.php?deleted=1");
        exit();

    } catch (Exception $e) {
        // কোনো ভুল হলে সব কাজ বাতিল (Rollback) করা
        $conn->rollBack();
        die("ডিলেট করতে সমস্যা হয়েছে: " . $e->getMessage());
    }
} else {
    header("Location: sales_history.php");
}