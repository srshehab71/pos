<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

// শপের তথ্য আনা (প্রিন্ট হেডার এর জন্য)
$shop_stmt = $conn->prepare("SELECT shop_name, address, phone FROM godowns WHERE id = ?");
$shop_stmt->execute([$godown_id]);
$shop = $shop_stmt->fetch();

// ১. সকল গ্রুপ নিয়ে আসা (ড্রপডাউনের জন্য)
$groups_stmt = $conn->prepare("SELECT id, group_name FROM product_groups WHERE godown_id = ? ORDER BY group_name ASC");
$groups_stmt->execute([$godown_id]);
$all_groups = $groups_stmt->fetchAll();

// ২. সিলেক্টেড গ্রুপ আইডি ধরা
$selected_group = isset($_GET['group_id']) ? $_GET['group_id'] : '';

// ফিল্টার রেঞ্জ নির্ধারণ
$range = isset($_GET['range']) ? $_GET['range'] : 'today';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$where_sql = "";
$label = "";

if (!empty($start_date) && !empty($end_date)) {
    $where_sql = "DATE(s.created_at) BETWEEN '$start_date' AND '$end_date'";
    $label = date('d/m/Y', strtotime($start_date)) . " হতে " . date('d/m/Y', strtotime($end_date));
    $range = 'custom';
} elseif ($range == 'today') {
    $where_sql = "DATE(s.created_at) = CURDATE()";
    $label = "আজকের তারিখ: " . date('d/m/Y');
} elseif ($range == 'week') {
    $where_sql = "s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $label = date('d/m/Y', strtotime('-7 days')) . " হতে " . date('d/m/Y');
} elseif ($range == 'month') {
    $where_sql = "MONTH(s.created_at) = MONTH(CURDATE()) AND YEAR(s.created_at) = YEAR(CURDATE())";
    $label = date('01/m/Y') . " হতে " . date('t/m/Y');
} elseif ($range == 'year') {
    $where_sql = "YEAR(s.created_at) = YEAR(CURDATE())";
    $label = date('01/01/Y') . " হতে " . date('31/12/Y');
}

// গ্রুপের ফিল্টার কুয়েরিতে যোগ করা
if (!empty($selected_group)) {
    $where_sql .= " AND p.group_id = " . intval($selected_group);
}

try {
    $query = "SELECT p.product_name, SUM(si.qty) as total_qty, SUM(si.subtotal) as total_amount 
              FROM sale_items si
              JOIN sales s ON si.sale_id = s.id
              JOIN products p ON si.product_id = p.id
              WHERE s.godown_id = ? AND $where_sql
              GROUP BY si.product_id 
              ORDER BY total_qty DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$godown_id]);
    $summary_data = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$grand_total_qty = 0;
$grand_total_amount = 0;
foreach($summary_data as $row) {
    $grand_total_qty += $row['total_qty'];
    $grand_total_amount += $row['total_amount'];
}

$page_title = "বিক্রয় সামারি";
include 'includes/header.php'; 
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap');

    :root { --font-main: 'Hind Siliguri', sans-serif; }
    body { font-family: var(--font-main); }
    
    /* স্ক্রিন স্টাইল */
    .summary-row { display: flex; flex-wrap: nowrap; gap: 10px; }
    .summary-card { flex: 1; min-width: 0; }
    .privacy-active .hide-price { display: none !important; }
    .privacy-active .star-price { display: inline !important; }
    .star-price { display: none; }
    .custom-date-box { display: none; }
    #thermal-receipt { display: none; } /* স্ক্রিনে সব সময় হাইড থাকবে */

    /* ===================================================
       PRINT CSS (Final Perfect Fix)
    =================================================== */
    @media print {
        /* ১. স্ক্রিনের সব এলিমেন্ট (হেডার, ফুটার, মেইন কন্টেইনার) হাইড করুন */
        body > * { display: none !important; }
        
        /* ২. শুধুমাত্র থার্মাল রিসিট দেখান */
        #thermal-receipt {
            display: block !important;
            position: absolute;
            top: 0;
            left: 0;
            width: 80mm !important;
            margin: 0 !important;
            padding: 2mm !important;
            background: #fff;
        }

        /* ৩. রিসিটের ভেতরের সব কন্টেন্ট যেন দেখা যায় */
        #thermal-receipt * { display: block; visibility: visible !important; }
        
        /* ৪. টেবিল ফরম্যাটিং ঠিক করা */
        #thermal-receipt table { display: table !important; width: 100% !important; border-collapse: collapse !important; table-layout: fixed !important; }
        #thermal-receipt thead { display: table-header-group !important; }
        #thermal-receipt tbody { display: table-row-group !important; }
        #thermal-receipt tr { display: table-row !important; border-bottom: 0.5px solid #ccc !important; }
        #thermal-receipt th, #thermal-receipt td { display: table-cell !important; padding: 4px 0 !important; vertical-align: middle; word-wrap: break-word; }

        /* ৫. প্রাইভেসি অনুযায়ী প্রিন্ট (দাম অথবা স্টার) */
        body.privacy-active .hide-price { display: none !important; }
        body.privacy-active .star-price { display: inline !important; }
        body:not(.privacy-active) .star-price { display: none !important; }
        body:not(.privacy-active) .hide-price { display: inline !important; }

        @page { size: 80mm auto; margin: 0; }
    }
