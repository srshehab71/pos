<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';

$gid = $_SESSION['godown_id'];


// ১. ইউজার থেকে ডাটা নেওয়া
$range = $_GET['range'] ?? '';
$search = trim($_GET['search'] ?? '');
$sr_id = $_GET['sr_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// ২. ডিফল্টভাবে আজকের তারিখ সেট করা (যদি সব খালি থাকে)
if ($range == '' && $search == '' && $sr_id == '' && $start_date == '') {
    $range = 'today';
}

// ৩. রেঞ্জ অনুযায়ী তারিখ প্রসেস করা
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

// ৪. কুয়েরি লজিক তৈরি করা
$where_clauses = ["sales.godown_id = ?"];
$params = [$gid];

if ($search !== '') {
    $where_clauses[] = "(sales.customer_name LIKE ? OR sales.customer_phone LIKE ? OR sales.id = ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search;
}

if ($sr_id !== '') {
    if ($sr_id === 'admin_only') {
        $where_clauses[] = "(users.role IS NULL OR users.role != 'sr')";
    } else {
        $where_clauses[] = "sales.user_id = ?";
        $params[] = $sr_id;
    }
}

if ($start_date !== '' && $end_date !== '') {
    $where_clauses[] = "DATE(sales.created_at) BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

$where_sql = implode(" AND ", $where_clauses);

// =====================================================
// SALES QUERY
// =====================================================
$query = "
    SELECT sales.*, users.name AS sr_name,
    (SELECT GROUP_CONCAT(p.product_name SEPARATOR '|') 
     FROM sale_items si 
     JOIN products p ON si.product_id = p.id 
     WHERE si.sale_id = sales.id) as items_list
    FROM sales
    LEFT JOIN users ON sales.user_id = users.id
    WHERE $where_sql
    ORDER BY sales.id DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =====================================================
// SUMMARY
// =====================================================
$total_payable = 0;
$total_paid = 0;
$total_due = 0;

foreach ($sales as $s) {
    $total_payable += (float)$s['payable_amount'];
    $total_paid += (float)$s['paid_amount'];
    $total_due += (float)$s['due_amount'];
}

// =====================================================
// SR LIST
// =====================================================
$sr_stmt = $conn->prepare("SELECT id, name FROM users WHERE godown_id = ? AND role = 'sr' ORDER BY name ASC");
$sr_stmt->execute([$gid]);
$srs = $sr_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "সেলস রিপোর্ট";
include 'includes/header.php';
?>

<style>
/* =====================================================
   THEME VARIABLES (Light & Dark)
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
    --hover-bg: rgba(78,115,223,0.06);
}

/* ডার্ক মোড সাপোর্ট (সব ধরণের কমন ক্লাসের জন্য) */
body.dark, body.dark-mode, [data-bs-theme="dark"], .dark, .dark-mode {
    --body-bg: #111418 !important;
    --card-bg: #1b1e22 !important;
    --text-main: #f8f9fa !important;
    --text-muted: #adb5bd !important;
    --border-color: #313539 !important;
    --input-bg: #2b3035 !important;
    --input-text: #f8f9fa !important;
    --table-head-bg: #15181c !important;
    --hover-bg: rgba(255,255,255,0.05) !important;
}

/* ডার্ক মোডে বডি কালার পরিবর্তন নিশ্চিত করা */
body.dark, body.dark-mode, [data-bs-theme="dark"] {
    background-color: var(--body-bg) !important;
    color: var(--text-main);
}

/* =====================================================
   LAYOUT STYLES
===================================================== */
body {
    background-color: var(--body-bg);
    color: var(--text-main);
    transition: all 0.25s ease;
}

.sr-card {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,.05);
}

.stat-box {
    padding: 15px;
    border-radius: 12px;
    background: var(--card-bg);
    border-left: 4px solid;
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
}

/* ড্রপডাউন সমস্যা সমাধানের জন্য CSS */
.table-responsive {
    overflow: visible !important; /* ডেস্কটপে ড্রপডাউন বাইরে যেতে দেবে */
}

@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto !important; /* মোবাইলে স্ক্রল দরকার */
    }
}

