<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

// ১. প্রোডাক্ট ও ভেরিয়েন্ট লিস্ট
$sql = "
    (SELECT id, product_name as display_name, purchase_price, sell_price, 'single' as p_type, 0 as variant_id FROM products WHERE godown_id = ?)
    UNION ALL
    (SELECT v.id, CONCAT(p.product_name, ' - ', v.variant_name) as display_name, v.purchase_price, v.sell_price, 'variant' as p_type, v.id as variant_id 
     FROM product_variants v 
     JOIN products p ON v.product_id = p.id 
     WHERE p.godown_id = ?)
    ORDER BY display_name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute([$godown_id, $godown_id]);
$all_items = $stmt->fetchAll();

// ২. সাপ্লায়ার লিস্ট
$supp_stmt = $conn->prepare("SELECT s.*, 
    (SELECT SUM(due_amount) FROM purchases WHERE supplier_id = s.id AND godown_id = ?) as current_balance 
    FROM suppliers s WHERE s.godown_id = ?");
$supp_stmt->execute([$godown_id, $godown_id]);
$suppliers = $supp_stmt->fetchAll();

$page_title = "প্রোডাক্ট পারচেস (ক্রয়)";
include 'includes/header.php'; 
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
:root { --card-bg: #ffffff; --border-soft: #eaecf4; }
body { background-color: #f4f7fc !important; }
.purchase-card { background-color: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
.form-control-custom { border-radius: 12px; padding: 10px; background-color: var(--card-bg) !important; border: 1px solid var(--border-soft) !important; color: inherit !important; font-size: 13px; }

.select2-container--default .select2-selection--single {
    height: 45px !important;
    border: 1px solid var(--border-soft) !important;
    border-radius: 12px !important;
    padding: 8px !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px !important; }

.table-modern thead th { background-color: var(--border-soft); color: #4e73df; font-size: 11px; text-transform: uppercase; padding: 12px; border: none; }
.table-modern tbody td { padding: 10px; border-bottom: 1px solid var(--border-soft); font-size: 13px; }
.summary-area { background: var(--border-soft); border-radius: 15px; padding: 20px; }

.balance-indicator { border-radius: 12px; padding: 10px 15px; font-size: 13px; margin-bottom: 15px; display: none; border: 1px solid transparent; }
.balance-advance { background-color: #e8f5e9; border-color: #a5d6a7; color: #2e7d32; }
.balance-due { background-color: #ffebee; border-color: #ef9a9a; color: #c62828; }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-primary"><i class="fas fa-truck-loading me-2"></i> প্রোডাক্ট পারচেস</h4>
            <p class="text-muted small mb-0">ক্রয়, বিক্রয় ও পরিমাণে দশমিক (Decimal) ব্যবহারের সুবিধা যুক্ত করা হয়েছে</p>
        </div>
    </div>

    <form action="process_purchase.php" method="POST" id="purchase_form">
        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="purchase-card p-3 mb-4 shadow-sm">
                    <h6 class="fw-bold mb-3 text-primary border-bottom pb-2"><i class="fas fa-user-tie me-2"></i> সাপ্লায়ার ও ভাউচার</h6>
                    <select name="supplier_id" id="supplier_id" class="form-select searchable-select mb-2" required onchange="updateSupplierDisplay()">
                        <option value="">সাপ্লায়ার টাইপ করুন...</option>
                        <?php foreach($suppliers as $s): 
                            $bal = $s['current_balance'] ?? 0;
                        ?>
                            <option value="<?= $s['id'] ?>" data-balance="<?= $bal ?>">
                                <?= $s['supplier_name'] ?> (<?= $s['company_name'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="supplier_balance_info" class="balance-indicator"></div>

                    <input type="text" name="chalan_no" class="form-control form-control-custom mt-2" placeholder="চালান/ভাউচার নম্বর" required>
                </div>

                <div class="purchase-card p-3 shadow-sm">
                    <h6 class="fw-bold mb-3 text-success border-bottom pb-2"><i class="fas fa-cart-plus me-2"></i> আইটেম নির্বাচন</h6>
                    <select id="product_selector" class="form-select searchable-select mb-3">
                        <option value="">প্রোডাক্টের নাম টাইপ করুন...</option>
                        <?php foreach($all_items as $item): ?>
                            <option value="<?= $item['variant_id'] > 0 ? $item['variant_id'] : $item['id']; ?>" 
                                    data-name="<?= htmlspecialchars($item['display_name']); ?>" 
                                    data-buy-price="<?= $item['purchase_price']; ?>"
                                    data-sell-price="<?= $item['sell_price']; ?>"
                                    data-type="<?= $item['p_type']; ?>">
                                <?= $item['display_name']; ?> 
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row g-2 mb-2">
                        <!-- step="any" যুক্ত করা হয়েছে -->
                        <div class="col-6"><label class="small fw-bold">ক্রয় মূল্য</label><input type="number" step="any" id="buy_price_input" class="form-control form-control-custom" placeholder="0.00"></div>
                        <div class="col-6"><label class="small fw-bold">বিক্রয় মূল্য</label><input type="number" step="any" id="sell_price_input" class="form-control form-control-custom" placeholder="0.00"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small fw-bold text-danger">মেয়াদ (Expiry)</label><input type="date" id="expiry_input" class="form-control form-control-custom"></div>
                        <!-- এখানেও দশমিক পরিমাণ ইনপুট নেওয়া যাবে -->
                        <div class="col-6"><label class="small fw-bold text-primary">পরিমাণ (Qty)</label><input type="number" step="any" id="qty_input" class="form-control form-control-custom" placeholder="0.00"></div>
                    </div>
                    <button type="button" onclick="addToPurchaseCart()" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow">
                        <i class="fas fa-plus-circle me-1"></i> তালিকায় যোগ করুন
                    </button>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="purchase-card p-3 p-md-4 shadow-sm h-100">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-list-ul me-2 text-warning"></i> পারচেস আইটেম ডিটেইলস</h6>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th width="35%">প্রোডাক্ট</th>
                                    <th>মূল্য (ক্রয়/বিক্রয়)</th>
                                    <th>পরিমাণ ও মেয়াদ</th>
                                    <th>মোট</th>
                                    <th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody id="purchase_cart_body"></tbody>
                        </table>
                    </div>

                    <div class="summary-area mt-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-7 text-end fw-bold">সর্বমোট বিল:</div>
                            <div class="col-5 text-end fw-bold text-primary fs-5">৳ <span id="total_bill_text">0.00</span></div>
                            
                            <div id="advance_adjust_row" class="col-12" style="display:none;">
                                <div class="row align-items-center">
                                    <div class="col-7 text-end fw-bold text-info small">অ্যাডভান্স থেকে সমন্বয় (Adjust):</div>
                                    <div class="col-5">
                                        <input type="number" step="any" name="adjusted_amount" id="adjusted_input" class="form-control text-end border-info fw-bold ms-auto rounded-3" style="max-width: 150px; background: #eefbff;" value="0" oninput="calculatePurchaseTotal()">
                                    </div>
                                </div>
                            </div>

                            <div class="col-7 text-end fw-bold text-success">আজ নগদ পরিশোধ (Paid):</div>
                            <div class="col-5"><input type="number" step="any" name="paid_amount" id="paid_input" class="form-control text-end border-success fw-bold ms-auto rounded-3" style="max-width: 150px;" value="0" oninput="calculatePurchaseTotal()"></div>
                            
                            <div class="col-7 text-end fw-bold text-danger">নিট বাকী (Due):</div>
                            <div class="col-5 text-end fw-bold text-danger fs-5">৳ <span id="due_text">0.00</span></div>
                        </div>
                    </div>
                    <input type="hidden" name="total_bill" id="total_bill_hidden">
                    <input type="hidden" name="due_amount" id="due_amount_hidden">
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-4 rounded-pill fw-bold py-3 shadow">
                        <i class="fas fa-check-circle me-2"></i> পারচেস ডাটা সেভ করুন
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        $('.searchable-select').select2({
            width: '100%',
            placeholder: "খুঁজতে টাইপ করুন..."
        });

        $('#product_selector').on('select2:select', function (e) {
            let option = e.params.data.element;
            document.getElementById('buy_price_input').value = option.getAttribute('data-buy-price');
            document.getElementById('sell_price_input').value = option.getAttribute('data-sell-price');
        });

        // ৩. ক্রয় মূল্য বক্সে এন্টার দিলে বিক্রয় মূল্য বক্সে যাবে
    $('#buy_price_input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#sell_price_input').focus();
        }
    });

    // ৪. বিক্রয় মূল্য বক্সে এন্টার দিলে পরিমাণ (Qty) বক্সে যাবে
    $('#sell_price_input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            $('#qty_input').focus();
        }
    });

    // ৫. পরিমাণ (Qty) বক্সে এন্টার দিলে কার্টে যোগ হবে
    $('#qty_input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            addToPurchaseCart();
            // কার্টে যোগ হওয়ার পর আবার প্রোডাক্ট সার্চ বক্সে ফোকাস যাবে
            $('#product_selector').select2('open');
        }
    });

    // ৬. নগদ পরিশোধ (Paid) বক্সে এন্টার দিলে পুরো ফর্ম সাবমিট হবে
    $('#paid_input').on('keypress', function(e) {
        if (e.which == 13) {
            e.preventDefault();
            if(purchaseCart.length > 0) {
                $('#purchase_form').submit();
            } else {
                alert("কার্টে কোনো প্রোডাক্ট নেই!");
            }
        }
    });

    });

    function updateSupplierDisplay() {
        let select = document.getElementById('supplier_id');
        let option = select.options[select.selectedIndex];
        let balanceDisplay = document.getElementById('supplier_balance_info');
        let adjustRow = document.getElementById('advance_adjust_row');
        let adjustedInput = document.getElementById('adjusted_input');

        if(option.value) {
            let balance = parseFloat(option.getAttribute('data-balance'));
            balanceDisplay.style.display = 'block';

            if(balance < 0) {
                let advanceAmt = Math.abs(balance);
                balanceDisplay.className = 'balance-indicator balance-advance';
                balanceDisplay.innerHTML = `<i class="fas fa-check-circle me-2"></i> আপনার <b>৳ ${advanceAmt.toFixed(2)}</b> এ্যাডভান্স জমা আছে।`;
                adjustRow.style.display = 'block';
            } else if(balance > 0) {
                balanceDisplay.className = 'balance-indicator balance-due';
                balanceDisplay.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i> আগের বাকী আছে: <b>৳ ${balance.toFixed(2)}</b>`;
                adjustRow.style.display = 'none';
                adjustedInput.value = 0;
            } else {
                balanceDisplay.className = 'balance-indicator bg-light border text-muted';
                balanceDisplay.innerHTML = `<i class="fas fa-info-circle me-2"></i> কোনো পূর্ববতী লেনদেন বাকী নেই।`;
                adjustRow.style.display = 'none';
                adjustedInput.value = 0;
            }
        } else {
            balanceDisplay.style.display = 'none';
            adjustRow.style.display = 'none';
            adjustedInput.value = 0;
        }
        calculatePurchaseTotal();
    }

    let purchaseCart = [];

    function addToPurchaseCart() {
        let select = document.getElementById('product_selector');
        let option = select.options[select.selectedIndex];
        if (!option.value) { alert("প্রোডাক্ট বেছে নিন"); return; }

        // parseFloat ব্যবহার করা হয়েছে দশমিকের জন্য
        let buyPrice = parseFloat(document.getElementById('buy_price_input').value);
        let sellPrice = parseFloat(document.getElementById('sell_price_input').value);
        let qty = parseFloat(document.getElementById('qty_input').value);
        let expiry = document.getElementById('expiry_input').value;

        if (isNaN(qty) || qty <= 0 || isNaN(buyPrice) || buyPrice < 0) {
            alert("সঠিক দাম ও পরিমাণ দিন"); return;
        }

        purchaseCart.push({
            id: option.value,
            name: option.getAttribute('data-name'),
            type: option.getAttribute('data-type'),
            buy_price: buyPrice,
            sell_price: sellPrice,
            qty: qty,
            expiry: expiry,
            subtotal: buyPrice * qty
        });

        renderPurchaseCart();
        document.getElementById('qty_input').value = '';
        document.getElementById('expiry_input').value = '';
        $('#product_selector').val(null).trigger('change');
    }

    function renderPurchaseCart() {
        let body = document.getElementById('purchase_cart_body');
        body.innerHTML = '';
        let totalBill = 0;

        purchaseCart.forEach((item, index) => {
            totalBill += item.subtotal;
            body.innerHTML += `
                <tr>
                    <td><div class="fw-bold">${item.name}</div><small class="badge bg-light text-muted border">${item.type}</small>
                        <input type="hidden" name="p_ids[]" value="${item.id}">
                        <input type="hidden" name="p_types[]" value="${item.type}"></td>
                    <td><div class="small">ক্রয়: ৳${item.buy_price.toFixed(2)}</div><div class="small text-success">বিক্রয়: ৳${item.sell_price.toFixed(2)}</div>
                        <input type="hidden" name="p_buy_prices[]" value="${item.buy_price}">
                        <input type="hidden" name="p_sell_prices[]" value="${item.sell_price}"></td>
                    <td><div class="fw-bold">${item.qty}</div><div class="small text-danger">${item.expiry ? item.expiry : 'No Expiry'}</div>
                        <input type="hidden" name="p_qtys[]" value="${item.qty}">
                        <input type="hidden" name="p_expiries[]" value="${item.expiry}"></td>
                    <td class="fw-bold text-dark">৳${item.subtotal.toFixed(2)}</td>
                    <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="removeFromPurchaseCart(${index})"><i class="fas fa-times-circle fa-lg"></i></button></td>
                </tr>`;
        });
        document.getElementById('total_bill_text').innerText = totalBill.toFixed(2);
        document.getElementById('total_bill_hidden').value = totalBill;
        calculatePurchaseTotal();
    }

    function removeFromPurchaseCart(index) {
        purchaseCart.splice(index, 1);
        renderPurchaseCart();
    }

    function calculatePurchaseTotal() {
        let total = parseFloat(document.getElementById('total_bill_hidden').value) || 0;
        let adjusted = parseFloat(document.getElementById('adjusted_input').value) || 0;
        let paid = parseFloat(document.getElementById('paid_input').value) || 0;
        
        let due = total - adjusted - paid;
        
        document.getElementById('due_text').innerText = due.toFixed(2);
        document.getElementById('due_amount_hidden').value = due;
    }
</script>

<?php include 'includes/footer.php'; ?>