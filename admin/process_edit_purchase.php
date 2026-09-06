<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['purchase_id'])) {
    $purchase_id = $_POST['purchase_id'];
    $supplier_id = $_POST['supplier_id'];
    $chalan_no   = $_POST['chalan_no'];
    $total_bill  = $_POST['total_bill'];
    $paid_amount = $_POST['paid_amount'];
    $due_amount  = $_POST['due_amount'];
    $godown_id   = $_SESSION['godown_id'];

    // এ্যারে ডাটাগুলো ধরা
    $p_full_ids   = $_POST['p_full_ids'] ?? [];
    $p_buy_prices = $_POST['p_buy_prices'] ?? [];
    $p_sell_prices= $_POST['p_sell_prices'] ?? [];
    $p_qtys       = $_POST['p_qtys'] ?? [];
    $p_expiries   = $_POST['p_expiries'] ?? [];

    $conn->beginTransaction();

    try {
        // ১. পুরাতন আইটেমগুলো ফেচ করা (স্টক রিভার্ট করার জন্য)
        $old_items_stmt = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
        $old_items_stmt->execute([$purchase_id]);
        $old_items = $old_items_stmt->fetchAll();

        foreach ($old_items as $old) {
            if ($old['item_type'] == 'single') {
                $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")
                     ->execute([$old['qty'], $old['product_id']]);
            } else {
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")
                     ->execute([$old['qty'], $old['product_id']]);
            }
        }

        // ২. পুরাতন আইটেম ডিটেইলস ডিলিট করা
        $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$purchase_id]);

        // ৩. মেইন পারচেস টেবিল আপডেট করা
        $update_p = $conn->prepare("UPDATE purchases SET supplier_id = ?, chalan_no = ?, total_amount = ?, paid_amount = ?, due_amount = ? WHERE id = ? AND godown_id = ?");
        $update_p->execute([$supplier_id, $chalan_no, $total_bill, $paid_amount, $due_amount, $purchase_id, $godown_id]);

        // ৪. নতুন আইটেম ইনসার্ট করা এবং স্টক আপডেট করা
        for ($i = 0; $i < count($p_full_ids); $i++) {
            $parts = explode('|', $p_full_ids[$i]); // single|5 বা variant|16
            $type  = $parts[0];
            $pid   = $parts[1];
            $buy   = $p_buy_prices[$i];
            $sell  = $p_sell_prices[$i];
            $qty   = $p_qtys[$i];
            $exp   = !empty($p_expiries[$i]) ? $p_expiries[$i] : null;

            // আইটেম ইনসার্ট
            $ins_item = $conn->prepare("INSERT INTO purchase_items (purchase_id, product_id, item_type, qty, purchase_price, sell_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_item->execute([$purchase_id, $pid, $type, $qty, $buy, $sell, $exp]);

            // নতুন স্টক এবং দাম আপডেট
            if ($type == 'single') {
                $conn->prepare("UPDATE products SET stock_qty = stock_qty + ?, purchase_price = ?, sell_price = ?, expiry_date = ? WHERE id = ?")
                     ->execute([$qty, $buy, $sell, $exp, $pid]);
            } else {
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ?, purchase_price = ?, sell_price = ?, expiry_date = ? WHERE id = ?")
                     ->execute([$qty, $buy, $sell, $exp, $pid]);
            }
        }

        $conn->commit();
        header("Location: purchase_list.php?updated=1");
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("ভুল হয়েছে: " . $e->getMessage());
    }
} else {
    header("Location: purchase_list.php");
    exit();
}