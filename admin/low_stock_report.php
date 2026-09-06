<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

$page_title = "লো স্টক রিপোর্ট";
include 'includes/header.php';

// ১. ভেরিয়েন্ট সহ সকল লো স্টক আইটেম লিস্ট করার কুয়েরি
$low_stock_query = $conn->prepare("
    (SELECT 
        p.id as product_id, 
        p.product_name, 
        'No Variant' as variant_name, 
        p.stock_qty, 
        p.alert_qty,
        'Main' as type
    FROM products p 
    WHERE p.godown_id = ? 
    AND p.stock_qty <= p.alert_qty 
    AND p.id NOT IN (SELECT product_id FROM product_variants))
    
    UNION ALL
    
    (SELECT 
        p.id as product_id, 
        p.product_name, 
        v.variant_name, 
        v.stock_qty, 
        v.alert_qty,
        'Variant' as type
    FROM product_variants v 
    JOIN products p ON v.product_id = p.id 
    WHERE p.godown_id = ? 
    AND v.stock_qty <= v.alert_qty)
    
    ORDER BY stock_qty ASC
");
$low_stock_query->execute([$gid, $gid]);
$low_stock_items = $low_stock_query->fetchAll();
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> লো স্টক সতর্কবার্তা তালিকা
                    </h5>
                    <!--<button onclick="window.print()" class="btn btn-sm btn-outline-secondary d-print-none">
                        <i class="fas fa-print me-1"></i> প্রিন্ট করুন
                    </button>-->
                </div>
                <div class="card-body">
                    <?php if (count($low_stock_items) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>ক্র.নং</th>
                                        <th>প্রোডাক্টের নাম</th>
                                        <th>ভেরিয়েন্ট</th>
                                        <th class="text-center">বর্তমান স্টক</th>
                                        <th class="text-center">অ্যালার্ট লেভেল</th>
                                        <th class="text-center">স্ট্যাটাস</th>
                                        <th class="text-center d-print-none">অ্যাকশন</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $sl = 1;
                                    foreach ($low_stock_items as $item): 
                                        $stock_class = ($item['stock_qty'] <= 0) ? 'bg-danger' : 'bg-warning text-dark';
                                    ?>
                                        <tr>
                                            <td><?= $sl++; ?></td>
                                            <td>
                                                <span class="fw-bold"><?= htmlspecialchars($item['product_name']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= htmlspecialchars($item['variant_name']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= $stock_class ?> px-3 py-2">
                                                    <?= $item['stock_qty']; ?> টি
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="text-muted small"><?= $item['alert_qty']; ?> টি এর নিচে</span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($item['stock_qty'] <= 0): ?>
                                                    <span class="text-danger fw-bold small">স্টক আউট</span>
                                                <?php else: ?>
                                                    <span class="text-warning fw-bold small">স্টক কমে গেছে</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center d-print-none">
                                                <a href="products.php?edit=<?= $item['product_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-plus-circle me-1"></i> স্টক আপডেট
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                            <h5>বর্তমানে কোন লো স্টক প্রোডাক্ট নেই।</h5>
                            <p class="text-muted">আপনার সব প্রোডাক্টের পর্যাপ্ত স্টক রয়েছে।</p>
                            <a href="products.php" class="btn btn-primary btn-sm">সব প্রোডাক্ট দেখুন</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- প্রিন্ট করার সময় কিছু তথ্য লুকানোর জন্য CSS -->
            <style>
                @media print {
                    .d-print-none { display: none !important; }
                    .card { border: none !important; box-shadow: none !important; }
                    .table-light { background-color: transparent !important; }
                }
            </style>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>