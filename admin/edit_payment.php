<?php
session_start();
include '../config/db.php';
if ($_SESSION['role'] != 'admin') exit();

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT cp.*, s.customer_name FROM customer_payments cp JOIN sales s ON cp.sale_id = s.id WHERE cp.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "পেমেন্ট এডিট";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-primary">নগদ জমা এডিট করুন</h5>
                    <p class="text-muted small">কাস্টমার: <strong><?= $payment['customer_name'] ?></strong></p>
                    
                    <form action="process_edit_payment.php" method="POST">
                        <input type="hidden" name="payment_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="fw-bold small">জমার পরিমাণ (টাকা)</label>
                            <input type="number" name="new_amount" class="form-control form-control-lg fw-bold text-center" value="<?= $payment['amount_paid'] ?>" step="any" required>
                            <small class="text-danger">* টাকা কমালে বা বাড়ালে ইনভয়েসের বাকী অটো আপডেট হবে।</small>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="customer_due.php" class="btn btn-light w-50 rounded-pill">বাতিল</a>
                            <button type="submit" class="btn btn-primary w-50 rounded-pill shadow">আপডেট করুন</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>