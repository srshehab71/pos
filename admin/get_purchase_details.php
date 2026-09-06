<?php
session_start();
include '../config/db.php';

if (!isset($_POST['purchase_id'])) {
    exit("সঠিক আইডি পাওয়া যায়নি।");
}

$purchase_id = $_POST['purchase_id'];
$godown_id = $_SESSION['godown_id'];

// ১. পারচেস এবং সাপ্লায়ার তথ্য আনা
$p_stmt = $conn->prepare("SELECT p.*, s.supplier_name, s.company_name, s.phone 
                          FROM purchases p 
                          JOIN suppliers s ON p.supplier_id = s.id 
                          WHERE p.id = ? AND p.godown_id = ?");
$p_stmt->execute([$purchase_id, $godown_id]);
$purchase = $p_stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    exit("কোনো তথ্য পাওয়া যায়নি।");
}

// ২. আইটেম লিস্ট আনা (ভেরিয়েন্ট এবং সিঙ্গেল প্রোডাক্ট হ্যান্ডেল করে)
$items_stmt = $conn->prepare("
    SELECT 
        pi.*, 
        IF(pi.item_type = 'variant', p2.product_name, p1.product_name) as product_display_name,
        pv.variant_name
    FROM purchase_items pi
    LEFT JOIN products p1 ON pi.product_id = p1.id AND pi.item_type = 'single'
    LEFT JOIN product_variants pv ON pi.product_id = pv.id AND pi.item_type = 'variant'
    LEFT JOIN products p2 ON pv.product_id = p2.id
    WHERE pi.purchase_id = ?
");
$items_stmt->execute([$purchase_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- ভাউচার ডিটেইলস ডিজাইন -->
<div class="row mb-4">
    <div class="col-6">
        <h6 class="text-muted mb-1 small uppercase">সাপ্লায়ার:</h6>
        <p class="fw-bold mb-0 text-dark"><?= htmlspecialchars($purchase['supplier_name']) ?></p>
        <p class="small text-muted mb-0"><?= htmlspecialchars($purchase['company_name']) ?></p>
        <p class="small text-muted"><?= htmlspecialchars($purchase['phone']) ?></p>
    </div>
    <div class="col-6 text-end">
        <h6 class="text-muted mb-1 small uppercase">চালান নং:</h6>
        <p class="fw-bold text-primary mb-0">#<?= htmlspecialchars($purchase['chalan_no']) ?></p>
        <p class="small text-muted">তারিখ: <?= date('d M, Y', strtotime($purchase['created_at'])) ?></p>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm align-middle" style="font-size: 13px;">
        <thead class="bg-light">
            <tr class="text-secondary">
                <th>পণ্যের নাম</th>
                <th class="text-center">পরিমাণ</th>
                <th class="text-end">ক্রয়মূল্য</th>
                <th class="text-end">মোট</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <span class="fw-bold d-block text-dark"><?= htmlspecialchars($item['product_display_name']) ?></span>
                    <?php if($item['variant_name']): ?>
                        <small class="badge bg-info text-white" style="font-size: 10px;"><?= htmlspecialchars($item['variant_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center fw-bold"><?= $item['qty'] ?></td>
                <td class="text-end">৳ <?= number_format($item['purchase_price'], 2) ?></td>
                <td class="text-end fw-bold">৳ <?= number_format($item['qty'] * $item['purchase_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light fw-bold">
            <tr>
                <td colspan="3" class="text-end">সর্বমোট বিল:</td>
                <td class="text-end">৳ <?= number_format($purchase['total_amount'], 2) ?></td>
            </tr>
            <tr>
                <td colspan="3" class="text-end text-success">পরিশোধ:</td>
                <td class="text-end text-success">৳ <?= number_format($purchase['paid_amount'], 2) ?></td>
            </tr>
            <?php if($purchase['due_amount'] > 0): ?>
            <tr>
                <td colspan="3" class="text-end text-danger">বকেয়া (Due):</td>
                <td class="text-end text-danger">৳ <?= number_format($purchase['due_amount'], 2) ?></td>
            </tr>
            <?php endif; ?>
        </tfoot>
    </table>
</div>

<div class="mt-4 text-center d-print-none">
    <button class="btn btn-primary btn-sm rounded-pill px-4" onclick="window.print()">
        <i class="fas fa-print me-2"></i> প্রিন্ট করুন
    </button>
</div>