<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';

$godown_id = $_SESSION['godown_id'];

// ১. নতুন ক্যাটাগরি যোগ করার লজিক
if (isset($_POST['add_category'])) {
    $cat_name = $_POST['cat_name'];
    if (!empty($cat_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (godown_id, name) VALUES (:gid, :name)");
        $stmt->execute(['gid' => $godown_id, 'name' => $cat_name]);
        header("Location: categories.php?success=1");
        exit();
    }
}

// ২. ক্যাটাগরি আপডেট (Update) করার লজিক
if (isset($_POST['update_category'])) {
    $cat_id = $_POST['cat_id'];
    $cat_name = $_POST['cat_name'];
    if (!empty($cat_name)) {
        $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ? AND godown_id = ?");
        $stmt->execute([$cat_name, $cat_id, $godown_id]);
        header("Location: categories.php?updated=1");
        exit();
    }
}

// ৩. ক্যাটাগরি ডিলিট করার লজিক
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = :id AND godown_id = :gid");
    $stmt->execute(['id' => $id, 'gid' => $godown_id]);
    header("Location: categories.php?deleted=1");
    exit();
}

// ৪. এডিট করার জন্য ডাটা নিয়ে আসা
$edit_mode = false;
$edit_data = ['id' => '', 'name' => ''];
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ? AND godown_id = ?");
    $stmt->execute([$edit_id, $godown_id]);
    $res = $stmt->fetch();
    if ($res) {
        $edit_mode = true;
        $edit_data = $res;
    }
}

// ৫. সব ক্যাটাগরি লিস্ট নিয়ে আসা
$stmt = $conn->prepare("SELECT * FROM categories WHERE godown_id = :gid ORDER BY id DESC");
$stmt->execute(['gid' => $godown_id]);
$categories = $stmt->fetchAll();

$page_title = "ক্যাটাগরি ম্যানেজমেন্ট";
include 'includes/header.php'; 
?>

<div class="row g-4">
    <!-- ক্যাটাগরি যোগ/এডিট ফর্ম -->
    <div class="col-md-4">
        <div class="stat-card">
            <h5 class="fw-bold mb-3">
                <i class="fas <?= $edit_mode ? 'fa-edit text-warning' : 'fa-folder-plus text-primary' ?> me-2"></i> 
                <?= $edit_mode ? 'ক্যাটাগরি এডিট করুন' : 'নতুন ক্যাটাগরি' ?>
            </h5>
            
            <form method="POST" class="mt-3">
                <?php if($edit_mode): ?>
                    <input type="hidden" name="cat_id" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="small fw-bold">ক্যাটাগরির নাম</label>
                    <input type="text" name="cat_name" class="form-control" value="<?= htmlspecialchars($edit_data['name']) ?>" placeholder="নাম লিখুন" required>
                </div>
                
                <button type="submit" name="<?= $edit_mode ? 'update_category' : 'add_category' ?>" class="btn <?= $edit_mode ? 'btn-warning' : 'btn-primary' ?> w-100 fw-bold">
                    <i class="fas fa-save me-2"></i> <?= $edit_mode ? 'আপডেট করুন' : 'সেভ করুন' ?>
                </button>
                
                <?php if($edit_mode): ?>
                    <a href="categories.php" class="btn btn-link w-100 mt-2 text-secondary text-decoration-none small">বাতিল করুন</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ক্যাটাগরি লিস্ট টেবিল -->
    <div class="col-md-8">
        <div class="stat-card">
            <h5 class="fw-bold mb-3"><i class="fas fa-list me-2 text-success"></i> সকল ক্যাটাগরি</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">#</th>
                            <th width="60%">নাম</th>
                            <th width="30%" class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 1;
                        foreach ($categories as $cat): ?>
                        <tr class="<?= (isset($_GET['edit']) && $_GET['edit'] == $cat['id']) ? 'table-warning' : '' ?>">
                            <td><?php echo $count++; ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($cat['name']); ?></td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-outline-warning btn-sm" title="এডিট">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('আপনি কি নিশ্চিতভাবে মুছবেন?')" title="মুছুন">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>