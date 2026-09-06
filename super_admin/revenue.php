<?php
session_start();
include '../config/db.php';

// সুপার অ্যাডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') { 
    header("Location: ../index.php"); 
    exit(); 
}

// আয় আপডেট লজিক
if (isset($_POST['update_income'])) {
    $id = $_POST['godown_id'];
    $new_amount = $_POST['new_amount'];

    $conn->prepare("UPDATE godowns SET total_recharged = ? WHERE id = ?")->execute([$new_amount, $id]);

    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
    exit();
}

// ডাটা কুয়েরি (ID সহ আনা হয়েছে এডিট/ডিলিটের জন্য)
$godowns = $conn->query("SELECT id, shop_name, owner_name, total_recharged, phone FROM godowns WHERE total_recharged > 0 ORDER BY total_recharged DESC")->fetchAll();

$page_title = "আয় রিপোর্ট ও গোডাউন ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<style>
/* =====================================================
   PREMIUM UI VARIABLES
===================================================== */
:root {
    --body-bg: #f8f9fc;
    --card-bg: #ffffff;
    --text-main: #2d3436;
    --border-soft: #eaecf4;
}
[data-bs-theme="dark"], body.dark, body.dark-mode {
    --body-bg: #111418;
    --card-bg: #1b1e22;
    --text-main: #f8f9fa;
    --border-soft: #313539;
}

body { background-color: var(--body-bg) !important; color: var(--text-main); transition: 0.3s; }

.sr-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-soft);
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,.05);
    overflow: visible !important;
}

.income-stat {
    background: linear-gradient(135deg, #6610f2 0%, #432d7d 100%);
    color: white;
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}

.table-modern thead th {
    background-color: var(--border-soft);
    color: #6610f2;
    font-weight: 700;
    padding: 18px;
    border: none;
    text-transform: uppercase;
    font-size: 13px;
}

.table-modern tbody td {
    padding: 18px;
    border-bottom: 1px solid var(--border-soft);
    vertical-align: middle;
}

/* ৩-ডট বাটন */
.action-btn {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: var(--border-soft);
    border: none;
    color: var(--text-main);
    display: flex; align-items: center; justify-content: center;
    transition: 0.3s;
}
.action-btn:hover { background: #6610f2; color: #fff; }

@media (max-width: 768px) {
    .table-responsive { padding-bottom: 80px !important; }
}
</style>

<div class="container-fluid py-4">

    <!-- টপ সামারি কার্ড -->
    <?php 
        $grand_total = 0; 
        foreach($godowns as $g) { $grand_total += $g['total_recharged']; } 
    ?>
    <div class="income-stat shadow-lg">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-uppercase opacity-75 fw-bold mb-1">সফটওয়্যার থেকে মোট আয়</h6>
                <h1 class="fw-bold mb-0">৳ <?= number_format($grand_total, 2) ?></h1>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <i class="fas fa-wallet fa-4x opacity-25"></i>
            </div>
        </div>
    </div>

    <!-- মেইন টেবিল কার্ড -->
    <div class="sr-card p-2">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom mb-2">
            <h5 class="fw-bold mb-0"><i class="fas fa-store text-primary me-2"></i> গোডাউন ভিত্তিক আয়</h5>
            <span class="badge bg-soft-primary text-primary border px-3 rounded-pill">গোডাউন সংখ্যা: <?= count($godowns) ?></span>
        </div>

        <div class="table-responsive" style="overflow: visible !important;">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">দোকানের নাম</th>
                        <th>মালিক ও ফোন</th>
                        <th class="text-end">মোট রিচার্জ/আয়</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($godowns as $g): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($g['shop_name']) ?></div>
                            <small class="text-muted">ID: #<?= $g['id'] ?></small>
                        </td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($g['owner_name']) ?></div>
                            <div class="small text-muted"><i class="fas fa-phone-alt me-1"></i> <?= htmlspecialchars($g['phone'] ?? 'N/A') ?></div>
                        </td>
                        <td class="text-end fw-bold text-success fs-5">
                            ৳ <?= number_format($g['total_recharged'], 2) ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="action-btn mx-auto" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3">
                                    <li>
                                        <a class="dropdown-item py-2 fw-bold text-primary" href="#" data-bs-toggle="modal" data-bs-target="#editIncome<?= $g['id'] ?>">
                                            <i class="fas fa-coins me-2"></i> আয় এডিট করুন
                                        </a>
                                    </li>

                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item py-2 fw-bold text-danger" href="delete_godown.php?id=<?= $g['id'] ?>" onclick="return confirm('আপনি কি নিশ্চিত? এই গোডাউনের সকল তথ্য মুছে যাবে!')">
                                            <i class="fas fa-trash-alt me-2"></i> গোডাউন মুছুন
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- আয় এডিট মোডাল লুপ -->
<?php foreach($godowns as $g): ?>
<div class="modal fade" id="editIncome<?= $g['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h6 class="modal-title fw-bold">আয় এডিট করুন</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p class="small text-muted mb-3"><?= $g['shop_name'] ?></p>
                    <input type="hidden" name="godown_id" value="<?= $g['id'] ?>">
                    <div class="mb-2">
                        <label class="small fw-bold mb-1">নতুন পরিমাণ (৳)</label>
                        <input type="number" name="new_amount" class="form-control fw-bold" value="<?= $g['total_recharged'] ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light btn-sm fw-bold" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="update_income" class="btn btn-primary btn-sm fw-bold">আপডেট</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include '../admin/includes/footer.php'; ?>