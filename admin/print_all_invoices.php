<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

$godown_id = $_SESSION['godown_id'];

// ১. ফিল্টার লজিক (sales.php থেকে প্রাপ্ত)
$where_clauses = ["sales.godown_id = ?"];
$params = [$godown_id];

$search = trim($_GET['search'] ?? '');
$sr_id = $_GET['sr_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if ($search !== '') {
    $where_clauses[] = "(sales.customer_name LIKE ? OR sales.customer_phone LIKE ? OR sales.id = ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term; $params[] = $search_term; $params[] = $search;
}
if ($sr_id !== '') { $where_clauses[] = "sales.user_id = ?"; $params[] = $sr_id; }
if ($start_date !== '' && $end_date !== '') {
    $where_clauses[] = "DATE(sales.created_at) BETWEEN ? AND ?";
    $params[] = $start_date; $params[] = $end_date;
}

$where_sql = implode(" AND ", $where_clauses);

// ২. সকল সেলস ডাটা এবং গোডাউন তথ্য একসাথে আনা
$query = "SELECT sales.*, godowns.shop_name, godowns.owner_name, godowns.phone as shop_phone, godowns.address as shop_address, godowns.logo 
          FROM sales 
          JOIN godowns ON sales.godown_id = godowns.id 
          WHERE $where_sql ORDER BY sales.id DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$all_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$all_sales) {
    die("প্রিন্ট করার জন্য কোনো ইনভয়েস পাওয়া যায়নি!");
}
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>সকল পেজ প্রিন্ট</title>
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

<div class="text-center no-print" style="margin-top: 20px; background: #333; padding: 15px; position: sticky; top: 0;">
    <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #27ae60; color: #fff; border: none; border-radius: 5px; font-weight:bold;">একসাথে সব প্রিন্ট করুন (<?= count($all_sales) ?>টি)</button>
    <button onclick="history.back()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #fff; border: 1px solid #000; border-radius: 5px; margin-left: 10px;">ফিরে যান</button>
</div>

<!-- এখন লুপ শুরু করুন -->
<?php foreach ($all_sales as $sale): 
    $sale_id = $sale['id'];
?>

<?php
    // ১. এই ইনভয়েসের আইটেমগুলো ডাটাবেস থেকে আনা
    $item_stmt = $conn->prepare("SELECT sale_items.*, products.product_name, product_variants.variant_name 
                                 FROM sale_items 
                                 JOIN products ON sale_items.product_id = products.id 
                                 LEFT JOIN product_variants ON sale_items.variant_id = product_variants.id 
                                 WHERE sale_items.sale_id = ?");
    $item_stmt->execute([$sale_id]);
    $db_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

    // ২. আইটেমগুলোকে গ্রুপ করা এবং হিসাব করা
    $grouped_items = [];
    $total_item_discount = 0;
    foreach ($db_items as $item) {
        $key = $item['product_id'] . '_' . ($item['variant_id'] ?? 0);
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
    // নিচে এই লাইনটি অবশ্যই যোগ করবেন (প্রতি আইটেমের ডিসকাউন্ট যোগ হবে)
    $grouped_items[$key]['discount'] += (float)$item['discount'];
    
    // বাকি কোড (qty, price_segments) আগের মতোই থাকবে...
        $grouped_items[$key]['total_qty'] += $item['qty'];
        $grouped_items[$key]['total_row_amount'] += ($item['unit_price'] * $item['qty']);
        $grouped_items[$key]['price_segments'][] = number_format($item['unit_price'], ) . "৳  x " . (float)$item['qty'] . "টি ";
        $total_item_discount += $item['discount'];
    }


    //  ডাটাবেজ থেকে সরাসরি তথ্য নেওয়া
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
<?php endforeach; ?> <!-- এই লুপ শেষ করার লাইনটি খুব জরুরি -->
</body>
</html>