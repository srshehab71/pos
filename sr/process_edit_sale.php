<?php
session_start();
include '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sale_id = $_POST['sale_id'];
    $gid = $_SESSION['godown_id'];
    $name = $_POST['customer_name'];
    $address = $_POST['customer_address'];
    $payable = $_POST['payable_amount'];
    $paid = $_POST['paid_amount'];
    $due = $_POST['due_amount'];

    try {
        $conn->beginTransaction();

        // ১. আগে পুরনো পণ্যগুলোর স্টক ফেরত দেওয়া (Restore old stock)
        // এখানে চেক করতে হবে পণ্যটি কি ভেরিয়েন্ট ছিল নাকি সাধারণ
        $old_items = $conn->prepare("SELECT product_id, variant_id, qty FROM sale_items WHERE sale_id = ?");
        $old_items->execute([$sale_id]);
        while($row = $old_items->fetch()) {
            if ($row['variant_id']) {
                // ভেরিয়েন্টের স্টক ফেরত দেওয়া
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$row['qty'], $row['variant_id']]);
            } else {
                // সাধারণ প্রোডাক্টের স্টক ফেরত দেওয়া
                $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")->execute([$row['qty'], $row['product_id']]);
            }
        }

        // ২. পুরনো আইটেমগুলো মুছে ফেলা
        $conn->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$sale_id]);

        // ৩. মেইন ইনভয়েস (sales) টেবিল আপডেট করা
        $up_sale = $conn->prepare("UPDATE sales SET customer_name = ?, customer_address = ?, total_amount = ?, payable_amount = ?, paid_amount = ?, due_amount = ? WHERE id = ?");
        $up_sale->execute([$name, $address, $payable, $payable, $paid, $due, $sale_id]);

        // ৪. নতুন আইটেমগুলো সেভ করা এবং স্টক থেকে কমানো
        $p_ids = $_POST['p_ids']; // এতে p148 বা v106 এমন ডাটা আসবে
        $p_qtys = $_POST['p_qtys'];
        $p_prices = $_POST['p_prices'];
        $p_discounts = $_POST['p_discounts'];

        for ($i = 0; $i < count($p_ids); $i++) {
            $raw_id = $p_ids[$i]; 
            $type = substr($raw_id, 0, 1); // 'p' অথবা 'v' বের করা
            $numeric_id = substr($raw_id, 1); // শুধু নম্বরটি নেওয়া
            
            $subtotal = ($p_prices[$i] * $p_qtys[$i]) - $p_discounts[$i];
            
            $product_id = null;
            $variant_id = null;

            if ($type == 'v') {
                // এটি ভেরিয়েন্ট প্রোডাক্ট
                $variant_id = $numeric_id;
                // মেইন প্রোডাক্ট আইডি খুঁজে বের করা (sale_items এ সেভ করার জন্য)
                $v_query = $conn->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                $v_query->execute([$variant_id]);
                $product_id = $v_query->fetchColumn();

                // স্টক কমানো (ভেরিয়েন্ট টেবিল থেকে)
                $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$p_qtys[$i], $variant_id]);
            } else {
                // এটি সাধারণ প্রোডাক্ট
                $product_id = $numeric_id;
                $variant_id = null;

                // স্টক কমানো (প্রোডাক্ট টেবিল থেকে)
                $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$p_qtys[$i], $product_id]);
            }

            // আইটেমটি sale_items টেবিলে সেভ করা
            $ins_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, qty, unit_price, discount, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_item->execute([$sale_id, $product_id, $variant_id, $p_qtys[$i], $p_prices[$i], $p_discounts[$i], $subtotal]);
        }

        $conn->commit();
        header("Location: sales_history.php?updated=1");

    } catch (Exception $e) {
        $conn->rollBack();
        die("এডিট করতে সমস্যা হয়েছে: " . $e->getMessage());
    }
}