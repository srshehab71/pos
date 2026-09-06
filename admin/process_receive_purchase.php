<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $purchase_id = $_POST['purchase_id'];
    $prev_received = $_POST['prev_received']; 
    $now_receive = $_POST['now_receive'];     

    $conn->beginTransaction();
    try {
        // স্ট্যাটাস নির্ধারণের জন্য তিনটি ফ্ল্যাগ
        $any_item_received = false; // কোনো একটি মালামালও কি রিসিভ হয়েছে?
        $all_items_completed = true; // সব মালামাল কি পূর্ণাঙ্গ রিসিভ হয়েছে?

        foreach ($now_receive as $item_db_id => $new_qty) {
            $stmt = $conn->prepare("SELECT * FROM purchase_items WHERE id = ?");
            $stmt->execute([$item_db_id]);
            $item = $stmt->fetch();

            $db_received = (float)$item['received_qty'];
            $edited_prev = (float)$prev_received[$item_db_id];
            $current_now = (float)$new_qty;

            $stock_diff = ($edited_prev - $db_received) + $current_now;

            if ($stock_diff != 0) {
                if ($item['item_type'] == 'single') {
                    $conn->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?")
                         ->execute([$stock_diff, $item['product_id']]);
                } else {
                    $conn->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                         ->execute([$stock_diff, $item['product_id']]);
                }
            }

            $total_received = $edited_prev + $current_now;
            $conn->prepare("UPDATE purchase_items SET received_qty = ? WHERE id = ?")
                 ->execute([$total_received, $item_db_id]);

            // স্ট্যাটাস লজিক চেক:
            if ($total_received > 0) {
                $any_item_received = true; // অন্তত কিছু মাল রিসিভ হয়েছে
            }
            
            if ($total_received < $item['qty']) {
                $all_items_completed = false; // এখনো কিছু বাকি আছে
            }
        }

        // ফাইনাল স্ট্যাটাস নির্ধারণ:
        if (!$any_item_received) {
            // যদি সব আইটেমের রিসিভড পরিমাণ ০ হয়
            $final_status = 'Ordered';
        } elseif ($all_items_completed) {
            // যদি সব আইটেম পূর্ণাঙ্গ রিসিভ হয়
            $final_status = 'Received';
        } else {
            // যদি কিছু রিসিভ হয় আর কিছু বাকি থাকে
            $final_status = 'Partial';
        }

        $conn->prepare("UPDATE purchases SET status = ? WHERE id = ?")
             ->execute([$final_status, $purchase_id]);

        $conn->commit();
        header("Location: purchase_list.php?success=received");
    } catch (Exception $e) {
        $conn->rollBack();
        die("ভুল হয়েছে: " . $e->getMessage());
    }
}