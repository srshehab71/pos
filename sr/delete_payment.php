<?php
session_start();
include '../config/db.php';

// শুধুমাত্র SR এই এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'sr') {
    exit("অনুমতি নেই");
}

if (isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
    $godown_id = $_SESSION['godown_id'];

    try {
        $conn->beginTransaction();

        // ১. পেমেন্টের তথ্য খুঁজে বের করা (নিশ্চিত করা যে এটি এই গোডাউনের)
        $stmt = $conn->prepare("SELECT * FROM customer_payments WHERE id = ? AND godown_id = ?");
        $stmt->execute([$payment_id, $godown_id]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment) {
            $amount = (float)$payment['amount_paid'];
            $sale_id = $payment['sale_id'];

            // ২. ইনভয়েসের বাকী টাকা পুনরায় বাড়ানো (Paid কমানো, Due বাড়ানো)
            $upd = $conn->prepare("UPDATE sales SET paid_amount = paid_amount - ?, due_amount = due_amount + ? WHERE id = ?");
            $upd->execute([$amount, $amount, $sale_id]);

            // ৩. পেমেন্ট রেকর্ডটি ডিলিট করা
            $del = $conn->prepare("DELETE FROM customer_payments WHERE id = ?");
            $del->execute([$payment_id]);

            $conn->commit();
            header("Location: due_collection.php?success=deleted");
            exit();
        } else {
            throw new Exception("পেমেন্ট রেকর্ড পাওয়া যায়নি।");
        }

    } catch (Exception $e) {
        $conn->rollBack();
        die("ভুল হয়েছে: " . $e->getMessage());
    }
} else {
    header("Location: due_collection.php");
    exit();
}