<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php"); exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// =====================================================
// ১. কাস্টমার যোগ করার লজিক (অপরিবর্তিত)
// =====================================================
if (isset($_POST['add_customer'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    try {
        $stmt = $conn->prepare("INSERT INTO customers (godown_id, name, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$gid, $name, $phone, $address]);
        header("Location: customers.php?success=1"); exit();
    } catch(PDOException $e) {
        $error = "এই মোবাইল নম্বরটি ইতিপূর্বে ব্যবহার করা হয়েছে!";
    }
}

// =====================================================
// ২. কাস্টমার তথ্য এডিট করার লজিক (অপরিবর্তিত)
// =====================================================
if (isset($_POST['update_customer'])) {
    $c_id = $_POST['c_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    try {
        $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, address = ? WHERE id = ? AND godown_id = ?");
        $stmt->execute([$name, $phone, $address, $c_id, $gid]);
        header("Location: customers.php?updated=1"); exit();
    } catch(PDOException $e) {
        $error = "আপডেট করা সম্ভব হয়নি! মোবাইল নম্বরটি হয়তো অন্য কাস্টমারের।";
    }
}

// =====================================================
// ৩. কাস্টমার ডিলিট করার লজিক (অপরিবর্তিত)
// =====================================================
if (isset($_GET['delete'])) {
    $c_id = $_GET['delete'];
    $del = $conn->prepare("DELETE FROM customers WHERE id = ? AND godown_id = ?");
    $del->execute([$c_id, $gid]);
    header("Location: customers.php?deleted=1"); exit();
}

// =====================================================
// ৪. কাস্টমার লিস্ট এবং সার্চ কুয়েরি
// =====================================================
$search = $_GET['search'] ?? '';
$query = "SELECT c.*, 
          (SELECT SUM(due_amount) FROM sales WHERE customer_phone = c.phone AND godown_id = c.godown_id) as total_due 
          FROM customers c 
          WHERE c.godown_id = ? AND (c.name LIKE ? OR c.phone LIKE ?) 
          ORDER BY c.name ASC";
$stmt = $conn->prepare($query);
$stmt->execute([$gid, "%$search%", "%$search%"]);
$customers = $stmt->fetchAll();

$page_title = "কাস্টমার ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<style>
/* =====================================================
   THEME VARIABLES
===================================================== */
:root {
    --body-bg: #f8f9fc;
    --card-bg: #ffffff;
    --text-main: #2d3436;
    --text-muted: #6c757d;
    --border-color: #eaecf4;
    --input-bg: #ffffff;
    --input-text: #2d3436;
    --table-head-bg: #f8f9fc;
}

/* ডার্ক মোড সাপোর্ট */
body.dark, body.dark-mode, [data-bs-theme="dark"], .dark, .dark-mode {
    --body-bg: #111418 !important;
    --card-bg: #1b1e22 !important;
    --text-main: #f8f9fa !important;
    --text-muted: #adb5bd !important;
    --border-color: #313539 !important;
    --input-bg: #2b3035 !important;
    --input-text: #f8f9fa !important;
    --table-head-bg: #15181c !important;
}

body {
    background-color: var(--body-bg) !important;
    color: var(--text-main);
    transition: all 0.25s ease;
}

/* =====================================================
   LAYOUT STYLES
===================================================== */
.sr-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
}

.table-responsive {
    overflow: visible !important;
}

.table-premium thead th {
    background-color: var(--table-head-bg);
    color: #4e73df;
    font-weight: 700;
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
}

.table-premium tbody td {
    padding: 16px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-main);
}

.form-control-premium {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 10px;
    height: 45px;
}

/* Modal Dark Fix */
.modal-content {
    background-color: var(--card-bg);
    color: var(--text-main);
    border: 1px solid var(--border-color);
    border-radius: 20px;
}

@media (max-width: 768px) {
    .page-title-area { flex-direction: column; align-items: flex-start !important; gap: 15px; }
    .table-responsive { overflow-x: auto !important; }
}
</style>

<div class="container-fluid py-4">

    <!-- হেডার এরিয়া -->
    <div class="d-flex justify-content-between align-items-center mb-4 px-2 page-title-area">
        <div>
            <h4 class="fw-bold mb-0"><i class="fas fa-users text-primary me-2"></i> কাস্টমার ম্যানেজমেন্ট</h4>
            <p class="text-muted small mb-0">আপনার দোকানের সকল কাস্টমার তালিকা</p>
        </div>
        
        <button class="btn btn-primary fw-bold px-4 py-2 shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
            <i class="fas fa-plus-circle me-1"></i> নতুন কাস্টমার
        </button>
    </div>

    <!-- সার্চ এবং নোটিফিকেশন -->
    <?php if(isset($error)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="sr-card p-3 mb-4">
        <form method="GET">
            <div class="row g-2">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control form-control-premium" placeholder="নাম বা মোবাইল নম্বর দিয়ে খুঁজুন..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100 fw-bold h-100 rounded-3">সার্চ করুন</button>
                </div>
            </div>
        </form>
    </div>

    <!-- কাস্টমার টেবিল -->
     <span class="badge bg-soft-primary text-primary">মোট কাস্টমার <?= count($customers) ?> জন</span>
    <div class="sr-card">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">নাম</th>
                        <th>মোবাইল নম্বর</th>
                        <th>ঠিকানা</th>
                        <th>মোট বাকী (Due)</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($customers as $c): ?>
                    <tr>
                        <td class="ps-4 fw-bold text-primary">
                            <?= htmlspecialchars($c['name']) ?>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($c['phone']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($c['address']) ?></td>
                        <td class="fw-bold">
                            <span class="<?= ($c['total_due'] > 0) ? 'text-danger' : 'text-success' ?>">
                                ৳ <?= number_format($c['total_due'] ?? 0, 0) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-light border btn-sm rounded-pill px-3" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                    <li>
                                        <a class="dropdown-item py-2" href="#" data-bs-toggle="modal" data-bs-target="#editModal<?= $c['id'] ?>">
                                            <i class="fas fa-edit me-2 text-warning"></i> তথ্য এডিট
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item py-2 text-danger" href="customers.php?delete=<?= $c['id'] ?>" onclick="return confirm('আপনি কি নিশ্চিত?')">
                                            <i class="fas fa-trash-alt me-2"></i> কাস্টমার মুছুন
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if(count($customers) == 0): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">কোনো কাস্টমার পাওয়া যায়নি।</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =====================================================
     MODALS SECTION
===================================================== -->

<!-- নতুন কাস্টমার যোগ করার মোডাল -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">নতুন কাস্টমার যোগ করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">কাস্টমারের নাম</label>
                        <input type="text" name="name" class="form-control form-control-premium" placeholder="পুরো নাম লিখুন" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">মোবাইল নম্বর</label>
                        <input type="text" name="phone" class="form-control form-control-premium" placeholder="017XXXXXXXX" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">ঠিকানা</label>
                        <textarea name="address" class="form-control form-control-premium" rows="2" style="height: auto;" placeholder="বিস্তারিত ঠিকানা..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light fw-bold px-4 rounded-pill border" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="add_customer" class="btn btn-primary fw-bold px-4 rounded-pill">কাস্টমার সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- কাস্টমার এডিট করার মোডাল লুপ -->
<?php foreach($customers as $c): ?>
<div class="modal fade" id="editModal<?= $c['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="modal-title fw-bold">কাস্টমার তথ্য এডিট</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="c_id" value="<?= $c['id'] ?>">
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">নাম</label>
                        <input type="text" name="name" class="form-control form-control-premium" value="<?= htmlspecialchars($c['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">মোবাইল নম্বর</label>
                        <input type="text" name="phone" class="form-control form-control-premium" value="<?= htmlspecialchars($c['phone']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold mb-2">ঠিকানা</label>
                        <textarea name="address" class="form-control form-control-premium" rows="2" style="height: auto;"><?= htmlspecialchars($c['address']) ?></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pb-4 px-4">
                    <button type="button" class="btn btn-light fw-bold px-4 rounded-pill border" data-bs-dismiss="modal">বাতিল</button>
                    <button type="submit" name="update_customer" class="btn btn-warning fw-bold px-4 rounded-pill">আপডেট করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include 'includes/footer.php'; ?>