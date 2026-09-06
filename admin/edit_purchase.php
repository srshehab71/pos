<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

$purchase_id = $_GET['id'] ?? 0;

// ১. মেইন পারচেস ডাটা আনা
$p_stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ? AND godown_id = ?");
$p_stmt->execute([$purchase_id, $godown_id]);
$purchase = $p_stmt->fetch();

if (!$purchase) { die("পারচেস রেকর্ড পাওয়া যায়নি!"); }

// ২. প্রোডাক্ট ও ভেরিয়েন্ট লিস্ট
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

// ৩. সাপ্লায়ার লিস্ট
$supp_stmt = $conn->prepare("SELECT s.*, 
    (SELECT SUM(due_amount) FROM purchases WHERE supplier_id = s.id AND godown_id = ? AND id != ?) as other_balance 
    FROM suppliers s WHERE s.godown_id = ?");
$supp_stmt->execute([$godown_id, $purchase_id, $godown_id]);
$suppliers = $supp_stmt->fetchAll();

// ৪. ইনভয়েসের বর্তমান আইটেমগুলো আনা
$items_stmt = $conn->prepare("
    SELECT pi.*, 
    CASE 
        WHEN pi.item_type = 'single' THEN p.product_name 
        ELSE CONCAT(p2.product_name, ' - ', pv.variant_name) 
    END as display_name
    FROM purchase_items pi 
    LEFT JOIN products p ON pi.product_id = p.id AND pi.item_type = 'single'
    LEFT JOIN product_variants pv ON pi.product_id = pv.id AND pi.item_type = 'variant'
    LEFT JOIN products p2 ON pv.product_id = p2.id
    WHERE pi.purchase_id = ?
");
$items_stmt->execute([$purchase_id]);
$current_items = $items_stmt->fetchAll();

$page_title = "পারচেস এডিট (#$purchase_id)";
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

/* টেবিলের ভেতরের ইনপুট বক্স ডিজাইন */
.table-input { width: 100%; border: 1px solid #d1d3e2; border-radius: 8px; padding: 5px; font-size: 13px; text-align: center; }
.balance-indicator { border-radius: 12px; padding: 10px 15px; font-size: 13px; margin-bottom: 15px; display: none; border: 1px solid transparent; }
.balance-due { background-color: #ffebee; border-color: #ef9a9a; color: #c62828; }
</style>

<div class="container-fluid py-4 px-3 px-md-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-primary"><i class="fas fa-edit me-2"></i> পারচেস আপডেট</h4>
            <p class="text-muted small mb-0">ইনভয়েস #<?= $purchase_id ?> এর তথ্য ও মালামাল পরিবর্তন করুন</p>
        </div>
        <a href="purchase_list.php" class="btn btn-outline-secondary rounded-pill btn-sm px-3 shadow-sm">পিছনে যান</a>
    </div>

    <form action="process_edit_purchase.php" method="POST" id="purchase_form">
        <input type="hidden" name="purchase_id" value="<?= $purchase_id ?>">
        
        <div class="row g-4">
            <!-- বাম দিক: ৪ কলাম -->
            <div class="col-lg-4">
                <div class="purchase-card p-3 mb-4 shadow-sm">
                    <h6 class="fw-bold mb-3 text-primary border-bottom pb-2"><i class="fas fa-user-tie me-2"></i> সাপ্লায়ার ও ভাউচার</h6>
                    <select name="supplier_id" id="supplier_id" class="form-select searchable-select mb-2" required onchange="updateSupplierDisplay()">
                        <?php foreach($suppliers as $s): 
                            $bal = $s['other_balance'] ?? 0;
                        ?>
                            <option value="<?= $s['id'] ?>" data-balance="<?= $bal ?>" <?= $s['id'] == $purchase['supplier_id'] ? 'selected' : '' ?>>
                                <?= $s['supplier_name'] ?> (<?= $s['company_name'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="supplier_balance_info" class="balance-indicator"></div>

                    <input type="text" name="chalan_no" class="form-control form-control-custom mt-2" placeholder="চালান নম্বর" value="<?= $purchase['chalan_no'] ?>" required>
                </div>

                <div class="purchase-card p-3 shadow-sm">
                    <h6 class="fw-bold mb-3 text-success border-bottom pb-2"><i class="fas fa-cart-plus me-2"></i> আইটেম নির্বাচন</h6>
                    <select id="product_selector" class="form-select searchable-select mb-3">
                        <option value="">প্রোডাক্টের নাম টাইপ করুন...</option>
                        <?php foreach($all_items as $item): ?>
                            <option value="<?= ($item['variant_id'] > 0 ? 'variant' : 'single') . '|' . ($item['variant_id'] > 0 ? $item['variant_id'] : $item['id']); ?>" 
                                    data-name="<?= htmlspecialchars($item['display_name']); ?>" 
                                    data-buy-price="<?= $item['purchase_price']; ?>"
                                    data-sell-price="<?= $item['sell_price']; ?>">
                                <?= $item['display_name']; ?> 
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="small fw-bold">ক্রয় মূল্য</label><input type="number" step="any" id="buy_price_input" class="form-control form-control-custom" placeholder="0.00"></div>
                        <div class="col-6"><label class="small fw-bold">বিক্রয় মূল্য</label><input type="number" step="any" id="sell_price_input" class="form-control form-control-custom" placeholder="0.00"></div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small fw-bold text-danger">মেয়াদ</label><input type="date" id="expiry_input" class="form-control form-control-custom"></div>
                        <div class="col-6"><label class="small fw-bold text-primary">পরিমাণ</label><input type="number" step="any" id="qty_input" class="form-control form-control-custom" placeholder="0.00"></div>
                    </div>
                    <button type="button" onclick="addToPurchaseCart()" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow">
                        <i class="fas fa-plus-circle me-1"></i> তালিকায় যোগ করুন
                    </button>
                </div>
            </div>

            <!-- ডান দিক: ৮ কলাম (টেবিল) -->
            <div class="col-lg-8">
                <div class="purchase-card p-3 p-md-4 shadow-sm h-100">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-list-ul me-2 text-warning"></i> পারচেস আইটেম ডিটেইলস</h6>
                    <div class="table-responsive">
                        <table class="table table-modern align-middle mb-0">
                            <thead>
                                <tr>
                                    <th width="30%">প্রোডাক্ট</th>
                                    <th width="15%">ক্রয় মূল্য</th>
                                    <th width="15%">বিক্রয় মূল্য</th>
                                    <th width="12%">পরিমাণ</th>
                                    <th width="15%">মেয়াদ</th>
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
                            
                            <div class="col-7 text-end fw-bold text-success">আজ নগদ পরিশোধ:</div>
                            <div class="col-5"><input type="number" step="any" name="paid_amount" id="paid_input" class="form-control text-end border-success fw-bold ms-auto rounded-3" style="max-width: 150px;" value="<?= $purchase['paid_amount'] ?>" oninput="calculatePurchaseTotal()"></div>
                            
                            <div class="col-7 text-end fw-bold text-danger">নিট বাকী:</div>
                            <div class="col-5 text-end fw-bold text-danger fs-5">৳ <span id="due_text">0.00</span></div>
                        </div>
                    </div>
                    <input type="hidden" name="total_bill" id="total_bill_hidden">
                    <input type="hidden" name="due_amount" id="due_amount_hidden">
                    <button type="submit" class="btn btn-warning btn-lg w-100 mt-4 rounded-pill fw-bold py-3 shadow">
                        <i class="fas fa-save me-2"></i> আপডেট করুন
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let purchaseCart = [];

    $(document).ready(function() {
        $('.searchable-select').select2({ width: '100%' });

        $('#product_selector').on('select2:select', function (e) {
            let option = e.params.data.element;
            document.getElementById('buy_price_input').value = option.getAttribute('data-buy-price');
            document.getElementById('sell_price_input').value = option.getAttribute('data-sell-price');
            $('#qty_input').focus();
        });

        // আগের ডাটা লোড করা
        <?php foreach($current_items as $item): 
            $name = $item['display_name'];
        ?>
        purchaseCart.push({
            full_id: "<?= $item['item_type'].'|'.$item['product_id'] ?>",
            name: "<?= addslashes($name) ?>",
            type: "<?= $item['item_type'] ?>",
            buy_price: parseFloat("<?= $item['purchase_price'] ?>"),
            sell_price: parseFloat("<?= $item['sell_price'] ?>"),
            qty: parseFloat("<?= $item['qty'] ?>"),
            expiry: "<?= $item['expiry_date'] ?>"
        });
        <?php endforeach; ?>

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


        renderPurchaseCart();
        updateSupplierDisplay();
    });

    function updateRow(index, field, value) {
        if(field !== 'expiry') {
            purchaseCart[index][field] = parseFloat(value) || 0;
        } else {
            purchaseCart[index][field] = value;
        }
        renderPurchaseCart();
    }

    function addToPurchaseCart() {
        let select = document.getElementById('product_selector');
        let option = select.options[select.selectedIndex];
        if (!option.value) return;

        let buyPrice = parseFloat(document.getElementById('buy_price_input').value) || 0;
        let sellPrice = parseFloat(document.getElementById('sell_price_input').value) || 0;
        let qty = parseFloat(document.getElementById('qty_input').value) || 0;
        let expiry = document.getElementById('expiry_input').value;

        if (qty <= 0) { alert("সঠিক পরিমাণ দিন"); return; }

        let idParts = option.value.split('|');
        purchaseCart.push({
            full_id: option.value,
            name: option.getAttribute('data-name'),
            type: idParts[0],
            buy_price: buyPrice,
            sell_price: sellPrice,
            qty: qty,
            expiry: expiry
        });

        renderPurchaseCart();
        $('#product_selector').val(null).trigger('change');
        $('#buy_price_input, #sell_price_input, #qty_input, #expiry_input').val('');
    }

    function renderPurchaseCart() {
        let body = document.getElementById('purchase_cart_body');
        body.innerHTML = '';
        let totalBill = 0;

        purchaseCart.forEach((item, index) => {
            let subtotal = item.buy_price * item.qty;
            totalBill += subtotal;
            body.innerHTML += `
                <tr>
                    <td>
                        <div class="fw-bold">${item.name}</div>
                        <input type="hidden" name="p_full_ids[]" value="${item.full_id}">
                    </td>
                    <td><input type="number" step="any" name="p_buy_prices[]" class="table-input" value="${item.buy_price}" onchange="updateRow(${index}, 'buy_price', this.value)"></td>
                    <td><input type="number" step="any" name="p_sell_prices[]" class="table-input" value="${item.sell_price}" onchange="updateRow(${index}, 'sell_price', this.value)"></td>
                    <td><input type="number" step="any" name="p_qtys[]" class="table-input" value="${item.qty}" onchange="updateRow(${index}, 'qty', this.value)"></td>
                    <td><input type="date" name="p_expiries[]" class="table-input" value="${item.expiry}" onchange="updateRow(${index}, 'expiry', this.value)"></td>
                    <td class="fw-bold">৳${subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm text-danger" onclick="purchaseCart.splice(${index},1);renderPurchaseCart()"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>`;
        });
        document.getElementById('total_bill_text').innerText = totalBill.toFixed(2);
        document.getElementById('total_bill_hidden').value = totalBill;
        calculatePurchaseTotal();
    }

    function calculatePurchaseTotal() {
        let total = parseFloat(document.getElementById('total_bill_hidden').value) || 0;
        let paid = parseFloat(document.getElementById('paid_input').value) || 0;
        let due = total - paid;
        document.getElementById('due_text').innerText = due.toFixed(2);
        document.getElementById('due_amount_hidden').value = due;
    }

    function updateSupplierDisplay() {
        let select = document.getElementById('supplier_id');
        let option = select.options[select.selectedIndex];
        let balanceDisplay = document.getElementById('supplier_balance_info');
        if(option.value) {
            let balance = parseFloat(option.getAttribute('data-balance')) || 0;
            balanceDisplay.style.display = 'block';
            balanceDisplay.className = 'balance-indicator balance-due';
            balanceDisplay.innerHTML = `<i class="fas fa-info-circle me-2"></i> অন্য ইনভয়েসে বাকী: <b>৳ ${balance.toFixed(2)}</b>`;
        }
    }
</script>

<?php include 'includes/footer.php'; ?>