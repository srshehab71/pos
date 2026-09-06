<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php"); exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// =====================================================
// ১. পেমেন্ট প্রসেসিং লজিক (FIFO - First In First Out)
// =====================================================
if(isset($_POST['collect_payment_global'])){
    $c_phone = $_POST['customer_phone'];
    $pay_amount = (float)$_POST['pay_amount'];

    if($pay_amount > 0){
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT id, due_amount FROM sales WHERE customer_phone = ? AND godown_id = ? AND due_amount > 0 ORDER BY id ASC");
            $stmt->execute([$c_phone, $gid]);
            $unpaid_sales = $stmt->fetchAll();

            $remaining_payment = $pay_amount;
            foreach($unpaid_sales as $sale){
                if($remaining_payment <= 0) break;
                $current_due = (float)$sale['due_amount'];
                $payment_for_this = min($remaining_payment, $current_due);
                
                $upd = $conn->prepare("UPDATE sales SET paid_amount = paid_amount + ?, due_amount = due_amount - ? WHERE id = ?");
                $upd->execute([$payment_for_this, $payment_for_this, $sale['id']]);

                $ins = $conn->prepare("INSERT INTO customer_payments (godown_id, sale_id, amount_paid) VALUES (?, ?, ?)");
                $ins->execute([$gid, $sale['id'], $payment_for_this]);

                $remaining_payment -= $payment_for_this;
            }
            $conn->commit();
            header("Location: customer_due.php?success=1"); exit();
        } catch (Exception $e) { $conn->rollBack(); }
    }
}

if(isset($_POST['pay_supplier_global'])){
    $supp_id = $_POST['supplier_id'];
    $pay_amount = (float)$_POST['pay_amount'];
    if($pay_amount > 0){
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("SELECT id, due_amount FROM purchases WHERE supplier_id = ? AND godown_id = ? AND due_amount > 0 ORDER BY id ASC");
            $stmt->execute([$supp_id, $gid]);
            $unpaid_purchases = $stmt->fetchAll();

            $remaining_payment = $pay_amount;
            foreach($unpaid_purchases as $pur){
                if($remaining_payment <= 0) break;
                $current_due = (float)$pur['due_amount'];
                $payment_for_this = min($remaining_payment, $current_due);

                $upd = $conn->prepare("UPDATE purchases SET paid_amount = paid_amount + ?, due_amount = due_amount - ? WHERE id = ?");
                $upd->execute([$payment_for_this, $payment_for_this, $pur['id']]);
                $remaining_payment -= $payment_for_this;
            }
            $conn->commit();
            header("Location: customer_due.php?success=2"); exit();
        } catch (Exception $e) { $conn->rollBack(); }
    }
}

// ডাটা ফেচিং
$grouped_customers = $conn->query("SELECT customer_name, customer_phone, customer_address, SUM(payable_amount) as total_bill, SUM(paid_amount) as total_paid, SUM(due_amount) as net_due FROM sales WHERE godown_id = $gid GROUP BY customer_phone HAVING net_due != 0 ORDER BY customer_name ASC")->fetchAll();
$grouped_suppliers = $conn->query("SELECT s.id as supplier_id, s.supplier_name, s.company_name, SUM(p.total_amount) as total_bill, SUM(p.paid_amount) as total_paid, SUM(p.due_amount) as net_due FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.godown_id = $gid GROUP BY p.supplier_id HAVING net_due != 0 ORDER BY s.supplier_name ASC")->fetchAll();

// সামারি ক্যালকুলেশন
$total_receivable = 0; $total_cust_advance = 0;
foreach($grouped_customers as $gc) { if($gc['net_due'] > 0) $total_receivable += $gc['net_due']; else $total_cust_advance += abs($gc['net_due']); }
$total_payable = 0; $total_supp_advance = 0;
foreach($grouped_suppliers as $gs) { if($gs['net_due'] > 0) $total_payable += $gs['net_due']; else $total_supp_advance += abs($gs['net_due']); }

