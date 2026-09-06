<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$gid = $_SESSION['godown_id'];

$message = "";

// ১. দোকানের তথ্য ও লোগো আপডেট
if (isset($_POST['update_shop'])) {
    $shop_name = $_POST['shop_name'];
    $owner_name = $_POST['owner_name']; // মালিকের নাম এডিট করার অপশন যোগ করা হলো
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email']; // ইমেইল এডিট করার অপশন
    $logo_name = $_POST['old_logo'];

    if (!empty($_FILES['logo']['name'])) {
        $target_dir = "../assets/uploads/logos/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_ext = pathinfo($_FILES["logo"]["name"], PATHINFO_EXTENSION);
        $logo_name = "shop_logo_" . $gid . "_" . time() . "." . $file_ext;
        move_uploaded_file($_FILES["logo"]["tmp_name"], $target_dir . $logo_name);
    }

    $stmt = $conn->prepare("UPDATE godowns SET shop_name = ?, owner_name = ?, address = ?, phone = ?, email = ?, logo = ? WHERE id = ?");
    $stmt->execute([$shop_name, $owner_name, $address, $phone, $email, $logo_name, $gid]);
    
    // সেশন নাম আপডেট করা যাতে হেডারে পরিবর্তন দেখা যায়
    $_SESSION['user_name'] = $owner_name;
    $_SESSION['shop_name'] = $shop_name;
    
    $message = "<div class='alert alert-success shadow-sm border-0'>দোকানের তথ্য সফলভাবে আপডেট হয়েছে!</div>";
}

// ২. প্রোফাইল ফটো আপডেট (এখন godowns টেবিল আপডেট করবে)
if (isset($_POST['update_profile_pic'])) {
    if (!empty($_FILES['profile_img']['name'])) {
        $target_dir = "../assets/uploads/users/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION);
        $img_name = "admin_pic_" . $gid . "_" . time() . "." . $file_ext;
        
        if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $target_dir . $img_name)) {
            $stmt = $conn->prepare("UPDATE godowns SET image = ? WHERE id = ?");
            $stmt->execute([$img_name, $gid]);
            $message = "<div class='alert alert-info shadow-sm border-0'>প্রোফাইল ছবি আপডেট হয়েছে!</div>";
        }
    }
}

// ৩. পাসওয়ার্ড পরিবর্তন (এখন godowns টেবিল আপডেট করবে)
if (isset($_POST['change_pass'])) {
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE godowns SET password = ? WHERE id = ?");
    $stmt->execute([$new_pass, $gid]);
    $message = "<div class='alert alert-warning shadow-sm border-0'>পাসওয়ার্ড পরিবর্তন সফল হয়েছে!</div>";
}

// বর্তমান ডাটা সরাসরি godowns টেবিল থেকে আনা (এরর ফিক্স করার জন্য)
$stmt = $conn->prepare("SELECT * FROM godowns WHERE id = ?");
$stmt->execute([$gid]);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);

// যদি ডাটা না পাওয়া যায় (প্রতিরক্ষামূলক লজিক)
if (!$shop) {
    die("Error: এডমিন তথ্য খুঁজে পাওয়া যায়নি। দয়া করে লগআউট করে আবার চেষ্টা করুন।");
}

$page_title = "মাস্টার সেটিংস";
include 'includes/header.php';
?>

<div class="row g-4">
    <!-- বাম পাশ: দোকানের সেটিংস -->
    <div class="col-lg-7">
        <div class="stat-card shadow-sm border-0 h-100">
            <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-store me-2"></i> দোকানের সেটিংস (Invoice Branding)</h5>
            <?php echo $message; ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="old_logo" value="<?= $shop['logo'] ?? '' ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="small fw-bold">দোকান/গোডাউনের নাম</label>
                        <input type="text" name="shop_name" class="form-control" value="<?= htmlspecialchars($shop['shop_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">মালিকের নাম</label>
                        <input type="text" name="owner_name" class="form-control" value="<?= htmlspecialchars($shop['owner_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">ইমেইল (লগইন আইডি)</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($shop['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">দোকানের মোবাইল</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($shop['phone']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small fw-bold">দোকানের লোগো (Invoice-এ দেখাবে)</label>
                        <input type="file" name="logo" class="form-control" accept="image/*">
                    </div>
                    <div class="col-12">
                        <label class="small fw-bold">দোকানের ঠিকানা</label>
                        <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($shop['address']) ?></textarea>
                    </div>
                    
                    <div class="col-12 mt-4">
                        <div class="p-3 bg-light rounded text-center border">
                            <small class="d-block mb-2 text-muted fw-bold">বর্তমান দোকানের লোগো প্রিভিউ</small>
                            <?php if(!empty($shop['logo']) && file_exists("../assets/uploads/logos/".$shop['logo'])): ?>
                                <img src="../assets/uploads/logos/<?= $shop['logo'] ?>" style="max-height: 80px;">
                            <?php else: ?>
                                <span class="text-muted">কোনো লোগো নেই</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12 text-end">
                        <button type="submit" name="update_shop" class="btn btn-primary fw-bold shadow-sm px-4">দোকানের তথ্য সেভ করুন</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ডান পাশ: প্রোফাইল এবং সিকিউরিটি -->
    <div class="col-lg-5">
        <!-- প্রোফাইল ছবি কার্ড -->
        <div class="stat-card shadow-sm border-0 mb-4 text-center">
            <h6 class="fw-bold mb-4 text-success">আপনার প্রোফাইল ফটো</h6>
            <div class="mb-3 position-relative d-inline-block">
                <?php 
                    $user_pic = "../assets/uploads/users/" . ($shop['image'] ?? '');
                    if(!empty($shop['image']) && file_exists($user_pic)): 
                ?>
                    <img src="<?= $user_pic ?>" class="rounded-circle border border-4 border-white shadow" style="width: 130px; height: 130px; object-fit: cover;">
                <?php else: ?>
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($shop['owner_name']) ?>&size=130&background=random&color=fff" class="rounded-circle shadow">
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" class="mt-3">
                <input type="file" name="profile_img" class="form-control form-control-sm mb-2" accept="image/*" required>
                <button type="submit" name="update_profile_pic" class="btn btn-success btn-sm w-100 fw-bold">প্রোফাইল ছবি পরিবর্তন করুন</button>
            </form>
        </div>

        <!-- সিকিউরিটি কার্ড -->
        <div class="stat-card shadow-sm border-0">
            <h6 class="fw-bold mb-3 text-danger"><i class="fas fa-lock me-2"></i> সিকিউরিটি সেটিংস</h6>
            <form method="POST">
                <div class="mb-3">
                    <label class="small fw-bold">নতুন পাসওয়ার্ড</label>
                    <input type="password" name="new_password" class="form-control" placeholder="নতুন পাসওয়ার্ড দিন" required minlength="6">
                </div>
                <button type="submit" name="change_pass" class="btn btn-outline-danger w-100 btn-sm fw-bold">পাসওয়ার্ড আপডেট করুন</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>