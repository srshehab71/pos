<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

$message = "";
$error = "";

// ১. নতুন এসআর যোগ করার লজিক
if (isset($_POST['add_sr'])) {
    $name = $_POST['name'];
    $email = trim($_POST['email']);
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $group_id = $_POST['group_id'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $conn->prepare("INSERT INTO users (godown_id, group_id, name, email, phone, address, password, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'sr')");
        $stmt->execute([$godown_id, $group_id, $name, $email, $phone, $address, $password]);
        header("Location: add_sr.php?success=1"); exit();
    } catch(PDOException $e) { $error = "এই ইমেইলটি ইতিমধ্যে ব্যবহার করা হয়েছে!"; }
}

// ২. এসআর আপডেট করার লজিক
if (isset($_POST['update_sr'])) {
    $sr_id = $_POST['sr_id'];
    $name = $_POST['name'];
    $email = trim($_POST['email']);
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $group_id = $_POST['group_id'];
    
    try {
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, group_id=?, password=? WHERE id=? AND godown_id=?");
            $stmt->execute([$name, $email, $phone, $address, $group_id, $password, $sr_id, $godown_id]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, address=?, group_id=? WHERE id=? AND godown_id=?");
            $stmt->execute([$name, $email, $phone, $address, $group_id, $sr_id, $godown_id]);
        }
        header("Location: add_sr.php?updated=1"); exit();
    } catch(PDOException $e) { $error = "আপডেট ব্যর্থ হয়েছে!"; }
}

// ৩. ডিলিট লজিক
if (isset($_GET['delete'])) {
    $conn->prepare("DELETE FROM users WHERE id=? AND godown_id=? AND role='sr'")->execute([$_GET['delete'], $godown_id]);
    header("Location: add_sr.php?deleted=1"); exit();
}

// ৪. এডিট ডাটা
$edit_mode = false;
$edit_data = ['id'=>'','name'=>'','email'=>'','phone'=>'','address'=>'','group_id'=>''];
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id=? AND godown_id=? AND role='sr'");
    $stmt->execute([$_GET['edit'], $godown_id]);
    $res = $stmt->fetch();
    if ($res) { $edit_mode = true; $edit_data = $res; }
}

// ৫. গ্রুপ এবং এসআর লিস্ট আনা (পারফরম্যান্স ডাটা সহ)
$groups = $conn->prepare("SELECT * FROM product_groups WHERE godown_id=?");
$groups->execute([$godown_id]);
$all_groups = $groups->fetchAll();

