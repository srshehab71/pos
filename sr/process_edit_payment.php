<?php
session_start();
include '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['role'] == 'sr') {
    $payment_id = $_POST['payment_id'];
    $new_amount = (float)$_POST['new_amount'];

    try {
        $conn->beginTransaction();

        // ১. আগের পেমেন্ট ডাটা আনা
        $stmt = $conn->prepare("SELECT amount_paid, sale_id FROM customer_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $old_payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($old_payment) {
            $old_amount = (float)$old_payment['amount_paid'];
            $sale_id = $old_payment['sale_id'];

            // ২. ব্যবধান বের করা (Difference)
            $diff = $new_amount - $old_amount;

            // ৩. ইনভয়েস (sales table) আপডেট করা
            // Paid বাড়বে ডিফারেন্স অনুযায়ী, Due কমবে ডিফারেন্স অনুযায়ী
            $upd = $conn->prepare("UPDATE sales SET paid_amount = paid_amount + ?, due_amount = due_amount - ? WHERE id = ?");
            $upd->execute([$diff, $diff, $sale_id]);

            // ৪. পেমেন্ট টেবিল আপডেট করা
            $upd_pay = $conn->prepare("UPDATE customer_payments SET amount_paid = ? WHERE id = ?");
            $upd_pay->execute([$new_amount, $payment_id]);

            $conn->commit();
            header("Location: due_collection.php?success=updated");
        }
    } catch (Exception $e) {
        $conn->rollBack();
        die("Error: " . $e->getMessage());
    }
}