<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// প্যাকেজ যোগ করা
if (isset($_POST['add_package'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $days = $_POST['days'];
    $stmt = $conn->prepare("INSERT INTO packages (name, price, duration_days) VALUES (?, ?, ?)");
    $stmt->execute([$name, $price, $days]);
    header("Location: packages.php?success=added");
    exit();
}

// প্যাকেজ আপডেট/এডিট করা
if (isset($_POST['update_package'])) {
    $id = $_POST['package_id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $days = $_POST['days'];
    $stmt = $conn->prepare("UPDATE packages SET name = ?, price = ?, duration_days = ? WHERE id = ?");
    $stmt->execute([$name, $price, $days, $id]);
    header("Location: packages.php?success=updated");
    exit();
}

// প্যাকেজ ডিলিট করা
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM packages WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: packages.php?success=deleted");
    exit();
}

$packages = $conn->query("SELECT * FROM packages ORDER BY id DESC")->fetchAll();
$page_title = "প্যাকেজ সেটিংস";
include 'includes/header.php';
?>

<div class="row">
    <!-- নতুন প্যাকেজ যোগ করার ফরম -->
    <div class="col-md-4">
        <div class="stat-card shadow-sm p-4 bg-white rounded">
            <h5 class="fw-bold mb-3 text-primary">নতুন প্যাকেজ যোগ করুন</h5>
            <form method="POST">
                <div class="mb-2">
                    <label class="form-label small fw-bold">প্যাকেজ নাম</label>
                    <input type="text" name="name" class="form-control" placeholder="যেমন: গোল্ড" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">মূল্য (৳)</label>
                    <input type="number" name="price" class="form-control" placeholder="0.00" required>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">মেয়াদ (দিন)</label>
                    <input type="number" name="days" class="form-control" placeholder="৩০" required>
                </div>
                <button type="submit" name="add_package" class="btn btn-primary w-100 fw-bold">সেভ প্যাকেজ</button>
            </form>
        </div>
    </div>

    <!-- প্যাকেজ তালিকা -->
    <div class="col-md-8">
        <div class="stat-card shadow-sm p-4 bg-white rounded">
            <h5 class="fw-bold mb-3 text-success">বর্তমান প্যাকেজসমূহ</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>প্যাকেজ</th>
                            <th>মূল্য</th>
                            <th>মেয়াদ</th>
                            <th class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($packages as $p): ?>
                        <tr>
                            <td><span class="fw-bold"><?= htmlspecialchars($p['name']) ?></span></td>
                            <td>৳ <?= number_format($p['price'], 2) ?></td>
                            <td><?= $p['duration_days'] ?> দিন</td>
                            <td class="text-center">
                                <!-- এডিট বাটন (মোডাল ট্রিগার) -->
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        onclick="editPackage(<?= $p['id'] ?>, '<?= $p['name'] ?>', <?= $p['price'] ?>, <?= $p['duration_days'] ?>)" 
                                        data-bs-toggle="modal" data-bs-target="#editModal">
                                    <i class="fas fa-edit"></i> এডিট
                                </button>
                                
                                <!-- ডিলিট বাটন -->
                                <a href="?delete_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('আপনি কি নিশ্চিতভাবে এই প্যাকেজটি ডিলিট করতে চান?')">
                                    <i class="fas fa-trash"></i> ডিলিট
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- এডিট প্যাকেজ মোডাল (Bootstrap Modal) -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">প্যাকেজ এডিট করুন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="package_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">প্যাকেজ নাম</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">মূল্য (৳)</label>
                    <input type="number" name="price" id="edit_price" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">মেয়াদ (দিন)</label>
                    <input type="number" name="days" id="edit_days" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বন্ধ করুন</button>
                <button type="submit" name="update_package" class="btn btn-success">আপডেট করুন</button>
            </div>
        </form>
    </div>
</div>

<script>
// এডিট মোডালে ডাটা পাঠানোর ফাংশন
function editPackage(id, name, price, days) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_days').value = days;
}
</script>

<?php include '../admin/includes/footer.php'; ?>