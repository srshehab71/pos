<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') { header("Location: ../index.php"); exit(); }

$msg = "";
// সেটিংস সেভ করা
if (isset($_POST['save_settings'])) {
    $app_key = $_POST['app_key'];
    $app_secret = $_POST['app_secret'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $is_live = isset($_POST['is_bkash_live']) ? 1 : 0;

    // চেক করা আগে কোনো ডাটা আছে কিনা
    $check = $conn->query("SELECT id FROM bkash_settings WHERE id = 1")->fetch();
    
    if ($check) {
        $stmt = $conn->prepare("UPDATE bkash_settings SET app_key=?, app_secret=?, username=?, password=?, is_bkash_live=? WHERE id=1");
        $stmt->execute([$app_key, $app_secret, $username, $password, $is_live]);
    } else {
        $stmt = $conn->prepare("INSERT INTO bkash_settings (id, app_key, app_secret, username, password, is_bkash_live) VALUES (1, ?, ?, ?, ?, ?)");
        $stmt->execute([$app_key, $app_secret, $username, $password, $is_live]);
    }
    $msg = "<div class='alert alert-success'>সেটিংস সফলভাবে সেভ হয়েছে!</div>";
}

// বর্তমান সেটিংস আনা
$settings = $conn->query("SELECT * FROM bkash_settings WHERE id = 1")->fetch();
$page_title = "বিকাশ মার্চেন্ট সেটিংস";
include 'includes/header.php';
?>

<div class="stat-card p-4 bg-white rounded shadow-sm col-md-8 mx-auto">
    <h4 class="fw-bold mb-4">বিকাশ মার্চেন্ট এপিআই সেটিংস</h4>
    <?= $msg ?>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-bold">App Key</label>
            <input type="text" name="app_key" class="form-control" value="<?= $settings['app_key'] ?? '' ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">App Secret</label>
            <input type="text" name="app_secret" class="form-control" value="<?= $settings['app_secret'] ?? '' ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Username</label>
            <input type="text" name="username" class="form-control" value="<?= $settings['username'] ?? '' ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Password</label>
            <input type="password" name="password" class="form-control" value="<?= $settings['password'] ?? '' ?>" required>
        </div>
        <div class="mb-4 form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_bkash_live" id="liveMode" <?= ($settings['is_bkash_live'] ?? 0) == 1 ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="liveMode">Live Mode চালু করুন (বন্ধ থাকলে Sandbox মোডে কাজ করবে)</label>
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary w-100 fw-bold">সেভিংস আপডেট করুন</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>