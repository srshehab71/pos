<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') { 
    header("Location: ../index.php"); 
    exit(); 
}

$uid = $_SESSION['user_id'];
$gid = $_SESSION['godown_id'];

$range = $_GET['range'] ?? '';
$search = trim($_GET['search'] ?? '');
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// ডিফল্টভাবে আজকের তারিখ সেট করা (যদি সব খালি থাকে)
if ($range == '' && $search == '' && $start_date == '') {
    $range = 'today';
}

if ($range === 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($range === 'week') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($range === 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
} elseif ($range === 'year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-m-d');
}

// এরর ফিক্স: সেশনে নাম না থাকলে আইডি দেখাবে
$sr_name = $_SESSION['user_name'] ?? $_SESSION['name'] ?? 'SR User';

// =====================================================
// --- ফিল্টার লজিক (অপরিবর্তিত) ---
// =====================================================
$where_clauses = ["s.user_id = ?", "s.godown_id = ?"];
$params = [$uid, $gid];

if ($search !== '') {
    $where_clauses[] = "(s.customer_name LIKE ? OR s.customer_phone LIKE ? OR s.id = ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search;
}

if ($start_date !== '' && $end_date !== '') {
    $where_clauses[] = "DATE(s.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if (!empty($_GET['search'])) {
    $where_clauses[] = "(customer_name LIKE ? OR customer_phone LIKE ?)";
    $params[] = "%" . $_GET['search'] . "%";
    $params[] = "%" . $_GET['search'] . "%";
}

$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