$sr_list = $conn->prepare("SELECT u.*, g.group_name, 
                           (SELECT COUNT(*) FROM sales WHERE user_id = u.id) as total_orders
                           FROM users u 
                           LEFT JOIN product_groups g ON u.group_id = g.id 
                           WHERE u.godown_id = ? AND u.role = 'sr' ORDER BY u.id DESC");
$sr_list->execute([$godown_id]);
$srs = $sr_list->fetchAll();

$page_title = "এসআর ম্যানেজমেন্ট";
include 'includes/header.php'; 
?>

<style>
    .sr-card { border-radius: 15px; border: none; transition: 0.3s; }
    .table-premium thead { background: #f8f9fa; color: #333; font-weight: 600; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
    .avatar-circle { width: 45px; height: 45px; background: #e9ecef; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #4e73df; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 11px; font-weight: 600; }
    .bg-soft-success { background: #d1f7e8; color: #155724; }
    .bg-soft-primary { background: #e0e7ff; color: #0038ff; }
    /* ড্রপডাউন মেনু যেন কেটে না যায় তার সমাধান */
.table-responsive {
    overflow: visible !important;
}

.sr-card {
    overflow: visible !important;
}

.dropdown-menu {
    z-index: 1060 !important; /* যাতে এটি সবকিছুর উপরে থাকে */
    min-width: 150px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
}

/* মোবাইল ভিউতে টেবিল যেন স্ক্রল হয় তবুও ড্রপডাউন দেখা যায় */
@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
}
</style>

<div class="row g-4">
    <!-- বাম পাশ: ফরম -->
    <div class="col-lg-4">
        <div class="card sr-card shadow-sm p-4 bg-white">
            <h5 class="fw-bold mb-4 text-dark">
                <i class="fas <?= $edit_mode ? 'fa-user-edit text-warning' : 'fa-user-plus text-primary' ?> me-2"></i> 
                <?= $edit_mode ? 'এসআর তথ্য সংশোধন' : 'নতুন এসআর যোগ' ?>
            </h5>
            
            <?php if($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>

            <form method="POST">
                <?php if($edit_mode): ?><input type="hidden" name="sr_id" value="<?= $edit_data['id'] ?>"><?php endif; ?>

                <div class="mb-3">
                    <label class="small fw-bold text-muted">নাম</label>
                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_data['name']) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted">গ্রুপ নির্বাচন</label>
                    <select name="group_id" class="form-select" required>
                        <option value="">গ্রুপ বেছে নিন...</option>
                        <?php foreach($all_groups as $g): ?>
                            <option value="<?= $g['id'] ?>" <?= ($edit_data['group_id'] == $g['id']) ? 'selected' : '' ?>><?= $g['group_name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="small fw-bold text-muted">মোবাইল</label>
                        <input type="text" name="phone" class="form-control" value="<?= $edit_data['phone'] ?>" required>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="small fw-bold text-muted">ইমেইল (লগইন আইডি)</label>
                        <input type="email" name="email" class="form-control" value="<?= $edit_data['email'] ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold text-muted">পাসওয়ার্ড <?= $edit_mode ? '(না বদলালে খালি)' : '' ?></label>
                    <input type="password" name="password" class="form-control" <?= $edit_mode ? '' : 'required' ?>>
                </div>
                <div class="mb-4">
                    <label class="small fw-bold text-muted">ঠিকানা</label>
                    <textarea name="address" class="form-control" rows="2"><?= $edit_data['address'] ?></textarea>
                </div>
                
                <button type="submit" name="<?= $edit_mode ? 'update_sr' : 'add_sr' ?>" class="btn <?= $edit_mode ? 'btn-warning' : 'btn-primary' ?> w-100 fw-bold py-2">
                    <?= $edit_mode ? 'আপডেট সেভ করুন' : 'এসআর একাউন্ট তৈরি করুন' ?>
                </button>

                <?php if($edit_mode): ?>
                    <a href="add_sr.php" class="btn btn-light w-100 mt-2 small border">বাতিল</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ডান পাশ: প্রিমিয়াম টেবিল -->
    <div class="col-lg-8">
        <div class="card sr-card shadow-sm bg-white overflow-hidden">
            <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">সক্রিয় এসআর তালিকা</h5>
                <span class="badge bg-soft-primary text-primary"><?= count($srs) ?> জন মোট</span>
            </div>
            <div class="table-responsive">
                <table class="table table-premium table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">এসআর প্রোফাইল</th>
                            <th>যোগাযোগ</th>
                            <th>ঠিকানা</th>
                            <th class="text-center">অর্ডার</th>
                            <th class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($srs as $sr): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle me-3">
                                        <?= strtoupper(substr($sr['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($sr['name']) ?></div>
                                        <div class="small text-primary fw-bold" style="font-size: 11px;">
                                            <i class="fas fa-layer-group me-1"></i> <?= $sr['group_name'] ?? 'No Group' ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small"><i class="fas fa-phone-alt me-2 text-muted"></i><?= $sr['phone'] ?></div>
                                <div class="small text-muted"><i class="fas fa-envelope me-2"></i><?= $sr['email'] ?></div>
                            </td>
                            <td>
                                <div class="small text-muted text-wrap" style="max-width: 150px;">
                                    <i class="fas fa-map-marker-alt me-2"></i><?= $sr['address'] ?: 'N/A' ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-soft-success text-success status-badge"><?= $sr['total_orders'] ?>টি</span>
                            </td>
                            <td class="text-center pe-3">
    <div class="dropdown">
        <button class="btn btn-light btn-sm rounded-circle shadow-sm" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
            <li>
                <a class="dropdown-item px-3 py-2" href="add_sr.php?edit=<?= $sr['id'] ?>">
                    <i class="fas fa-edit text-warning me-2"></i> এডিট করুন
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item px-3 py-2 text-danger" href="add_sr.php?delete=<?= $sr['id'] ?>" onclick="return confirm('আপনি কি নিশ্চিতভাবে এই এসআর ডিলিট করতে চান?')">
                    <i class="fas fa-trash-alt me-2"></i> মুছে ফেলুন
                </a>
            </li>
        </ul>
    </div>
</td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($srs) == 0): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">কোনো এসআর ডাটা পাওয়া যায়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>