<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

// ১. সেভ লজিক (এক্সপায়ারি ডেট সহ)
if (isset($_POST['save_product'])) {
    $product_id = $_POST['product_id'];
    $name = $_POST['product_name'];
    $cat_id = $_POST['cat_id'];
    $group_id = $_POST['group_id'];
    $sku = $_POST['sku'];
    $p_type = $_POST['product_type']; 
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    $p_price = ($p_type == 'single') ? $_POST['purchase_price'] : 0;
    $s_price = ($p_type == 'single') ? $_POST['sell_price'] : 0;
    $qty = ($p_type == 'single') ? $_POST['stock_qty'] : 0;
    $alert = ($p_type == 'single') ? $_POST['alert_qty'] : 5;

    if (!empty($product_id)) {
        $stmt = $conn->prepare("UPDATE products SET cat_id=?, group_id=?, product_name=?, sku=?, purchase_price=?, sell_price=?, stock_qty=?, alert_qty=?, expiry_date=? WHERE id=? AND godown_id=?");
        $stmt->execute([$cat_id, $group_id, $name, $sku, $p_price, $s_price, $qty, $alert, $expiry_date, $product_id, $godown_id]);
    } else {
        $stmt = $conn->prepare("INSERT INTO products (godown_id, cat_id, group_id, product_name, sku, purchase_price, sell_price, stock_qty, alert_qty, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$godown_id, $cat_id, $group_id, $name, $sku, $p_price, $s_price, $qty, $alert, $expiry_date]);
        $product_id = $conn->lastInsertId();
    }

    $conn->prepare("DELETE FROM product_variants WHERE product_id=?")->execute([$product_id]);
    if ($p_type == 'variant' && isset($_POST['variant_names'])) {
        foreach ($_POST['variant_names'] as $i => $v_name) {
            if (!empty($v_name)) {
                $v_sku_input = $_POST['v_skus'][$i];
                $final_v_sku = empty($v_sku_input) ? ($name . "-" . ($i + 1)) : ($name . "-" . $v_sku_input);
                $v_expiry = !empty($_POST['v_expiry_dates'][$i]) ? $_POST['v_expiry_dates'][$i] : null;

                $v_stmt = $conn->prepare("INSERT INTO product_variants (product_id, variant_name, sku, purchase_price, sell_price, stock_qty, alert_qty, expiry_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $v_stmt->execute([$product_id, $v_name, $final_v_sku, $_POST['v_purchase_prices'][$i], $_POST['v_sell_prices'][$i], $_POST['v_stock_qtys'][$i], $_POST['v_alert_qtys'][$i], $v_expiry]);
            }
        }
    }
    header("Location: products.php?success=1"); exit();
}

// ডিলিট লজিক
if (isset($_GET['delete'])) {
    $conn->prepare("DELETE FROM products WHERE id=? AND godown_id=?")->execute([$_GET['delete'], $godown_id]);
    header("Location: products.php?deleted=1"); exit();
}

// ১. সিলেক্টেড গ্রুপ আইডি ধরা (এটি যোগ করুন)
$selected_group = isset($_GET['group_id']) ? $_GET['group_id'] : '';

// ডাটা ফেচিং শুরু
$edit_mode = false;
$edit_data = ['id'=>'','product_name'=>'','cat_id'=>'','group_id'=>'','sku'=>'','purchase_price'=>'0','sell_price'=>'0','stock_qty'=>'0','alert_qty'=>'5', 'expiry_date'=>''];
$variants_data = [];

if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id=? AND godown_id=?");
    $stmt->execute([$_GET['edit'], $godown_id]);
    $res = $stmt->fetch();
    if ($res) { 
        $edit_mode = true; $edit_data = $res; 
        $v_stmt = $conn->prepare("SELECT * FROM product_variants WHERE product_id = ?");
        $v_stmt->execute([$res['id']]); $variants_data = $v_stmt->fetchAll();
    }
}

// ক্যাটাগরি এবং সব গ্রুপ লোড করা
$all_cats = $conn->query("SELECT * FROM categories WHERE godown_id = $godown_id")->fetchAll();
$all_groups = $conn->query("SELECT * FROM product_groups WHERE godown_id = $godown_id")->fetchAll();

// --- প্রধান কুয়েরি পরিবর্তন (ফিল্টার সহ) ---
$sql = "SELECT p.*, c.name as cat_name, g.group_name FROM products p 
        LEFT JOIN categories c ON p.cat_id = c.id 
        LEFT JOIN product_groups g ON p.group_id = g.id 
        WHERE p.godown_id = ?";

$params = [$godown_id];

// যদি গ্রুপ সিলেক্ট করা থাকে তবে কুয়েরিতে যোগ হবে
if (!empty($selected_group)) {
    $sql .= " AND p.group_id = ?";
    $params[] = $selected_group;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

include 'includes/header.php'; 
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
    body { font-family: 'Poppins', 'Hind Siliguri', sans-serif; background-color: #f8f9fc; color: #444; letter-spacing: 0.3px; }
    .premium-card { background: #fff; border-radius: 24px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.03); }
    .table-modern thead th { background: #fcfdfe; color: #7f8fa4; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border: none; padding: 20px 15px; }
    .table-modern tbody tr { transition: all 0.3s; }
    .table-modern tbody tr:hover { background-color: #f8faff; }
    .table-modern td { padding: 18px 15px; border-bottom: 1px solid #f1f4f8; }
    .sl-badge { width: 32px; height: 32px; background: #eff2f7; color: #5a6d90; display: flex; align-items: center; justify-content: center; border-radius: 10px; font-weight: 700; font-size: 13px; }
    .badge-soft { border-radius: 8px; padding: 4px 10px; font-weight: 600; font-size: 11px; }
    .badge-soft-primary { background: #eef2ff; color: #4361ee; }
    .badge-soft-success { background: #ecfdf5; color: #10b981; }
    .inv-value { font-size: 13px; font-weight: 600; color: #1e293b; }
    .margin-text { font-size: 11px; color: #64748b; margin-top: 2px; }
    .action-btn { width: 36px; height: 36px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: 0.2s; }
    .btn-edit { background: #f0f3ff; color: #4361ee; }
    .btn-delete { background: #fff1f2; color: #e11d48; }
    .btn-edit:hover { background: #4361ee; color: #fff; transform: scale(1.1); }
    .btn-delete:hover { background: #e11d48; color: #fff; transform: scale(1.1); }
    .type-card { border: 2px solid #f1f4f8; border-radius: 20px; cursor: pointer; transition: 0.3s; overflow: hidden; position: relative; }
    .type-input:checked + .type-card { border-color: #4361ee; background: #f0f3ff; }
</style>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">

<div class="col-md-6">
    <h3 class="fw-bold text-dark mb-1">প্রোডাক্ট লিস্ট</h3>
    <p class="text-muted small">আপনার স্টকের তথ্যবহুল চিত্র</p>
    
    <!-- গ্রুপ ফিল্টার ড্রপডাউন -->
    <form action="" method="GET" class="d-print-none">
        <select name="group_id" class="form-select form-select-sm rounded-pill shadow-sm" onchange="this.form.submit()" style="min-width: 140px;">
            <option value="">-- সব গ্রুপ --</option>
            <?php foreach($all_groups as $group): ?>
                <option value="<?= $group['id'] ?>" <?= $selected_group == $group['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($group['group_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

                    <span class="badge bg-soft-primary text-primary">মোট প্রোডাক্ট <?= count($products) ?> টি</span>

</div>

        <div class="col-md-6 text-end">
            <button class="btn btn-primary px-4 py-2 rounded-pill shadow fw-bold" data-bs-toggle="modal" data-bs-target="#productModal">+ নতুন পণ্য যোগ</button>
        </div>
    </div>

    <div class="premium-card p-4">
        <div class="table-responsive">
            <table class="table table-modern" id="mainTable">
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>পণ্য বিবরণ</th>
                        <th>ক্যাটাগরি ও গ্রুপ</th>
                        <th>ক্রয় ও বিক্রয় মূল্য</th>
                        <th class="text-center">স্টক ও ভ্যালু</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
    <?php $sl = 1; foreach($products as $p): 

// --- ১. মেইন প্রোডাক্টের ব্যাচ কুয়েরি (sell_price সহ) ---
$batch_stmt = $conn->prepare("SELECT pi.id, pi.remaining_qty, pi.purchase_price, pi.sell_price, p.chalan_no 
                             FROM purchase_items pi 
                             LEFT JOIN purchases p ON pi.purchase_id = p.id 
                             WHERE pi.product_id = ? AND pi.item_type = 'single' AND pi.remaining_qty > 0 
                             ORDER BY pi.id ASC");
$batch_stmt->execute([$p['id']]);
$p_batches = $batch_stmt->fetchAll();

        $v_stmt = $conn->prepare("SELECT * FROM product_variants WHERE product_id = ?");
        $v_stmt->execute([$p['id']]);
        $variants = $v_stmt->fetchAll();
        $has_variants = count($variants) > 0;

        $total_stock = 0; $total_value = 0;
        if($has_variants){
            foreach($variants as $v){ $total_stock += $v['stock_qty']; $total_value += ($v['purchase_price'] * $v['stock_qty']); }
        } else {
            $total_stock = $p['stock_qty']; $total_value = $p['purchase_price'] * $p['stock_qty'];
        }

        $margin = 0;
        if($p['purchase_price'] > 0) $margin = (($p['sell_price'] - $p['purchase_price']) / $p['purchase_price']) * 100;

        $grp_id = $p['id']; 
        $grp_sl = sprintf('%010d', $sl); 
        $grp_name = strtolower($p['product_name']); 
        $grp_cat = strtolower($p['cat_name']); 
        $grp_stock = sprintf('%010d', $total_stock); 
        $grp_price = sprintf('%012d', (float)($has_variants ? 0 : $p['purchase_price']) * 100); 
    ?>
    
    <tr style="background-color: #fcfdfe; border-left: 4px solid #4361ee;">
        <td class="text-center" data-sort="<?= $grp_sl ?>_<?= $grp_id ?>_0"><div class="sl-badge"><?= $sl++ ?></div></td>
        <td data-sort="<?= $grp_name ?>_<?= $grp_id ?>_0">
            <div class="fw-bold text-dark mb-0"><?= $p['product_name'] ?></div>
            <!-- ২. ব্যাচ লিস্ট ডিজাইন -->
<?php if(!$has_variants && count($p_batches) > 0): ?>
    <div class="mt-2 d-flex flex-wrap gap-1">
        <?php foreach($p_batches as $pb): ?>
            <span class="badge bg-light text-dark border shadow-sm" style="font-size: 10px;" title="চালান নং: <?= $pb['chalan_no'] ?>">
                ব্যাচ নং-<?= $pb['id'] ?> (স্টক-<?= $pb['remaining_qty'] ?> টি) - কেনা৳<?= number_format($pb['purchase_price'], 0) ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
            <small class="text-muted">SKU: <?= $p['sku'] ?: 'N/A' ?></small>
        </td>
        <td data-sort="<?= $grp_cat ?>_<?= $grp_id ?>_0">
            <span class="badge-soft badge-soft-primary d-inline-block mb-1"><?= $p['cat_name'] ?></span><br>
            <span class="text-muted small"><i class="fas fa-user-friends me-1"></i><?= $p['group_name'] ?></span>
        </td>
        <td data-sort="<?= $grp_price ?>_<?= $grp_id ?>_0">
    <?php if($has_variants): ?>
        <span class="badge-soft badge-soft-success">Variable Pricing</span>
    <?php else: 
        // রানিং ব্যাচ থেকে দাম নেওয়া
        $r_purchase = (!empty($p_batches)) ? $p_batches[0]['purchase_price'] : $p['purchase_price'];
        $r_sell = (!empty($p_batches)) ? $p_batches[0]['sell_price'] : $p['sell_price'];
        $margin = ($r_purchase > 0) ? (($r_sell - $r_purchase) / $r_purchase) * 100 : 0;
    ?>
        <div class="small fw-bold text-primary">ক্রয়: ৳<?= number_format($r_purchase, 2) ?></div>
        <div class="text-success fw-bold">বিক্রয়: ৳<?= number_format($r_sell, 2) ?></div>
        <div class="margin-text">Margin: <?= round($margin, 1) ?>%</div>
    <?php endif; ?>
</td>
        <td class="text-center" data-sort="<?= $grp_stock ?>_<?= $grp_id ?>_0">
            <div class="h5 fw-bold mb-0"><?= $total_stock ?></div>
            <div class="inv-value mt-1">৳<?= number_format($total_value, 0) ?></div>
        </td>
        <td class="text-center">
            <a href="products.php?edit=<?= $p['id'] ?>" class="action-btn btn-edit"><i class="fas fa-pencil-alt"></i></a>
            <a href="products.php?delete=<?= $p['id'] ?>" class="action-btn btn-delete ms-2" onclick="return confirm('মুছে ফেলবেন?')"><i class="fas fa-trash-alt"></i></a>
        </td>
    </tr>

    <?php if($has_variants): ?>
        <?php foreach($variants as $idx => $v): 
        // --- ৩. ভেরিয়েন্ট ব্যাচ কুয়েরি (sell_price সহ) ---
$v_batch_stmt = $conn->prepare("SELECT pi.id, pi.remaining_qty, pi.purchase_price, pi.sell_price, p.chalan_no 
                               FROM purchase_items pi 
                               LEFT JOIN purchases p ON pi.purchase_id = p.id 
                               WHERE pi.product_id = ? AND pi.item_type = 'variant' AND pi.remaining_qty > 0 
                               ORDER BY pi.id ASC");
$v_batch_stmt->execute([$v['id']]);
$v_batches = $v_batch_stmt->fetchAll();
            $v_idx = $idx + 1; 
            $v_margin = 0;
            if($v['purchase_price'] > 0) $v_margin = (($v['sell_price'] - $v['purchase_price']) / $v['purchase_price']) * 100;
        ?>
        <tr style="background-color: #fafbfd;">
            <td class="text-center" data-sort="<?= $grp_sl ?>_<?= $grp_id ?>_<?= $v_idx ?>"></td> 
            <td data-sort="<?= $grp_name ?>_<?= $grp_id ?>_<?= $v_idx ?>" style="padding-left: 35px;">
                <div class="text-dark mb-0" style="font-size: 13.5px;"><i class="fas fa-level-up-alt fa-rotate-90 me-2 text-muted"></i> <?= $v['variant_name'] ?></div>
                <!-- ৪. ভেরিয়েন্ট ব্যাচ ডিজাইন -->
<?php if(count($v_batches) > 0): ?>
    <div class="mt-1 d-flex flex-wrap gap-1">
        <?php foreach($v_batches as $vb): ?>
            <span class="badge bg-white text-muted border" style="font-size: 9px; font-weight: normal;" title="চালান নং: <?= $vb['chalan_no'] ?>">
                B#<?= $vb['id'] ?>: <?= $vb['remaining_qty'] ?> টি - ৳<?= $vb['purchase_price'] ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
                <small class="text-muted" style="font-size: 10px;">SKU: <?= $v['sku'] ?></small>
            </td>
            <td data-sort="<?= $grp_cat ?>_<?= $grp_id ?>_<?= $v_idx ?>"><span class="text-muted small"><?= $p['cat_name'] ?> | <?= $p['group_name'] ?></span></td>
            <td data-sort="<?= $grp_price ?>_<?= $grp_id ?>_<?= $v_idx ?>">
    <?php 
        // ভেরিয়েন্ট রানিং ব্যাচ থেকে দাম নেওয়া
        $vr_purchase = (!empty($v_batches)) ? $v_batches[0]['purchase_price'] : $v['purchase_price'];
        $vr_sell = (!empty($v_batches)) ? $v_batches[0]['sell_price'] : $v['sell_price'];
        $v_margin = ($vr_purchase > 0) ? (($vr_sell - $vr_purchase) / $vr_purchase) * 100 : 0;
    ?>
    <div class="small fw-bold text-primary">ক্রয়: ৳<?= number_format($vr_purchase, 2) ?></div>
    <div class="text-success fw-bold">বিক্রয়: ৳<?= number_format($vr_sell, 2) ?></div>
    <div class="margin-text">Margin: <?= round($v_margin, 1) ?>%</div>
</td>
            <td class="text-center" data-sort="<?= $grp_stock ?>_<?= $grp_id ?>_<?= $v_idx ?>">
                <div class="fw-bold mb-0" style="font-size: 13px;"><?= $v['stock_qty'] ?></div>
                <div class="inv-value mt-1" style="font-size: 11px;">৳<?= number_format($v['purchase_price'] * $v['stock_qty'], 0) ?></div>
            </td>
            <td class="text-center"></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php endforeach; ?>
</tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="productModal" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 px-4 pt-4">
                <h5 class="fw-bold">পণ্যের বিস্তারিত কনফিগারেশন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="window.location.href='products.php'"></button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="product_id" value="<?= $edit_data['id'] ?>">
                
                <div class="modal-body p-4 pt-0">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="w-100 mb-0">
                                <input type="radio" name="product_type" value="single" class="d-none type-input" <?= empty($variants_data) ? 'checked' : '' ?>>
                                <div class="type-card p-2 text-center" style="cursor: pointer;">
                                    <i class="fas fa-shopping-bag mb-1"></i><br><small><b>একক পণ্য</b></small>
                                </div>
                            </label>
                        </div>
                        <div class="col-6">
                            <label class="w-100 mb-0">
                                <input type="radio" name="product_type" value="variant" class="d-none type-input" <?= !empty($variants_data) ? 'checked' : '' ?>>
                                <div class="type-card p-2 text-center" style="cursor: pointer;">
                                    <i class="fas fa-layer-group mb-1"></i><br><small><b>ভেরিয়েন্ট পণ্য</b></small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6 mb-2"><label class="small fw-bold text-muted">পণ্যের নাম</label><input type="text" name="product_name" class="form-control form-control-sm rounded-3" value="<?= $edit_data['product_name'] ?>" required></div>
                        <div class="col-md-3 mb-2"><label class="small fw-bold text-muted">ক্যাটাগরি</label><select name="cat_id" class="form-select form-select-sm rounded-3"><?php foreach($all_cats as $c): ?><option value="<?= $c['id'] ?>" <?= $edit_data['cat_id']==$c['id'] ? 'selected' : '' ?>><?= $c['name'] ?></option><?php endforeach; ?></select></div>
                        <div class="col-md-3 mb-2"><label class="small fw-bold text-muted">মেইন SKU</label><input type="text" name="sku" class="form-control form-control-sm rounded-3" value="<?= $edit_data['sku'] ?>"></div>
                        <div class="col-md-12 mb-3"><label class="small fw-bold text-muted">গ্রুপ (SR)</label><select name="group_id" class="form-select form-select-sm rounded-3"><?php foreach($all_groups as $g): ?><option value="<?= $g['id'] ?>" <?= $edit_data['group_id']==$g['id'] ? 'selected' : '' ?>><?= $g['group_name'] ?></option><?php endforeach; ?></select></div>
                    </div>

                    <!-- সিঙ্গল প্রোডাক্ট ফিল্ডস -->
                    <div id="singleFields" class="row g-2 bg-light p-3 rounded-4 mb-3">
                        <div class="col-6 col-md-2"><label class="small fw-bold">স্টক</label><input type="number" step="any" name="stock_qty" class="form-control form-control-sm main-input" value="<?= $edit_data['stock_qty'] ?>"></div>
                        <div class="col-6 col-md-2"><label class="small fw-bold">ক্রয়</label><input type="number" step="any" name="purchase_price" class="form-control form-control-sm main-input" value="<?= $edit_data['purchase_price'] ?>"></div>
                        <div class="col-6 col-md-2"><label class="small fw-bold">বিক্রয়</label><input type="number" step="any" name="sell_price" class="form-control form-control-sm main-input" value="<?= $edit_data['sell_price'] ?>"></div>
                        <div class="col-6 col-md-3"><label class="small fw-bold text-danger">মেয়াদ (Expiry)</label><input type="date" name="expiry_date" class="form-control form-control-sm main-input" value="<?= $edit_data['expiry_date'] ?>"></div>
                        <div class="col-6 col-md-3"><label class="small fw-bold text-muted">এলার্ট</label><input type="number" step="any" name="alert_qty" class="form-control form-control-sm main-input" value="<?= $edit_data['alert_qty'] ?>"></div>
                    </div>

                    <div id="variantFields" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0" style="font-size: 14px;">ভেরিয়েন্ট ডিটেইলস</h6>
                            <button type="button" id="addV" class="btn btn-sm btn-dark rounded-pill px-3">+ অ্যাড</button>
                        </div>
                        <div id="vContainer" style="max-height: 250px; overflow-y: auto; overflow-x: hidden; padding: 5px;">
                            <?php foreach($variants_data as $v): ?>
                            <div class="row g-2 mb-2 p-2 bg-white rounded-3 shadow-sm border">
                                <div class="col-4"><label class="small text-muted" style="font-size: 10px;">নাম</label><input type="text" name="variant_names[]" class="form-control form-control-sm" value="<?= $v['variant_name'] ?>" required></div>
                                <div class="col-4"><label class="small text-muted" style="font-size: 10px;">SKU</label><input type="text" name="v_skus[]" class="form-control form-control-sm" value="<?= str_replace($edit_data['product_name'].'-', '', $v['sku']) ?>"></div>
                                <div class="col-4"><label class="small text-muted" style="font-size: 10px; color:red !important;">মেয়াদ (Expiry)</label><input type="date" name="v_expiry_dates[]" class="form-control form-control-sm" value="<?= $v['expiry_date'] ?>"></div>
                                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">ক্রয়</label><input type="number" step="any" name="v_purchase_prices[]" class="form-control form-control-sm" value="<?= $v['purchase_price'] ?>"></div>
                                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">বিক্রয়</label><input type="number" step="any" name="v_sell_prices[]" class="form-control form-control-sm" value="<?= $v['sell_price'] ?>"></div>
                                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">স্টক</label><input type="number" step="any" name="v_stock_qtys[]" class="form-control form-control-sm" value="<?= $v['stock_qty'] ?>"></div>
                                <div class="col-2"><label class="small text-muted" style="font-size: 10px;">এলার্ট</label><input type="number" step="any" name="v_alert_qtys[]" class="form-control form-control-sm" value="<?= $v['alert_qty'] ?>"></div>
                                <div class="col-1 text-end"><label>&nbsp;</label><br><button type="button" class="btn btn-sm text-danger remove-v"><i class="fas fa-times-circle"></i></button></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="save_product" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow">সব তথ্য সেভ করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#mainTable').DataTable({ 
        "stateSave": true, 
        "pageLength": 25, 
        "order": [], 
        "language": { "search": "", "searchPlaceholder": "দ্রুত খুঁজুন..." },
        "columnDefs": [{
            "targets": [0,1,2,3,4],
            "render": function(data, type, row, meta) {
                if (type === 'sort') {
                    var parts = data.split('_');
                    if (parts.length === 3) {
                        var suffix = parts[2] === '0' ? '!' : parts[2];
                        return parts[0] + '_' + parts[1] + '_' + suffix;
                    }
                }
                return data;
            }
        }]
    });

    function toggleForm() {
        let val = $('input[name="product_type"]:checked').val();
        if(val === 'single') { $('.main-input').prop('disabled', false); $('#singleFields').fadeIn(); $('#variantFields').hide(); }
        else { $('.main-input').prop('disabled', true); $('#singleFields').hide(); $('#variantFields').fadeIn(); }
    }
    $('input[name="product_type"]').change(toggleForm); toggleForm();

    $('#addV').click(function() {
        let html = `<div class="row g-2 mb-2 p-2 bg-white rounded-3 shadow-sm border">
                <div class="col-4"><label class="small text-muted" style="font-size: 10px;">নাম</label><input type="text" name="variant_names[]" class="form-control form-control-sm" required></div>
                <div class="col-4"><label class="small text-muted" style="font-size: 10px;">SKU</label><input type="text" name="v_skus[]" class="form-control form-control-sm"></div>
                <div class="col-4"><label class="small text-muted" style="font-size: 10px; color:red !important;">মেয়াদ (Expiry)</label><input type="date" name="v_expiry_dates[]" class="form-control form-control-sm"></div>
                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">ক্রয়</label><input type="number" step="any" name="v_purchase_prices[]" class="form-control form-control-sm"></div>
                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">বিক্রয়</label><input type="number" step="any" name="v_sell_prices[]" class="form-control form-control-sm" required></div>
                <div class="col-3"><label class="small text-muted" style="font-size: 10px;">স্টক</label><input type="number" step="any" name="v_stock_qtys[]" class="form-control form-control-sm"></div>
                <div class="col-2"><label class="small text-muted" style="font-size: 10px;">এলার্ট</label><input type="number" step="any" name="v_alert_qtys[]" class="form-control form-control-sm" value="5"></div>
                <div class="col-1 text-end"><label>&nbsp;</label><br><button type="button" class="btn btn-sm text-danger remove-v"><i class="fas fa-times-circle"></i></button></div>
            </div>`;
        $('#vContainer').append(html);
    });
    $(document).on('click', '.remove-v', function() { $(this).closest('.row').remove(); });
    <?php if($edit_mode): ?> new bootstrap.Modal(document.getElementById('productModal')).show(); <?php endif; ?>
});
</script>
<?php include 'includes/footer.php'; ?>