if (!empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "DATE(created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$where_sql = implode(" AND ", $where_clauses);
$stmt = $conn->prepare("
    SELECT s.*, 
    (SELECT GROUP_CONCAT(p.product_name SEPARATOR '|') 
     FROM sale_items si 
     JOIN products p ON si.product_id = p.id 
     WHERE si.sale_id = s.id) as items_list
    FROM sales s
    WHERE $where_sql 
    ORDER BY s.id DESC
");
$stmt->execute($params);
$sales = $stmt->fetchAll();

// সামারি হিসাব
$total_bill = 0; $total_paid = 0; $total_due = 0;
foreach ($sales as $s) {
    $total_bill += $s['payable_amount'];
    $total_paid += $s['paid_amount'];
    $total_due += $s['due_amount'];
}

$page_title = "সেলস রিপোর্ট ও ইতিহাস";
include 'includes/header.php'; 
?>

<style>
/* =====================================================
   PREMIUM DESIGN & DROPDOWN FIX
===================================================== */
:root {
    --glass-bg: #ffffff;
    --border-soft: #eaecf4;
    --text-dark: #2d3436;
}

[data-bs-theme="dark"], body.dark, body.dark-mode {
    --glass-bg: #1b1e22;
    --border-soft: #313539;
    --text-dark: #f8f9fa;
}

body { background-color: #f4f7fc !important; color: var(--text-dark); }
body.dark, body.dark-mode { background-color: #111418 !important; }

/* কার্ড ডিজাইন: overflow সরানো হয়েছে যাতে ড্রপডাউন দেখা যায় */
.premium-card {
    background: var(--glass-bg);
    border: 1px solid var(--border-soft);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    position: relative;
    overflow: visible !important; 
}

/* ড্রপডাউন যেন ডেবে না যায় তার জন্য মেইন ফিক্স */
.table-responsive {
    overflow: visible !important; /* ডেস্কটপে মেনু বের হতে দেবে */
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto !important; /* মোবাইলে ডানে-বামে স্ক্রল থাকবে */
        -webkit-overflow-scrolling: touch;
        /* ড্রপডাউন খোলার জন্য টেবিলের নিচে ১০০ পিক্সেল জায়গা রাখা হয়েছে */
        padding-bottom: 100px !important; 
    }
}

.dropdown-menu {
    border: none !important;
    box-shadow: 0 12px 35px rgba(0,0,0,0.2) !important;
    border-radius: 12px !important;
    z-index: 1060 !important;
    position: absolute !important;
}

/* সামারি কার্ড ডিজাইন */
.stat-card-premium {
    padding: 20px;
    border-radius: 18px;
    color: #fff;
    text-align: center;
    border: none;
}

.action-circle {
    width: 38px; height: 38px;
    border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    background: #4e73df;
    color: #fff;
    border: none;
}
</style>

<div class="container-fluid py-4">

    <!-- হেডার এরিয়া -->
    <div class="d-flex justify-content-between align-items-center mb-4 px-2">
        <div>
            <h3 class="fw-bold mb-0">বিক্রয় রিপোর্ট</h3>
            <span class="badge bg-primary rounded-pill px-3 py-2 mt-1">SR: <?= htmlspecialchars($sr_name) ?></span>
        </div>
        <div class="text-end text-muted small d-none d-md-block">
            <i class="far fa-calendar-alt"></i> <?= date('d M, Y') ?>
        </div>
    </div>

    
    <!-- সামারি কার্ডস (এক লাইনে ৩টি) -->
    <div class="row g-2 mb-4">
        <div class="col-4">
            <div class="stat-card-premium shadow-sm" style="background: linear-gradient(135deg, #4e73df, #224abe);">
                <small class="opacity-75 d-block" style="font-size: 10px;">মোট বিক্রয়</small>
                <h5 class="fw-bold mb-0">৳<?= number_format($total_bill, 0) ?></h5>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card-premium shadow-sm" style="background: linear-gradient(135deg, #1cc88a, #13855c);">
                <small class="opacity-75 d-block" style="font-size: 10px;">আদায়</small>
                <h5 class="fw-bold mb-0">৳<?= number_format($total_paid, 0) ?></h5>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-card-premium shadow-sm" style="background: linear-gradient(135deg, #e74a3b, #be2617);">
                <small class="opacity-75 d-block" style="font-size: 10px;">বাকী</small>
                <h5 class="fw-bold mb-0">৳<?= number_format($total_due, 0) ?></h5>
            </div>
        </div>
    </div>


<!-- FILTER -->
<div class="sr-card p-3 mb-4">
    <form method="GET" id="filterForm">
    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
        <div class="row g-2 align-items-center">
            <!-- সার্চ ইনপুট (অটো সাবমিট হবে) -->
            <div class="col-7">
                <input type="text" name="search" class="form-control" style="border-radius:10px;" placeholder="নাম বা ফোন..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
                        


            <!-- রেঞ্জ ফিল্টার ও বড় আইকন (ডান পাশে সরানো হয়েছে) -->
            <div class="col-md-5 d-flex gap-2 justify-content-end align-items-center">
                <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white border">
                    <a href="?range=today" class="btn btn-sm btn-outline-primary <?= $range == 'today' ? 'active' : '' ?>">আজ</a>
                    <a href="?range=week" class="btn btn-sm btn-outline-primary <?= $range == 'week' ? 'active' : '' ?>">সপ্তাহ</a>
                    <a href="?range=month" class="btn btn-sm btn-outline-primary <?= $range == 'month' ? 'active' : '' ?>">মাস</a>
                    <a href="?range=year" class="btn btn-sm btn-outline-primary <?= $range == 'year' ? 'active' : '' ?>">বছর</a>
                    <button type="button" onclick="toggleDateFilter()" class="btn btn-sm btn-outline-primary <?= ($start_date && $range=='') ? 'active' : '' ?>">
                        <i class="fas fa-calendar-day fa-lg"></i>
                    </button>
                </div>
                
                <!-- বড় রিসেট আইকন -->
                <a href="sales_history.php" class="btn btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;" title="রিসেট">
                    <i class="fas fa-sync-alt text-muted fa-lg"></i>
                </a>
            </div>
        </div>

        <!-- কাস্টম ডেট ক্যালেন্ডার বক্স (পয়েন্ট ২ সমাধান) -->
        <div id="date-filter-box" class="mt-3 p-3 bg-light border rounded-3" style="display: <?= ($start_date && $range == '') ? 'block' : 'none' ?>;">
            <div class="row g-2 align-items-end">
                <div class="col-5">
                    <label class="small fw-bold">হতে</label>
                    <input type="date" name="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                <div class="col-5">
                    <label class="small fw-bold">পর্যন্ত</label>
                    <input type="date" name="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-primary w-100">ঠিক আছে</button>
                </div>
            </div>
        </div>
    </form>
</div>


    <!-- ডাটা টেবিল -->
    <div class="premium-card shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-3 border-0 py-3" style="font-size: 11px; color:#4e73df;">ইনভয়েস</th>
                        <th class="border-0 py-3" style="font-size: 11px; color:#4e73df;">কাস্টমার</th>
                        <th class="border-0 py-3" style="font-size: 11px; color:#4e73df;">পণ্যসমূহ</th>
                        <th class="border-0 py-3" style="font-size: 11px; color:#4e73df;">বিল</th>
                        <th class="border-0 py-3 text-center" style="font-size: 11px; color:#4e73df;">আদায়/বাকী</th>
                        <th class="border-0 py-3 text-center" style="font-size: 11px; color:#4e73df;">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($sales) > 0): ?>
                        <?php foreach($sales as $s): ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-primary border shadow-sm">#<?= $s['id'] ?></span>
                                <div class="text-muted mt-1" style="font-size: 9px;"><?= date('d M, y', strtotime($s['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-bold small"><?= htmlspecialchars($s['customer_name']) ?></div>
                                <div class="small text-muted" style="font-size: 10px;"><?= htmlspecialchars($s['customer_phone']) ?></div>
                            </td>
                            
                            
                            <td style="min-width: 160px; vertical-align: top;">
    <?php if(!empty($s['items_list'])): ?>
        <div class="d-flex flex-column">
            <?php 
            // পাইপ (|) চিহ্ন দিয়ে নামগুলো আলাদা করা হচ্ছে
            $items = explode('|', $s['items_list']); 
            foreach($items as $item): 
            ?>
                <div class="mb-1 d-flex align-items-center" style="font-size: 11px; color: #444;">
                    <i class="fas fa-caret-right text-primary me-2"></i> 
                    <span><?= htmlspecialchars($item) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <span class="text-muted small">কোনো পণ্য নেই</span>
    <?php endif; ?>
</td>


                            <td class="fw-bold">৳<?= number_format($s['payable_amount'], 0) ?></td>
                            <td class="text-center">
                                <div class="small text-success fw-bold" style="font-size: 10px;">জমা: <?= number_format($s['paid_amount'], 0) ?></div>
                                <div class="small text-danger fw-bold" style="font-size: 10px;">বাকী: <?= number_format($s['due_amount'], 0) ?></div>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="action-circle mx-auto" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                                        <li><a class="dropdown-item py-2 fw-bold" href="print_invoice.php?id=<?= $s['id'] ?>" target="_blank"><i class="fas fa-print me-2 text-primary"></i> প্রিন্ট মেমো</a></li>
                                        <li><a class="dropdown-item py-2 fw-bold" href="edit_sale.php?id=<?= $s['id'] ?>"><i class="fas fa-edit me-2 text-warning"></i> এডিট করুন</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item py-2 fw-bold text-danger" href="delete_sale.php?id=<?= $s['id'] ?>" onclick="return confirm('আপনি কি নিশ্চিত?')"><i class="fas fa-trash-alt me-2"></i> ডিলিট করুন</a></li>
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

<script>
function toggleDateFilter() {
    var x = document.getElementById("date-filter-box");
    if (x.style.display === "none") {
        x.style.display = "block";
    } else {
        x.style.display = "none";
    }
}
</script>

<?php include 'includes/footer.php'; ?>