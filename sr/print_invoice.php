<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

$sale_id = $_GET['id'];
$godown_id = $_SESSION['godown_id'];

// ১. তথ্য নিয়ে আসা
$stmt = $conn->prepare("SELECT sales.*, godowns.shop_name, godowns.owner_name, godowns.phone as shop_phone, godowns.address as shop_address, godowns.logo 
                        FROM sales 
                        JOIN godowns ON sales.godown_id = godowns.id 
                        WHERE sales.id = ? AND sales.godown_id = ?");
$stmt->execute([$sale_id, $godown_id]);
$sale = $stmt->fetch();

if (!$sale) { die("ইনভয়েস পাওয়া যায়নি!"); }

// ২. প্রোডাক্ট লিস্ট (ভেরিয়েন্টসহ)
$item_stmt = $conn->prepare("SELECT sale_items.*, products.product_name, product_variants.variant_name 
                             FROM sale_items 
                             JOIN products ON sale_items.product_id = products.id 
                             LEFT JOIN product_variants ON sale_items.variant_id = product_variants.id 
                             WHERE sale_items.sale_id = ?");
$item_stmt->execute([$sale_id]);
$items = $item_stmt->fetchAll();

// --- গ্রুপিং লজিক শুরু ---
$grouped_items = [];
$total_item_discount = 0;

foreach ($items as $item) {
    $key = $item['product_id'] . '_' . ($item['variant_id'] ?? '0');
    $total_item_discount += (float)$item['discount'];

    if (!isset($grouped_items[$key])) {
        $grouped_items[$key] = [
            'name' => $item['product_name'] . (!empty($item['variant_name']) ? " - " . $item['variant_name'] : ""),
            'total_qty' => 0,
            'total_row_amount' => 0,
            'single_price' => $item['unit_price'],
            'discount' => 0, // <--- এটি নিশ্চিত করুন
            'price_segments' => []
        ];
    }
// ডাটাবেজে discount ০ থাকলেও subtotal দেখে ডিসকাউন্ট বের করার ব্যাকআপ
    $expected_price = (float)$item['qty'] * (float)$item['unit_price'];
    if ($grouped_items[$key]['discount'] == 0 && isset($item['subtotal']) && $expected_price > (float)$item['subtotal']) {
        $grouped_items[$key]['discount'] += ($expected_price - (float)$item['subtotal']);
    }
    
    // বাকি কোড (qty, price_segments) আগের মতোই থাকবে...

    $qty = (float)$item['qty'];
    $price = (float)$item['unit_price'];
    
    // সঠিক মোট হিসাব: প্রতিটি ব্যাচের (পরিমাণ x মূল্য) যোগ হবে
    $grouped_items[$key]['total_qty'] += $qty;
    $grouped_items[$key]['total_row_amount'] += ($qty * $price);
    
    // উপরে নিচে দেখানোর জন্য ফরম্যাট (যেমন: 10.45 x 10)
    $grouped_items[$key]['price_segments'][] = number_format($price, ) . "৳  x " . (float)$qty . "টি ";
}
// --- গ্রুপিং লজিক শেষ ---

 
// মোট ডিসকাউন্ট হিসাব করার জন্য ভেরিয়েবল
$total_item_discount = 0;

// লজিক: আজকের পন্যের বিল এবং আগের বাকী সমন্বয় হিসাব করা
// ১. ডাটাবেজ থেকে সরাসরি তথ্য নেওয়া
$gross_total = (float)$sale['total_amount'];      // ছাড় ছাড়া আসল দাম
$total_discount = (float)$sale['discount'];     // মোট ছাড় (৳)
$today_net_bill = (float)$sale['payable_amount']; // ছাড় দেওয়ার পর নিট দাম

$paid_amount = (float)$sale['paid_amount']; 
$due_adjustment = 0;

// যদি পরিশোধ নিট বিলের চেয়ে বেশি হয়, তবে সেটি আগের বাকী সমন্বয়
if ($paid_amount > $today_net_bill) {
    $due_adjustment = $paid_amount - $today_net_bill;
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>প্রিন্ট ইনভয়েস</title>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        html {
    -webkit-text-size-adjust: 100%; /* ফন্ট বড় হওয়া বন্ধ করবে */
}

body {
    -webkit-text-size-adjust: none;
}

/* ফুটার টেক্সট ফিক্স করার জন্য এটি যোগ করুন */
.footer p {
    font-size: 12px !important;
    line-height: 1.4;
    margin: 5px 0;
}

        /* থার্মাল প্রিন্টার সেটিংস (80mm) */
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body { 
            font-family: 'Hind Siliguri', sans-serif; 
            margin: 0; 
            padding: 0; 
            background: #e0e0e0; 
        }

        .pos-receipt {
            width: 80mm;
            background: #fff;
            margin: 20px auto;
            padding: 10mm 5mm;
            color: #000;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }

        @media print {
            body { background: #fff; }
            .pos-receipt { 
                margin: 0; 
                box-shadow: none; 
                width: 80mm; 
                padding: 5mm 2mm;
            }
            .no-print { display: none !important; }
            @page { margin: 0; size: auto; }
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: 700; }
        
        .logo { width: 60px; height: auto; margin-bottom: 5px; }
        .shop-name { font-size: 22px; font-weight: 700; margin: 0; line-height: 1.2; }
        .shop-info { font-size: 13px; margin-bottom: 10px; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        
        .bill-info { font-size: 13px; margin-bottom: 10px; line-height: 1.4; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }

        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .items-table thead th { 
            font-size: 13px; 
            border-bottom: 1px solid #000; 
            background: #f2f2f2; 
            padding: 5px 2px;
        }
        .items-table tbody td { font-size: 13px; padding: 7px 2px; vertical-align: top; border-bottom: 0.5px solid #eee; }

        .summary { margin-top: 5px; font-size: 14px; }
        .summary-row { display: flex; justify-content: space-between; padding: 2px 0; }
        .grand-total { font-size: 16px; border-top: 1px solid #000; margin-top: 5px; padding-top: 5px; }

        .footer { margin-top: 20px; font-size: 12px; border-top: 1px dashed #000; padding-top: 10px; }
    </style>
</head>
<body>

<div class="text-center no-print" style="margin-top: 20px;">
    <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px;">প্রিন্ট করুন (Print)</button>
    <button onclick="history.back()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; border: 1px solid #000; border-radius: 5px; margin-left: 10px;">ফিরে যান</button>
</div>

<div class="pos-receipt">
    <div class="text-center">
        <?php 
            $logo_path = "../assets/uploads/logos/" . ($sale['logo'] ?: 'default_logo.png');
            if(!empty($sale['logo']) && file_exists($logo_path)): ?>
            <img src="<?= $logo_path ?>" class="logo">
        <?php endif; ?>
        <h1 class="shop-name"><?php echo $sale['shop_name']; ?></h1>
        <strong><div class="shop-info">
            <?php echo $sale['shop_address']; ?><br>
            মোবাইল: <?php echo $sale['shop_phone']; ?>

    </div>
        </strong>
        </div>
    

    <table style="width: 100%; border-collapse: collapse; font-size: 13px; line-height: 1.3; table-layout: fixed; margin: 5px 0;">
    <tr>
        <!-- বাম পাশের অংশ: ক্রেতা ও ফোন -->
        <td style="width: 48%; vertical-align: top; text-align: left; word-wrap: break-word; padding-right: 2px;">
            <strong>ক্রেতা: <?php echo $sale['customer_name']; ?><br></strong>
            <strong>ফোন: <?php echo $sale['customer_phone']; ?></strong>
        </td>
        
        <!-- ডান পাশের অংশ: ঠিকানা -->
        <td style="width: 52%; vertical-align: top; text-align: right; word-wrap: break-word; padding-left: 2px;">
            <?php if(!empty($sale['customer_address'])): ?>
                <strong>ঠিকানা: <?php echo $sale['customer_address']; ?></strong>
            <?php endif; ?>
        </td>
    </tr>
</table>

<div class="divider"></div>


<table style="width: 100%; border-collapse: collapse; font-size: 13px; line-height: 0.2; table-layout: fixed; margin: 8px 0 5px 0;">
    <tr>
        <!-- বাম পাশের অংশ: ক্রেতা ও ফোন -->
        <td style="width: 48%; vertical-align: top; text-align: left; word-wrap: break-word; padding-right: 2px;">
             <strong>তারিখ: <?php echo date('d/m/y', strtotime($sale['created_at'])); ?> </strong>
        </td>
        
        <!-- ডান পাশের অংশ: ঠিকানা -->
        <td style="width: 52%; vertical-align: top; text-align: right; word-wrap: break-word; padding-left: 2px;">
                <strong>সময়: <?php echo date('h:i A', strtotime($sale['created_at'])); ?></strong>
        </td>
    </tr>
</table>

    <table class="items-table">
        <thead>
            <tr>
                <th align="left">আইটেম</th>
                <th align="left">মূল্য</th>
                <th align="center">পরিমাণ</th>
                <th align="right">মোট</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grouped_items as $item): ?>
<tr>
        <td align="left">
    <strong><?php echo $item['name']; ?></strong>
    
    <?php 
    // যদি প্রতিটি আইটেমে আলাদা ডিসকাউন্ট থাকে
    if(isset($item['discount']) && $item['discount'] > 0): ?>
        <br><span style="color:black; font-size: 11px; font-weight: bold;">(ছাড়: -৳<?php echo number_format($item['discount'], 2); ?>)</span>
    <?php 
    // যদি আইটেমে ডিসকাউন্ট না থাকে কিন্তু পুরো বিলে থাকে এবং লিস্টে মাত্র ১টি আইটেম থাকে
    elseif (count($grouped_items) == 1 && $total_discount > 0): ?>
        <br><span style="color:black; font-size: 11px; font-weight: bold;">(ছাড়: -৳<?php echo number_format($total_discount, 2); ?>)</span>
    <?php endif; ?>
</td>
    <td align="center" style="font-size: 11px; line-height: 1.2; vertical-align: middle;">
    <strong>
        <?php 
        if (count($item['price_segments']) > 1) {
            // ব্যাচ আলাদা হলে উপরে নিচে দেখাবে
            echo implode("<br>", $item['price_segments']); 
        } else {
            // একটি ব্যাচ হলে শুধু সাধারণ দাম
            echo number_format($item['single_price'], 2);
        }
        ?>
    </strong>
</td>

<td align="center" style="vertical-align: middle;">
    <strong><?php echo (float)$item['total_qty']; ?></strong>
</td>

<td align="right" style="vertical-align: middle;">
    <strong><?php echo number_format($item['total_row_amount'], 2); ?></strong>
</td>
</tr>
<?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
    <!-- যদি ডিসকাউন্ট থাকে তবেই এই অংশটি দেখাবে -->
    <?php if ($total_discount > 0): ?>
        <div class="summary-row">
            <strong><span>মোট বিল:</span></strong>
            <strong><span><?php echo number_format($gross_total, 2); ?></span></strong>
        </div>

        <div class="summary-row">
            <strong><span>মোট ছাড় (Discount):</span></strong>
            <strong><span style="color:black;">- <?php echo number_format($total_discount, 2); ?></span></strong>
        </div>
        <div class="divider"></div>
    <?php endif; ?>

    <!-- নিট বিল -->
    <div class="summary-row fw-bold">
        <strong><span> সর্বমোট বিল:</span></strong>
        <strong><span><?php echo number_format($today_net_bill, 2); ?></span></strong>
    </div>

    <!-- বাকী সমন্বয় -->
    <?php if ($due_adjustment > 0): ?>
    <div class="summary-row fw-bold" style="color: #000000;">
        <span>আগের বাকী সমন্বয় (+):</span>
        <span><?php echo number_format($due_adjustment, 2); ?></span>
    </div>
    <?php endif; ?>

    <!-- পরিশোধ -->
    <div class="summary-row fw-bold grand-total">
        <span>নগদ পরিশোধ:</span>
        <span><?php echo number_format($paid_amount, 2); ?></span>
    </div>
    
    <!-- বকেয়া -->
    <?php if ($sale['due_amount'] > 0): ?>
    <div class="summary-row fw-bold">
        <span style="color:black;">বকেয়া (Due):</span>
        <span style="color:black;"><?php echo number_format($sale['due_amount'], 2); ?></span>
    </div>
    <?php endif; ?>
</div>

    <div class="footer text-center">
        <p class="fw-bold">বিক্রিত মালামাল ফেরত নেওয়া হয় না।</p>
        <strong><p style="font-size: 11px; color: #000000; margin-top: 10px;">Software by: StockPro System</p></strong>
    </div>
</div>

</body>
</html>