<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $godown_id = $_SESSION['godown_id'];
    $user_id = $_SESSION['user_id'];
    
    $customer_name = $_POST['customer_name'];
    $customer_phone = trim($_POST['customer_phone']); // স্পেস সরিয়ে নেওয়া হলো
    $customer_address = $_POST['customer_address'];
    $total_amount = $_POST['total_amount'];
    $payable_amount = $_POST['payable_amount'];
    $paid_amount = $_POST['paid_amount'];
    $due_amount = $_POST['due_amount'];
    // ফর্ম থেকে আসা ডেট MySQL ফরম্যাটে (Y-m-d H:i:s) রূপান্তর
if (!empty($_POST['sale_date'])) {
    $sale_date = date('Y-m-d H:i:s', strtotime($_POST['sale_date']));
} else {
    $sale_date = date('Y-m-d H:i:s'); // যদি ফাঁকা থাকে তবে বর্তমান সময়
}
    // --- নতুন চেক লজিক শুরু (আপনার অনুরোধ অনুযায়ী) ---
    // ডাটাবেজে এই নম্বরের কোনো কাস্টমার আছে কি না দেখা
    $stmt_val = $conn->prepare("SELECT name FROM customers WHERE phone = ? AND godown_id = ?");
    $stmt_val->execute([$customer_phone, $godown_id]);
    $check_cust = $stmt_val->fetch();

    if ($check_cust) {
        // যদি নম্বর থাকে কিন্তু নাম আলাদা হয়, তবে এরর দেখাবে
        if (trim($check_cust['name']) !== trim($customer_name)) {
            echo "<script>
                alert('ভুল: এই মোবাইল নম্বরটি (".$customer_phone.") ইতিমধ্যে কাস্টমার \"".$check_cust['name']."\" এর নামে সেভ করা আছে। দয়া করে নম্বরটি পরিবর্তন করুন অথবা সঠিক কাস্টমার সিলেক্ট করুন।');
                window.history.back(); 
            </script>";
            exit(); 
        }
    }
    // --- নতুন চেক লজিক শেষ ---

    try {
        $conn->beginTransaction();

        if (empty($customer_phone)) {
            die("Error: মোবাইল নম্বর ছাড়া কাস্টমার সেভ করা যাবে না। নম্বরটি চেক করুন।");
        }

        // ২. চেক করুন এই নম্বরে কাস্টমার আগে থেকে আছে কি না
        $stmt_check = $conn->prepare("SELECT id, name FROM customers WHERE phone = ? AND godown_id = ?");
        $stmt_check->execute([$customer_phone, $godown_id]);
        $existing_customer = $stmt_check->fetch();

        if (!$existing_customer) {
            // ৩. কাস্টমার না থাকলে নতুন ইনসার্ট করুন
            $stmt_cust = $conn->prepare("INSERT INTO customers (godown_id, name, phone, address, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt_cust->execute([$godown_id, $customer_name, $customer_phone, $customer_address]);
        } else {
            // ৪. পুরাতন কাস্টমার হলে শুধু ঠিকানা আপডেট
            $stmt_upd = $conn->prepare("UPDATE customers SET address = ? WHERE phone = ? AND godown_id = ?");
            $stmt_upd->execute([$customer_address, $customer_phone, $godown_id]);
            
            // ৫. ইনভয়েসে যেন পুরাতন কাস্টমারের অরিজিনাল নামটাই থাকে
            $customer_name = $existing_customer['name'];
        }

        // ১. সেলস টেবিলে এন্ট্রি
        $total_item_discount = isset($_POST['p_discounts']) ? array_sum($_POST['p_discounts']) : 0;
        $gross_total = $payable_amount + $total_item_discount; // ডিসকাউন্ট ছাড়া মোট দাম

        $stmt = $conn->prepare("INSERT INTO sales (godown_id, user_id, customer_name, customer_phone, customer_address, total_amount, discount, payable_amount, paid_amount, due_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$godown_id, $user_id, $customer_name, $customer_phone, $customer_address, $gross_total, $total_item_discount, $payable_amount, $paid_amount, $due_amount, $sale_date]);
        $sale_id = $conn->lastInsertId();


        // ২. সেল আইটেম এন্ট্রি ও স্টক আপডেট (ব্যাচ সিস্টেম বা FIFO লজিক)
        if (isset($_POST['p_ids'])) {
            foreach ($_POST['p_ids'] as $key => $p_id_type) {
                $type_code = substr($p_id_type, 0, 1); // 'p' or 'v'
                $id = substr($p_id_type, 1);
                $qty_needed = $_POST['p_qtys'][$key];
                $price = $_POST['p_prices'][$key];
                $discount_total = $_POST['p_discounts'][$key];
                
                $item_type = ($type_code == 'p') ? 'single' : 'variant';
                $main_p_id = $id;

                if ($type_code == 'v') {
                    $v_stmt = $conn->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                    $v_stmt->execute([$id]);
                    $main_p_id = $v_stmt->fetchColumn();
                }

                // --- ব্যাচ থেকে স্টক কমানোর লজিক শুরু ---
                $remaining_to_deduct = $qty_needed;

                // ১. পুরনো ব্যাচগুলো আগে খোঁজা (FIFO) - এখানে sell_price যোগ করা হয়েছে
                $batch_stmt = $conn->prepare("SELECT id, remaining_qty, purchase_price, sell_price FROM purchase_items WHERE product_id = ? AND item_type = ? AND remaining_qty > 0 ORDER BY id ASC");
                $batch_stmt->execute([$id, $item_type]);

                while ($remaining_to_deduct > 0 && $batch = $batch_stmt->fetch()) {
                    $take = min($remaining_to_deduct, $batch['remaining_qty']);
                    $batch_id = $batch['id'];
                    $buy_price = $batch['purchase_price']; // ওই চালানের কেনা দাম
                    $batch_sell_price = $batch['sell_price']; // ওই চালানের বিক্রয় দাম

                    // যদি SR বিক্রির সময় দাম পরিবর্তন না করে, তবে চালানের দামটিই আসল দাম হবে
                    $current_unit_price = ($price > 0) ? $price : $batch_sell_price;

                    // এই ব্যাচের জন্য ডিসকাউন্ট ও সাবটোটাল হিসাব
                    $proportional_discount = ($discount_total / $qty_needed) * $take;
                    $subtotal = ($current_unit_price * $take) - $proportional_discount;

                    // sale_items এ এন্ট্রি (সবচেয়ে গুরুত্বপূর্ণ: এখানে ব্যাচের কেনা দাম সেভ হচ্ছে)
                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, purchase_item_id, qty, unit_price, buy_price_at_sale, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $variant_val = ($type_code == 'v') ? $id : null;
                    $stmt->execute([$sale_id, $main_p_id, $variant_val, $batch_id, $take, $current_unit_price, $buy_price, $subtotal, $proportional_discount]);

                    // ২. purchase_items টেবিলের ওই ব্যাচ থেকে স্টক কমানো
                    $conn->prepare("UPDATE purchase_items SET remaining_qty = remaining_qty - ? WHERE id = ?")->execute([$take, $batch_id]);

                    $remaining_to_deduct -= $take;
                }

// যদি কোনো কারণে ব্যাচে মাল না থাকে (পুরানো মাল) তবুও মেইন স্টক থেকে কাটবে
                if ($remaining_to_deduct > 0) {
                    $item_discount_calc = ($qty_needed > 0) ? ($discount_total / $qty_needed * $remaining_to_deduct) : 0;
                    $subtotal = ($price * $remaining_to_deduct) - $item_discount_calc;
                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, qty, unit_price, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $variant_val = ($type_code == 'v') ? $id : null;
                    $stmt->execute([$sale_id, $main_p_id, $variant_val, $remaining_to_deduct, $price, $subtotal, $item_discount_calc]);
                }
                
                // ৩. মূল প্রোডাক্ট বা ভ্যারিয়েন্ট টেবিলের স্টক আপডেট (সামারি স্টক)
                if ($type_code == 'p') {
                    $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty_needed, $id]);
                } else {
                    $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty_needed, $id]);
                }
                // --- ব্যাচ লজিক শেষ ---
            }
        }

        $conn->commit();
        header("Location: ../sr/print_invoice.php?id=" . $sale_id); 
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}