.dropdown-menu {
    z-index: 1060 !important;
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
}

.dropdown-item {
    color: var(--text-main) !important;
}

.dropdown-item:hover {
    background-color: var(--hover-bg) !important;
}

/* ফন্ট ও ইনপুট ফিক্স */
.form-control-premium {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border: 1px solid var(--border-color) !important;
    height: 42px;
    border-radius: 10px;
}

.filter-wrapper {
    display: flex;
    gap: 8px;
    align-items: center;
}

.table-premium thead th {
    background-color: var(--table-head-bg);
    color: #4e73df;
    font-weight: 700;
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
}

.table-premium tbody td {
    padding: 15px;
    border-bottom: 1px solid var(--border-color);
    color: var(--text-main);
}

.custom-date-box { display: none; }
.btn-outline-primary.active {
    background-color: #1548e1 !important;
    color: white !important;
}

.fa-lg { font-size: 1.2rem !important; }
.btn-group .btn { padding: 8px 14px; font-size: 12px; }
.rounded-circle { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
</style>

<div class="container-fluid py-4">

    <!-- PAGE TITLE -->
    <div class="d-flex justify-content-between align-items-center mb-4 px-2">
        <h4 class="fw-bold mb-0">বিক্রয় রিপোর্ট</h4>
        <small class="text-muted">সময়কাল: <?= date('d M', strtotime($start_date)) ?> - <?= date('d M, Y', strtotime($end_date)) ?></small>
        <div class="fw-bold small opacity-75">ID: #<?= (int)$gid ?></div>
    </div>

    <!-- SUMMARY -->
    <div class="row g-2 g-md-3 mb-4">
        <div class="col-4">
            <div class="stat-box" style="border-color:#4e73df;">
                <small class="text-muted fw-bold">মোট বিক্রয়</small>
                <div class="fw-bold h5 mb-0 mt-1">৳<?= number_format($total_payable, 0) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box" style="border-color:#1cc88a;">
                <small class="text-muted fw-bold">নগদ আদায়</small>
                <div class="fw-bold h5 mb-0 mt-1 text-success">৳<?= number_format($total_paid, 0) ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="stat-box" style="border-color:#e74a3b;">
                <small class="text-muted fw-bold">মোট বাকী</small>
                <div class="fw-bold h5 mb-0 mt-1 text-danger">৳<?= number_format($total_due, 0) ?></div>
            </div>
        </div>
    </div>

<!-- FILTER -->
<div class="sr-card p-3 mb-4">
    <form method="GET" id="filterForm">
    <input type="hidden" name="range" value="<?= htmlspecialchars($range) ?>">
        <div class="row g-2 align-items-center">
            <!-- সার্চ ইনপুট (অটো সাবমিট হবে) -->
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-premium" placeholder="নাম/ফোন/ইনভয়েস..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
            </div>
            
            <!-- ড্রপডাউন (সিলেক্ট করলেই অটো কাজ করবে) -->
            <div class="col-md-2">
                <select name="sr_id" class="form-select form-control-premium" onchange="this.form.submit()">
                    <option value="">সব সেলস</option>
                    <option value="admin_only" <?= ($sr_id == 'admin_only') ? 'selected' : '' ?>>👤 অ্যাডমিন সেলস</option>
                    <optgroup label="এসআর লিস্ট">
                        <?php foreach ($srs as $sr): ?>
                            <option value="<?= (int)$sr['id'] ?>" <?= ($sr_id == (string)$sr['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sr['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>

            


            <!-- রেঞ্জ ফিল্টার ও বড় আইকন (ডান পাশে সরানো হয়েছে) -->
            <div class="col-md-7 d-flex gap-2 justify-content-end align-items-center">
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
                <a href="sales.php" class="btn btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;" title="রিসেট">
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

        <!-- প্রিন্ট বাটন -->
<div class="col-md-12 mt-3">
    <!-- এখানে ম্যানুয়ালি ভেরিয়েবলগুলো পাস করা হয়েছে যাতে সব ফিল্টার কাজ করে -->
    <a href="print_all_invoices.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&search=<?= urlencode($search) ?>&sr_id=<?= urlencode($sr_id) ?>" 
       target="_blank" 
       class="btn btn-success w-100 fw-bold rounded-pill">
        <i class="fas fa-print me-2"></i> এই তালিকার সকল ইনভয়েস একসাথে প্রিন্ট করুন
    </a>
</div>
    </form>
</div>

    <!-- SALES TABLE -->
    <div class="sr-card">
        <div class="table-responsive">
            <table class="table table-premium mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ইনভয়েস</th>
                        <th>তারিখ</th>
                        <th>কাস্টমার ও ফোন</th>
                        <th>পণ্যসমূহ</th>
                        <th>এসআর (SR)</th>
                        <th>মোট বিল</th>
                        <th>নগদ / বাকী</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sales) > 0): ?>
                        <?php foreach ($sales as $s): ?>
                            <tr>
                                <td class="ps-4"><span class="fw-bold text-primary">#<?= (int)$s['id'] ?></span></td>
                                <td class="text-muted small"><?= date('d M, Y', strtotime($s['created_at'])) ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($s['customer_name']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($s['customer_phone']) ?></div>
                                </td>

<!-- এই অংশটুকু নতুন বসান -->
<td style="min-width: 150px; vertical-align: top;">
    <?php if(!empty($s['items_list'])): ?>
        <ul class="list-unstyled mb-0" style="font-size: 11px; line-height: 1.5;">
            <?php 
            $items = explode('|', $s['items_list']); 
            foreach($items as $item): 
            ?>
                <li class="text-dark d-flex align-items-start mb-1">
                    <i class="fas fa-caret-right text-primary me-2 mt-1"></i> 
                    <span><?= htmlspecialchars($item) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <span class="text-muted small">পণ্য নেই</span>
    <?php endif; ?>
</td>
<!-- নতুন অংশ শেষ -->


                                <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($s['sr_name'] ?? 'N/A') ?></span></td>
                                <td class="fw-bold">৳<?= number_format($s['payable_amount'], 0) ?></td>
                                <td>
                                    <div class="small text-success fw-bold">নগদ: <?= number_format($s['paid_amount'], 0) ?></div>
                                    <div class="small text-danger fw-bold">বাকী: <?= number_format($s['due_amount'], 0) ?></div>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-light border action-btn" style="width:38px; height:38px; border-radius:50%;" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
    <!-- প্রিন্ট -->
    <li><a class="dropdown-item py-2 fw-bold" href="../sr/print_invoice.php?id=<?= (int)$s['id'] ?>" target="_blank">
        <i class="fas fa-print me-2 text-primary"></i> প্রিন্ট ইনভয়েস</a></li>
    
    <!-- এডিট -->
    <li><a class="dropdown-item py-2 fw-bold" href="edit_sale.php?id=<?= (int)$s['id'] ?>">
        <i class="fas fa-edit me-2 text-warning"></i> এডিট করুন</a></li>
    
    <li><hr class="dropdown-divider"></li>
    
    <!-- ডিলিট -->
    <li><a class="dropdown-item py-2 fw-bold text-danger" href="delete_sale.php?id=<?= (int)$s['id'] ?>" 
        onclick="return confirm('আপনি কি নিশ্চিত? ডিলিট করলে সকল প্রোডাক্টের স্টক পুনরায় গোডাউনে যোগ হবে।')">
        <i class="fas fa-trash-alt me-2 fw-bold text-danger"></i> ডিলিট করুন</a></li>
</ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">কোনো রেকর্ড পাওয়া যায়নি।</td></tr>
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