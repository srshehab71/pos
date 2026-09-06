<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// ১. ডিলিট লজিক (অপরিবর্তিত)
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $conn->beginTransaction();
    try {
        $items_stmt = $conn->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
        $items_stmt->execute([$del_id]);
        $items = $items_stmt->fetchAll();
        foreach ($items as $item) {
            if ($item['received_qty'] > 0) {
                $table = ($item['item_type'] == 'single') ? 'products' : 'product_variants';
                $conn->prepare("UPDATE $table SET stock_qty = stock_qty - ? WHERE id = ?")
                     ->execute([$item['received_qty'], $item['product_id']]);
            }
        }
        $conn->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$del_id]);
        $conn->prepare("DELETE FROM purchases WHERE id = ? AND godown_id = ?")->execute([$del_id, $gid]);
        $conn->commit();
        header("Location: purchase_list.php?deleted=1"); exit();
    } catch (Exception $e) { $conn->rollBack(); die("ভুল হয়েছে: " . $e->getMessage()); }
}

// ২. ফিল্টার প্যারামিটার রিসিভ
$search = $_GET['search'] ?? '';
$supplier_id = $_GET['supplier_id'] ?? '';
$range = $_GET['range'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// --- ডিফল্ট লজিক: যদি কোনো ফিল্টার না থাকে, তবে 'today' সেট হবে ---
if (empty($search) && empty($range) && empty($start_date) && empty($supplier_id)) {
    $range = 'today';
}

// ৩. ড্রপডাউনের জন্য সাপ্লায়ার লিস্ট
$supp_stmt = $conn->prepare("SELECT id, supplier_name, company_name FROM suppliers WHERE godown_id = ?");
$supp_stmt->execute([$gid]);
$suppliers = $supp_stmt->fetchAll();

// ৪. ডাইনামিক কুয়েরি বিল্ড করা
$where_clauses = ["p.godown_id = ?"];
$params = [$gid];

if ($search) {
    $where_clauses[] = "(p.chalan_no LIKE ? OR s.supplier_name LIKE ? OR s.company_name LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

if ($supplier_id) {
    $where_clauses[] = "p.supplier_id = ?";
    $params[] = $supplier_id;
}

// ডেট রেঞ্জ লজিক
if ($range == 'today') {
    $where_clauses[] = "DATE(p.created_at) = CURDATE()";
} elseif ($range == 'week') {
    $where_clauses[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($range == 'month') {
    $where_clauses[] = "MONTH(p.created_at) = MONTH(NOW()) AND YEAR(p.created_at) = YEAR(NOW())";
} elseif ($range == 'year') {
    $where_clauses[] = "YEAR(p.created_at) = YEAR(NOW())";
} elseif ($start_date && $end_date) {
    $where_clauses[] = "DATE(p.created_at) BETWEEN ? AND ?";
    $params[] = $start_date; $params[] = $end_date;
}

$where_sql = implode(" AND ", $where_clauses);

// ৫. সামারি ডাটা
$sum_stmt = $conn->prepare("SELECT SUM(p.total_amount) as total, SUM(p.paid_amount) as paid, SUM(p.due_amount) as due 
                            FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE $where_sql");
$sum_stmt->execute($params);
$summary = $sum_stmt->fetch();

// ৬. মেইন লিস্ট ফেচ করা
$stmt = $conn->prepare("SELECT p.*, s.supplier_name, s.company_name FROM purchases p 
                        JOIN suppliers s ON p.supplier_id = s.id 
                        WHERE $where_sql ORDER BY p.id DESC");
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$page_title = "ক্রয়কৃত মালের তালিকা";
include 'includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body { font-family: 'Poppins', 'Hind Siliguri', sans-serif; background-color: #f8f9fc; }
    .premium-card { background: #fff; border-radius: 20px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
    .stat-card { border: none; border-radius: 15px; padding: 20px; color: #fff; }
    .table-modern thead th { background: #fcfdfe; color: #7f8fa4; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; padding: 15px; border: none; }
    .table-modern tbody td { padding: 15px; border-bottom: 1px solid #f1f4f8; font-size: 14px; }
    .action-btn { width: 35px; height: 35px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; transition: 0.3s; }
    .btn-view { background: #eef2ff; color: #4361ee; }
    .btn-delete { background: #fff1f2; color: #e11d48; }
    .btn-view:hover { background: #4361ee; color: #fff; }
    .btn-delete:hover { background: #e11d48; color: #fff; }
    .btn-edit { background: #fff7ed; color: #f59e0b; }
    .btn-edit:hover { background: #f59e0b; color: #fff; }
    .btn-receive { background: #ecfdf5; color: #10b981; }
    .btn-receive:hover { background: #10b981; color: #fff; }
    .custom-date-box { display: none; }
.btn-outline-primary.active {
    background-color: #1548e1 !important;
    color: white !important;
}

.fa-lg { font-size: 1.2rem !important; }
.btn-group .btn { padding: 8px 14px; font-size: 12px; }
.rounded-circle { width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; }
</style>

<!-- FILTER SECTION -->
<div class="sr-card p-3 mb-4">
    <form method="GET" id="filterForm">
        <div class="row g-2 align-items-center">
            <!-- সার্চ -->
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-premium" placeholder="চালান নং/সাপ্লায়ার..." value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
            </div>
            
            <!-- সাপ্লায়ার ড্রপডাউন -->
            <div class="col-md-3">
                <select name="supplier_id" class="form-select form-control-premium" onchange="this.form.submit()">
                    <option value="">সব সাপ্লায়ার</option>
                    <?php foreach ($suppliers as $supp): ?>
                        <option value="<?= $supp['id'] ?>" <?= ($supplier_id == $supp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($supp['supplier_name']) ?> (<?= htmlspecialchars($supp['company_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- ডেট ফিল্টার বাটনসমূহ -->
            <div class="col-md-6 d-flex gap-2 justify-content-end align-items-center">
                <div class="btn-group shadow-sm rounded-pill overflow-hidden bg-white border">
                    <a href="?range=today" class="btn btn-sm btn-outline-primary <?= $range == 'today' ? 'active' : '' ?>">আজ</a>
                    <a href="?range=week" class="btn btn-sm btn-outline-primary <?= $range == 'week' ? 'active' : '' ?>">সপ্তাহ</a>
                    <a href="?range=month" class="btn btn-sm btn-outline-primary <?= $range == 'month' ? 'active' : '' ?>">মাস</a>
                    <a href="?range=year" class="btn btn-sm btn-outline-primary <?= $range == 'year' ? 'active' : '' ?>">বছর</a>
                    <button type="button" onclick="toggleDateFilter()" class="btn btn-sm btn-outline-primary <?= ($start_date) ? 'active' : '' ?>">
                        <i class="fas fa-calendar-day fa-lg"></i>
                    </button>
                    <!-- 'সব' ডাটা দেখার জন্য একটি বাটন (ঐচ্ছিক) -->
                    <a href="purchase_list.php?range=all" class="btn btn-sm btn-outline-primary <?= $range == 'all' ? 'active' : '' ?>">সব</a>
                </div>
                
                <!-- রিসেট (রিসেট করলে আবার আজকের ডেটে ফিরে যাবে) -->
                <a href="purchase_list.php" class="btn btn-light border rounded-circle shadow-sm d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;" title="আজকের তালিকা">
                    <i class="fas fa-sync-alt text-muted fa-lg"></i>
                </a>
            </div>
        </div>

        <!-- কাস্টম ডেট ক্যালেন্ডার -->
        <div id="date-filter-box" class="mt-3 p-3 bg-light border rounded-3" style="display: <?= ($start_date) ? 'block' : 'none' ?>;">
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


    <!-- সামারি কার্ডস -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);">
                <small class="opacity-75">মোট ক্রয় (Total Purchase)</small>
                <h3 class="fw-bold mb-0">৳ <?= number_format($summary['total'] ?? 0, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);">
                <small class="opacity-75">মোট পরিশোধ (Total Paid)</small>
                <h3 class="fw-bold mb-0">৳ <?= number_format($summary['paid'] ?? 0, 2) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%);">
                <small class="opacity-75">মোট বাকী (Total Due)</small>
                <h3 class="fw-bold mb-0">৳ <?= number_format($summary['due'] ?? 0, 2) ?></h3>
            </div>
        </div>
    </div>

    <!-- পারচেস টেবিল -->
    <div class="premium-card p-4">
        <div class="table-responsive">
            <table class="table table-modern align-middle" id="purchaseTable">
                <thead>
                    <tr>
                        <th>তারিখ ও আইডি</th>
                        <th>ভাউচার/চালান</th>
                        <th>সাপ্লায়ার ও কোম্পানি</th>
                        <th>মোট বিল</th>
                        <th>পরিশোধ ও বাকী</th>
                        <th>অবস্থা (Status)</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $p): ?>
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">#<?= $p['id'] ?></div>
                            <small class="text-muted"><?= date('d M, Y h:i A', strtotime($p['created_at'])) ?></small>
                        </td>
                        <td><span class="badge bg-light text-primary border px-3 rounded-pill fw-bold"><?= $p['chalan_no'] ?></span></td>
                        <td>
                            <div class="fw-bold"><?= $p['supplier_name'] ?></div>
                            <small class="text-muted"><?= $p['company_name'] ?></small>
                        </td>
                        <td class="fw-bold text-dark">৳ <?= number_format($p['total_amount'], 2) ?></td>
                        <td>
                            <div class="text-success fw-bold small">পেইড: ৳ <?= number_format($p['paid_amount'], 2) ?></div>
                            <div class="text-danger fw-bold small">বাকী: ৳ <?= number_format($p['due_amount'], 2) ?></div>
                        </td>
<td>
    <?php 
    $status = $p['status'];
    if($status == 'Ordered' || $status == ''): ?>
        <span class="badge bg-warning text-dark">পেন্ডিং (অর্ডার)</span>
    <?php elseif($status == 'Partial'): ?>
        <span class="badge bg-info text-white">আংশিক রিসিভ</span>
    <?php elseif($status == 'Received'): ?>
        <span class="badge bg-success text-white">সম্পূর্ণ রিসিভ</span>
    <?php else: ?>
        <span class="badge bg-secondary text-white">অজানা (<?= $status ?>)</span>
    <?php endif; ?>
</td>
                        <td class="text-center">
                            <a href="receive_purchase.php?id=<?= $p['id'] ?>" class="action-btn btn-receive me-1" title="মাল রিসিভ বা সংশোধন">
        <i class="fas fa-truck-loading"></i>
    </a>

    
    <button class="action-btn btn-view" onclick="viewDetails(<?= $p['id'] ?>)" title="বিস্তারিত">
        <i class="fas fa-eye"></i>
    </button>

    <!-- এডিট বাটন (নতুন যুক্ত করা হয়েছে) -->
    <a href="edit_purchase.php?id=<?= $p['id'] ?>" class="action-btn btn-edit ms-1" title="এডিট">
        <i class="fas fa-edit"></i>
    </a>
                            <a href="purchase_list.php?delete_id=<?= $p['id'] ?>" class="action-btn btn-delete ms-1" onclick="return confirm('এটি ডিলিট করলে স্টক থেকেও মাল কমে যাবে। আপনি কি নিশ্চিত?')" title="ডিলিট">
                                <i class="fas fa-trash-alt"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- বিস্তারিত দেখার মোডাল -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="fw-bold">ক্রয়কৃত পণ্যের বিবরণ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="modal_content">
                <!-- AJAX লোড হবে এখানে -->
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script>
    function viewDetails(purchase_id) {
        $('#detailModal').modal('show');
        $.ajax({
            url: 'get_purchase_details.php',
            type: 'POST',
            data: { purchase_id: purchase_id },
            success: function(response) {
                $('#modal_content').html(response);
            }
        });
    }
</script>
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