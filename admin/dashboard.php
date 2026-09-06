<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

// ১. মোট প্রোডাক্ট সংখ্যা
$p_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE godown_id = ?");
$p_stmt->execute([$gid]);
$total_products = $p_stmt->fetch()['total'];

// ২. আজকের মোট বিক্রি (ডিসকাউন্টের পর)
$s_stmt = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE godown_id = ? AND DATE(created_at) = CURDATE()");
$s_stmt->execute([$gid]);
$today_sales = $s_stmt->fetch()['total'] ?? 0;

// ৩. এই মাসের মোট বিক্রি
$m_stmt = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE godown_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$m_stmt->execute([$gid]);
$monthly_sales = $m_stmt->fetch()['total'] ?? 0;

// ৪. মোট বাকী (Total Due) - সব এসআর এর মিলিয়ে
$d_stmt = $conn->prepare("SELECT SUM(due_amount) as total FROM sales WHERE godown_id = ?");
$d_stmt->execute([$gid]);
$total_due = $d_stmt->fetch()['total'] ?? 0;

// ৫. লো স্টক অ্যালার্ট সংখ্যা
$l_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE godown_id = ? AND stock_qty <= alert_qty");
$l_stmt->execute([$gid]);
$low_stock_count = $l_stmt->fetch()['total'];

// --- এক্সপায়ারি সিস্টেম লজিক (স্টক থাকলে কেবল এলার্ট দেখাবে) ---
// মেয়াদ শেষ হওয়া পণ্যের সংখ্যা (মেইন ও ভেরিয়েন্ট মিলিয়ে এবং স্টক > ০)
$exp_stmt = $conn->prepare("
    SELECT (
        (SELECT COUNT(*) FROM products WHERE godown_id = ? AND stock_qty > 0 AND expiry_date <= CURDATE() AND expiry_date IS NOT NULL AND id NOT IN (SELECT product_id FROM product_variants))
        +
        (SELECT COUNT(*) FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.godown_id = ? AND v.stock_qty > 0 AND v.expiry_date <= CURDATE() AND v.expiry_date IS NOT NULL)
    ) as total_expired");
$exp_stmt->execute([$gid, $gid]);
$expired_count = $exp_stmt->fetchColumn();

// ১৫ দিনের মধ্যে মেয়াদ শেষ হবে এমন পণ্যের সংখ্যা (স্টক > ০)
$up_exp_stmt = $conn->prepare("
    SELECT (
        (SELECT COUNT(*) FROM products WHERE godown_id = ? AND stock_qty > 0 AND expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 15 DAY) AND id NOT IN (SELECT product_id FROM product_variants))
        +
        (SELECT COUNT(*) FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.godown_id = ? AND v.stock_qty > 0 AND v.expiry_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 1 DAY) AND DATE_ADD(CURDATE(), INTERVAL 15 DAY))
    ) as total_upcoming");
$up_exp_stmt->execute([$gid, $gid]);
$upcoming_expiry = $up_exp_stmt->fetchColumn();


// --- গ্রাফের জন্য গত ৭ দিনের ডাটা সংগ্রহ ---
$sales_labels = [];
$sales_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales_labels[] = date('D', strtotime($date));
    $chart_stmt = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE godown_id = ? AND DATE(created_at) = ?");
    $chart_stmt->execute([$gid, $date]);
    $sales_values[] = $chart_stmt->fetch()['total'] ?? 0;
}
$js_labels = json_encode($sales_labels);
$js_values = json_encode($sales_values);

$page_title = "এডমিন ড্যাশবোর্ড";
include 'includes/header.php';
?>

