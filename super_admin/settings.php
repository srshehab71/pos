<?php
$page_title = "প্রোফাইল সেটিংস";
include 'includes/header.php';

$admin_id = $_SESSION['user_id']; // সেশন থেকে আইডি নেওয়া

// আপডেট প্রসেসিং
if (isset($_POST['update_profile'])) {
    $name    = $_POST['name'];
    $email   = $_POST['email'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];
    $password = $_POST['password'];

    // ১. ইমেজ হ্যান্ডলিং
    $image_query = "";
    if (!empty($_FILES['profile_image']['name'])) {
        $target_dir = "..assets/uploads/superadmin/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
        $file_name = "admin_" . $admin_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $image_query = ", image = '$file_name'";
        }
    }

    // ২. পাসওয়ার্ড হ্যান্ডলিং
    $pass_query = "";
    if (!empty($password)) {
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $pass_query = ", password = '$hashed_pass'";
    }

    // ৩. ফাইনাল আপডেট কুয়েরি
    $sql = "UPDATE users SET name=?, email=?, phone=?, address=? $image_query $pass_query WHERE id=?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$name, $email, $phone, $address, $admin_id])) {
        echo "<script>Swal.fire('সফল!', 'আপনার প্রোফাইল আপডেট হয়েছে', 'success');</script>";
    } else {
        echo "<div class='alert alert-danger'>আপডেট করতে সমস্যা হয়েছে!</div>";
    }
}

// বর্তমান ডাটা ফেচ
$user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$admin_id]);
$data = $user->fetch();

$profile_pic = !empty($data['image']) ? '../assets/uploads/superadmin/' . $data['image'] : '../assets/img/default-user.png';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- বাম পাশ: প্রোফাইল প্রিভিউ -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <div class="mt-3 mb-4">
                        <img src="<?= $profile_pic ?>" class="rounded-circle img-thumbnail shadow-sm" style="width: 150px; height: 150px; object-fit: cover;">
                    </div>
                    <h4 class="mb-0"><?= $data['name'] ?></h4>
                    <span class="badge bg-danger mb-3">সুপার অ্যাডমিন</span>
                    <p class="text-muted small"><i class="fas fa-envelope"></i> <?= $data['email'] ?></p>
                    <hr>
                    <div class="row text-start ps-3">
                        <div class="col-12 mb-2"><strong>ফোন:</strong> <span class="text-muted"><?= $data['phone'] ?? 'N/A' ?></span></div>
                        <div class="col-12 mb-2"><strong>ঠিকানা:</strong> <span class="text-muted"><?= $data['address'] ?? 'N/A' ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ডান পাশ: এডিট ফর্ম -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-edit"></i> বিস্তারিত তথ্য পরিবর্তন করুন</h6>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <!-- ব্যক্তিগত তথ্য -->
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 fw-bold">পুরো নাম</label>
                                <input class="form-control border-left-primary" name="name" type="text" value="<?= $data['name'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 fw-bold">ইমেইল এড্রেস</label>
                                <input class="form-control border-left-primary" name="email" type="email" value="<?= $data['email'] ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 fw-bold">মোবাইল নাম্বার</label>
                                <input class="form-control border-left-primary" name="phone" type="text" value="<?= $data['phone'] ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="small mb-1 fw-bold">প্রোফাইল ছবি পরিবর্তন</label>
                                <input class="form-control" name="profile_image" type="file" accept="image/*">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small mb-1 fw-bold">বর্তমান ঠিকানা</label>
                            <textarea class="form-control border-left-primary" name="address" rows="3"><?= $data['address'] ?></textarea>
                        </div>

                        <div class="bg-light p-3 rounded mb-4">
                            <h6 class="text-danger fw-bold"><i class="fas fa-lock"></i> সিকিউরিটি সেটিংস</h6>
                            <div class="row">
                                <div class="col-md-12 mt-2">
                                    <label class="small mb-1">নতুন পাসওয়ার্ড (পরিবর্তন না করলে খালি রাখুন)</label>
                                    <input class="form-control" name="password" type="password" placeholder="সর্বনিম্ন ৬ ডিজিট দিন">
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button class="btn btn-primary shadow-sm px-5" name="update_profile" type="submit">
                                <i class="fas fa-save me-1"></i> তথ্য সংরক্ষণ করুন
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .border-left-primary { border-left: 4px solid #4e73df !important; }
    .card { border: none; border-radius: 10px; }
    .img-thumbnail { border: 3px solid #f8f9fc; }
</style>

<?php include 'includes/footer.php'; ?>