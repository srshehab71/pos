<?php
$page_title = "ট্রানজেকশন হিস্ট্রি";
include 'includes/header.php';

// ডিলেট লজিক
if(isset($_GET['delete'])){
    $id = $_GET['delete'];
    $conn->prepare("DELETE FROM recharges WHERE id = ?")->execute([$id]);
    echo "<script>window.location='transactions.php';</script>";
}

$stmt = $conn->query("SELECT recharges.*, godowns.shop_name FROM recharges JOIN godowns ON recharges.godown_id = godowns.id ORDER BY id DESC");
$recharges = $stmt->fetchAll();
?>

<div class="stat-card">
    <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-history me-2"></i> রিচার্জ ও পেমেন্ট হিস্ট্রি</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>তারিখ</th>
                    <th>দোকানের নাম</th>
                    <th>টাকা</th>
                    <th>মেয়াদ (দিন)</th>
                    <th>TrxID</th>
                    <th>অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recharges as $r): ?>
                <tr>
                    <td><?= date('d M, Y', strtotime($r['created_at'])) ?></td>
                    <td><?= $r['shop_name'] ?></td>
                    <td>৳ <?= $r['amount'] ?></td>
                    <td><?= $r['days_added'] ?> দিন</td>
                    <td><span class="badge bg-light text-dark"><?= $r['trx_id'] ?: 'N/A' ?></span></td>
                    <td>
                        <a href="transactions.php?delete=<?= $r['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('এটি ডিলিট করলে হিসাব থেকে মুছে যাবে। নিশ্চিত?')"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>