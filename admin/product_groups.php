<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}
include '../config/db.php';
$godown_id = $_SESSION['godown_id'];

$message = "";

// ১. নতুন গ্রুপ যোগ করার লজিক
if (isset($_POST['add_group'])) {
    $group_name = trim($_POST['group_name']);
    if (!empty($group_name)) {
        $stmt = $conn->prepare("INSERT INTO product_groups (godown_id, group_name) VALUES (?, ?)");
        $stmt->execute([$godown_id, $group_name]);
        $message = "<div class='alert alert-success'>নতুন গ্রুপ সফলভাবে যোগ হয়েছে!</div>";
    }
}

// ২. গ্রুপ আপডেট করার লজিক
if (isset($_POST['update_group'])) {
    $id = $_POST['group_id'];
    $group_name = trim($_POST['group_name']);
    if (!empty($group_name)) {
        $stmt = $conn->prepare("UPDATE product_groups SET group_name = ? WHERE id = ? AND godown_id = ?");
        $stmt->execute([$group_name, $id, $godown_id]);
        $message = "<div class='alert alert-info'>গ্রুপের নাম আপডেট হয়েছে!</div>";
    }
}

// ৩. গ্রুপ ডিলিট করার লজিক
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // চেক করা এই গ্রুপের সাথে কোনো প্রোডাক্ট বা এসআর যুক্ত আছে কি না
    $check_p = $conn->prepare("SELECT COUNT(*) FROM products WHERE group_id = ?");
    $check_p->execute([$id]);
    
    if ($check_p->fetchColumn() > 0) {
        $message = "<div class='alert alert-danger'>দুঃখিত! এই গ্রুপের আন্ডারে প্রোডাক্ট রয়েছে, তাই এটি ডিলিট করা যাবে না।</div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM product_groups WHERE id = ? AND godown_id = ?");
        $stmt->execute([$id, $godown_id]);
        $message = "<div class='alert alert-warning'>গ্রুপটি মুছে ফেলা হয়েছে।</div>";
    }
}

// ৪. সব গ্রুপ লিস্ট নিয়ে আসা
$stmt = $conn->prepare("SELECT * FROM product_groups WHERE godown_id = ? ORDER BY id DESC");
$stmt->execute([$godown_id]);
$groups = $stmt->fetchAll();

$page_title = "প্রোডাক্ট গ্রুপ ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<div class="row g-4">
    <!-- বাম পাশ: নতুন গ্রুপ যোগ করার ফর্ম -->
    <div class="col-md-4">
        <div class="stat-card shadow-sm border-0 bg-white p-4 rounded-3">
            <h5 class="fw-bold mb-4 text-primary"><i class="fas fa-plus-circle me-2"></i> নতুন গ্রুপ তৈরি করুন</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold small">গ্রুপের নাম</label>
                    <input type="text" name="group_name" class="form-control" placeholder="যেমন: গ্রুপ এ / কসমেটিকস" required>
                </div>
                <button type="submit" name="add_group" class="btn btn-primary w-100 fw-bold">সেভ গ্রুপ</button>
            </form>
            <div class="mt-3 small text-muted">
                <i class="fas fa-info-circle me-1"></i> এই গ্রুপের মাধ্যমে আপনি এসআরদের প্রোডাক্ট এক্সেস কন্ট্রোল করতে পারবেন।
            </div>
        </div>
    </div>

    <!-- ডান পাশ: বর্তমান গ্রুপগুলোর তালিকা -->
    <div class="col-md-8">
        <div class="stat-card shadow-sm border-0 bg-white p-4 rounded-3">
            <h5 class="fw-bold mb-4 text-success"><i class="fas fa-layer-group me-2"></i> বর্তমান গ্রুপসমূহ</h5>
            <?php echo $message; ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>গ্রুপের নাম</th>
                            <th>তৈরির তারিখ</th>
                            <th class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $g): ?>
                        <tr>
                            <td>#<?= $g['id']; ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($g['group_name']); ?></td>
                            <td class="small text-muted"><?= date('d M, Y', strtotime($g['created_at'])); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        onclick="editGroup(<?= $g['id']; ?>, '<?= htmlspecialchars($g['group_name']); ?>')" 
                                        title="এডিট">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="product_groups.php?delete=<?= $g['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('আপনি কি নিশ্চিত? এই গ্রুপে কোনো প্রোডাক্ট থাকলে ডিলিট হবে না।')" 
                                   title="মুছুন">
                                    <i class="fas fa-trash-alt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($groups) == 0): ?>
                            <tr><td colspan="4" class="text-center py-4">কোনো গ্রুপ তৈরি করা হয়নি।</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- গ্রুপ এডিট করার মোডাল -->
<div class="modal fade" id="editGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content shadow">
            <div class="modal-header bg-warning">
                <h5 class="modal-title fw-bold text-dark">গ্রুপ নাম সংশোধন</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <input type="hidden" name="group_id" id="edit_group_id">
                <div class="mb-3">
                    <label class="form-label fw-bold small">গ্রুপের নাম</label>
                    <input type="text" name="group_name" id="edit_group_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">বাতিল</button>
                <button type="submit" name="update_group" class="btn btn-warning fw-bold px-4">আপডেট করুন</button>
            </div>
        </form>
    </div>
</div>

<script>
// এডিট মোডাল ওপেন করার ফাংশন
function editGroup(id, name) {
    document.getElementById('edit_group_id').value = id;
    document.getElementById('edit_group_name').value = name;
    var myModal = new bootstrap.Modal(document.getElementById('editGroupModal'));
    myModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>