<?php
session_start();
include '../config/db.php';

// ১. নিরাপত্তা চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php");
    exit();
}

$godown_id = $_SESSION['godown_id'];

// ২. ক্যাটাগরি লিস্ট আনা (ফিল্টারের জন্য)
$cat_stmt = $conn->prepare("SELECT * FROM categories WHERE godown_id = ?");
$cat_stmt->execute([$godown_id]);
$categories = $cat_stmt->fetchAll();

// ৩. প্রোডাক্ট ও ভেরিয়েন্ট একত্রে খোঁজা ও ফিল্টারিং লজিক (কাজের অংশ)
$cat_filter = "";
$params = [$godown_id, $godown_id]; // দুইটা কোয়েরির জন্য দুইবার গোডাউন আইডি

if (isset($_GET['cat_id']) && !empty($_GET['cat_id'])) {
    $cat_filter = " AND p.cat_id = ?";
    $params = [$godown_id, $_GET['cat_id'], $godown_id, $_GET['cat_id']];
}

// ইউনিয়ন কুয়েরি: মেইন প্রোডাক্ট এবং ভেরিয়েন্ট প্রোডাক্ট একসাথে আনা
$query = "
    (SELECT p.id, p.product_name, p.sku, p.sell_price, p.stock_qty, p.alert_qty, c.name as cat_name 
     FROM products p 
     JOIN categories c ON p.cat_id = c.id 
     WHERE p.godown_id = ? $cat_filter AND p.id NOT IN (SELECT product_id FROM product_variants))
    UNION ALL
    (SELECT v.id, CONCAT(p.product_name, ' - ', v.variant_name) AS product_name, v.sku, v.sell_price, v.stock_qty, v.alert_qty, c.name as cat_name 
     FROM product_variants v
     JOIN products p ON v.product_id = p.id
     JOIN categories c ON p.cat_id = c.id
     WHERE p.godown_id = ? $cat_filter)
    ORDER BY product_name ASC";

$p_stmt = $conn->prepare($query);
$p_stmt->execute($params);
$products = $p_stmt->fetchAll();

$page_title = "প্রোডাক্ট ও স্টক লিস্ট";
include 'includes/header.php';
?>

<style>
    /* আপনার সব CSS হুবহু রাখা হয়েছে */
    .stock-badge { width: 100px; display: inline-block; text-align: center; font-weight: bold; }
    .search-box { border-radius: 50px; padding-left: 40px; border: 2px solid #e2e8f0; }
    .search-icon { position: absolute; left: 15px; top: 10px; color: #a0aec0; }
    .product-card-mobile { display: none; } 

    @media (max-width: 768px) {
        .table-view { display: none; } 
        .product-card-mobile { display: block; } 
    }
</style>

<div class="container-fluid py-3">
    
    <!-- কুইক অ্যাকশন এবং ফিল্টার বার -->
    <div class="card border-0 shadow-sm mb-4 rounded-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-5 position-relative">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="liveSearch" class="form-control search-box" placeholder="প্রোডাক্ট নাম বা কোড লিখুন...">
                </div>
                <div class="col-md-4">
                    <form method="GET" class="d-flex">
                        <select name="cat_id" class="form-select border-2" onchange="this.form.submit()" style="border-radius: 50px;">
                            <option value="">সব ক্যাটাগরি</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_GET['cat_id']) && $_GET['cat_id'] == $c['id']) ? 'selected' : '' ?>><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-3 text-md-end">
                    <span class="badge bg-primary rounded-pill p-2 px-3">মোট: <?= count($products) ?> টি</span>
                </div>
            </div>
        </div>
    </div>

    <!-- কম্পিউটার/ট্যাব ভিউ (টেবিল) -->
<div class="table-view">
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="productTable">
                <thead class="table-primary text-white">
                    <tr>
                        <th class="ps-4 py-3 text-dark">#</th> <!-- ক্রমিক নম্বর হেডার -->
                        <th class="py-3 text-dark">প্রোডাক্ট ও কোড</th>
                        <th class="text-dark">ক্যাটাগরি</th>
                        <th class="text-dark">বিক্রয় মূল্য</th>
                        <th class="text-center text-dark">বর্তমান স্টক</th>
                        <th class="text-center text-dark">অবস্থা</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sl = 1; // ক্রমিক শুরু
                    foreach($products as $p): 
                        $status_class = ($p['stock_qty'] <= 0) ? 'bg-secondary' : (($p['stock_qty'] <= $p['alert_qty']) ? 'bg-danger' : 'bg-success');
                        $status_text = ($p['stock_qty'] <= 0) ? 'Out of Stock' : (($p['stock_qty'] <= $p['alert_qty']) ? 'Low Stock' : 'Available');
                    ?>
                    <tr class="product-row">
                        <td class="ps-4"><?= $sl++ ?></td> <!-- ক্রমিক নম্বর ডাটা -->
                        <td>
                            <h6 class="fw-bold mb-0"><?= htmlspecialchars($p['product_name']) ?></h6>
                            <small class="text-muted">SKU: <?= $p['sku'] ?></small>
                        </td>
                        <td><span class="text-secondary"><?= $p['cat_name'] ?></span></td>
                        <td><h6 class="fw-bold text-primary mb-0">৳ <?= number_format($p['sell_price'], 2) ?></h6></td>
                        <td class="text-center"><h5 class="mb-0 fw-bold"><?= $p['stock_qty'] ?></h5></td>
                        <td class="text-center"><span class="badge rounded-pill <?= $status_class ?> p-2 px-3"><?= $status_text ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- মোবাইল ভিউ (কার্ড স্টাইল) -->
<div class="product-card-mobile" id="mobileContainer">
    <?php 
    $sl_m = 1; // মোবাইল ক্রমিক শুরু
    foreach($products as $p): ?>
    <div class="card border-0 shadow-sm mb-3 rounded-4 p-3 product-row-mobile">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
                <h6 class="fw-bold mb-1"><?= $sl_m++ ?>. <?= htmlspecialchars($p['product_name']) ?></h6> <!-- নামের আগে ক্রমিক -->
                <span class="badge bg-light text-dark border small"><?= $p['cat_name'] ?></span>
            </div>
            <h6 class="fw-bold text-primary">৳ <?= number_format($p['sell_price']) ?></h6>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2 border-top pt-2">
            <small class="text-muted">SKU: <?= $p['sku'] ?></small>
            <div class="text-end">
                <span class="small text-muted me-2">স্টক:</span>
                <strong class="<?= ($p['stock_qty'] <= $p['alert_qty']) ? 'text-danger' : 'text-success' ?>"><?= $p['stock_qty'] ?> টি</strong>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

</div>

<script>
    // আপনার সব JavaScript হুবহু রাখা হয়েছে
    document.getElementById('liveSearch').addEventListener('keyup', function() {
        let value = this.value.toLowerCase();
        
        let tableRows = document.querySelectorAll('.product-row');
        tableRows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(value) ? '' : 'none';
        });

        let cardRows = document.querySelectorAll('.product-row-mobile');
        cardRows.forEach(card => {
            card.style.display = card.innerText.toLowerCase().includes(value) ? '' : 'none';
        });
    });
</script>

<?php include 'includes/footer.php'; ?>