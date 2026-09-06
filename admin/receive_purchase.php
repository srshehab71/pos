<?php
session_start();
include '../config/db.php';
$purchase_id = $_GET['id'];
$gid = $_SESSION['godown_id'];

// পারচেস ডিটেইলস
$stmt = $conn->prepare("SELECT p.*, s.supplier_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ? AND p.godown_id = ?");
$stmt->execute([$purchase_id, $gid]);
$purchase = $stmt->fetch();

// আইটেম লিস্ট
$item_stmt = $conn->prepare("
    SELECT pi.*, 
    CASE WHEN pi.item_type = 'single' THEN pr.product_name ELSE CONCAT(pr2.product_name, ' - ', pv.variant_name) END as name
    FROM purchase_items pi 
    LEFT JOIN products pr ON pi.product_id = pr.id AND pi.item_type = 'single'
    LEFT JOIN product_variants pv ON pi.product_id = pv.id AND pi.item_type = 'variant'
    LEFT JOIN products pr2 ON pv.product_id = pr2.id
    WHERE pi.purchase_id = ?
");
$item_stmt->execute([$purchase_id]);
$items = $item_stmt->fetchAll();

$page_title = "মালামাল রিসিভ করুন";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="purchase-card p-4 shadow-sm bg-white rounded-4">
        <h4 class="fw-bold text-primary mb-4">রিসিভ মালামাল (ইনভয়েস #<?= $purchase_id ?>)</h4>
        <p class="text-muted">সাপ্লায়ার: <b><?= $purchase['supplier_name'] ?></b> | চালান: <b><?= $purchase['chalan_no'] ?></b></p>
        
        <form action="process_receive_purchase.php" method="POST">
            <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th>পণ্যের নাম</th>
                            <th class="text-center">অর্ডার করা</th>
                            <th class="text-center" width="150">আগে রিসিভ (Edit)</th>
                            <th class="text-center" width="150">এখন রিসিভ করছেন</th>
                            <th class="text-center">অবশিষ্ট</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): 
                            $remaining = $item['qty'] - $item['received_qty'];
                        ?>
                        <tr>
                            <td><?= $item['name'] ?></td>
                            <td class="text-center fw-bold"><?= $item['qty'] ?></td>
                            <td>
                                <!-- এটি এখন ইনপুট ফিল্ড, আগের রিসিভ সংশোধন করতে পারবেন -->
                                <input type="number" step="any" name="prev_received[<?= $item['id'] ?>]" 
                                       class="form-control text-center bg-light" 
                                       value="<?= $item['received_qty'] ?>">
                            </td>
                            <td>
                                <input type="number" step="any" name="now_receive[<?= $item['id'] ?>]" 
                                       class="form-control text-center border-primary" 
                                       placeholder="0" value="">
                            </td>
                            <td class="text-center text-danger"><?= $remaining ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-4">
                <a href="purchase_list.php" class="btn btn-light rounded-pill px-4 me-2">বাতিল</a>
                <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow">
                    রিসিভ কনফার্ম করুন
                </button>
            </div>
        </form>
    </div>
</div>
<?php include 'includes/footer.php'; ?>