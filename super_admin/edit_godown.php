<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') { header("Location: ../index.php"); exit(); }

$id = $_GET['id'];
if (isset($_POST['update'])) {
    if (!empty($_POST['password'])) {
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $conn->prepare("UPDATE godowns SET shop_name=?, owner_name=?, email=?, password=?, phone=?, address=?, expiry_date=? WHERE id=?")
             ->execute([$_POST['shop_name'], $_POST['owner_name'], $_POST['email'], $pass, $_POST['phone'], $_POST['address'], $_POST['expiry_date'], $id]);
    } else {
        $conn->prepare("UPDATE godowns SET shop_name=?, owner_name=?, email=?, phone=?, address=?, expiry_date=? WHERE id=?")
             ->execute([$_POST['shop_name'], $_POST['owner_name'], $_POST['email'], $_POST['phone'], $_POST['address'], $_POST['expiry_date'], $id]);
    }
    header("Location: index.php?msg=updated"); exit();
}

$g = $conn->prepare("SELECT * FROM godowns WHERE id = ?");
$g->execute([$id]);
$data = $g->fetch();
$page_title = "তথ্য এডিট";
include 'includes/header.php';
?>
<div class="stat-card p-4 bg-white rounded shadow-sm col-md-8 mx-auto">
    <h4 class="fw-bold mb-4">তথ্য এডিট: <?= $data['shop_name'] ?></h4>
    <form method="POST" class="row g-3">
        <div class="col-md-6"><label>দোকানের নাম</label><input type="text" name="shop_name" class="form-control" value="<?= $data['shop_name'] ?>" required></div>
        <div class="col-md-6"><label>মালিকের নাম</label><input type="text" name="owner_name" class="form-control" value="<?= $data['owner_name'] ?>" required></div>
        <div class="col-md-6"><label>ইমেইল</label><input type="email" name="email" class="form-control" value="<?= $data['email'] ?>" required></div>
        <div class="col-md-6"><label>পাসওয়ার্ড (না বদলালে খালি রাখুন)</label><input type="password" name="password" class="form-control"></div>
        <div class="col-md-6"><label>ফোন</label><input type="text" name="phone" class="form-control" value="<?= $data['phone'] ?>" required></div>
        <div class="col-md-6"><label>মেয়াদ শেষ</label><input type="date" name="expiry_date" class="form-control" value="<?= $data['expiry_date'] ?>"></div>
        <div class="col-12"><label>ঠিকানা</label><textarea name="address" class="form-control"><?= $data['address'] ?></textarea></div>
        <div class="col-12"><button type="submit" name="update" class="btn btn-success w-100 fw-bold">আপডেট সেভ করুন</button></div>
    </form>
</div>
<?php include 'includes/footer.php'; ?>