<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') { 
    header("Location: ../index.php"); exit(); 
}

if (isset($_POST['add'])) {
    $shop_name   = $_POST['shop_name'];
    $owner_name  = $_POST['owner_name'];
    $email       = $_POST['email'];
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone       = $_POST['phone'];
    $address     = $_POST['address'];
    $expiry_date = $_POST['expiry_date'];
    $role        = 'admin'; // ডিফল্ট রোল

    try {
        $stmt = $conn->prepare("INSERT INTO godowns (shop_name, owner_name, email, password, phone, address, expiry_date, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$shop_name, $owner_name, $email, $password, $phone, $address, $expiry_date, $role]);
        
        header("Location: index.php?msg=added"); 
        exit();
    } catch (PDOException $e) {
        // যদি ডাটাবেসে কলাম না থাকে বা অন্য সমস্যা হয়
        die("ডাটাবেস এরর: " . $e->getMessage());
    }
}

$page_title = "নতুন এডমিন যোগ";
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="stat-card p-4 bg-white rounded shadow-sm col-md-8 mx-auto">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0 text-primary">নতুন এডমিন/দোকান যোগ করুন</h4>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">ফিরে যান</a>
        </div>
        
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-bold">দোকানের নাম</label>
                <input type="text" name="shop_name" class="form-control" placeholder="যেমন: জনতা স্টোর" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">মালিকের নাম</label>
                <input type="text" name="owner_name" class="form-control" placeholder="পুরো নাম" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">ইমেইল (লগইন আইডি)</label>
                <input type="email" name="email" class="form-control" placeholder="example@mail.com" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">পাসওয়ার্ড</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">ফোন নাম্বার</label>
                <input type="text" name="phone" class="form-control" placeholder="017xxxxxxxx" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold">মেয়াদ (তারিখ)</label>
                <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label fw-bold">ঠিকানা</label>
                <textarea name="address" class="form-control" rows="2" placeholder="দোকানের ঠিকানা লিখুন"></textarea>
            </div>
            <div class="col-12 mt-4">
                <button type="submit" name="add" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">সাকসেসফুলি এড করুন</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>