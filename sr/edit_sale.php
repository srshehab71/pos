<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php"); exit();
}

$sale_id = $_GET['id'];
$uid = $_SESSION['user_id'];
$gid = $_SESSION['godown_id'];
$sr_group = $_SESSION['group_id'];

// ১. মেইন সেলস ডাটা আনা
$sale_stmt = $conn->prepare("SELECT * FROM sales WHERE id = ? AND user_id = ?");
$sale_stmt->execute([$sale_id, $uid]);
$sale = $sale_stmt->fetch();

if (!$sale) { die("ইনভয়েস পাওয়া যায়নি বা আপনার এডিট করার অনুমতি নেই!"); }

// ২. ইনভয়েসের আইটেমগুলো নিয়ে আসা (ভেরিয়েন্টসহ)
$items_stmt = $conn->prepare("
    SELECT si.*, p.product_name, pv.variant_name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    LEFT JOIN product_variants pv ON si.variant_id = pv.id 
    WHERE si.sale_id = ?
");
$items_stmt->execute([$sale_id]);
$current_items = $items_stmt->fetchAll();

// ৩. সব প্রোডাক্ট এবং ভেরিয়েন্ট আনা (সার্চের জন্য)
$p_stmt = $conn->prepare("
    SELECT id, product_name, sku, sell_price, stock_qty, 'p' as type 
    FROM products 
    WHERE godown_id = ? AND group_id = ?
    UNION ALL
    SELECT v.id, CONCAT(p.product_name, ' - ', v.variant_name) as product_name, v.sku, v.sell_price, v.stock_qty, 'v' as type 
    FROM product_variants v
    JOIN products p ON v.product_id = p.id
    WHERE p.godown_id = ? AND p.group_id = ?
");
$p_stmt->execute([$gid, $sr_group, $gid, $sr_group]);
$all_products = $p_stmt->fetchAll();

$page_title = "ইনভয়েস এডিট (#$sale_id)";
include 'includes/header.php';
?>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single { height: 45px; border: 1px solid #dee2e6; border-radius: 10px; padding: 8px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="sales_history.php" class="btn btn-outline-primary btn-sm me-3 rounded-pill px-3">
            <i class="fas fa-arrow-left"></i> পিছনে যান
        </a>
        <h4 class="fw-bold mb-0">ইনভয়েস আপডেট (#<?= $sale_id ?>)</h4>
    </div>

    <form action="process_edit_sale.php" method="POST" id="editSaleForm">
        <input type="hidden" name="sale_id" value="<?= $sale_id ?>">
        
        <div class="row g-4">
            <!-- কাস্টমার তথ্য -->
            <div class="col-lg-4">
                <div class="stat-card p-3 shadow-sm bg-white rounded-4 mb-4">
                    <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-user-edit me-2"></i> কাস্টমার তথ্য</h6>
                    <label class="small fw-bold">কাস্টমার নাম</label>
                    <input type="text" name="customer_name" class="form-control mb-2 rounded-3" value="<?= $sale['customer_name'] ?>" required>
                    <label class="small fw-bold">মোবাইল নম্বর</label>
                    <input type="text" name="customer_phone" class="form-control mb-2 rounded-3" value="<?= $sale['customer_phone'] ?>" readonly>
                    <label class="small fw-bold">ঠিকানা</label>
                    <textarea name="customer_address" class="form-control rounded-3" rows="2"><?= $sale['customer_address'] ?></textarea>
                </div>

                <!-- প্রোডাক্ট যোগ করার সেকশন -->
                <div class="stat-card p-3 shadow-sm bg-white rounded-4">
                    <h6 class="fw-bold mb-3 text-success"><i class="fas fa-plus-circle me-2"></i> প্রোডাক্ট যোগ করুন</h6>
                    <div class="mb-3">
                        <select id="product_selector" class="form-control select2-product">
                            <option value="">প্রোডাক্ট সিলেক্ট করুন...</option>
                            <?php foreach($all_products as $p): ?>
                                <option value="<?= $p['type'].$p['id']; ?>" 
                                        data-sku="<?= htmlspecialchars($p['sku']); ?>" 
                                        data-name="<?= htmlspecialchars($p['product_name']); ?>" 
                                        data-price="<?= $p['sell_price']; ?>" 
                                        data-stock="<?= $p['stock_qty']; ?>">
                                    <?= $p['product_name']; ?> - ৳<?= $p['sell_price']; ?> (স্টক: <?= $p['stock_qty']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="small fw-bold">বিক্রয় রেট</label>
                        <input type="number" id="price_input" class="form-control rounded-3 border-primary" placeholder="0.00" step="any">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="small fw-bold">পরিমাণ</label>
                            <input type="number" id="qty_input" class="form-control rounded-3" placeholder="0" step="any">
                        </div>
                        <div class="col-6">
                            <label class="small fw-bold">ডিসকাউন্ট</label>
                            <input type="number" id="item_discount_input" class="form-control rounded-3" value="0" step="any">
                        </div>
                    </div>
                    <button type="button" onclick="addToCart()" class="btn btn-primary w-100 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="fas fa-cart-plus me-1"></i> তালিকায় যোগ করুন
                    </button>
                </div>
            </div>

            <!-- অর্ডার লিস্ট/কার্ট -->
            <div class="col-lg-8">
                <div class="stat-card p-4 shadow-sm bg-white rounded-4">
                    <h6 class="fw-bold mb-4 border-bottom pb-2"><i class="fas fa-shopping-basket me-2 text-warning"></i> আইটেম লিস্ট</h6>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="bg-light">
                                <tr>
                                    <th>নাম</th><th>মূল্য</th><th>পরিমাণ</th><th>ডিসকাউন্ট</th><th>মোট</th><th class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody id="cart_body">
                                <!-- JS will load items here -->
                            </tbody>
                        </table>
                    </div>

                    <div class="summary-box mt-4 border-top pt-4">
                        <div class="row g-3 text-end align-items-center">
                            <div class="col-8 text-secondary fs-6">মোট বিল:</div>
                            <div class="col-4 fw-bold fs-5 text-dark">৳ <span id="current_bill_text">0.00</span></div>
                            
                            <div class="col-8 text-success fw-bold fs-6">নগদ আদায়:</div>
                            <div class="col-4">
                                <input type="number" name="paid_amount" id="paid_input" class="form-control fw-bold text-end rounded-3 ms-auto" style="width: 120px;" value="<?= $sale['paid_amount'] ?>" step="any" oninput="calculateTotal()">
                            </div>

                            <div class="col-8 text-danger fw-bold fs-6">মোট বাকী:</div>
                            <div class="col-4 text-danger fw-bold fs-4">৳ <span id="final_due_text">0.00</span></div>
                        </div>
                    </div>

                    <input type="hidden" name="payable_amount" id="payable_amount_hidden">
                    <input type="hidden" name="due_amount" id="due_amount_hidden">
                    
                    <div class="d-flex gap-2 mt-5">
                        <a href="sales_history.php" class="btn btn-light btn-lg w-50 rounded-pill fw-bold">বাতিল</a>
                        <button type="submit" class="btn btn-warning btn-lg w-50 shadow rounded-pill fw-bold">
                            <i class="fas fa-save me-2"></i> আপডেট করুন
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let cart = [];

    $(document).ready(function() {
        // ১. Select2 Initialize (SKU সার্চসহ)
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

        // ২. প্রোডাক্ট সিলেক্ট করলে দাম অটো বসানো
        $('#product_selector').on('select2:select', function (e) {
            let price = $(this).find(':selected').data('price');
            $('#price_input').val(price);
            $('#qty_input').focus();
        });

        // ৩. আগের আইটেমগুলো কার্টে লোড করা (Edit Mode)
        <?php foreach($current_items as $item): 
            $type = $item['variant_id'] ? 'v' : 'p';
            $id = $item['variant_id'] ? $item['variant_id'] : $item['product_id'];
            $full_id = $type . $id;
            $name = $item['product_name'] . ($item['variant_name'] ? " - ".$item['variant_name'] : "");
        ?>
        cart.push({
            id: "<?= $full_id ?>",
            name: "<?= addslashes($name) ?>",
            price: parseFloat("<?= $item['unit_price'] ?>"),
            qty: parseFloat("<?= $item['qty'] ?>"),
            discount: parseFloat("<?= $item['discount'] ?>"),
            subtotal: parseFloat("<?= $item['subtotal'] ?>")
        });
        <?php endforeach; ?>
        renderCart();

        // এন্টার চাপলে অ্যাড করা
        $('#qty_input, #item_discount_input').on('keypress', function (e) {
            if (e.which == 13) { e.preventDefault(); addToCart(); }
        });
    });

    function addToCart() {
        let select = document.getElementById('product_selector');
        let option = select.options[select.selectedIndex];
        if (!option.value) { alert("প্রোডাক্ট বেছে নিন"); return; }

        let id = option.value;
        let name = option.getAttribute('data-name');
        let price = parseFloat(document.getElementById('price_input').value);
        let qty = parseFloat(document.getElementById('qty_input').value);
        let itemDiscount = parseFloat(document.getElementById('item_discount_input').value) || 0;

        if (isNaN(qty) || qty <= 0) { alert("সঠিক পরিমাণ দিন"); return; }
        if (isNaN(price)) { alert("সঠিক মূল্য দিন"); return; }

        let existingItem = cart.find(item => item.id === id);
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
        
        // রিসেট
        $('#product_selector').val(null).trigger('change');
        document.getElementById('price_input').value = '';
        document.getElementById('qty_input').value = '';
        document.getElementById('item_discount_input').value = '0';
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
                    <td class="fw-bold">
                        ${item.name} 
                        <input type="hidden" name="p_ids[]" value="${item.id}">
                    </td>
                    <td>
    <input type="number" name="p_prices[]" class="form-control form-control-sm" style="width:100px" value="${item.price}" step="any" onchange="updateRow(${index}, null, this.value)">
</td>
                    <td><input type="number" name="p_qtys[]" class="form-control form-control-sm" style="width:70px" value="${item.qty}" step="any" onchange="updateRow(${index}, this.value)"></td>
                    <td class="text-danger">${item.discount.toFixed(2)} <input type="hidden" name="p_discounts[]" value="${item.discount}"></td>
                    <td class="fw-bold">${item.subtotal.toFixed(2)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm text-danger" onclick="cart.splice(${index},1);renderCart()">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        });

        document.getElementById('current_bill_text').innerText = itemTotal.toFixed(2);
        document.getElementById('payable_amount_hidden').value = itemTotal.toFixed(2);
        calculateTotal();
    }

    function renderCart() {
    let body = document.getElementById('cart_body');
    body.innerHTML = '';
    let itemTotal = 0;

    cart.forEach((item, index) => {
        itemTotal += item.subtotal;
        body.innerHTML += `
            <tr class="border-bottom">
                <td class="fw-bold">
                    ${item.name} 
                    <input type="hidden" name="p_ids[]" value="${item.id}">
                </td>
                <!-- এখানে মূল্য এখন ইনপুট বক্স হিসেবে আছে -->
                <td>
                    <input type="number" name="p_prices[]" class="form-control form-control-sm" style="width:100px" value="${item.price.toFixed(2)}" step="any" onchange="updateRow(${index}, null, this.value)">
                </td>
                <td>
                    <input type="number" name="p_qtys[]" class="form-control form-control-sm" style="width:70px" value="${item.qty}" step="any" onchange="updateRow(${index}, this.value, null)">
                </td>
                <td class="text-danger">
                    ${item.discount.toFixed(2)} 
                    <input type="hidden" name="p_discounts[]" value="${item.discount}">
                </td>
                <td class="fw-bold">${item.subtotal.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm text-danger" onclick="cart.splice(${index},1);renderCart()">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
    });

    document.getElementById('current_bill_text').innerText = itemTotal.toFixed(2);
    document.getElementById('payable_amount_hidden').value = itemTotal.toFixed(2);
    calculateTotal();
}

// আপডেট ফাংশন যা পরিমাণ এবং মূল্য উভয়ই হ্যান্ডেল করবে
function updateRow(index, newQty = null, newPrice = null) {
    if (newQty !== null) cart[index].qty = parseFloat(newQty) || 0;
    if (newPrice !== null) cart[index].price = parseFloat(newPrice) || 0;

    cart[index].subtotal = (cart[index].price * cart[index].qty) - cart[index].discount;
    renderCart();
}

    function calculateTotal() {
        let bill = parseFloat(document.getElementById('payable_amount_hidden').value) || 0;
        let paid = parseFloat(document.getElementById('paid_input').value) || 0;
        let due = bill - paid;
        
        document.getElementById('final_due_text').innerText = due.toFixed(2);
        document.getElementById('due_amount_hidden').value = due.toFixed(2);
    }

    // ইনভয়েস আপডেট করার আগে চেক করা
    $('#editSaleForm').on('submit', function(e) {
        if (cart.length === 0) {
            e.preventDefault(); // সাবমিট আটকানো
            alert("আপনি কোন প্রোডাক্ট যোগ করেননি! আগে যোগ করুন এরপর আপডেট করুন।");
            return false;
        }
    });
</script>

<?php include 'includes/footer.php'; ?>