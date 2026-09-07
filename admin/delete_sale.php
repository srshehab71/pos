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

        // ২. স্টক এবং ব্যাচ ফেরত দেওয়া
        $items_stmt = $conn->prepare("SELECT product_id, variant_id, purchase_item_id, qty FROM sale_items WHERE sale_id = ?");
        $items_stmt->execute([$sale_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            // ২.১ ব্যাচ স্টক ফেরত দেওয়া (Purchase Items টেবিলে)
            if ($item['purchase_item_id']) {
                $conn->prepare("UPDATE purchase_items SET remaining_qty = remaining_qty + ? WHERE id = ?")
                     ->execute([$item['qty'], $item['purchase_item_id']]);
            }

            // ২.২ মেইন স্টক ফেরত দেওয়া (Products/Variants টেবিলে)
            if ($item['variant_id']) {
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                     ->execute([$item['qty'], $item['variant_id']]);
            } else {
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