<?php
session_start();
require_once '../config/db.php';

// ১. সুপার এডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// ২. ডিলিট লজিক
if (isset($_GET['delete_godown'])) {
    $id = $_GET['delete_godown'];
    $conn->prepare("DELETE FROM godowns WHERE id = ?")->execute([$id]);
    header("Location: manage_godowns.php?deleted=1");
    exit();
}

// ৩. আপডেট লজিক (মেয়াদ ও স্ট্যাটাস)
if (isset($_POST['update_expiry'])) {
    $id = $_POST['godown_id'];
    $new_date = $_POST['expiry_date'];
    $status = $_POST['status'];
    $conn->prepare("UPDATE godowns SET expiry_date = ?, status = ? WHERE id = ?")->execute([$new_date, $status, $id]);
    header("Location: manage_godowns.php?updated=1");
    exit();
}

// ৪. সকল গোডাউন ডাটা ফেচ করা
$query = "SELECT * FROM godowns ORDER BY id DESC";
$godowns = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);

$page_title = "গোডাউন মালিক ম্যানেজমেন্ট";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">

        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary">
                <i class="fas fa-store me-2"></i> সকল গোডাউন ও দোকান মালিক
            </h5>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">দোকানের নাম</th>
                            <th>মালিক ও ফোন</th>
                            <th>মেয়াদ শেষ</th>
                            <th>স্ট্যাটাস</th>
                            <th class="text-center">অ্যাকশন</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach($godowns as $g): ?>

                        <tr>

                            <td class="ps-4">
                                <div class="fw-bold text-dark">
                                    <?= htmlspecialchars($g['shop_name']) ?>
                                </div>

                                <small class="text-muted">
                                    ID: #<?= $g['id'] ?>
                                </small>
                            </td>

                            <td>
                                <div>
                                    <?= htmlspecialchars($g['owner_name']) ?>
                                </div>

                                <small class="text-muted">
                                    <?= htmlspecialchars($g['email']) ?>
                                </small>
                            </td>

                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= date('d M, Y', strtotime($g['expiry_date'])) ?>
                                </span>
                            </td>

                            <td>

                                <?php if($g['status'] == 'active'): ?>

                                    <span class="badge bg-success-soft text-success">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-danger-soft text-danger">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="text-center">

                                <!-- অ্যাকশন বাটন -->
                                <button
                                    class="btn btn-sm btn-outline-primary rounded-pill px-3"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editGodown<?= $g['id'] ?>">

                                    <i class="fas fa-edit me-1"></i> এডিট

                                </button>

                                <a
                                    href="manage_godowns.php?delete_godown=<?= $g['id'] ?>"
                                    class="btn btn-sm btn-outline-danger rounded-pill px-3"
                                    onclick="return confirm('আপনি কি নিশ্চিত?')">

                                    <i class="fas fa-trash"></i>

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


<!-- ===================================================== -->
<!-- প্রতিটি গোডাউনের জন্য আলাদা Modal -->
<!-- Modal এখন Table-এর বাইরে রাখা হয়েছে -->
<!-- ===================================================== -->

<?php foreach($godowns as $g): ?>

<div
    class="modal fade"
    id="editGodown<?= $g['id'] ?>"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form
            method="POST"
            class="modal-content border-0 shadow-lg rounded-4">

            <div class="modal-header border-0">

                <h5 class="fw-bold">
                    এডিট এডমিন:
                    <?= htmlspecialchars($g['shop_name']) ?>
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body p-4">

                <input
                    type="hidden"
                    name="godown_id"
                    value="<?= $g['id'] ?>">

                <div class="mb-3">

                    <label class="form-label fw-bold small">
                        মেয়াদ শেষ হওয়ার তারিখ
                    </label>

                    <input
                        type="date"
                        name="expiry_date"
                        class="form-control"
                        value="<?= $g['expiry_date'] ?>"
                        required>

                </div>

                <div class="mb-3">

                    <label class="form-label fw-bold small">
                        একউন্ট স্ট্যাটাস
                    </label>

                    <select
                        name="status"
                        class="form-select">

                        <option
                            value="active"
                            <?= $g['status'] == 'active' ? 'selected' : '' ?>>
                            Active (সচল)
                        </option>

                        <option
                            value="inactive"
                            <?= $g['status'] == 'inactive' ? 'selected' : '' ?>>
                            Inactive (বন্ধ)
                        </option>

                    </select>

                </div>

            </div>

            <div class="modal-footer border-0">

                <button
                    type="button"
                    class="btn btn-light rounded-pill px-4"
                    data-bs-dismiss="modal">

                    বাতিল

                </button>

                <button
                    type="submit"
                    name="update_expiry"
                    class="btn btn-primary rounded-pill px-4">

                    আপডেট করুন

                </button>

            </div>

        </form>

    </div>

</div>

<?php endforeach; ?>


<?php include '../admin/includes/footer.php'; ?>