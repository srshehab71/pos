<div class="sidebar">
    <div class="text-center mb-4">
        <h4><i class="fas fa-user-tie"></i> StockPro</h4>
        <small>এসআর প্যানেল</small>
    </div>
    <hr>
    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt me-2"></i> ড্যাশবোর্ড
    </a>
    <a href="new_sale.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'new_sale.php' ? 'active' : ''; ?>">
        <i class="fas fa-cart-plus me-2"></i> নতুন সেলস
    </a>
    <a href="sales_history.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'sales_history.php' ? 'active' : ''; ?>">
        <i class="fas fa-history me-2"></i> সেলস হিস্ট্রি
    </a>
    <a href="../logout.php" class="text-danger mt-5">
        <i class="fas fa-sign-out-alt me-2"></i> লগআউট
    </a>
</div>