$page_title = "লেনদেন ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
:root { --body-bg: #f8f9fc; --card-bg: #ffffff; --text-main: #2d3436; --border-color: #eaecf4; }
body.dark, body.dark-mode { --body-bg: #111418; --card-bg: #1b1e22; --text-main: #f8f9fa; --border-color: #313539; }
body { background-color: var(--body-bg) !important; color: var(--text-main); }
.sr-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,.05); overflow: hidden; }
.nav-pills .nav-link { border-radius: 50px; font-weight: 600; color: #6e707e; padding: 10px 25px; transition: 0.3s; }
.nav-pills .nav-link.active { background-color: #4e73df; color: #fff; }
.summary-card { border: none; border-radius: 20px; color: #fff; position: relative; overflow: hidden; }
.bg-gradient-primary { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
.bg-gradient-danger { background: linear-gradient(135deg, #e74a3b 0%, #be2617 100%); }
.bg-gradient-info { background: linear-gradient(135deg, #36b9cc 0%, #258391 100%); }

/* পপআপ ডিজাইন */
.profile-card-pop { background: linear-gradient(135deg, #091961 0%, #370668 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
.profile-card-supp-pop { background: linear-gradient(135deg, #610909 0%, #68064a 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
.amount-input-lg { font-size: 32px !important; font-weight: 700; text-align: center; border-radius: 15px !important; border: 2px solid #eee !important; color: #4e73df; }
.select2-container--default .select2-selection--single { height: 46px; border-radius: 50px; padding: 8px 15px; border: 1px solid #ddd; background: #fff; }
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px; }
</style>

<div class="container-fluid py-4">

    <!-- সামারি সেকশন -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="summary-card bg-gradient-primary p-4 shadow">
                <small class="opacity-75">মোট পাওনা (Receivable)</small>
                <h2 class="fw-bold mb-0">৳ <?= number_format($total_receivable, 0) ?></h2>
                <div class="small opacity-75">অ্যাডভান্স জমা: ৳ <?= number_format($total_cust_advance, 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card bg-gradient-danger p-4 shadow">
                <small class="opacity-75">মোট দেনা (Payable)</small>
                <h2 class="fw-bold mb-0">৳ <?= number_format($total_payable, 0) ?></h2>
                <div class="small opacity-75">সাপ্লায়ার অ্যাডভান্স: ৳ <?= number_format($total_supp_advance, 0) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="summary-card bg-gradient-info p-4 shadow">
                <small class="opacity-75">নিট ক্যাশ পজিশন</small>
                <h2 class="fw-bold mb-0">৳ <?= number_format(($total_receivable + $total_supp_advance) - ($total_payable + $total_cust_advance), 0) ?></h2>
            </div>
        </div>
    </div>

    <!-- ট্যাব ও দ্রুত সার্চ এক লাইনে (ডিজাইন ফিক্স) -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4 gap-3">
        
        <!-- বাম পাশে ট্যাব কন্ট্রোল -->
        <ul class="nav nav-pills bg-white p-2 rounded-pill shadow-sm d-inline-flex" id="pills-tab">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pills-customer">
                    কাস্টমার (<?= count($grouped_customers) ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pills-supplier">
                    সাপ্লায়ার (<?= count($grouped_suppliers) ?>)
                </button>
            </li>
        </ul>

        <!-- ডান পাশে ফাস্ট সার্চ বক্স -->
        <div class="flex-grow-1" style="min-width: 300px;">
            <select id="fast_search" class="form-control select2 shadow-sm">
                <option value="">দ্রুত খুঁজুন (নাম বা ফোন)...</option>
                <optgroup label="কাস্টমার">
                    <?php foreach($grouped_customers as $c): ?>
                        <option value="cust_<?= $c['customer_phone']; ?>" data-name="<?= htmlspecialchars($c['customer_name']); ?>" data-balance="<?= $c['net_due']; ?>">
                            👤 <?= htmlspecialchars($c['customer_name']); ?> - <?= $c['customer_phone']; ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="সাপ্লায়ার">
                    <?php foreach($grouped_suppliers as $s): ?>
                        <option value="supp_<?= $s['supplier_id']; ?>" data-name="<?= htmlspecialchars($s['supplier_name']); ?>" data-balance="<?= $s['net_due']; ?>">
                            🚚 <?= htmlspecialchars($s['supplier_name']); ?> (<?= $s['company_name']; ?>)
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </div>
    </div>

    <!-- মেইন কন্টেন্ট টেবিল -->
    <div class="tab-content">
        <!-- কাস্টমার ট্যাব -->
        <div class="tab-pane fade show active" id="pills-customer">
            <div class="sr-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">কাস্টমার</th>
                                <th>মোট বিল</th>
                                <th>ব্যালেন্স (Due/Adv)</th>
                                <th class="text-center">অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($grouped_customers as $row): ?>
                            <tr>
                                <td class="ps-4"><div class="fw-bold"><?= $row['customer_name'] ?></div><small><?= $row['customer_phone'] ?></small></td>
                                <td>৳ <?= number_format($row['total_bill'], 0) ?></td>
                                <td>
                                    <?php if($row['net_due'] > 0): ?>
                                        <span class="text-danger fw-bold">৳ <?= number_format($row['net_due'], 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-bold">৳ <?= number_format(abs($row['net_due']), 0) ?></span> (জমা)
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-primary btn-sm rounded-pill px-3 me-2" onclick="openCustModal('<?= $row['customer_phone'] ?>', '<?= addslashes($row['customer_name']) ?>', '<?= $row['net_due'] ?>')">টাকা নিন</button>
                                    <button class="btn btn-outline-secondary btn-circle" onclick="loadLedger('cust', '<?= $row['customer_phone'] ?>', '<?= addslashes($row['customer_name']) ?>')"><i class="fas fa-history"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- সাপ্লায়ার ট্যাব -->
        <div class="tab-pane fade" id="pills-supplier">
            <div class="sr-card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">সাপ্লায়ার</th>
                                <th>মোট ক্রয়</th>
                                <th>ব্যালেন্স (Due/Adv)</th>
                                <th class="text-center">অ্যাকশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($grouped_suppliers as $srow): ?>
                            <tr>
                                <td class="ps-4"><div class="fw-bold"><?= $srow['supplier_name'] ?></div><small><?= $srow['company_name'] ?></small></td>
                                <td>৳ <?= number_format($srow['total_bill'], 0) ?></td>
                                <td>
                                    <?php if($srow['net_due'] > 0): ?>
                                        <span class="text-danger fw-bold">৳ <?= number_format($srow['net_due'], 0) ?></span>
                                    <?php else: ?>
                                        <span class="text-success fw-bold">৳ <?= number_format(abs($srow['net_due']), 0) ?></span> (জমা)
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-danger btn-sm rounded-pill px-3 me-2" onclick="openSuppModal('<?= $srow['supplier_id'] ?>', '<?= addslashes($srow['supplier_name']) ?>', '<?= $srow['net_due'] ?>')">টাকা দিন</button>
                                    <button class="btn btn-outline-secondary btn-circle" onclick="loadLedger('supp', '<?= $srow['supplier_id'] ?>', '<?= addslashes($srow['supplier_name']) ?>')"><i class="fas fa-history"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- কালেকশন মডাল -->
<div class="modal fade" id="payModalGlobal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:25px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold" id="modal_title">লেনদেন জমা</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div id="pop_card" class="profile-card-pop">
                        <div class="row align-items-center">
                            <div class="col-7">
                                <small class="opacity-75">নাম</small>
                                <div class="h6 fw-bold mb-0" id="pop_name">-</div>
                            </div>
                            <div class="col-5 text-end border-start border-white border-opacity-25">
                                <small class="opacity-75">বর্তমান বকেয়া</small>
                                <h3 class="fw-bold mb-0">৳<span id="pop_due">0</span></h3>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mb-3">
                        <label class="fw-bold text-muted mb-2">টাকার পরিমাণ</label>
                        <input type="number" name="pay_amount" id="pop_pay_input" class="form-control amount-input-lg" placeholder="0.00" step="any" required>
                    </div>
                    <input type="hidden" name="customer_phone" id="hid_c_phone">
                    <input type="hidden" name="supplier_id" id="hid_s_id">
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" id="pop_submit_btn" class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow">কনফার্ম করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- হিস্ট্রি মডাল (লেজার) -->
<div class="modal fade" id="ledgerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold">লেনদেন ইতিহাস: <span id="ledger_title_name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 pt-0" id="ledger_content">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.select2').select2({ width: '100%' });

        $('#fast_search').on('change', function() {
            let val = $(this).val();
            if(!val) return;
            let opt = $(this).find(':selected');
            if(val.startsWith('cust_')) {
                openCustModal(val.replace('cust_', ''), opt.data('name'), opt.data('balance'));
            } else {
                openSuppModal(val.replace('supp_', ''), opt.data('name'), opt.data('balance'));
            }
            $(this).val('').trigger('change.select2');
        });
    });

    function openCustModal(phone, name, due) {
        $('#modal_title').text('কাস্টমার বাকী আদায়');
        $('#pop_card').removeClass('profile-card-supp-pop').addClass('profile-card-pop');
        $('#pop_name').text(name);
        $('#pop_due').text(parseFloat(due).toLocaleString());
        $('#hid_c_phone').val(phone);
        $('#hid_s_id').val('');
        $('#pop_submit_btn').attr('name', 'collect_payment_global').removeClass('btn-danger').addClass('btn-dark');
        new bootstrap.Modal(document.getElementById('payModalGlobal')).show();
        setTimeout(()=> $('#pop_pay_input').focus(), 500);
    }

    function openSuppModal(id, name, due) {
        $('#modal_title').text('সাপ্লায়ার দেনা পরিশোধ');
        $('#pop_card').removeClass('profile-card-pop').addClass('profile-card-supp-pop');
        $('#pop_name').text(name);
        $('#pop_due').text(parseFloat(due).toLocaleString());
        $('#hid_s_id').val(id);
        $('#hid_c_phone').val('');
        $('#pop_submit_btn').attr('name', 'pay_supplier_global').removeClass('btn-dark').addClass('btn-danger');
        new bootstrap.Modal(document.getElementById('payModalGlobal')).show();
        setTimeout(()=> $('#pop_pay_input').focus(), 500);
    }

    function loadLedger(type, id, name) {
        $('#ledger_title_name').text(name);
        $('#ledger_content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        new bootstrap.Modal(document.getElementById('ledgerModal')).show();
        $.ajax({
            url: 'get_transaction_history.php',
            method: 'POST',
            data: { type: type, id: id },
            success: function(res) { $('#ledger_content').html(res); }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>