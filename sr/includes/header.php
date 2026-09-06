<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// লগইন এবং রোল চেক (এসআর কি না)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    header("Location: ../index.php");
    exit();
}

// মেয়াদ (Expiry) ও এক্সেস চেক ফাংশন কল
if (function_exists('checkAccess')) {
    checkAccess($conn);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'SR প্যানেল'; ?> | StockPro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Anek+Bangla:wght@100..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root { --primary: #4e73df; --bg: #f8f9fc; --card: #ffffff; --text: #333; --sidebar: #224abe; }
        [data-theme="dark"] { --bg: #1a202c; --card: #2d3748; --text: #e2e8f0; --sidebar: #111827; }
        
        body { font-family: 'Anek Bangla', 'Poppins', 'Hind Siliguri', sans-serif; background-color: var(--bg); color: var(--text); transition: 0.3s; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { height: 100vh; width: 250px; position: fixed; left: 0; top: 0; background: var(--sidebar); color: white; padding-top: 20px; z-index: 1050; transition: all 0.3s; }
        .sidebar a { padding: 15px 25px; display: block; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #fff; }
        
        .main-content { margin-left: 250px; padding: 25px; min-height: 100vh; transition: all 0.3s; }
        
        /* Mobile View Styles */
        @media (max-width: 768px) {
            .sidebar { left: -250px; } /* Hide sidebar off-screen */
            .sidebar.active { left: 0; } /* Show sidebar */
            .main-content { margin-left: 0; }
            .overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; }
            .overlay.active { display: block; }
        }

        .stat-card { background: var(--card); border: none; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .icon-box { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body data-theme="light">

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="text-center mb-4">
            <h4><i class="fas fa-user-tie"></i> StockPro</h4>
            <small>এসআর প্যানেল</small>
        </div>
        <hr>
        <?php $current_page = basename($_SERVER['PHP_SELF']); ?>
        <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt me-2"></i> ড্যাশবোর্ড</a>
        <a href="new_sale.php" class="<?= $current_page == 'new_sale.php' ? 'active' : '' ?>"><i class="fas fa-cart-plus me-2"></i> নতুন সেলস</a>
        <a href="sales_history.php" class="<?= $current_page == 'sales_history.php' ? 'active' : '' ?>"><i class="fas fa-history me-2"></i> সেলস হিস্ট্রি</a>
        <a href="due_collection.php" class="<?= $current_page == 'due_collection.php' ? 'active' : '' ?>"><i class="fas fa-hand-holding-usd me-2"></i> বাকী আদায়</a>
        <a href="products.php" class="<?= $current_page == 'products.php' ? 'active' : '' ?>"><i class="fas fa-box-open me-2"></i> প্রোডাক্ট লিস্ট</a>
        <a href="../logout.php" class="text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> লগআউট</a>
    </div>

    <!-- Main Content Start -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <!-- Mobile Menu Toggle Button -->
                <button class="btn btn-primary d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h4 class="m-0 fw-bold">
                    <?php 
                        // বর্তমান ফাইলের নাম অনুযায়ী টাইটেল ঠিক করা
                        $current_file = basename($_SERVER['PHP_SELF']);
                        
                        if ($current_file == 'dashboard.php') {
                            // ড্যাশবোর্ডে থাকলে স্বাগতম জানাবে
                            echo "স্বাগতম, " . htmlspecialchars($_SESSION['user_name']) . " 👋";
                        } else {
                            // অন্য পেজে থাকলে সেই পেজের নাম দেখাবে
                            echo isset($page_title) ? $page_title : 'StockPro';
                        }
                    ?>
                </h4>
            </div>
            <button onclick="toggleGlobalTheme()" class="btn btn-outline-secondary btn-sm" id="theme-btn">
                <i class="fas fa-moon"></i>
            </button>
        </div>