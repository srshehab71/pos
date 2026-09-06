<?php
$page_title = "নোটিশ বোর্ড";
include 'includes/header.php';

if(isset($_POST['post_notice'])){
    $msg = $_POST['message'];
    $conn->query("UPDATE broadcasts SET status = 'inactive'"); // আগের গুলো ইনঅ্যাক্টিভ করা
    $conn->prepare("INSERT INTO broadcasts (message) VALUES (?)")->execute([$msg]);
}

$current_notice = $conn->query("SELECT * FROM broadcasts WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetch();
?>

<div class="row">
    <div class="col-md-6">
        <div class="stat-card">
            <h5>নতুন নোটিশ ব্রডকাস্ট করুন</h5>
            <form method="POST">
                <textarea name="message" class="form-control mb-3" rows="4" placeholder="আপনার মেসেজ লিখুন যা সবাই দেখবে..." required></textarea>
                <button name="post_notice" class="btn btn-primary w-100 fw-bold">ব্রডকাস্ট নোটিশ</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="stat-card bg-light">
            <h5>বর্তমানে সক্রিয় নোটিশ:</h5>
            <div class="alert alert-info border-0 shadow-sm mt-3">
                <?= $current_notice['message'] ?? 'কোনো সক্রিয় নোটিশ নেই।' ?>
            </div>
            <a href="notice_stop.php" class="btn btn-sm btn-danger mt-2">নোটিশ বন্ধ করুন</a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>