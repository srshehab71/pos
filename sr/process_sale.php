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

// ১. সেলস টেবিলে এন্ট্রি (ডিসকাউন্টসহ)
        $total_item_discount = isset($_POST['p_discounts']) ? array_sum($_POST['p_discounts']) : 0;

        $stmt = $conn->prepare("INSERT INTO sales (godown_id, user_id, customer_name, customer_phone, customer_address, total_amount, discount, payable_amount, paid_amount, due_amount, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$godown_id, $user_id, $customer_name, $customer_phone, $customer_address, $total_amount, $total_item_discount, $payable_amount, $paid_amount, $due_amount, $sale_date]);
        $sale_id = $conn->lastInsertId();

        
       // ২. সেল আইটেম এন্ট্রি ও স্টক আপডেট (ব্যাচ সিস্টেম বা FIFO লজিক)
        // ২. সেল আইটেম এন্ট্রি ও স্টক আপডেট (ব্রাউজারে ১ রো, ডাটাবেজে একাধিক চালানের এন্ট্রি)
        if (isset($_POST['p_ids'])) {
            foreach ($_POST['p_ids'] as $key => $p_id_type) {
                $type_code = substr($p_id_type, 0, 1);
                $id = substr($p_id_type, 1);
                $qty_needed = (float)$_POST['p_qtys'][$key];
                $input_price = (float)$_POST['p_prices'][$key];
                $input_discount = (float)$_POST['p_discounts'][$key];
                
                $item_type = ($type_code == 'p') ? 'single' : 'variant';
                $main_p_id = ($type_code == 'v') ? $conn->query("SELECT product_id FROM product_variants WHERE id = $id")->fetchColumn() : $id;

                // মেইন দাম চেক (ইউজার এডিট করেছে কি না তা বুঝার জন্য)
                $std_price = ($type_code == 'p') ? 
                             $conn->query("SELECT sell_price FROM products WHERE id = $id")->fetchColumn() : 
                             $conn->query("SELECT sell_price FROM product_variants WHERE id = $id")->fetchColumn();

                $remaining_to_deduct = $qty_needed;

                // ১. ভেরিয়েবল এবং কেনা দাম সেট করে রাখা (মুছবেন না, শুধু যোগ করুন)
                $variant_val = ($type_code == 'v') ? $id : null;
                $get_buy = $conn->prepare(($type_code == 'p') ? "SELECT purchase_price FROM products WHERE id = ?" : "SELECT purchase_price FROM product_variants WHERE id = ?");
                $get_buy->execute([$id]);
                $orig_buy_price = $get_buy->fetchColumn();

                // FIFO অনুযায়ী ব্যাচ খোঁজা
                $batch_stmt = $conn->prepare("SELECT id, remaining_qty, purchase_price, sell_price FROM purchase_items WHERE product_id = ? AND item_type = ? AND remaining_qty > 0 ORDER BY id ASC");
                $batch_stmt->execute([$id, $item_type]);

                while ($remaining_to_deduct > 0 && $batch = $batch_stmt->fetch()) {
                    $take = min($remaining_to_deduct, $batch['remaining_qty']);
                    $batch_id = $batch['id'];
                    $buy_price = $batch['purchase_price']; 
                    $batch_sell_price = $batch['sell_price'];

                    // লজিক: যদি ইউজার দাম এডিট না করে, তবে চালানের একচুয়াল দাম বসবে
                    // আর যদি এডিট করে, তবে সেই এডিটেড দামটিই বসবে
                    if (abs($input_price - $std_price) < 0.1) {
                        $actual_unit_price = $batch_sell_price;
                    } else {
                        $actual_unit_price = $input_price;
                    }

                    $p_discount = ($input_discount / $qty_needed) * $take;
                    $subtotal = ($actual_unit_price * $take) - $p_discount;
                    $calculated_total_amount += $subtotal;

                    // ডাটাবেজে ইনসার্ট (একই প্রোডাক্টের জন্য আলাদা ব্যাচ এন্ট্রি)
                    $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, purchase_item_id, qty, unit_price, buy_price_at_sale, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $variant_val = ($type_code == 'v') ? $id : null;
                    $stmt_item->execute([$sale_id, $main_p_id, $variant_val, $batch_id, $take, $actual_unit_price, $buy_price, $subtotal, $p_discount]);

                    // ব্যাচ স্টক মাইনাস
                    $conn->prepare("UPDATE purchase_items SET remaining_qty = remaining_qty - ? WHERE id = ?")->execute([$take, $batch_id]);
                    $remaining_to_deduct -= $take;
                }
                // ২. যদি কোনো কারণে চালানে (Batch) তথ্য না থাকে, তবুও যেন এন্ট্রি হয়
                if ($remaining_to_deduct > 0) {
                    $p_discount = ($input_discount / $qty_needed) * $remaining_to_deduct;
                    $subtotal = ($input_price * $remaining_to_deduct) - $p_discount;

                    $stmt_fallback = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, variant_id, purchase_item_id, qty, unit_price, buy_price_at_sale, subtotal, discount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt_fallback->execute([$sale_id, $main_p_id, $variant_val, null, $remaining_to_deduct, $input_price, $orig_buy_price, $subtotal, $p_discount]);
                }

                // মেইন স্টক আপডেট
                $table = ($type_code == 'p') ? 'products' : 'product_variants';
                $conn->prepare("UPDATE $table SET stock_qty = stock_qty - ? WHERE id = ?")->execute([$qty_needed, $id]);
            }
        }

        $conn->commit();
        header("Location: ../sr/print_invoice.php?id=" . $sale_id); 
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}