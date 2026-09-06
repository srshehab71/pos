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
    $sale_date = $_POST['sale_date']; // ফর্ম থেকে আসা ডেট ও টাইম

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
        // এখানে NOW() এর বদলে ? বসানো হয়েছে এবং execute এর ভেতরে $sale_date যোগ করা হয়েছে
        $stmt = $conn->prepare("INSERT INTO sales (godown_id, user_id, customer_name, customer_phone, customer_address, total_amount, discount, payable_amount, paid_amount, due_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
        $stmt->execute([$godown_id, $user_id, $customer_name, $customer_phone, $customer_address, $total_amount, $payable_amount, $paid_amount, $due_amount, $sale_date]);
        $sale_id = $conn->lastInsertId();

        // ২. সেল আইটেম এন্ট্রি ও স্টক আপডেট
        if (isset($_POST['p_ids'])) {
            foreach ($_POST['p_ids'] as $key => $p_id_type) {
                $type = substr($p_id_type, 0, 1);
                $id = substr($p_id_type, 1);
                $qty = $_POST['p_qtys'][$key];
                $price = $_POST['p_prices'][$key];
                $discount = $_POST['p_discounts'][$key];
                $subtotal = ($price * $qty) - $discount;

                if ($type == 'p') {
                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, qty, unit_price, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$sale_id, $id, $qty, $price, $subtotal, $discount]);
                    $conn->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $id]);
                } else {
                    $v_stmt = $conn->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                    $v_stmt->execute([$id]);
                    $main_p_id = $v_stmt->fetchColumn();

                    $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, qty, unit_price, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$sale_id, $main_p_id, $id, $qty, $price, $subtotal, $discount]);
                    $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty, $id]);
                }
            }
        }

        $conn->commit();
        header("Location: ../sr/print_invoice.php?id=" . $sale_id); 
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}