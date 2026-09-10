<?php
session_start();
// ১. শুধুমাত্র অ্যাডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

// ২. অ্যাডমিনের জন্য সকল গ্রুপের প্রোডাক্ট এবং ভেরিয়েন্ট আনা (group_id ফিল্টার নেই)
// সাথে গ্রুপ নেম জয়েন করা হয়েছে যাতে ড্রপডাউনে চেনা যায়
$stmt = $conn->prepare("
    SELECT p.id, p.product_name, p.sku, g.group_name,
           COALESCE((SELECT pi.sell_price FROM purchase_items pi WHERE pi.product_id = p.id AND pi.item_type = 'single' AND pi.remaining_qty > 0 ORDER BY pi.id ASC LIMIT 1), p.sell_price) as sell_price,
           p.stock_qty, 'p' as type 
    FROM products p 
    LEFT JOIN product_groups g ON p.group_id = g.id
    WHERE p.godown_id = ? AND p.stock_qty > 0
    UNION ALL
    SELECT v.id, CONCAT(p.product_name, ' - ', v.variant_name) as product_name, v.sku, g.group_name,
           COALESCE((SELECT pi.sell_price FROM purchase_items pi WHERE pi.product_id = v.id AND pi.item_type = 'variant' AND pi.remaining_qty > 0 ORDER BY pi.id ASC LIMIT 1), v.sell_price) as sell_price,
           v.stock_qty, 'v' as type 
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    LEFT JOIN product_groups g ON p.group_id = g.id
    WHERE p.godown_id = ? AND v.stock_qty > 0
");
$stmt->execute([$godown_id, $godown_id]);
$products = $stmt->fetchAll();

// ৩. কাস্টমারদের লিস্ট
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
$existing_customers = $c_stmt->fetchAll();

$page_title = "নতুন ইনভয়েস (অ্যাডমিন)";
include 'includes/header.php'; 
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single { height: 45px; border: 1px solid #dee2e6; border-radius: 10px; padding: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
    .due-alert { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 12px; border-radius: 10px; margin-top: 10px; font-weight: bold; display: none; }
    .stat-card { border-radius: 15px; transition: 0.3s; }
    .stat-card:hover { transform: translateY(-2px); }
    input[type="datetime-local"] { cursor: pointer; }
    .bg-white { background-color: #ffffff !important; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="dashboard.php" class="btn btn-outline-primary btn-sm me-3 shadow-sm rounded-pill px-3">
            <i class="fas fa-arrow-left"></i> ড্যাশবোর্ড
        </a>
        <div class="mb-0">
            <input type="datetime-local" name="sale_date" id="sale_date" form="saleForm" class="form-control rounded-3 shadow-none" value="<?= date('Y-m-d\TH:i'); ?>" required>
        </div>
    </div>

    <!-- অ্যাডমিনের জন্য প্রসেস ফাইল process_global_sale.php হতে পারে -->
    <form action="process_global_sale.php" method="POST" id="saleForm" onsubmit="return validateSaleForm()">
        <div class="row g-4">
            <div class="col-lg-4">
                <!-- কাস্টমার সেকশন -->
                <div class="stat-card p-3 shadow-sm border-0 mb-4 bg-white rounded-4">
                    <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-user-circle me-2"></i> কাস্টমার তথ্য</h6>
                    <div class="mb-3">
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
                        <i class="fas fa-exclamation-triangle me-1 text-danger"></i> পূর্বের বাকী: ৳ <span id="prev_due_text">0.00</span>
                    </div>
                    <input type="text" name="customer_phone" id="c_phone" class="form-control mb-3 rounded-3 mt-3 shadow-none" placeholder="মোবাইল নম্বর" required>
                    <textarea name="customer_address" id="c_address" class="form-control rounded-3 shadow-none" rows="2" placeholder="ঠিকানা"></textarea>
                </div>

                <!-- প্রোডাক্ট সেকশন -->
                <div class="stat-card p-3 shadow-sm border-0 bg-white rounded-4">
                    <h6 class="fw-bold mb-3 text-success"><i class="fas fa-plus-circle me-2"></i> প্রোডাক্ট যোগ করুন</h6>
                    <div class="mb-3">
                        <select id="product_selector" class="form-control select2-product">
                            <option value="">প্রোডাক্ট সিলেক্ট করুন...</option>
                            <?php foreach($products as $p): ?>
                                <option value="<?= $p['type'].$p['id']; ?>" 
                                        data-sku="<?= htmlspecialchars($p['sku']); ?>" 
                                        data-name="<?= htmlspecialchars($p['product_name']); ?>" 
                                        data-price="<?= $p['sell_price']; ?>" 
                                        data-stock="<?= $p['stock_qty']; ?>">
                                        <?= $p['product_name']; ?> - ৳<?= number_format($p['sell_price'], 2); ?> (স্টক: <?= $p['stock_qty']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="small fw-bold">বিক্রয় রেট</label>
                        <input type="number" id="price_input" class="form-control rounded-3 border-primary" placeholder="0.00" step="any">
                    </div>
                    
                    <!-- ব্যাচ ক্যালকুলেশন বক্স -->
                    <div id="batch_calculation_box" class="mt-2" style="display:none;">
    <div class="p-3 border-warning bg-light shadow-sm" style="border: 2px dashed #ffc107; border-radius: 12px; background-color: #fffef0 !important;">
        <div class="alert alert-warning py-2 px-3 mb-0 shadow-sm border-warning" style="font-size: 12px; border-radius: 8px;">
            <i class="fas fa-exclamation-triangle me-1 text-danger"></i> 
            <b>চালান সতর্কবার্তা:</b> মালের স্টক শেষ হওয়ায় নতুন চালানের রেট (৳) যুক্ত হয়েছে। 
            <br><span id="price_details" class="text-dark fw-bold"></span>
        </div>
        <div id="price_breakdown_display" class="text-dark mb-2" style="font-size: 14px; line-height: 1.6;">
            <!-- ব্রেকডাউন -->
        </div>
        <div class="border-top pt-1 mt-1">
            <span class="fw-bold text-primary" style="font-size: 16px;">
                আইটেম মোট বিল: ৳ <span id="item_total_display">0.00</span>
            </span>
        </div>
    </div>
</div>  

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">পরিমাণ</label>
                            <input type="number" id="qty_input" class="form-control rounded-3" placeholder="0" step="any">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">ডিসকাউন্ট</label>
                            <input type="number" id="item_discount_input" class="form-control rounded-3" placeholder="0" step="any">
                        </div>
                    </div>
                    <button type="button" onclick="addToCart()" class="btn btn-primary w-100 py-2 fw-bold rounded-pill">
                        <i class="fas fa-cart-plus me-1"></i> তালিকায় যোগ করুন
                    </button>
                </div>
            </div>

            <!-- অর্ডার লিস্ট সেকশন -->
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
                                <input type="number" name="paid_amount" id="paid_input" class="form-control form-control-lg border-success fw-bold text-end rounded-3 ms-auto" style="width: 120px;" placeholder="0.00" oninput="calculateTotal()">
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
    let lastPriceData = null;
    let isPriceEdited = false;

    $(document).ready(function() {
        $('.select2-customer').select2({ tags: true, width: '100%' });
        $('.select2-product').select2({
            placeholder: "নাম বা SKU দিয়ে সার্চ করুন",
            allowClear: true,
            width: '100%',
            matcher: function(params, data) {
                if ($.trim(params.term) === '') return data;
                if (typeof data.text === 'undefined') return null;
                var term = params.term.toLowerCase();
                var name = data.text.toLowerCase();
                var sku = $(data.element).data('sku') ? $(data.element).data('sku').toString().toLowerCase() : '';
                return (name.indexOf(term) > -1 || sku.indexOf(term) > -1) ? data : null;
            }
        });

        $('#price_input').on('input', function() {
            isPriceEdited = true;
            $('#batch_calculation_box').fadeOut();
        });

        $('#product_selector').on('select2:select', function (e) {
            isPriceEdited = false;
            let price = parseFloat($(this).find(':selected').data('price')) || 0;
            $('#price_input').val(price.toFixed(2));
            setTimeout(() => { $('#qty_input').focus(); }, 100);
        });

$('#qty_input').on('input', function() {
    let p_id_type = $('#product_selector').val();
    let qty = $(this).val();

    if (p_id_type && qty > 0) {
        // --- নতুন লজিক: কার্টে এই প্রোডাক্ট অলরেডি কতটুকু আছে তা বের করা ---
        let current_cart_qty = cart.filter(item => item.id === p_id_type)
                                   .reduce((sum, item) => sum + item.qty, 0);

        // কার্টে থাকা পরিমাণসহ (cart_qty) রিকোয়েস্ট পাঠানো
        $.getJSON('../sr/get_real_price.php', { 
            p_id_type: p_id_type, 
            qty: qty, 
            cart_qty: current_cart_qty 
        }, function(data) {
            lastPriceData = data;
            
            if (!isPriceEdited) {
                $('#price_input').val(data.avg_price.toFixed(2));
                if (data.show_warning) {
                    // এখানে আপনি আপনার সতর্কবার্তা দেখাতে পারেন
                    $('#price_breakdown_display').html("নতুন চালানের মিক্সড রেট প্রয়োগ হয়েছে।");
                    $('#batch_calculation_box').fadeIn();
                } else {
                    $('#batch_calculation_box').hide();
                }
            }
        });
    } else {
        $('#batch_calculation_box').hide();
        lastPriceData = null;
    }
});

        $('#qty_input, #item_discount_input').on('keypress', function (e) {
            if (e.which == 13) { e.preventDefault(); addToCart(); }
        });

        $('#c_name_select').on('select2:select', function (e) {
            let opt = $(this).find(':selected');
            customerPrevBalance = parseFloat(opt.data('balance')) || 0;
            $('#c_phone').val(opt.data('phone') || '');
            $('#c_address').val(opt.data('address') || '');
            if(customerPrevBalance > 0) {
                $('#due_warning_box').show();
                $('#prev_due_text, #summary_prev_due').text(customerPrevBalance.toFixed(2));
            } else { $('#due_warning_box').hide(); }
            calculateTotal();
        });
    });

function addToCart() {
    let select = document.getElementById('product_selector');
    let option = select.options[select.selectedIndex];
    if (!option || !option.value) { alert("প্রোডাক্ট বেছে নিন"); return; }

    let p_id_type = option.value;
    let name = option.getAttribute('data-name');
    
    // --- কড়া স্টক চেক লজিক শুরু (আপনার আগের লজিক ঠিক রেখে) ---
    let maxPhysicalStock = parseFloat(option.getAttribute('data-stock')) || 0;
    let qtyInput = parseFloat(document.getElementById('qty_input').value) || 0;

    // কার্টে এই মালের কতটুকু অলরেডি যোগ করা হয়েছে তা বের করা
    let currentInCart = cart.filter(item => item.id === p_id_type)
                            .reduce((sum, item) => sum + parseFloat(item.qty), 0);

    let remainingAvailable = maxPhysicalStock - currentInCart;

    if (qtyInput > remainingAvailable) {
        if (remainingAvailable <= 0) {
            alert("দুঃখিত! এই প্রোডাক্টটির সবটুকু (" + maxPhysicalStock + ") অলরেডি কার্টে যোগ করা হয়েছে।");
        } else {
            alert("দুঃখিত! আর মাত্র " + remainingAvailable + " পিস অবশিষ্ট আছে। (কার্টে ইতিমধ্যে আছে: " + currentInCart + ")");
        }
        return; // কার্টে যোগ না করে এখানেই ফাংশন বন্ধ করে দিবে
    }
    // --- স্টক চেক লজিক শেষ ---

    let itemDiscountInput = parseFloat(document.getElementById('item_discount_input').value) || 0;

    if (isPriceEdited) {
        let qty = parseFloat(document.getElementById('qty_input').value) || 0;
        let price = parseFloat(document.getElementById('price_input').value) || 0;
        let subtotal = (price * qty) - itemDiscountInput;

        let existing = cart.find(item => item.id === p_id_type && item.batch_id === null);
        if (existing) {
            existing.qty = parseFloat(existing.qty) + qty;
            existing.discount = parseFloat(existing.discount) + itemDiscountInput;
            existing.subtotal = parseFloat(existing.subtotal) + subtotal;
        } else {
            cart.push({ id: p_id_type, batch_id: null, name: name + " (Manual)", qty: qty, price: price, discount: itemDiscountInput, subtotal: subtotal });
        }
    } else {
        if (!lastPriceData) { alert("পরিমাণ দিন"); return; }
        
        lastPriceData.segments.forEach((seg, index) => {
            let seg_qty = parseFloat(seg.qty);
            let seg_price = parseFloat(seg.price);
            let seg_discount = (index === 0) ? itemDiscountInput : 0;
            let seg_subtotal = (seg_price * seg_qty) - seg_discount;
            
            // একই দাম হলে ব্যাচ আইডি সাময়িকভাবে এক করে দেওয়ার লজিক (নতুন সংযোজন)
            let priceMatch = cart.find(item => item.id === p_id_type && item.price === seg_price);
            if (priceMatch) { seg.batch_id = priceMatch.batch_id; }
            let existing = cart.find(item => item.id === p_id_type && item.batch_id === seg.batch_id);
            
            if (existing) {
                existing.qty = parseFloat(existing.qty) + seg_qty;
                existing.discount = parseFloat(existing.discount) + seg_discount;
                existing.subtotal = parseFloat(existing.subtotal) + seg_subtotal;
            } else {
                cart.push({
                    id: p_id_type,
                    batch_id: seg.batch_id,
                    name: name + (seg.batch_id ? ` [ব্যাচ নং-${seg.batch_id}]` : ''),
                    qty: seg_qty,
                    price: seg_price,
                    discount: seg_discount,
                    subtotal: seg_subtotal
                });
            }
        });
    }

    // একই প্রোডাক্ট ও একই দাম হলে ব্যাচ হাইড ও মার্জ করার চূড়ান্ত লজিক
    let mergedCart = [];
    cart.forEach(item => {
        let match = mergedCart.find(m => m.id === item.id && m.price === item.price);
        if (match) {
            match.qty += item.qty;
            match.discount += item.discount;
            match.subtotal += item.subtotal;
            match.name = name; // নাম ক্লিন করে দিবে
            match.batch_id = null;
        } else {
            // যদি আইটেমটির দাম অরিজিনাল দামের সমান হয়, তবে নাম থেকে ব্যাচ সরিয়ে দাও
            if (item.id === p_id_type && item.price === parseFloat(option.getAttribute('data-price'))) {
                item.name = name;
                item.batch_id = null;
            }
            mergedCart.push(item);
        }
    });
    cart = mergedCart;

    renderCart();
    resetInputs();
}

function refreshDropdownStock() {
    $('#product_selector option').each(function() {
        let option = $(this);
        let id = option.val();
        if (!id) return;

        let maxPhysicalStock = parseFloat(option.data('stock'));
        
        // কার্টে এই আইডি-র কতটুকু আছে তা দেখা
        let inCart = cart.filter(item => item.id === id)
                         .reduce((sum, item) => sum + item.qty, 0);
        
        let virtualStock = maxPhysicalStock - inCart;
        let originalName = option.data('name'); // প্রোডাক্টের নাম

        // ড্রপডাউনের টেক্সট আপডেট (Select2 এটি সাপোর্ট করে)
        if (virtualStock <= 0) {
            option.text(originalName + " (স্টক শেষ!)");
            option.prop('disabled', true); // স্টক শেষ হলে অপশন ডিজেবল করে দেওয়া
        } else {
            option.text(originalName + " - স্টক: " + virtualStock);
            option.prop('disabled', false);
        }
    });
    
    // Select2 কে জানানো যে ডাটা আপডেট হয়েছে
    $('#product_selector').select2({
        placeholder: "নাম বা SKU দিয়ে সার্চ করুন",
        allowClear: true,
        width: '100%'
    });
}

    function resetInputs() {
        $('#product_selector').val(null).trigger('change');
        $('#qty_input, #price_input, #item_discount_input').val('');
        $('#batch_calculation_box').hide();
        isPriceEdited = false;
        setTimeout(() => { $('#product_selector').select2('open'); }, 100);
    }

function renderCart() {
    let body = document.getElementById('cart_body');
    body.innerHTML = '';
    let total = 0;
    cart.forEach((item, index) => {
        total += item.subtotal;
        body.innerHTML += `<tr class="border-bottom">
            <td>
                ${item.name} 
                <input type="hidden" name="p_ids[]" value="${item.id}">
                <input type="hidden" name="batch_ids[]" value="${item.batch_id}">
            </td>
            <td>৳ ${item.price.toFixed(2)} <input type="hidden" name="p_prices[]" value="${item.price}"></td>
            <td>${item.qty} <input type="hidden" name="p_qtys[]" value="${item.qty}"></td>
            <td class="text-danger">${item.discount.toFixed(2)} <input type="hidden" name="p_discounts[]" value="${item.discount}"></td>
            <td class="fw-bold">৳ ${item.subtotal.toFixed(2)}</td>
            <td class="text-center">
                <button type="button" class="btn btn-sm text-danger" onclick="removeFromCart(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>`;
    });
    document.getElementById('current_bill_text').innerText = total.toFixed(2);
    document.getElementById('total_amount_hidden').value = total.toFixed(2);
    calculateTotal();
    refreshDropdownStock();
}

    function removeFromCart(i) { if(confirm("বাদ দিবেন?")) { cart.splice(i, 1); renderCart(); } }

    function calculateTotal() {
        let bill = parseFloat(document.getElementById('total_amount_hidden').value) || 0;
        let paid = parseFloat(document.getElementById('paid_input').value) || 0;
        
        if (customerPrevBalance > 0) {
            $('#prev_due_container').css('display', 'flex');
            $('#summary_prev_due').text(customerPrevBalance.toFixed(2));
        } else { $('#prev_due_container').hide(); }

        let grand = bill + customerPrevBalance;
        $('#grand_total_text').text(grand.toFixed(2));
        
        let due = grand - paid;
        if (due > 0) {
            $('#final_due_container').css('display', 'flex');
            $('#final_due_text').text(due.toFixed(2));
        } else { $('#final_due_container').hide(); }

        document.getElementById('payable_amount_hidden').value = bill.toFixed(2);
        document.getElementById('due_amount_hidden').value = (bill - paid).toFixed(2);
    }

    function validateSaleForm() {
        if (cart.length === 0) { alert("কার্ট খালি!"); $('#product_selector').select2('open'); return false; }
        return true;
    }
</script>

<?php include 'includes/footer.php'; ?>