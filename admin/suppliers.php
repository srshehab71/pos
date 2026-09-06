<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// ১. সাপ্লায়ার যোগ করার লজিক (অপরিবর্তিত)
if (isset($_POST['add_supplier'])) {
    $supplier_name = $_POST['supplier_name'];
    $company_name  = $_POST['company_name'];
    $phone         = $_POST['phone'];
    $address       = $_POST['address'];
    try {
        $stmt = $conn->prepare("INSERT INTO suppliers (godown_id, supplier_name, company_name, phone, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$gid, $supplier_name, $company_name, $phone, $address]);
        header("Location: suppliers.php?success=1"); exit();
    } catch(PDOException $e) { $error = "মোবাইল নম্বরটি ইতিমধ্যে ব্যবহার করা হয়েছে!"; }
}

// ২. আপডেট লজিক (অপরিবর্তিত)
if (isset($_POST['update_supplier'])) {
    $s_id = $_POST['s_id'];
    $supplier_name = $_POST['supplier_name'];
    $company_name = $_POST['company_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    try {
        $stmt = $conn->prepare("UPDATE suppliers SET supplier_name=?, company_name=?, phone=?, address=? WHERE id=? AND godown_id=?");
        $stmt->execute([$supplier_name, $company_name, $phone, $address, $s_id, $gid]);
        header("Location: suppliers.php?updated=1"); exit();
    } catch(PDOException $e) { $error = "আপডেট করা সম্ভব হয়নি!"; }
}

// ৩. ডিলিট লজিক (অপরিবর্তিত)
if (isset($_GET['delete'])) {
    $s_id = $_GET['delete'];
    $del = $conn->prepare("DELETE FROM suppliers WHERE id=? AND godown_id=?");
    $del->execute([$s_id, $gid]);
    header("Location: suppliers.php?deleted=1"); exit();
}

// ৪. সাপ্লায়ার লিস্ট
$search = $_GET['search'] ?? '';
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE godown_id=? AND (supplier_name LIKE ? OR company_name LIKE ?) ORDER BY id DESC");
$stmt->execute([$gid, "%$search%", "%$search%"]);
$suppliers = $stmt->fetchAll();

$page_title = "সাপ্লায়ার ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<style>
/* =====================================================
   MODERN UI & MOBILE OPTIMIZATION
===================================================== */
:root {
    --card-bg: #ffffff;
    --border-soft: #eaecf4;
}
[data-bs-theme="dark"], body.dark, body.dark-mode {
    --card-bg: #1b1e22;
    --border-soft: #313539;
}

.sr-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-soft);
    border-radius: 16px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}

/* টেবিল মোবাইলে ফিট করার জন্য */
.table-modern {
    width: 100%;
    min-width: 600px; /* মোবাইলে যেন কলামগুলো একেবারে চ্যাপ্টা না হয় */
}

.table-modern thead th {
    background-color: var(--border-soft);
    color: #4e73df;
    font-size: 13px;
    padding: 12px 15px;
    border: none;
    text-transform: uppercase;
}

.table-modern tbody td {
    padding: 15px;
    border-bottom: 1px solid var(--border-soft);
    font-size: 14px;
}

/* ৩-ডট ড্রপডাউন মোবাইল ফিক্স */
.table-responsive {
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
}

@media (max-width: 768px) {
    .table-responsive {
        padding-bottom: 100px !important; /* মেনু যেন নিচে ডেবে না যায় */
    }
    .page-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    .search-area .col-md-10 { margin-bottom: 10px; }
}

.action-circle {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 1px solid var(--border-soft);
    background: var(--border-soft);
    display: flex; align-items: center; justify-content: center;
    color: #4e73df;
    transition: 0.3s;
}

.form-control-custom {
    border-radius: 10px;
    padding: 10px 15px;
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-soft) !important;
    color: inherit !important;
}

.modal-content {
    border-radius: 20px;
    border: none;
}
</style>

