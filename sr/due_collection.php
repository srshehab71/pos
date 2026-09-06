<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php"); exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// =====================================================
// ১. বাকী আদায় লজিক (FIFO - First In First Out)
// =====================================================
if(isset($_POST['collect_payment_sr'])){
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
            header("Location: due_collection.php?success=1"); exit();
        } catch (Exception $e) { $conn->rollBack(); }
    }
}

// কাস্টমার ডাটা ফেচিং
$grouped_customers = $conn->query("SELECT customer_name, customer_phone, customer_address, SUM(payable_amount) as total_bill, SUM(paid_amount) as total_paid, SUM(due_amount) as net_due FROM sales WHERE godown_id = $gid GROUP BY customer_phone HAVING net_due > 0 ORDER BY customer_name ASC")->fetchAll();

$total_receivable = array_sum(array_column($grouped_customers, 'net_due'));

$page_title = "বাকী আদায়";
include 'includes/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
:root { --body-bg: #f8f9fc; --card-bg: #ffffff; --text-main: #2d3436; --border-color: #eaecf4; }
body.dark, body.dark-mode { --body-bg: #111418; --card-bg: #1b1e22; --text-main: #f8f9fa; --border-color: #313539; }
body { background-color: var(--body-bg) !important; color: var(--text-main); }
.sr-card { background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,.05); overflow: hidden; }
.summary-card { border: none; border-radius: 20px; color: #fff; position: relative; overflow: hidden; background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); }
.profile-card-pop { background: linear-gradient(135deg, #091961 0%, #370668 100%); color: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
.amount-input-lg { font-size: 32px !important; font-weight: 700; text-align: center; border-radius: 15px !important; border: 2px solid #eee !important; color: #4e73df; }

    /* Select2 এর উইডথ ১০০% নিশ্চিত করা */
    .select2-container {
        width: 100% !important;
    }

    /* Select2 এর নিজস্ব বর্ডার রিমুভ করা যাতে কার্ডের সাথে মিশে যায় */
    .select2-container--default .select2-selection--single {
        border: none !important;
        background: transparent !important;
        height: 40px !important;
        display: flex;
        align-items: center;
    }

    /* মোবাইলে টেক্সট ছোট করা (ঐচ্ছিক) */
    @media (max-width: 576px) {
        .select2-container--default .select2-selection--rendered {
            font-size: 13px;
            padding-left: 5px !important;
        }
        .card.rounded-md-pill {
            border-radius: 12px !important; /* মোবাইলে পিল শেপ খুব একটা ভালো লাগে না */
        }
    }

</style>

<div class="container-fluid py-4">

    <!-- হেডার এরিয়া (টাইটেল ও ব্যাক বাটন) -->
    <div class="d-flex align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-light shadow-sm rounded-circle me-3"><i class="fas fa-arrow-left"></i></a>
        <h4 class="fw-bold mb-0">বাকী আদায় প্যানেল</h4>
    </div>

    <!-- সামারি ও দ্রুত সার্চ এক লাইনে -->
    <div class="row g-3 mb-4 align-items-center">
        <div class="col-md-4">
            <div class="summary-card p-4 shadow ">
                <small class="opacity-75 fw-bold">মোট কাস্টমার বকেয়া</small>
                <h2 class="fw-bold mb-0">৳ <?= number_format($total_receivable, 0) ?></h2>
            </div>
        </div>
<div class="col-12 col-md-8">
    <div class="card border-0 shadow-sm rounded-4 rounded-md-pill p-1 border">
        <div class="card-body p-1">
            <div class="d-flex align-items-center w-100">
                <!-- আইকন: মোবাইলে প্যাডিং কমানো হয়েছে -->
                <div class="ps-2 ps-md-3 pe-2 text-muted border-end">
                    <i class="fas fa-search"></i>
                </div>
                
                <!-- সার্চ বক্স: ফ্লেক্স গ্রো নিশ্চিত করা হয়েছে -->
                <div class="flex-grow-1 px-1 px-md-2" style="min-width: 0;"> 
                    <select id="fast_search" class="form-control select2">
                        <option value="">দ্রুত কাস্টমার খুঁজুন...</option>
                        <?php foreach($grouped_customers as $c): ?>
                            <option value="<?= $c['customer_phone']; ?>" 
                                    data-name="<?= htmlspecialchars($c['customer_name']); ?>" 
                                    data-balance="<?= $c['net_due']; ?>">
                                <?= htmlspecialchars($c['customer_name']); ?> - <?= $c['customer_phone']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</div>
    <!-- বকেয়া তালিকা টেবিল -->
    <div class="sr-card">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users me-2 text-primary"></i>সকল বকেয়া কাস্টমার</h5>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">কাস্টমার তথ্য</th>
                        <th>ফোন নম্বর</th>
                        <th>বকেয়া পরিমাণ</th>
                        <th class="text-center">অ্যাকশন</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($grouped_customers as $row): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-bold text-dark"><?= $row['customer_name'] ?></div>
                            <small class="text-muted"><?= $row['customer_address'] ?></small>
                        </td>
                        <td><?= $row['customer_phone'] ?></td>
                        <td><span class="text-danger fw-bold">৳ <?= number_format($row['net_due'], 0) ?></span></td>
                        <td class="text-center">
                            <button class="btn btn-primary btn-sm rounded-pill px-3 me-2 fw-bold" onclick="openCustPayModal('<?= $row['customer_phone'] ?>', '<?= addslashes($row['customer_name']) ?>', '<?= $row['net_due'] ?>')">আদায় করুন</button>
                            <button class="btn btn-outline-secondary btn-sm rounded-circle shadow-sm" onclick="loadLedger('<?= $row['customer_phone'] ?>', '<?= addslashes($row['customer_name']) ?>')"><i class="fas fa-history"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($grouped_customers)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted small">সব বকেয়া আদায় করা হয়েছে।</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- কালেকশন মডাল (পপআপ) -->
<div class="modal fade" id="payModalGlobal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:25px; border:none; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold">টাকা জমা নিন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="profile-card-pop shadow-sm">
                        <div class="row align-items-center">
                            <div class="col-7">
                                <small class="opacity-75">কাস্টমার নাম</small>
                                <div class="h6 fw-bold mb-0" id="pop_name">-</div>
                            </div>
                            <div class="col-5 text-end border-start border-white border-opacity-25">
                                <small class="opacity-75">মোট বাকী</small>
                                <h4 class="fw-bold mb-0">৳<span id="pop_due">0</span></h4>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mb-3">
                        <label class="fw-bold text-muted mb-2">আদায়ের পরিমাণ লিখুন</label>
                        <input type="number" name="pay_amount" id="pop_pay_input" class="form-control amount-input-lg shadow-sm" placeholder="0.00" step="any" required>
                    </div>
                    <input type="hidden" name="customer_phone" id="hid_c_phone">
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="submit" name="collect_payment_sr" class="btn btn-dark w-100 py-3 rounded-pill fw-bold shadow">আদায় নিশ্চিত করুন</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- হিস্ট্রি মডাল (লেজার) -->
<div class="modal fade" id="ledgerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 shadow-lg border-0">
            <div class="modal-header border-0 pt-4 px-4">
                <h5 class="fw-bold">লেনদেন ইতিহাস: <span id="ledger_title_name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" id="ledger_content">
                <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Select2 ইনিশিয়ালাইজেশন
        $('.select2').select2({
            width: '100%',
            placeholder: "দ্রুত কাস্টমার খুঁজুন...",
            allowClear: true
        });

        // কাস্টমার সিলেক্ট করলে যা হবে
        $('#fast_search').on('select2:select', function (e) {
            let data = e.params.data.element; // সিলেক্ট করা অপশন এলিমেন্ট
            let phone = data.value;
            let name = data.getAttribute('data-name');
            let balance = data.getAttribute('data-balance');

            if(phone) {
                // পপআপ খোলার ফাংশন কল
                openCustPayModal(phone, name, balance);
                
                // সার্চ বক্সটি আবার খালি করে দেওয়া (যাতে আবার সার্চ করা যায়)
                $(this).val(null).trigger('change');
            }
        });
    });

    // পপআপ খোলার ফাংশন (টেবিলের বাটন এবং সার্চ - দুই জায়গার জন্যই এক)
    function openCustPayModal(phone, name, due) {
        // ডাটা সেট করা
        $('#pop_name').text(name);
        // সংখ্যা ফরম্যাট করে দেখানো
        $('#pop_due').text(parseFloat(due).toLocaleString('bn-BD')); 
        $('#hid_c_phone').val(phone);
        $('#pop_pay_input').val(''); // আগের ইনপুট ক্লিয়ার করা

        // বুটস্ট্রাপ মডাল ওপেন
        var myModal = new bootstrap.Modal(document.getElementById('payModalGlobal'));
        myModal.show();

        // মডাল খোলার পর অটো ফোকাস ইনপুট বক্সে
        var modalEl = document.getElementById('payModalGlobal');
        modalEl.addEventListener('shown.bs.modal', function () {
            $('#pop_pay_input').focus();
        });
    }

    // লেজার লোড করার ফাংশন
    function loadLedger(phone, name) {
        $('#ledger_title_name').text(name);
        $('#ledger_content').html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        var ledgerModal = new bootstrap.Modal(document.getElementById('ledgerModal'));
        ledgerModal.show();
        $.ajax({
            url: 'get_transaction_history.php',
            method: 'POST',
            data: { id: phone },
            success: function(res) { $('#ledger_content').html(res); }
        });
    }
</script>

<?php include 'includes/footer.php'; ?>