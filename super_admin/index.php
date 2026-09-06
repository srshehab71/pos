<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php"); exit();
}

if (isset($_GET['delete_godown'])) {
    $id = $_GET['delete_godown'];
    $conn->prepare("DELETE FROM godowns WHERE id = ?")->execute([$id]);
    header("Location: index.php?msg=deleted"); exit();
}

$page_title = "মাস্টার ড্যাশবোর্ড";
include 'includes/header.php';

$total_g = $conn->query("SELECT COUNT(*) FROM godowns")->fetchColumn();
$active_g = $conn->query("SELECT COUNT(*) FROM godowns WHERE expiry_date >= CURDATE()")->fetchColumn();
$total_rev = $conn->query("SELECT SUM(total_recharged) FROM godowns")->fetchColumn() ?? 0;
$notice = $conn->query("SELECT message FROM broadcasts WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
$godowns = $conn->query("SELECT * FROM godowns ORDER BY expiry_date ASC")->fetchAll();
?>

<?php if($notice): ?>
<div class="alert alert-warning border-0 shadow-sm mb-4 d-flex align-items-center">
    <i class="fas fa-bullhorn me-3 fa-lg"></i>
    <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();">
        <strong>সিস্টেম নোটিশ:</strong> <?= htmlspecialchars($notice) ?>
    </marquee>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="stat-card border-start border-primary border-4 h-100 p-3 shadow-sm bg-white rounded"><small class="text-muted fw-bold">মোট এডমিন</small><h2 class="fw-bold m-0"><?= $total_g ?></h2></div></div>
    <div class="col-md-4"><div class="stat-card border-start border-success border-4 h-100 p-3 shadow-sm bg-white rounded"><small class="text-muted fw-bold">সক্রিয় মেম্বার</small><h2 class="fw-bold m-0 text-success"><?= $active_g ?></h2></div></div>
    <div class="col-md-4"><div class="stat-card border-start border-info border-4 h-100 p-3 shadow-sm bg-white rounded"><small class="text-muted fw-bold">মোট আয়</small><h2 class="fw-bold m-0 text-primary">৳ <?= number_format($total_rev) ?></h2></div></div>
</div>

<div class="stat-card bg-white p-4 rounded shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0">সব গোডাউন মালিকের তালিকা</h5>
        <a href="add_godown.php" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-plus-circle me-1"></i> নতুন এডমিন যোগ করুন</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>দোকানের নাম</th>
                    <th>মালিক ও ইমেইল</th>
                    <th>মেয়াদ শেষ</th>
                    <th>অবস্থা</th>
                    <th class="text-center">অ্যাকশন</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($godowns as $g): 
                    $is_expired = ($g['expiry_date'] < date('Y-m-d') || $g['expiry_date'] == NULL);
                ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($g['shop_name']) ?></td>
                    <td><?= htmlspecialchars($g['owner_name']) ?> </br> <?= htmlspecialchars($g['email']) ?></td>
                    <td class="fw-bold <?= $is_expired ? 'text-danger' : 'text-success' ?>">
                        <?= $g['expiry_date'] ? date('d M, Y', strtotime($g['expiry_date'])) : 'সেট করা হয়নি' ?>
                    </td>
                    <td><?php if($g['status'] == 'inactive'): ?><span class="badge bg-secondary">Inactive</span><?php elseif($is_expired): ?><span class="badge bg-danger">Expired</span><?php else: ?><span class="badge bg-success">Active</span><?php endif; ?></td>                    <td class="text-center">
                        <a href="impersonate.php?gid=<?= $g['id'] ?>" class="btn btn-info btn-sm text-white" title="লগইন" target="_blank"><i class="fas fa-user-secret"></i></a>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recharge<?= $g['id'] ?>"><i class="fas fa-bolt"></i></button>
                        <a href="edit_godown.php?id=<?= $g['id'] ?>" class="btn btn-outline-warning btn-sm"><i class="fas fa-edit"></i></a>
                        <a href="index.php?delete_godown=<?= $g['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('নিশ্চিত ডিলিট করবেন?')"><i class="fas fa-trash"></i></a>

                        <!-- Recharge Modal -->
                        <div class="modal fade" id="recharge<?= $g['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <form action="recharge_process.php" method="POST" class="modal-content text-start">
                                    <div class="modal-header bg-primary text-white"><h5>রিচার্জ: <?= $g['shop_name'] ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body p-4">
                                        <input type="hidden" name="godown_id" value="<?= $g['id'] ?>">
                                        <label class="fw-bold small">মেয়াদ বাড়ান</label>
                                        <select name="days" class="form-select mb-3" required>
                                            <option value="30">১ মাস (৩০ দিন)</option>
                                            <option value="90">৩ মাস (৯০ দিন)</option>
                                            <option value="180">৬ মাস (১৮০ দিন)</option>
                                            <option value="365">১ বছর (৩৬৫ দিন)</option>
                                            <option value="0">মেয়াদ বাড়াবো না</option>
                                        </select>
                                        <label class="fw-bold small">টাকার পরিমাণ (৳)</label>
                                        <input type="number" name="amount" class="form-control" placeholder="0.00" required>
                                    </div>
                                    <div class="modal-footer bg-light"><button type="submit" name="recharge" class="btn btn-primary w-100 fw-bold">রিচার্জ সম্পন্ন করুন</button></div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>