</style>

<div class="container-fluid py-4">

    <!-- প্রিন্টেবল হেডার (শুধুমাত্র প্রিন্ট করার সময় দেখা যাবে) -->
    <div class="d-none d-print-block text-center mb-4 border-bottom pb-2">
        <h2 class="fw-bold mb-1"><?= $shop['shop_name'] ?></h2>
        <p class="mb-0 small"><?= $shop['address'] ?> | মোবাইল: <?= $shop['phone'] ?></p>
        <h5 class="mt-2 text-decoration-underline">বিক্রয় সামারি রিপোর্ট</h5>
        <p class="fw-bold small">সময়কাল: <?= $label ?></p>
    </div>

    <!-- স্ক্রিন হেডার (বাটন এবং টাইটেল) -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 d-print-none">
        <div>
            <h4 class="fw-bold mb-0 text-primary"><i class="fas fa-chart-line me-2"></i> বিক্রয় সামারি</h4>
            <span class="text-muted small fw-bold"><i class="far fa-calendar-alt me-1"></i> <?= $label ?></span>
        </div>
                    <!-- গ্রুপ ফিল্টার ড্রপডাউন -->
<form action="" method="GET" class="d-print-none">
    <input type="hidden" name="range" value="<?= $range ?>">
    <input type="hidden" name="start_date" value="<?= $start_date ?>">
    <input type="hidden" name="end_date" value="<?= $end_date ?>">
    <select name="group_id" class="form-select form-select-sm rounded-pill shadow-sm" onchange="this.form.submit()" style="min-width: 140px;">
        <option value="">-- সব গ্রুপ --</option>
        <?php foreach($all_groups as $group): ?>
            <option value="<?= $group['id'] ?>" <?= $selected_group == $group['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($group['group_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

        <div class="d-flex gap-2 align-items-center">
            
            <!-- প্রিন্ট বাটন -->
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-3 shadow-sm print-btn">
                <i class="fas fa-print"></i>
            </button>
            <!-- প্রাইভেসি বাটন -->
            <button onclick="togglePrivacy()" id="privacy-btn" class="btn btn-dark rounded-pill shadow-sm">
                <i class="fas fa-eye-slash" id="privacy-icon"></i>
            </button>
            <!-- ফিল্টার গ্রুপ -->
            <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white">
                <a href="?range=today" class="btn btn-sm btn-outline-primary <?= $range == 'today' ? 'active' : '' ?>">আজ</a>
                <a href="?range=week" class="btn btn-sm btn-outline-primary <?= $range == 'week' ? 'active' : '' ?>">সপ্তাহ</a>
                <a href="?range=month" class="btn btn-sm btn-outline-primary <?= $range == 'month' ? 'active' : '' ?>">মাস</a>
                <a href="?range=year" class="btn btn-sm btn-outline-primary <?= $range == 'year' ? 'active' : '' ?>">বছর</a>
                <button onclick="toggleDateFilter()" class="btn btn-sm btn-outline-primary <?= $range == 'custom' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-day"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- কাস্টম ডেট ফিল্টার বক্স -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 custom-date-box d-print-none" id="date-filter-box" style="<?= $range == 'custom' ? 'display:block' : '' ?>">
        <div class="card-body p-3">
            <form action="" method="GET" class="row g-2 align-items-end">
                <div class="col-5">
                    <label class="small fw-bold">হতে</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= $start_date ?>" required>
                </div>
                <div class="col-5">
                    <label class="small fw-bold">পর্যন্ত</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= $end_date ?>" required>
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-search"></i></button>
                </div>
            </form>
        </div>
    </div>

    <!-- টোটাল হাইলাইট কার্ডস (মোবাইল ও পিসিতে সবসময় পাশাপাশি) -->
    <div class="summary-row mb-4">
        <div class="summary-card">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-primary">
                <p class="text-muted small mb-1 fw-bold">মোট পরিমাণ</p>
                <h3 class="fw-bold text-dark mb-0">
                    <?= number_format($grand_total_qty, 0) ?> 
                    <small class="fs-6 text-muted">পিস</small>
                </h3>
            </div>
        </div>
        <div class="summary-card">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100 border-start border-4 border-success">
                <p class="text-muted small mb-1 fw-bold">মোট বিক্রি</p>
                <h3 class="fw-bold text-success mb-0">
                    <span class="hide-price">৳ <?= number_format($grand_total_amount, 2) ?></span>
                    <span class="star-price">****</span>
                </h3>
            </div>
        </div>
    </div>

    <!-- মেইন টেবিল -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr class="small text-secondary">
                            <th class="ps-4 py-3">প্রোডাক্টের নাম</th>
                            <th class="text-center">পরিমাণ</th>
                            <th class="text-end pe-4">মোট টাকা</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($summary_data) > 0): ?>
                            <?php foreach ($summary_data as $item): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-secondary"><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="text-center fw-bold">
                                        <?= number_format($item['total_qty'], 0) ?>
                                    </td>
                                    <td class="text-end pe-4 fw-bold">
                                        <span class="hide-price text-dark">৳ <?= number_format($item['total_amount'], 2) ?></span>
                                        <span class="star-price">****</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center py-5 text-muted">কোনো বিক্রয় তথ্য পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-light fw-bold border-top">
                            <td class="ps-4">সর্বমোট সামারি</td>
                            <td class="text-center"><?= number_format($grand_total_qty, 0) ?></td>
                            <td class="text-end pe-4 text-primary">
                                <span class="hide-price">৳ <?= number_format($grand_total_amount, 2) ?></span>
                                <span class="star-price">****</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- প্রিন্ট ফুটার (শুধুমাত্র প্রিন্ট এ দেখা যাবে) -->
    <div class="d-none d-print-block mt-5">
        <div class="d-flex justify-content-between">
            <div class="text-center">
                <hr style="width: 150px;">
                <p>ম্যানেজার স্বাক্ষর</p>
            </div>
            <div class="text-center">
                <hr style="width: 150px;">
                <p>স্বত্ত্বাধিকারী স্বাক্ষর</p>
            </div>
        </div>
        <p class="text-center small mt-4">রিপোর্ট তৈরির সময়: <?= date('d/m/Y h:i A') ?></p>
    </div>
