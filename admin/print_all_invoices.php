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
    <title>Bulk_POS_Receipts_<?= date('d_m_Y') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
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
            page-break-after: always; /* প্রতিটি ইনভয়েসের পর নতুন কাগজ শুরু হবে */
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
        
        /* প্রিন্ট বাটন ফিক্সড এরিয়া */
        .print-nav {
            background: #333; padding: 15px; position: sticky; top: 0; z-index: 1000;
        }
    </style>
</head>
<body>

<div class="text-center no-print print-nav">
    <button onclick="window.print()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #27ae60; color: #fff; border: none; border-radius: 5px; font-weight:bold;">একসাথে সব প্রিন্ট করুন (<?= count($all_sales) ?>টি)</button>
    <button onclick="history.back()" style="padding: 10px 30px; font-size: 16px; cursor: pointer; background: #fff; border: 1px solid #000; border-radius: 5px; margin-left: 10px;">ফিরে যান</button>
</div>

<?php foreach ($all_sales as $sale): 
    $sale_id = $sale['id'];
    
    // প্রতিটি ইনভয়েসের জন্য আলাদা করে প্রোডাক্ট লিস্ট নিয়ে আসা
    $item_stmt = $conn->prepare("SELECT sale_items.*, products.product_name, product_variants.variant_name 
                                 FROM sale_items 
                                 JOIN products ON sale_items.product_id = products.id 
                                 LEFT JOIN product_variants ON sale_items.variant_id = product_variants.id 
                                 WHERE sale_items.sale_id = ?");
    $item_stmt->execute([$sale_id]);
    $items = $item_stmt->fetchAll();

    // ক্যালকুলেশন লজিক
    $total_item_discount = 0;
    $today_net_bill = (float)$sale['total_amount'];
    $paid_amount = (float)$sale['paid_amount'];
    $due_adjustment = ($paid_amount > $today_net_bill) ? ($paid_amount - $today_net_bill) : 0;
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
            <?php foreach ($items as $item): 
                $total_item_discount += $item['discount']; 
            ?>
            <tr>
                <td align="left">
                    <strong><?php 
                        echo $item['product_name']; 
                        if(!empty($item['variant_name'])) { echo " - " . $item['variant_name']; }
                        if($item['discount'] > 0) { echo "<br><small>(ছাড়: ৳".number_format($item['discount'], 2).")</small>"; }
                    ?></strong>
                </td>
                <td>
                    <strong>৳<?php echo number_format($item['unit_price'], 2); ?></strong>
                </td>
                <td align="center"><strong><?php echo $item['qty']; ?></strong></td>
                <td align="right"><strong><?php echo number_format($item['unit_price'] * $item['qty'], 2); ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary">
        <?php if ($total_item_discount > 0): ?>
            <div class="summary-row">
                <strong><span>মোট বিল:</span></strong>
                <strong><span><?php echo number_format($sale['total_amount'] + $total_item_discount, 2); ?></span></strong>
            </div>
            <div class="summary-row">
                <strong><span>মোট ছাড় (Discount):</span></strong>
                <strong><span>- <?php echo number_format($total_item_discount, 2); ?></span></strong>
            </div>
            <div class="divider"></div>
        <?php endif; ?>

        <div class="summary-row fw-bold">
            <strong><span> সর্বমোট বিল:</span></strong>
            <strong><span><?php echo number_format($today_net_bill, 2); ?></span></strong>
        </div>

        <?php if ($due_adjustment > 0): ?>
        <div class="summary-row fw-bold" style="color: #2e7d32;">
            <span>পূর্বের বাকী সমন্বয় (+):</span>
            <span><?php echo number_format($due_adjustment, 2); ?></span>
        </div>
        <?php endif; ?>

        <div class="summary-row fw-bold grand-total">
            <span>পরিশোধ:</span>
            <span><?php echo number_format($paid_amount, 2); ?></span>
        </div>
        
        <?php if ($sale['due_amount'] > 0): ?>
        <div class="summary-row fw-bold">
            <span>বকেয়া:</span>
            <span><?php echo number_format($sale['due_amount'], 2); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer text-center">
        <p class="fw-bold">বিক্রিত মালামাল ফেরত নেওয়া হয় না।</p>
        <strong><p style="font-size: 11px; color: #000000; margin-top: 10px;">Software by: StockPro System</p></strong>
    </div>
</div>
<?php endforeach; ?>

</body>
</html>