<!-- ড্যাশবোর্ড স্ট্যাটাস কার্ডস (৪টি কার্ড) -->
<div class="row g-4">
    <div class="col-md-3">
        <div class="stat-card border-start border-primary border-4">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-primary text-white me-3">
                    <i class="fas fa-cubes"></i>
                </div>
                <div>
                    <small class="text-secondary fw-bold">মোট প্রোডাক্ট</small>
                    <h4 class="fw-bold m-0"><?php echo $total_products; ?> টি</h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-success border-4">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-success text-white me-3">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div>
                    <small class="text-secondary fw-bold">আজকের বিক্রি</small>
                    <h4 class="fw-bold m-0">৳ <?php echo number_format($today_sales); ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-info border-4">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-info text-white me-3">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <small class="text-secondary fw-bold">এই মাসের বিক্রি</small>
                    <h4 class="fw-bold m-0">৳ <?php echo number_format($monthly_sales); ?></h4>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card border-start border-danger border-4">
            <div class="d-flex align-items-center">
                <div class="icon-box bg-danger text-white me-3">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div>
                    <small class="text-secondary fw-bold">মোট বাকী (Due)</small>
                    <h4 class="fw-bold m-0 text-danger">৳ <?php echo number_format($total_due); ?></h4>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- এক্সপায়ারি এলার্ট সেকশন (নতুন) -->
<div class="row mt-4">
    <div class="col-12">
        <?php if($expired_count > 0): ?>
        <div class="alert alert-danger shadow-sm border-0 rounded-4 d-flex align-items-center mb-2" role="alert">
            <i class="fas fa-calendar-times me-3 fa-lg"></i>
            <div>
                <strong>সাবধান!</strong> <?= $expired_count ?> টি পণ্যের মেয়াদ শেষ হয়ে গেছে। 
                <a href="expired_products.php" class="alert-link ms-2 text-decoration-underline">তালিকা দেখুন</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if($upcoming_expiry > 0): ?>
        <div class="alert alert-warning shadow-sm border-0 rounded-4 d-flex align-items-center mb-0" role="alert">
            <i class="fas fa-hourglass-half me-3 fa-lg"></i>
            <div>
                <strong>সতর্কতা!</strong> <?= $upcoming_expiry ?> টি পণ্যের মেয়াদ আগামী ১৫ দিনের মধ্যে শেষ হবে। 
                <a href="expired_products.php?type=upcoming" class="alert-link ms-2 text-decoration-underline">চেক করুন</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- গ্রাফ ও লো স্টক অ্যালার্ট রো -->
<div class="row mt-4 g-4">
    <div class="col-md-8">
        <div class="stat-card shadow-sm">
    <h5 class="fw-bold mb-3"><i class="fas fa-chart-area me-2 text-primary"></i> সেলস অ্যানালিটিক্স (লাস্ট ৭ দিন)</h5>
    <!-- এখানে উচ্চতা ২০০ পিক্সেল ফিক্সড করে দেওয়া হয়েছে -->
    <div style="height: 100;"> 
        <canvas id="adminSalesChart"></canvas>
    </div>
</div>
    </div>
