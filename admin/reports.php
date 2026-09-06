<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// --- ১. তারিখ প্রসেস করার লজিক (অক্ষত রাখা হয়েছে) ---
$range = $_GET['range'] ?? '';
$s_inp = $_GET['start_date'] ?? '';
$e_inp = $_GET['end_date'] ?? '';

if ($range == '' && $s_inp == '') {
    $range = 'today'; // ডিফল্ট আজকের সেলস দেখাবে
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
} else {
    $start_date = $s_inp;
    $end_date = $e_inp;
}

// --- ২. সামারি স্ট্যাটাস (আপনার লজিক) ---
$summary_stmt = $conn->prepare("SELECT 
    SUM(total_amount) as gross_sales,
    SUM(payable_amount) as net_sales,
    SUM(paid_amount) as total_paid,
    SUM(due_amount) as total_due
    FROM sales 
    WHERE godown_id = ? AND DATE(created_at) BETWEEN ? AND ?");
$summary_stmt->execute([$gid, $start_date, $end_date]);
$summary = $summary_stmt->fetch();

// --- ৩. নিট লাভের হিসাব (আপনার ভেরিয়েন্টসহ লজিক) ---
$profit_stmt = $conn->prepare("SELECT 
    SUM(
        (si.unit_price - 
            CASE 
                WHEN si.variant_id IS NOT NULL AND si.variant_id > 0 THEN pv.purchase_price 
                ELSE p.purchase_price 
            END
        ) * si.qty
    ) as total_profit
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN product_variants pv ON si.variant_id = pv.id
    WHERE s.godown_id = ? AND DATE(s.created_at) BETWEEN ? AND ?");
$profit_stmt->execute([$gid, $start_date, $end_date]);
$profit_data = $profit_stmt->fetch();
$total_profit = $profit_data['total_profit'] ?? 0;

// --- ৪. এসআর পারফরম্যান্স (আপনার লজিক) ---
$sr_stmt = $conn->prepare("SELECT users.name, SUM(sales.payable_amount) as total_sold
    FROM sales 
    JOIN users ON sales.user_id = users.id
    WHERE sales.godown_id = ? AND DATE(sales.created_at) BETWEEN ? AND ?
    GROUP BY sales.user_id 
    ORDER BY total_sold DESC");
$sr_stmt->execute([$gid, $start_date, $end_date]);
$sr_performance = $sr_stmt->fetchAll();

// --- ৫. টপ ৫ বিক্রিত প্রোডাক্ট (আপনার লজিক) ---
$top_p_stmt = $conn->prepare("SELECT 
    CASE 
        WHEN si.variant_id IS NOT NULL AND si.variant_id > 0 THEN CONCAT(p.product_name, ' - ', pv.variant_name)
        ELSE p.product_name 
    END as display_name, 
    SUM(si.qty) as total_qty
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN product_variants pv ON si.variant_id = pv.id
    WHERE s.godown_id = ? AND DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY si.product_id, si.variant_id 
    ORDER BY total_qty DESC LIMIT 5");
$top_p_stmt->execute([$gid, $start_date, $end_date]);
$top_products = $top_p_stmt->fetchAll();

$page_title = "মাস্টার বিজনেস রিপোর্ট";
include 'includes/header.php';
?>

<style>
/* আইকন ও পিল ডিজাইনের CSS */
.custom-date-box { display: none; }
.btn-outline-primary.active {
    background-color: #1548e1 !important;
    color: white !important;
}

.fa-lg { font-size: 1.2rem !important; }
.btn-group .btn { padding: 8px 8px; font-size: 12px; }
.rounded-circle { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
.stat-card { background: white; padding: 20px; border-radius: 15px; }
@media print { .d-print-none { display: none; } }
</style>

<div class="container-fluid py-4">
    <!-- তারিখ ফিল্টার সেকশন -->
    <div class="stat-card mb-4 shadow-sm d-print-none">
        <form method="GET" id="filterForm">
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <h5 class="fw-bold mb-0 text-dark">মাস্টার বিজনেস রিপোর্ট</h5>
                    <small class="text-muted">সময়কাল: <?= date('d M', strtotime($start_date)) ?> - <?= date('d M, Y', strtotime($end_date)) ?></small>
                </div>
                
                <div class="col-md-8 d-flex gap-2 justify-content-end align-items-center">
                    <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white border">
                        <a href="?range=today" class="btn btn-sm btn-outline-primary <?= $range == 'today' ? 'active' : '' ?>">আজ</a>
                        <a href="?range=week" class="btn btn-sm btn-outline-primary <?= $range == 'week' ? 'active' : '' ?>">সপ্তাহ</a>
                        <a href="?range=month" class="btn btn-sm btn-outline-primary <?= $range == 'month' ? 'active' : '' ?>">মাস</a>
                        <a href="?range=year" class="btn btn-sm btn-outline-primary <?= $range == 'year' ? 'active' : '' ?>">বছর</a>
                        <button type="button" onclick="toggleDateFilter()" class="btn btn-sm btn-outline-primary <?= ($range == '' && $s_inp != '') ? 'active' : '' ?>">
                            <i class="fas fa-calendar-day fa-lg"></i>
                        </button>
                    </div>
                    
                    <a href="reports.php" class="btn btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;" title="রিসেট">
                        <i class="fas fa-sync-alt text-muted fa-lg"></i>
                    </a>
                    
                    <button type="button" onclick="window.print()" class="btn btn-primary rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                        <i class="fas fa-print fa-lg"></i>
                    </button>
                </div>
            </div>

            <!-- কাস্টম ডেট ক্যালেন্ডার বক্স -->
            <div id="date-filter-box" class="mt-3 p-3 bg-light border rounded-3" style="display: <?= ($range == '' && $s_inp != '') ? 'block' : 'none' ?>;">
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
                        <button type="submit" class="btn btn-primary w-100 fw-bold">ঠিক আছে</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- সামারি কার্ডস (আপনার ডিজাইন) -->
    <div class="row g-4 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card bg-primary text-white border-0 shadow">
                <small class="opacity-75">মোট বিক্রি (Net Sales)</small>
                <h3 class="fw-bold m-0">৳ <?= number_format($summary['net_sales'] ?? 0, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card bg-success text-white border-0 shadow">
                <small class="opacity-75">নিট লাভ (Profit)</small>
                <h3 class="fw-bold m-0">৳ <?= number_format($total_profit, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card bg-info text-white border-0 shadow">
                <small class="opacity-75">নগদ আদায় (Cash)</small>
                <h3 class="fw-bold m-0">৳ <?= number_format($summary['total_paid'] ?? 0, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card bg-danger text-white border-0 shadow">
                <small class="opacity-75">মোট বাকী (Due)</small>
                <h3 class="fw-bold m-0">৳ <?= number_format($summary['total_due'] ?? 0, 2) ?></h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- এসআর রিপোর্ট (আপনার লজিক) -->
        <div class="col-md-6">
            <div class="stat-card h-100 shadow-sm">
                <h6 class="fw-bold mb-4 text-primary"><i class="fas fa-user-tie me-2"></i> এসআর ভিত্তিক রিপোর্ট</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>এসআর এর নাম</th><th class="text-end">মোট বিক্রি</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($sr_performance as $sr): ?>
                            <tr>
                                <td class="fw-bold"><?= $sr['name'] ?></td>
                                <td class="text-end text-primary fw-bold">৳ <?= number_format($sr['total_sold'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- টপ প্রোডাক্ট রিপোর্ট (আপনার লজিক) -->
        <div class="col-md-6">
            <div class="stat-card h-100 shadow-sm">
                <h6 class="fw-bold mb-4 text-success"><i class="fas fa-chart-pie me-2"></i> টপ বিক্রিত ৫টি প্রোডাক্ট</h6>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>প্রোডাক্টের নাম</th><th class="text-center">পরিমাণ</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($top_products as $tp): ?>
                            <tr>
                                <td class="fw-bold"><?= $tp['display_name'] ?></td>
                                <td class="text-center"><span class="badge bg-success rounded-pill"><?= $tp['total_qty'] ?> টি</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleDateFilter() {
    var x = document.getElementById("date-filter-box");
    x.style.display = (x.style.display === "none" || x.style.display === "") ? "block" : "none";
}
</script>

<?php include 'includes/footer.php'; ?>