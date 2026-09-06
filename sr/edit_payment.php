<?php
session_start();
include '../config/db.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') exit();

$id = $_GET['id'];
$stmt = $conn->prepare("SELECT cp.*, s.customer_name FROM customer_payments cp JOIN sales s ON cp.sale_id = s.id WHERE cp.id = ?");
$stmt->execute([$id]);
$payment = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "পেমেন্ট এডিট";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0 rounded-4" style="background: #fff;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-primary"><i class="fas fa-edit me-2"></i>নগদ জমা সংশোধন</h5>
                    <div class="p-3 mb-4 rounded-3" style="background: linear-gradient(135deg, #091961 0%, #370668 100%); color:white;">
                        <small class="opacity-75">কাস্টমার</small>
                        <div class="h6 fw-bold"><?= $payment['customer_name'] ?></div>
                    </div>
                    
                    <form action="process_edit_payment.php" method="POST">
                        <input type="hidden" name="payment_id" value="<?= $id ?>">
                        <div class="mb-4">
                            <label class="fw-bold small text-muted mb-2">সংশোধিত টাকার পরিমাণ</label>
                            <input type="number" name="new_amount" class="form-control form-control-lg fw-bold text-center border-primary" value="<?= $payment['amount_paid'] ?>" step="any" required style="font-size: 28px; border-radius: 12px;">
                            <p class="text-danger mt-2 small">* টাকা কমালে বা বাড়ালে ইনভয়েসের বাকী অটো আপডেট হবে।</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="due_collection.php" class="btn btn-light w-50 rounded-pill py-2 fw-bold">বাতিল</a>
                            <button type="submit" class="btn btn-dark w-50 rounded-pill py-2 fw-bold shadow">আপডেট করুন</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>