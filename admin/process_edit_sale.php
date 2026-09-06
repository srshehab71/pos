<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] == 'admin') {
    $sale_id = $_POST['sale_id'];
    $godown_id = $_SESSION['godown_id'];
    
    $customer_name = $_POST['customer_name'];
    $customer_address = $_POST['customer_address'];
    $payable_amount = $_POST['payable_amount'];
    $paid_amount = $_POST['paid_amount'];
    $due_amount = $_POST['due_amount'];

    try {
        $conn->beginTransaction();

        // ১. আগের আইটেমগুলোর স্টক ফেরত দেওয়া
        $old_items = $conn->prepare("SELECT product_id, variant_id, qty FROM sale_items WHERE sale_id = ?");
        $old_items->execute([$sale_id]);
        while($row = $old_items->fetch(PDO::FETCH_ASSOC)) {
            if ($row['variant_id']) {
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$row['qty'], $row['variant_id']]);
            } else {
                $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$row['qty'], $row['product_id']]);
            }
        }

        // ২. পুরোনো আইটেম ডিলিট করা
        $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$sale_id]);

        // ৩. ইনভয়েস আপডেট
        $upd = $conn->prepare("UPDATE sales SET customer_name=?, customer_address=?, total_amount=?, payable_amount=?, paid_amount=?, due_amount=? WHERE id=? AND godown_id=?");
        $upd->execute([$customer_name, $customer_address, $payable_amount, $payable_amount, $paid_amount, $due_amount, $sale_id, $godown_id]);

        // ৪. নতুন আইটেম যোগ ও স্টক মাইনাস
        if (isset($_POST['p_ids'])) {
            foreach ($_POST['p_ids'] as $key => $p_id_type) {
                $type = substr($p_id_type, 0, 1);
                $id = substr($p_id_type, 1);
                $qty = $_POST['p_qtys'][$key];
                $price = $_POST['p_prices'][$key];
                $discount = $_POST['p_discounts'][$key];
                $subtotal = ($price * $qty) - $discount;

                if ($type == 'p') {
                    $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?)")
                         ->execute([$sale_id, $id, $qty, $price, $subtotal, $discount]);
                    $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $id]);
                } else {
                    $v_stmt = $conn->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                    $v_stmt->execute([$id]);
                    $main_p_id = $v_stmt->fetchColumn();

                    $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, qty, unit_price, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                         ->execute([$sale_id, $main_p_id, $id, $qty, $price, $subtotal, $discount]);
                    $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $id]);
                }
            }
        }

        $conn->commit();
        echo "<script>alert('ইনভয়েস আপডেট হয়েছে!'); window.location.href='sales.php';</script>";
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}