<div class="container-fluid py-4 px-3 px-md-4">

    <!-- হেডার এরিয়া -->
    <div class="d-flex justify-content-between align-items-center mb-4 page-header">
        <div>
            <h4 class="fw-bold mb-1 text-primary"><i class="fas fa-truck-loading me-2"></i> সাপ্লায়ার তালিকা</h4>
            <p class="text-muted small mb-0">মোট সাপ্লায়ার: <?= count($suppliers) ?> জন</p>
        </div>
        <button class="btn btn-primary fw-bold px-4 rounded-pill shadow" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
            <i class="fas fa-plus-circle me-1"></i> নতুন সাপ্লায়ার
        </button>
    </div>

    <!-- সার্চবার -->
    <div class="sr-card p-3 mb-4 shadow-sm search-area">
        <form method="GET">
            <div class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control form-control-custom" placeholder="সাপ্লায়ার বা কোম্পানির নাম..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold h-100 rounded-3">
                        <i class="fas fa-search me-1"></i> সার্চ
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- মেইন টেবিল -->
          <span class="badge bg-soft-primary text-primary">মোট সাপ্লায়ার <?= count($suppliers) ?> জন</span>

    <div class="sr-card shadow-sm overflow-hidden">
        <div class="table-responsive">
            <table class="table table-modern align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">কোম্পানি</th>
                        <th>সাপ্লায়ার</th>
                        <th>ফোন নম্বর</th>
                        <th>ঠিকানা</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($suppliers) > 0): ?>
                        <?php foreach($suppliers as $s): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-primary"><?= htmlspecialchars($s['company_name']) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($s['supplier_name']) ?></div>
                            </td>
                            <td>
                                <a href="tel:<?= $s['phone'] ?>" class="text-decoration-none text-dark">
                                    <i class="fas fa-phone-alt me-1 text-success small"></i> <?= htmlspecialchars($s['phone']) ?>
                                </a>
                            </td>
                            <td>
                                <div class="text-muted small text-truncate" style="max-width: 150px;"><?= htmlspecialchars($s['address']) ?></div>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="action-circle mx-auto" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4">
                                        <li>
                                            <a class="dropdown-item py-2 px-3 fw-bold" href="#" data-bs-toggle="modal" data-bs-target="#editModal<?= $s['id'] ?>">
                                                <i class="fas fa-edit me-2 text-warning"></i> তথ্য এডিট
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item py-2 px-3 fw-bold text-danger" href="suppliers.php?delete=<?= $s['id'] ?>" onclick="return confirm('আপনি কি নিশ্চিত?')">
                                                <i class="fas fa-trash-alt me-2"></i> মুছুন
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">কোনো ডাটা পাওয়া যায়নি।</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- মোডাল: অ্যাড সাপ্লায়ার -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">নতুন সাপ্লায়ার যোগ করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">কোম্পানি/ডিলারের নাম</label>
                        <input type="text" name="company_name" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">সাপ্লায়ার ব্যক্তির নাম</label>
                        <input type="text" name="supplier_name" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">মোবাইল নম্বর</label>
                        <input type="text" name="phone" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">ঠিকানা</label>
                        <textarea name="address" class="form-control form-control-custom" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="submit" name="add_supplier" class="btn btn-primary w-100 fw-bold py-2 rounded-pill shadow">সাপ্লায়ার সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- মোডাল: এডিট সাপ্লায়ার লুপ -->
<?php foreach($suppliers as $s): ?>
<div class="modal fade" id="editModal<?= $s['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">তথ্য এডিট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="s_id" value="<?= $s['id'] ?>">
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">কোম্পানির নাম</label>
                        <input type="text" name="company_name" class="form-control form-control-custom" value="<?= $s['company_name'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">সাপ্লায়ার নাম</label>
                        <input type="text" name="supplier_name" class="form-control form-control-custom" value="<?= $s['supplier_name'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">মোবাইল নম্বর</label>
                        <input type="text" name="phone" class="form-control form-control-custom" value="<?= $s['phone'] ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">ঠিকানা</label>
                        <textarea name="address" class="form-control form-control-custom" rows="2"><?= $s['address'] ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="submit" name="update_supplier" class="btn btn-warning w-100 fw-bold py-2 rounded-pill shadow">আপডেট করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>