<?php
session_start();
// এডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

$godown_id = $_SESSION['godown_id'];

// ১. এডমিনের জন্য সকল গ্রুপের প্রোডাক্ট এবং ভেরিয়েন্ট আনা (group_id ফিল্টার নেই)
$stmt = $conn->prepare("
    SELECT p.id, p.product_name, p.sku, p.sell_price, p.stock_qty, 'p' as type, g.group_name 
    FROM products p
    LEFT JOIN product_groups g ON p.group_id = g.id
    WHERE p.godown_id = ? AND p.stock_qty > 0
    UNION ALL
    SELECT v.id, CONCAT(p.product_name, ' - ', v.variant_name) as product_name, v.sku, v.sell_price, v.stock_qty, 'v' as type, g.group_name 
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    LEFT JOIN product_groups g ON p.group_id = g.id
    WHERE p.godown_id = ? AND v.stock_qty > 0
");
$stmt->execute([$godown_id, $godown_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ২. কাস্টমার লিস্ট এবং ব্যালেন্স হিসেব
$c_stmt = $conn->prepare("
    SELECT 
        c.name, 
        c.phone, 
        c.address, 
        COALESCE(SUM(s.due_amount), 0) as current_balance 
    FROM customers c
    LEFT JOIN sales s ON c.phone = s.customer_phone AND c.godown_id = s.godown_id
    WHERE c.godown_id = ? 
    GROUP BY c.phone, c.name, c.address
    ORDER BY c.name ASC
");
$c_stmt->execute([$godown_id]);
$existing_customers = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "নতুন ইনভয়েস (এডমিন প্যানেল)";
include 'includes/header.php'; 
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<style>
    .select2-container--default .select2-selection--single { height: 45px; border: 1px solid #dee2e6; border-radius: 10px; padding: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
    .due-alert { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px; border-radius: 10px; margin-top: 10px; font-weight: bold; display: none; }
    .stat-card { border-radius: 15px; transition: 0.3s; }
    .bg-white { background-color: #ffffff !important; }
    input[type="datetime-local"] {cursor: pointer;}
</style>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-3 shadow-sm rounded-pill px-3">
            <i class="fas fa-arrow-left"></i> ড্যাশবোর্ড
        </a>
                <!-- ডেট এবং টাইম ইনপুট -->
<div class="mb-8">
    <input type="datetime-local" name="sale_date" id="sale_date" class="form-control rounded-3 shadow-none" 
    value="<?= date('Y-m-d\TH:i'); ?>" required>
</div>
    </div>

    <!-- লজিক সেম রাখতে process_sale.php কেই কল করা হচ্ছে (অথবা এডমিনের জন্য আলাদা প্রসেস ফাইল ব্যবহার করতে পারেন) -->
    <form action="process_global_sale.php" method="POST" id="saleForm" onsubmit="return validateSaleForm()">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="stat-card p-3 shadow-sm border-0 mb-4 bg-white rounded-4">



                    <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-user-circle me-2"></i> কাস্টমার তথ্য</h6>
                    
                    <div class="mb-3">
                        <label class="small fw-bold text-secondary mb-1">কাস্টমার সিলেক্ট করুন</label>
                        <select name="customer_name" id="c_name_select" class="form-control select2-customer" required>
                            <option value="">কাস্টমার সার্চ করুন</option>
                            <?php foreach($existing_customers as $cust): 
                                $bal = (float)($cust['current_balance'] ?? 0);
                            ?>
                                <option value="<?= htmlspecialchars($cust['name']); ?>" 
                                        data-phone="<?= htmlspecialchars($cust['phone']); ?>" 
                                        data-address="<?= htmlspecialchars($cust['address']); ?>"
                                        data-balance="<?= $bal ?>">
                                    <?= htmlspecialchars($cust['name']); ?> - <?= htmlspecialchars($cust['address']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="due_warning_box" class="due-alert shadow-sm">
                        <i class="fas fa-exclamation-triangle me-1 text-danger"></i> পূর্বের বাকী আছে: ৳ <span id="prev_due_text">0.00</span>
                    </div>

                    <input type="text" name="customer_phone" id="c_phone" class="form-control mb-3 rounded-3 mt-3 shadow-none" placeholder="মোবাইল নম্বর" required>
                    <textarea name="customer_address" id="c_address" class="form-control rounded-3 shadow-none" rows="2" placeholder="ঠিকানা"></textarea>
                </div>


                <div class="stat-card p-3 shadow-sm border-0 bg-white rounded-4">
                    <h6 class="fw-bold mb-3 text-success"><i class="fas fa-plus-circle me-2"></i> প্রোডাক্ট যোগ করুন</h6>
                    <div class="mb-3">
                        <select id="product_selector" class="form-control select2-product">
                            <option value="">প্রোডাক্ট সিলেক্ট করুন...</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?= $p['type'].$p['id']; ?>" 
                                    data-sku="<?= htmlspecialchars($p['sku']); ?>" 
                                    data-name="<?= htmlspecialchars($p['product_name']); ?> [<?= $p['group_name'] ?>]" 
                                    data-price="<?= $p['sell_price']; ?>" 
                                    data-stock="<?= $p['stock_qty']; ?>">
                                    <?= $p['product_name']; ?> - স্টক (<?= $p['stock_qty'] ?>) - ৳<?= $p['sell_price']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small fw-bold">বিক্রয় রেট (প্রতি ইউনিট)</label>
                        <input type="number" id="price_input" class="form-control rounded-3 border-primary" placeholder="0.00" step="any">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">পরিমাণ</label>
                            <input type="number" id="qty_input" class="form-control rounded-3" placeholder="0" step="any">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">ডিসকাউন্ট (৳)</label>
                            <input type="number" id="item_discount_input" class="form-control rounded-3" placeholder="0" step="any">
                        </div>
                    </div>
                    <button type="button" onclick="addToCart()" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-cart-plus me-1"></i> তালিকায় যোগ করুন
                    </button>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="stat-card p-4 shadow-sm border-0 bg-white rounded-4">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-shopping-basket me-2 text-warning"></i> অর্ডার লিস্ট</h6>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>নাম</th><th>মূল্য</th><th>পরিমাণ</th><th>ডিসকাউন্ট</th><th>মোট</th><th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody id="cart_body"></tbody>
                        </table>
                    </div>

                    <div class="summary-box mt-4 border-top pt-4">
                        <div class="row g-3 text-end align-items-center">
                            <div class="col-8 text-secondary fs-6">পণ্যের মোট বিল:</div>
                            <div class="col-4 fw-bold fs-5 text-dark">৳ <span id="current_bill_text">0.00</span></div>
                            
                            <div id="prev_due_container" style="display:none;" class="row g-3 text-end align-items-center w-100 m-0 p-0">
                                <div class="col-8 text-danger fs-6">পূর্বের বাকী (+):</div>
                                <div class="col-4 fw-bold fs-5 text-danger">৳ <span id="summary_prev_due">0.00</span></div>

                                <div class="col-8 text-dark fw-bold fs-6">সর্বমোট পাওনা:</div>
                                <div class="col-4 fw-bold fs-4 text-primary">৳ <span id="grand_total_text">0.00</span></div>
                            </div>

                            <div class="col-8 text-success fw-bold fs-6">নগদ আদায়:</div>
                            <div class="col-4">
                                <input type="number" name="paid_amount" id="paid_input" class="form-control form-control-lg border-success fw-bold text-end rounded-3 ms-auto" style="width: 105px;" placeholder="0.00" step="any" oninput="calculateTotal()">
                            </div>

                            <div id="final_due_container" style="display:none;" class="row g-3 text-end align-items-center w-100 m-0 p-0">
                                <div class="col-8 text-danger fw-bold fs-6">মোট বাকী:</div>
                                <div class="col-4 text-danger fw-bold fs-4">৳ <span id="final_due_text">0.00</span></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="total_amount" id="total_amount_hidden" value="0"> 
                    <input type="hidden" name="payable_amount" id="payable_amount_hidden" value="0"> 
                    <input type="hidden" name="due_amount" id="due_amount_hidden" value="0"> 
                    
                    <button type="submit" class="btn btn-success btn-lg w-100 mt-5 shadow rounded-pill fw-bold py-3">
                        <i class="fas fa-check-double me-2"></i> কনফার্ম সেলস ও প্রিন্ট
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let cart = [];
    let customerPrevBalance = 0;

    $(document).ready(function() {
        $('.select2-customer').select2({ tags: true, placeholder: "কাস্টমার নাম লিখুন", allowClear: true, width: '100%' });
        
        $('.select2-product').select2({
            placeholder: "প্রোডাক্টের নাম বা SKU দিয়ে সার্চ করুন",
            allowClear: true,
            width: '100%',
            matcher: function(params, data) {
                if ($.trim(params.term) === '') { return data; }
                if (typeof data.text === 'undefined') { return null; }
                var term = params.term.toLowerCase();
                var name = data.text.toLowerCase();
                var sku = $(data.element).data('sku') ? $(data.element).data('sku').toString().toLowerCase() : '';
                if (name.indexOf(term) > -1 || sku.indexOf(term) > -1) { return data; }
                return null;
            }
        });

        $('#product_selector').on('select2:select', function (e) {
            let option = $(this).find(':selected');
            $('#price_input').val(option.data('price'));
            $('#qty_input').focus();
        });

        $('#qty_input, #item_discount_input').on('keypress', function (e) {
            if (e.which == 13) { e.preventDefault(); addToCart(); }
        });

        $('#c_name_select').on('select2:select', function (e) {
            let option = $(this).find(':selected');
            let phone = option.data('phone');
            let address = option.data('address');
            customerPrevBalance = parseFloat(option.data('balance')) || 0;

            if(phone) {
                $('#c_phone').val(phone);
                $('#c_address').val(address);
                if(customerPrevBalance > 0) {
                    $('#due_warning_box').show();
                    $('#prev_due_text, #summary_prev_due').text(customerPrevBalance.toFixed(2));
                } else {
                    $('#due_warning_box').hide();
                }
            } else {
                $('#c_phone').val(''); $('#c_address').val(''); $('#due_warning_box').hide();
                customerPrevBalance = 0;
            }
            calculateTotal();
        });
    });

    function addToCart() {
        let select = document.getElementById('product_selector');
        let option = select.options[select.selectedIndex];
        if (!option || !option.value) { alert("প্রোডাক্ট বেছে নিন"); return; }

        let id = option.value;
        let name = option.getAttribute('data-name');
        let price = parseFloat(document.getElementById('price_input').value);
        let maxStock = parseFloat(option.getAttribute('data-stock')); 
        let qty = parseFloat(document.getElementById('qty_input').value);
        let itemDiscount = parseFloat(document.getElementById('item_discount_input').value) || 0;

        if (isNaN(qty) || qty <= 0) { alert("সঠিক পরিমাণ দিন"); return; }
        if (isNaN(price) || price < 0) { alert("সঠিক মূল্য দিন"); return; }

        let existingItem = cart.find(item => item.id === id);
        let currentQtyInCart = existingItem ? existingItem.qty : 0;

        if ((currentQtyInCart + qty) > maxStock) {
            alert("দুঃখিত! স্টকে আছে: " + maxStock);
            return;
        }

        if (existingItem) {
            existingItem.price = price;
            existingItem.qty += qty;
            existingItem.discount += itemDiscount;
            existingItem.subtotal = (existingItem.price * existingItem.qty) - existingItem.discount;
        } else {
            let subtotal = (price * qty) - itemDiscount;
            cart.push({ id, name, price, qty, discount: itemDiscount, subtotal });
        }

        renderCart();
        $('#product_selector').val(null).trigger('change');
        document.getElementById('price_input').value = '';
        document.getElementById('qty_input').value = '';
        document.getElementById('item_discount_input').value = '';
        $('#product_selector').select2('open');
    }

    function renderCart() {
        let body = document.getElementById('cart_body');
        body.innerHTML = '';
        let itemTotal = 0;

        cart.forEach((item, index) => {
            itemTotal += item.subtotal;
            body.innerHTML += `
                <tr class="border-bottom">
                    <td class="fw-bold">${item.name} <input type="hidden" name="p_ids[]" value="${item.id}"></td>
                    <td>${item.price.toFixed(2)} <input type="hidden" name="p_prices[]" value="${item.price}"></td>
                    <td>${item.qty} <input type="hidden" name="p_qtys[]" value="${item.qty}"></td>
                    <td class="text-danger">${item.discount.toFixed(2)} <input type="hidden" name="p_discounts[]" value="${item.discount}"></td>
                    <td class="fw-bold">${item.subtotal.toFixed(2)}</td>
                    <td class="text-center"><button type="button" class="btn btn-sm text-danger" onclick="removeFromCart(${index})"><i class="fas fa-trash"></i></button></td>
                </tr>`;
        });

        document.getElementById('current_bill_text').innerText = itemTotal.toFixed(2);
        document.getElementById('total_amount_hidden').value = itemTotal.toFixed(2);
        calculateTotal();
    }

    function removeFromCart(index) {
        if(confirm("বাদ দিতে চান?")) {
            cart.splice(index, 1);
            renderCart();
        }
    }

    function calculateTotal() {
        let todayBill = parseFloat(document.getElementById('total_amount_hidden').value) || 0;
        let paid = parseFloat(document.getElementById('paid_input').value) || 0;

        if (customerPrevBalance > 0) {
            document.getElementById('prev_due_container').style.display = 'flex';
            document.getElementById('summary_prev_due').innerText = customerPrevBalance.toFixed(2);
        } else {
            document.getElementById('prev_due_container').style.display = 'none';
        }

        let grandTotal = todayBill + customerPrevBalance;
        document.getElementById('grand_total_text').innerText = grandTotal.toFixed(2);

        let netDue = grandTotal - paid;
        if (netDue > 0) {
            document.getElementById('final_due_container').style.display = 'flex';
            document.getElementById('final_due_text').innerText = netDue.toFixed(2);
        } else {
            document.getElementById('final_due_container').style.display = 'none';
        }

        document.getElementById('payable_amount_hidden').value = todayBill.toFixed(2); 
        document.getElementById('due_amount_hidden').value = (todayBill - paid).toFixed(2);
    }

    function validateSaleForm() {
    if (typeof cart === 'undefined' || cart.length === 0) {
        alert("দুঃখিত! তালিকায় কোনো প্রোডাক্ট নেই। দয়া করে অন্তত একটি প্রোডাক্ট যোগ করুন।");
        
        // সিলেক্টরটি ওপেন করার চেষ্টা করবে
        try {
            $('#product_selector').select2('open');
        } catch(e) {}
        
        return false; // এই রিটার্ন ফলস থাকলে ফর্ম সাবমিট হবে না
    }
    return true; // কার্টে প্রোডাক্ট থাকলে সাবমিট হবে
}
</script>

<?php include 'includes/footer.php'; ?>