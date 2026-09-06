<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// লগইন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
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
    <title><?php echo isset($page_title) ? $page_title : 'এডমিন ড্যাশবোর্ড'; ?> | StockPro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Anek+Bangla:wght@100..800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root { --primary: #4e73df; --bg: #f8f9fc; --card: #ffffff; --text: #333; --sidebar: #224abe; }
        [data-theme="dark"] { --bg: #1a202c; --card: #2d3748; --text: #e2e8f0; --sidebar: #111827; }
        
        body { font-family: 'Anek Bangla', 'Poppins', sans-serif; background-color: var(--bg); color: var(--text); transition: 0.3s; overflow-x: hidden; margin: 0; }
        
        /* Sidebar - Hide Scrollbar but keep functionality */
        .sidebar { 
            height: 100vh; 
            width: 250px; 
            position: fixed; 
            left: 0; 
            top: 0; 
            background: var(--sidebar); 
            color: white; 
            padding-top: 20px; 
            z-index: 1050; 
            transition: all 0.3s; 
            
            overflow-y: auto; /* স্ক্রল হবে */
            overflow-x: hidden;
            
            /* Firefox and Edge/IE scrollbar hide */
            -ms-overflow-style: none;  
            scrollbar-width: none;     
        }

        /* Chrome, Safari এবং Opera এর জন্য স্ক্রলবার হাইড */
        .sidebar::-webkit-scrollbar { 
            display: none; 
        }

        .sidebar a { padding: 15px 25px; display: block; color: rgba(255,255,255,0.8); text-decoration: none; transition: 0.3s; font-size: 15px; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; border-left: 4px solid #fff; }
        
        .main-content { margin-left: 250px; padding: 25px; min-height: 100vh; transition: all 0.3s; }
        
        @media (max-width: 768px) {
            .sidebar { left: -250px; }
            .sidebar.active { left: 0; }
            .main-content { margin-left: 0; }
            .overlay { display: none; position: fixed; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1040; }
            .overlay.active { display: block; }
        }

        .stat-card { background: var(--card); border: none; border-radius: 15px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; }
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
            <h4 class="fw-bold mb-0"><i class="fas fa-boxes-stacked"></i> StockPro</h4>
            <small class="opacity-75">মালিক প্যানেল</small>
        </div>
        <hr class="mx-3 opacity-25">
        
        <?php $current = basename($_SERVER['PHP_SELF']); ?>
        <a href="dashboard.php" class="<?= $current == 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt me-2"></i> ড্যাশবোর্ড</a>
        <a href="global_sales.php" class="<?= $current == 'global_sales.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart me-2"></i> নতুন সেলস</a>
        <a href="product_groups.php" class="<?= $current == 'product_groups.php' ? 'active' : '' ?>"><i class="fas fa-th-large me-2"></i> গ্রুপ ম্যানেজমেন্ট</a>
        <a href="categories.php" class="<?= $current == 'categories.php' ? 'active' : '' ?>"><i class="fas fa-list me-2"></i> ক্যাটাগরি</a>
        <a href="purchase.php" class="<?= $current == 'purchase.php' ? 'active' : '' ?>"><i class="fas fa-cart-plus me-2"></i> প্রোডাক্ট ক্রয়</a>
        <a href="purchase_list.php" class="<?= $current == 'purchase_list.php' ? 'active' : '' ?>"><i class="fas fa-cart-plus me-2"></i> প্রোডাক্ট ক্রয় লিস্ট</a>
        <a href="products.php" class="<?= $current == 'products.php' ? 'active' : '' ?>"><i class="fas fa-box-open me-2"></i> প্রোডাক্ট লিস্ট</a>
        <a href="sales.php" class="<?= $current == 'sales.php' ? 'active' : '' ?>"><i class="fas fa-shopping-cart me-2"></i> সেলস হিস্ট্রি</a>
        <a href="sales_summary.php" class="<?= $current == 'sales_summary.php' ? 'active' : '' ?>"><i class="fas fa fa-pie-chart"></i> সেলস সামারি</a>
        <a href="customer_due.php" class="<?= $current == 'customer_due.php' ? 'active' : '' ?>"><i class="fas fa-hand-holding-usd me-2"></i> বাকীর হিস্ট্রি</a>
        <a href="suppliers.php" class="<?= $current == 'suppliers.php' ? 'active' : '' ?>"><i class="fas fa-user-tie me-2"></i> সাপ্লায়ার তালিকা</a>
        <a href="customers.php" class="<?= $current == 'customers.php' ? 'active' : '' ?>"><i class="fas fa-users me-2"></i> কাস্টমার ম্যানেজ</a>
        <a href="add_sr.php" class="<?= $current == 'add_sr.php' ? 'active' : '' ?>"><i class="fas fa-user-tag me-2"></i> এসআর ম্যানেজ</a>
        <a href="reports.php" class="<?= $current == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-line me-2"></i> রিপোর্টস</a>
        <a href="settings.php" class="<?= $current == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog me-2"></i> সেটিংস</a>
        <a href="../logout.php" class="text-danger mt-3 border-top pt-3"><i class="fas fa-sign-out-alt me-2"></i> লগআউট</a>
    </div>

<div class="main-content">

<!-- সুপার এডমিন ইমপারসোনেশন বার -->
<?php if(isset($_SESSION['is_impersonating'])): ?>
    <div class="alert alert-info py-1 px-3 mb-0 text-center rounded-0 border-0 shadow-sm" style="background: #e3f2fd; color: #0d47a1; position: sticky; top: 0; z-index: 2000;">
        <i class="fas fa-user-secret me-2"></i> আপনি এখন এডমিন হিসেবে আছেন। 
        <a href="../super_admin/stop_impersonate.php" class="fw-bold text-decoration-none border-start ps-2 ms-2">সুপার এডমিন প্যানেলে ফিরুন</a>
    </div>
<?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="btn btn-primary d-md-none me-3" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h4 class="m-0 fw-bold">
                    <?php 
                        $current_file = basename($_SERVER['PHP_SELF']);
                        if ($current_file == 'dashboard.php') {
                            echo "স্বাগতম, " . htmlspecialchars($_SESSION['user_name']) . " 👋";
                        } else {
                            echo isset($page_title) ? $page_title : 'StockPro';
                        }
                    ?>
                </h4>
            </div>
            
            <div class="d-flex align-items-center">
                <button onclick="toggleGlobalTheme()" class="btn btn-outline-secondary btn-sm me-3 border-0" id="theme-btn">
                    <i class="fas fa-moon"></i>
                </button>

                <!-- প্রোফাইল ছবি ও নাম প্রদর্শন -->
                <?php 
    $u_id = $_SESSION['user_id'];
    // আমরা ডাটাবেস থেকে নাম (owner_name) এবং ছবি (image) দুটোই নিয়ে আসছি
    $u_stmt = $conn->prepare("SELECT owner_name, image FROM godowns WHERE id = ?");
    $u_stmt->execute([$u_id]);
    $u_info = $u_stmt->fetch(PDO::FETCH_ASSOC);
    
    $display_name = ($u_info) ? $u_info['owner_name'] : ($_SESSION['user_name'] ?? 'Admin');
    $user_pic_name = ($u_info) ? $u_info['image'] : '';

    // ছবির আসল লোকেশন পাথ
    $user_pic_path = "../assets/uploads/users/" . $user_pic_name;

    // চেক করছি: ডাটাবেসে ছবির নাম আছে কি না এবং সেই ফাইলটি ফোল্ডারে আছে কি না
    if(!empty($user_pic_name) && file_exists($user_pic_path)): 
?>
    <!-- যদি ছবি পাওয়া যায় তবে সেটি দেখাবে -->
    <img src="<?= $user_pic_path ?>" class="rounded-circle shadow-sm border" width="38" height="38" style="object-fit: cover;">
<?php else: ?>
    <!-- যদি ছবি না থাকে তবে আগের মতো অক্ষরের লোগো দেখাবে -->
    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_name); ?>&background=4e73df&color=fff" class="rounded-circle shadow-sm" width="38" height="38">
<?php endif; ?>
            </div>
        </div>
        <!-- নোটিশ ব্রডকাস্ট সেকশন শুরু -->
<?php 
// ডাটাবেস থেকে সক্রিয় নোটিশটি নিয়ে আসা
$broadcast_notice = $conn->query("SELECT message FROM broadcasts WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();

if($broadcast_notice): 
?>
    <div class="container-fluid mt-2">
        <div class="alert alert-warning alert-dismissible fade show" role="alert" style="background-color: #fff3cd; border-left: 5px solid #ffc107;">
            <strong><i class="fas fa-bullhorn"></i></strong> 
            <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();" style="display: inline-block; width: 90%; vertical-align: middle;">
                <?= htmlspecialchars($broadcast_notice['message']) ?>
            </marquee>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
<!-- নোটিশ ব্রডকাস্ট সেকশন শেষ -->
