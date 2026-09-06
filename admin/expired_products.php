<?php
session_start();
include '../config/db.php';
$gid = $_SESSION['godown_id'];
$type = isset($_GET['type']) ? $_GET['type'] : 'expired';
$page_title = ($type == 'upcoming') ? "মেয়াদ উত্তীর্ণ হতে যাওয়া পণ্য" : "মেয়াদ উত্তীর্ণ পণ্য";
include 'includes/header.php';

// কুয়েরি লজিক (stock_qty > 0 কন্ডিশন যুক্ত করা হয়েছে)
if($type == 'upcoming'){
    $sql = " (SELECT product_name, expiry_date, stock_qty, 'Single' as type FROM products WHERE godown_id = ? AND stock_qty > 0 AND expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 15 DAY))
             UNION ALL
             (SELECT CONCAT(p.product_name, ' - ', v.variant_name), v.expiry_date, v.stock_qty, 'Variant' FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.godown_id = ? AND v.stock_qty > 0 AND v.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)) ";
} else {
    $sql = " (SELECT product_name, expiry_date, stock_qty, 'Single' as type FROM products WHERE godown_id = ? AND stock_qty > 0 AND expiry_date <= CURDATE() AND expiry_date IS NOT NULL)
             UNION ALL
             (SELECT CONCAT(p.product_name, ' - ', v.variant_name), v.expiry_date, v.stock_qty, 'Variant' FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.godown_id = ? AND v.stock_qty > 0 AND v.expiry_date <= CURDATE() AND v.expiry_date IS NOT NULL) ";
}

$stmt = $conn->prepare($sql);
$stmt->execute([$gid, $gid]);
$items = $stmt->fetchAll();
?>

<div class="stat-card shadow-sm border-0 rounded-4 p-4" style="background: #fff;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-calendar-times me-2"></i> <?= $page_title ?></h4>
        <a href="dashboard.php" class="btn btn-light rounded-pill px-4 shadow-sm">ড্যাশবোর্ড</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>পণ্যের নাম</th>
                    <th>ধরণ</th>
                    <th>স্টক</th>
                    <th>মেয়াদ শেষ হওয়ার তারিখ</th>
                    <th>অবস্থা</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): 
                    $days_left = (strtotime($item['expiry_date']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
                ?>
                <tr>
                    <td class="fw-bold"><?= $item['product_name'] ?></td>
                    <td><span class="badge bg-info text-dark" style="font-size: 11px;"><?= $item['type'] ?></span></td>
                    <td class="fw-bold text-dark"><?= $item['stock_qty'] ?> পিস</td>
                    <td class="text-danger fw-bold"><?= date('d M, Y', strtotime($item['expiry_date'])) ?></td>
                    <td>
                        <?php if($days_left <= 0): ?>
                            <span class="badge bg-danger rounded-pill px-3 py-2">Expired</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><?= (int)$days_left ?> দিন বাকি</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($items)): ?>
                    <tr><td colspan="5" class="text-center py-5 text-muted">বর্তমানে কোনো পণ্য স্টকে নেই যার মেয়াদ শেষ হয়েছে।</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>