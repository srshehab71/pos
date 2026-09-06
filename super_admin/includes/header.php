<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// সুপার এডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'সুপার এডমিন'; ?> | StockPro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;600&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root { --primary: #6610f2; --bg: #f4f7f6; --card: #ffffff; --text: #333; --sidebar: #140042; }
        [data-theme="dark"] { --bg: #1a1a1a; --card: #2d2d2d; --text: #e0e0e0; --sidebar: #000000; }
        
        body { font-family: 'Poppins', 'Hind Siliguri', sans-serif; background-color: var(--bg); color: var(--text); transition: 0.3s; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { height: 100vh; width: 260px; position: fixed; left: 0; top: 0; background: var(--sidebar); color: white; padding-top: 20px; z-index: 1050; transition: all 0.3s; }
        .sidebar a { padding: 15px 25px; display: block; color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; border-left: 4px solid #fff; }
        
        .main-content { margin-left: 260px; padding: 25px; min-height: 100vh; transition: all 0.3s; }
        
        /* Mobile Layout */
        @media (max-width: 768px) {
            .sidebar { left: -260px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; }
            .overlay.active { display: block; }
        }

        .stat-card { background: var(--card); border: none; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
    </style>
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body data-theme="light">

    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="sidebar">
        <div class="text-center mb-4">
            <h4 class="fw-bold"><i class="fas fa-user-shield"></i> Control Hub</h4>
            <small class="opacity-50">সুপার এডমিন প্যানেল</small>
        </div>
        <hr>
        <?php $current = basename($_SERVER['PHP_SELF']); ?>
        <a href="index.php" class="<?= $current == 'index.php' ? 'active' : '' ?>"><i class="fas fa-th-large me-2"></i> সব গোডাউন মালিক</a>
        <a href="packages.php" class="<?= $current == 'packages.php' ? 'active' : '' ?>"><i class="fas fa-box-open me-2"></i> প্যাকেজ সেটিংস</a>
        <a href="revenue.php" class="<?= $current == 'revenue.php' ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar me-2"></i> আয় রিপোর্ট</a>
        <a href="transactions.php" class="<?= $current == 'transactions.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart me-2"></i> ট্রানজেকশন হিস্ট্রি</a>
        <a href="manage_godowns.php" class="<?= $current == 'manage_godowns.php' ? 'active' : '' ?>"><i class="fas fa-users me-2"></i> এডমিন ম্যানেজমেন্ট</a>
        <a href="bkash_setup.php" class="<?= $current == 'bkash_setup.php' ? 'active' : '' ?>"><i class="fas fa-mobile-alt me-2"></i> বিকাশ সেটিংস</a>
        <a href="backup.php" class="<?= $current == 'backup.php' ? 'active' : '' ?>"><i class="fas fa-mobile-alt me-2"></i> ডাটাবেস ব্যাকআপ (SQL)</a>
        <a href="settings.php" class="<?= $current == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog me-2"></i> সেটিংস</a>
        <a href="../logout.php" class="text-danger mt-5"><i class="fas fa-power-off me-2"></i> লগআউট</a>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-primary d-md-none me-3" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <h4 class="m-0 fw-bold">
                    <?php 
                    if (basename($_SERVER['PHP_SELF']) == 'index.php') echo "মাস্টার ড্যাশবোর্ড 👋";
                    else echo isset($page_title) ? $page_title : 'Control Hub';
                    ?>
                </h4>
            </div>
            <div class="btn-group">
            <a href="notice.php" class="btn btn-sm btn-outline-primary"><i class="fas fa-bullhorn me-1"></i> নোটিশ দিন</a>
            <button onclick="toggleGlobalTheme()" class="btn btn-outline-secondary btn-sm" id="theme-btn"><i class="fas fa-moon"></i></button>
        </div>
        </div>