</div>

<script>
    function toggleDateFilter() {
        let box = document.getElementById('date-filter-box');
        box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'block' : 'none';
    }

    function togglePrivacy() {
        document.body.classList.toggle('privacy-active');
        let icon = document.getElementById('privacy-icon');
        let isHidden = document.body.classList.contains('privacy-active');
        icon.classList.toggle('fa-eye-slash', !isHidden);
        icon.classList.toggle('fa-eye', isHidden);
        localStorage.setItem('sales_privacy_only_price', isHidden ? 'on' : 'off');
    }

    window.onload = function() {
        if (localStorage.getItem('sales_privacy_only_price') === 'on') {
            togglePrivacy();
        }
    }
</script>


<!-- থার্মাল প্রিন্ট এরিয়া (শুধুমাত্র প্রিন্টে আসবে) -->
</div> <!-- container-fluid শেষ হওয়ার পর -->

    <!-- থার্মাল প্রিন্ট এরিয়া (এটি এখন কন্টেইনারের বাইরে) -->
    <div id="thermal-receipt">
        <div style="text-align: center;">
            <h2 style="margin: 0; font-size: 18px; font-weight: 700;"><?= $shop['shop_name'] ?></h2>
            <p style="margin: 2px 0; font-size: 11px;"><?= $shop['address'] ?></p>
            <p style="margin: 2px 0; font-size: 11px;">মোবাইল: <?= $shop['phone'] ?></p>
            
            <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; margin: 8px 0; padding: 4px 0;">
                <strong style="font-size: 13px;">বিক্রয় সামারি রিপোর্ট</strong><br>
                <strong><span style="font-size: 10px;">সময়কাল: <?= $label ?></span></strong>
            </div>
        </div>

<?php if(!empty($selected_group)): ?>
    <?php 
        $g_name = ""; 
        foreach($all_groups as $g) if($g['id'] == $selected_group) $g_name = $g['group_name'];
    ?>
    <strong><span style="font-size: 10px;">গ্রুপ: <?= $g_name ?></span></strong><br>
<?php endif; ?>

        <table>
            <thead>
                <tr style="border-bottom: 1px solid #000;">
                    <th style="text-align: left; width: 50%;">আইটেম</th>
                    <th style="text-align: center; width: 20%;">পরিমাণ</th>
                    <th style="text-align: right; width: 30%;">টাকা</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summary_data as $item): ?>
                <tr>
                    <td style="text-align: left; font-size: 12px;"><strong><?= htmlspecialchars($item['product_name']) ?></strong></td>
                    <td style="text-align: center; font-size: 12px;"><strong><?= number_format($item['total_qty'], 0) ?></strong></td>
                    <td style="text-align: right; font-size: 12px;">
                        <strong><span class="hide-price"><?= number_format($item['total_amount'], 2) ?></span></strong>
                        <span class="star-price">****</span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 10px; font-size: 13px; border-top: 1px solid #000; padding-top: 5px;">
            <div style="display: flex; justify-content: space-between;">
                <strong><span>মোট আইটেম:</span></strong>
                <strong><?= number_format($grand_total_qty, 0) ?> পিস</strong>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-top: 4px; font-weight: bold;">
                <span>সর্বমোট বিক্রি:</span>
                <span>
                    <span class="hide-price">৳ <?= number_format($grand_total_amount, 2) ?></span>
                    <span class="star-price">****</span>
                </span>
            </div>
        </div>

        <div style="text-align: center; margin-top: 15px; font-size: 9px; color: #555;">
            <strong><p style="margin: 0;">রিপোর্ট তৈরির সময়: <?= date('d/m/Y h:i A') ?></p></strong>
            <strong style="display: block; margin-top: 3px;">Software by: StockPro System</strong>
        </div>
    </div>


<?php include 'includes/footer.php'; ?>