<div class="col-md-4">
        <div class="stat-card bg-warning bg-opacity-10 border border-warning h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-exclamation-triangle me-2 text-danger"></i> লো স্টক সতর্কবার্তা</h5>
            
            <?php
            // ১. ভেরিয়েন্ট সহ লো স্টক কাউন্ট বের করার কুয়েরি
            $count_stmt = $conn->prepare("
                SELECT (
                    (SELECT COUNT(*) FROM products WHERE godown_id = ? AND stock_qty <= alert_qty AND id NOT IN (SELECT product_id FROM product_variants))
                    +
                    (SELECT COUNT(*) FROM product_variants v JOIN products p ON v.product_id = p.id WHERE p.godown_id = ? AND v.stock_qty <= v.alert_qty)
                ) as total_low");
            $count_stmt->execute([$gid, $gid]);
            $low_stock_count = $count_stmt->fetchColumn();
            ?>

            <div class="display-4 fw-bold text-danger mb-2"><?php echo $low_stock_count; ?> <small style="font-size: 20px;">টি প্রোডাক্ট</small></div>
            <p class="text-muted small">নিচের প্রোডাক্টগুলোর স্টক ফুরিয়ে যাচ্ছে, দ্রুত মাল কিনুন।</p>
            <ul class="list-group list-group-flush rounded shadow-sm">
                <?php
                // ২. ভেরিয়েন্ট সহ লো স্টক আইটেম লিস্ট আনার কুয়েরি
                $low_items = $conn->prepare("
                    (SELECT product_name, stock_qty FROM products WHERE godown_id = ? AND stock_qty <= alert_qty AND id NOT IN (SELECT product_id FROM product_variants))
                    UNION ALL
                    (SELECT CONCAT(p.product_name, ' - ', v.variant_name) as product_name, v.stock_qty 
                     FROM product_variants v 
                     JOIN products p ON v.product_id = p.id 
                     WHERE p.godown_id = ? AND v.stock_qty <= v.alert_qty)
                    LIMIT 5
                ");
                $low_items->execute([$gid, $gid]);
                while($item = $low_items->fetch()){
                    echo "<li class='list-group-item d-flex justify-content-between align-items-center py-2'>
                            <span class='small fw-bold'>{$item['product_name']}</span>
                            <span class='badge bg-danger rounded-pill'>{$item['stock_qty']} পিস</span>
                          </li>";
                }
                ?>
            </ul>
            <a href="low_stock_report.php" class="btn btn-outline-dark btn-sm w-100 mt-3 fw-bold">সবগুলো দেখুন</a>
        </div>
    </div>
</div>

<div class="row mt-4 g-4">
    <!-- সাম্প্রতিক সেলস টেবিল -->
    <div class="col-md-8">
        <div class="stat-card">
            <h5 class="fw-bold mb-4"><i class="fas fa-shopping-cart me-2 text-primary"></i> সাম্প্রতিক ৫টি সেলস (সর্বশেষ)</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ইনভয়েস</th>
                            <th>কাস্টমার</th>
                            <th>মোট বিল</th>
                            <th>বাকী (Due)</th>
                            <th class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent = $conn->prepare("SELECT * FROM sales WHERE godown_id = ? ORDER BY id DESC LIMIT 5");
                        $recent->execute([$gid]);
                        while($row = $recent->fetch()){
                            $due_class = ($row['due_amount'] > 0) ? 'text-danger fw-bold' : 'text-success';
                            echo "<tr>
                                    <td><span class='badge bg-secondary'>#{$row['id']}</span></td>
                                    <td><strong>{$row['customer_name']}</strong><br><small class='text-muted'>{$row['customer_phone']}</small></td>
                                    <td>৳ ".number_format($row['payable_amount'])."</td>
                                    <td class='{$due_class}'>৳ ".number_format($row['due_amount'])."</td>
                                    <td class='text-center'><a href='../sr/print_invoice.php?id={$row['id']}' target='_blank' class='btn btn-sm btn-outline-primary'><i class='fas fa-eye'></i></a></td>
                                  </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- কুইক অ্যাকশন -->
    <div class="col-md-4">
        <div class="stat-card text-center h-100">
            <h5 class="fw-bold mb-4">কুইক অ্যাকশন মেনু</h5>
            <a href="global_sales.php" class="btn btn-primary w-100 py-3 mb-3 shadow-sm fw-bold">
                <i class="fas fa-plus-circle me-2"></i> নতুন সেলস করুন
            </a>
            <a href="products.php" class="btn btn-primary w-100 py-3 mb-3 shadow-sm fw-bold">
                <i class="fas fa-plus-circle me-2"></i> নতুন প্রোডাক্ট যোগ করুন
            </a>
            <a href="customer_due.php" class="btn btn-danger w-100 py-3 mb-3 shadow-sm fw-bold">
                <i class="fas fa-hand-holding-usd me-2"></i> বাকী (Due) আদায় করুন
            </a>
            <a href="reports.php" class="btn btn-outline-success w-100 py-3 mb-3 fw-bold">
                <i class="fas fa-file-invoice me-2"></i> সেলস রিপোর্ট দেখুন
            </a>
            <a href="sales_summary.php" class="btn btn-outline-success w-100 py-3 mb-3 fw-bold">
                <i class="fas fa fa-pie-chart"></i> সেলস সামারি রিপোর্ট দেখুন
            </a>
            <a href="expired_products.php" class="btn btn-outline-warning w-100 py-3 mb-3 fw-bold">
                <i class="fas fa-calendar-times me-2"></i> এক্সপায়ারি রিপোর্ট দেখুন
            </a>
            <a href="add_sr.php" class="btn btn-outline-dark w-100 py-3 fw-bold">
                <i class="fas fa-user-plus me-2"></i> নতুন এসআর যোগ করুন
            </a>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('adminSalesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $js_labels; ?>,
            datasets: [{
                label: 'বিক্রি (৳)',
                data: <?php echo $js_values; ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#4e73df'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>