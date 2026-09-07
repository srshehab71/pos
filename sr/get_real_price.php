<?php
session_start();
include '../config/db.php';

if (isset($_GET['p_id_type']) && isset($_GET['qty'])) {
    $p_id_type = $_GET['p_id_type'];
    $qty_needed = (float)$_GET['qty'];
    // কার্টে অলরেডি কতটুকু আছে তা প্যারামিটার থেকে নিচ্ছি
    $cart_offset = isset($_GET['cart_qty']) ? (float)$_GET['cart_qty'] : 0; 

    $type_code = substr($p_id_type, 0, 1);
    $id = substr($p_id_type, 1);
    $item_type = ($type_code == 'p') ? 'single' : 'variant';

    $stmt = $conn->prepare("SELECT id, remaining_qty, sell_price FROM purchase_items WHERE product_id = ? AND item_type = ? AND remaining_qty > 0 ORDER BY id ASC");
    $stmt->execute([$id, $item_type]);
    $batches = $stmt->fetchAll();

    $total_bill = 0;
    $remaining_to_calculate = $qty_needed;
    $skip_count = $cart_offset; // কার্টে থাকা মালগুলো স্কিপ করার জন্য
    $segments = [];
    $prices_used = [];

    foreach ($batches as $batch) {
        $batch_qty = (float)$batch['remaining_qty'];
        $batch_price = (float)$batch['sell_price'];

        // ধাপ ১: কার্টে অলরেডি থাকা মালগুলো ব্যাচ থেকে বাদ দেওয়া (Skip Logic)
        if ($skip_count > 0) {
            $can_skip = min($skip_count, $batch_qty);
            $batch_qty -= $can_skip;
            $skip_count -= $can_skip;
        }

        // ধাপ ২: স্কিপ করার পর যদি ব্যাচে মাল অবশিষ্ট থাকে তবে হিসাব করা
        if ($batch_qty > 0 && $remaining_to_calculate > 0) {
            $take = min($remaining_to_calculate, $batch_qty);
            $sub = $take * $batch_price;
            
            $total_bill += $sub;
            $segments[] = ['batch_id' => $batch['id'], 'qty' => $take, 'price' => $batch_price, 'subtotal' => $sub];
            $prices_used[] = $batch_price;
            $remaining_to_calculate -= $take;
        }
        
        if ($remaining_to_calculate <= 0) break;
    }

    // যদি ব্যাচ শেষ হয়ে যায় কিন্তু আরও মাল লাগে (মেইন প্রাইস থেকে নেওয়া)
    if ($remaining_to_calculate > 0) {
        $stmt_main = ($type_code == 'p') ? $conn->prepare("SELECT sell_price FROM products WHERE id = ?") : $conn->prepare("SELECT sell_price FROM product_variants WHERE id = ?");
        $stmt_main->execute([$id]);
        $main_p = (float)$stmt_main->fetchColumn();
        
        $sub = $remaining_to_calculate * $main_p;
        $total_bill += $sub;
        $segments[] = ['batch_id' => null, 'qty' => $remaining_to_calculate, 'price' => $main_p, 'subtotal' => $sub];
        $prices_used[] = $main_p;
    }

    echo json_encode([
        'total_sum' => $total_bill,
        'avg_price' => $qty_needed > 0 ? ($total_bill / $qty_needed) : 0,
        'segments' => $segments,
        'show_warning' => count(array_unique($prices_used)) > 1
    ]);
}