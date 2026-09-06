<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$uid = $_SESSION['user_id'];
$gid = $_SESSION['godown_id'];

// ১. স্ট্যাটিস্টিকস ডাটা সংগ্রহ
$today = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE()");
$today->execute([$uid]);
$today_sales = $today->fetch()['total'] ?? 0;

$month = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE user_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE())");
$month->execute([$uid]);
$month_sales = $month->fetch()['total'] ?? 0;

$due = $conn->prepare("SELECT SUM(due_amount) as total FROM sales WHERE user_id = ?");
$due->execute([$uid]);
$total_due = $due->fetch()['total'] ?? 0;

$orders = $conn->prepare("SELECT COUNT(id) as total FROM sales WHERE user_id = ?");
$orders->execute([$uid]);
$total_orders = $orders->fetch()['total'] ?? 0;

// --- গ্রাফের ডাটা ---
$sales_labels = []; $sales_values = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $sales_labels[] = date('D', strtotime($date));
    $chart_stmt = $conn->prepare("SELECT SUM(payable_amount) as total FROM sales WHERE user_id = ? AND DATE(created_at) = ?");
    $chart_stmt->execute([$uid, $date]);
    $sales_values[] = $chart_stmt->fetch()['total'] ?? 0;
}
$js_labels = json_encode($sales_labels);
$js_values = json_encode($sales_values);

$page_title = "SR ড্যাশবোর্ড";
include 'includes/header.php'; // এখানে হেডার ও সাইডবার চলে আসবে
?>

<!-- Stats Cards -->
<div class="row g-4">
    <div class="col-md-3">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-box bg-primary text-white me-3"><i class="fas fa-calendar-day"></i></div>
            <div>
                <small class="text-secondary">আজকের বিক্রি</small>
                <h5 class="fw-bold m-0">৳ <?php echo number_format($today_sales); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-box bg-success text-white me-3"><i class="fas fa-chart-line"></i></div>
            <div>
                <small class="text-secondary">এই মাসের বিক্রি</small>
                <h5 class="fw-bold m-0">৳ <?php echo number_format($month_sales); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-box bg-danger text-white me-3"><i class="fas fa-hand-holding-usd"></i></div>
            <div>
                <small class="text-secondary">মোট বাকী (Due)</small>
                <h5 class="fw-bold m-0">৳ <?php echo number_format($total_due); ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card d-flex align-items-center">
            <div class="icon-box bg-warning text-dark me-3"><i class="fas fa-shopping-bag"></i></div>
            <div>
                <small class="text-secondary">মোট অর্ডার</small>
                <h5 class="fw-bold m-0"><?php echo $total_orders; ?> টি</h5>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4 g-4">
    <div class="col-md-8">
        <div class="stat-card">
            <h5><i class="fas fa-chart-area me-2 text-primary"></i> সেলস অ্যানালিটিক্স (লাস্ট ৭ দিন)</h5>
            <canvas id="salesChart" height="150"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card h-100 text-center">
            <h5>কুইক অ্যাকশন</h5>
            <a href="new_sale.php" class="btn btn-primary w-100 py-3 mb-3 mt-3 shadow">
                <i class="fas fa-cart-plus me-2"></i> নতুন অর্ডার নিন
            </a>
            <div class="mt-4 p-3 bg-light rounded text-start text-dark">
                <small class="fw-bold text-danger"><i class="fas fa-info-circle"></i> লো-স্টক অ্যালার্ট:</small>
                <ul class="small mb-0 mt-2">
                    <?php
                    $low_stock = $conn->prepare("SELECT product_name, stock_qty FROM products WHERE godown_id = ? AND stock_qty <= alert_qty LIMIT 3");
                    $low_stock->execute([$gid]);
                    while($ls = $low_stock->fetch()){
                        echo "<li>{$ls['product_name']} ({$ls['stock_qty']} পিস বাকি)</li>";
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo $js_labels; ?>,
            datasets: [{
                label: 'বিক্রি (৳)',
                data: <?php echo $js_values; ?>, 
                borderColor: '#4e73df',
                tension: 0.4,
                fill: true,
                backgroundColor: 'rgba(78, 115, 223, 0.1)'
            }]
        },
        options: { 
            responsive: true, 
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
</script>

<?php include 'includes/footer.php'; // এখানে ফুটার ও স্ক্রিপ্ট চলে আসবে ?>