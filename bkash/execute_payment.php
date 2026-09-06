<?php
session_start();
require_once '../config/db.php';
require_once '../config/bkash_config.php';

$status = $_GET['status'] ?? '';
$paymentID = $_GET['paymentID'] ?? '';

// সেশন না পাওয়া গেলে ইউআরএল (GET) থেকে আইডি সংগ্রহ করা (সেশন লস্ট সমস্যার স্থায়ী সমাধান)
$godown_id = $_SESSION['godown_id'] ?? $_GET['gid'] ?? null;
$user_id = $_SESSION['user_id'] ?? $_GET['uid'] ?? 0;

// ১. পেমেন্ট সফল না হলে হ্যান্ডেল করা
if ($status !== 'success') {
    $error_msg = ($status == 'cancel') ? "আপনি পেমেন্টটি বাতিল করেছেন।" : "পেমেন্ট ব্যর্থ হয়েছে!";
    die("<div style='text-align:center;margin-top:50px;font-family:sans-serif;'><h2>$error_msg</h2><a href='../renew_account.php'>আবার চেষ্টা করুন</a></div>");
}

// আইডি না পাওয়া গেলে প্রসেস থামিয়ে দেয়া
if(!$godown_id){
    die("<div style='text-align:center;margin-top:50px;'><h2>Error: Session expired.</h2><p>আপনার সেশনটি পাওয়া যায়নি। দয়া করে আবার লগইন করে চেষ্টা করুন।</p></div>");
}

// ২. গ্র্যান্ট টোকেন আনা
$post_token = array('app_key' => BKASH_APP_KEY, 'app_secret' => BKASH_APP_SECRET);
$url = BKASH_BASE_URL . "/checkout/token/grant";
$header = array('Content-Type:application/json', 'password:' . BKASH_PASSWORD, 'username:' . BKASH_USERNAME);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header); 
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_token));
$result_data = curl_exec($ch); 
curl_close($ch);
$response = json_decode($result_data, true);
$id_token = $response['id_token'] ?? null;

// ৩. পেমেন্ট এক্সিকিউট করা
$execute_url = BKASH_BASE_URL . "/checkout/execute";
$execute_header = array('Content-Type:application/json', 'Authorization:' . $id_token, 'X-APP-Key:' . BKASH_APP_KEY);
$ch = curl_init($execute_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $execute_header); 
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['paymentID' => $paymentID]));
$result_execute = curl_exec($ch); 
curl_close($ch);

$res = json_decode($result_execute, true);

// চেক করা পেমেন্ট সফল কি না
if (isset($res['transactionStatus']) && $res['transactionStatus'] == 'Completed') {
    
    $trxID = $res['trxID'];
    $amount = $res['amount'];
    $invoice = $res['merchantInvoiceNumber'] ?? 'N/A';
    
    try {
        $conn->beginTransaction();

        // ক. প্যাকেজ ডিটেইলস খুঁজে বের করা (টাকার অংক দিয়ে)
        $pkg_stmt = $conn->prepare("SELECT id, duration_days FROM packages WHERE price = ? LIMIT 1");
        $pkg_stmt->execute([$amount]);
        $package = $pkg_stmt->fetch(PDO::FETCH_ASSOC);
        
        $days_to_add = $package ? $package['duration_days'] : 30; // প্যাকেজ না পেলে ডিফল্ট ৩০ দিন
        $package_id = $package ? $package['id'] : 1;

        // খ. বর্তমান মেয়াদ বের করা
        $g_stmt = $conn->prepare("SELECT expiry_date FROM godowns WHERE id = ?");
        $g_stmt->execute([$godown_id]);
        $current_expiry = $g_stmt->fetchColumn();

        $today = date('Y-m-d');
        $base_date = ($current_expiry < $today || !$current_expiry) ? $today : $current_expiry;
        $new_expiry = date('Y-m-d', strtotime($base_date . " + $days_to_add days"));

        // ১. bkash_payments টেবিলে সেভ (লগ রাখার জন্য)
        $conn->prepare("INSERT INTO bkash_payments (payment_id, invoice_no, amount, trx_id, payment_status, customer_id) VALUES (?, ?, ?, ?, 'Success', ?)")
             ->execute([$paymentID, $invoice, $amount, $trxID, $godown_id]);

        // ২. recharges টেবিলে সেভ (রিচার্জ হিস্ট্রির জন্য)
        $conn->prepare("INSERT INTO recharges (godown_id, amount, days_added, trx_id, payment_method, status) VALUES (?, ?, ?, ?, 'bKash', 'completed')")
             ->execute([$godown_id, $amount, $days_to_add, $trxID]);

        // ৩. transactions টেবিলে সেভ (মেইন ট্রানজেকশন লিস্টের জন্য)
        $conn->prepare("INSERT INTO transactions (user_id, package_id, amount, trx_id, payment_status) VALUES (?, ?, ?, ?, 'Completed')")
             ->execute([$user_id, $package_id, $amount, $trxID]);

        // ৪. godowns টেবিলে মেয়াদ এবং টোটাল রিচার্জ আপডেট
        $conn->prepare("UPDATE godowns SET expiry_date = ?, total_recharged = total_recharged + ? WHERE id = ?")
             ->execute([$new_expiry, $amount, $godown_id]);

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        die("ডাটাবেস এরর: " . $e->getMessage());
    }

    // সফল মেসেজ এবং রিডাইরেক্ট UI
    ?>
    <!DOCTYPE html>
    <html lang="bn">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>পেমেন্ট সফল!</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: #f4f7fe; font-family: 'Segoe UI', Tahoma, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .success-card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 90%; }
            .check-icon { width: 70px; height: 70px; background: #27ae60; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 35px; margin: 0 auto 20px; }
            .trx-box { background: #f8f9fa; padding: 15px; border-radius: 10px; text-align: left; margin-top: 20px; }
            .btn-dash { background: #e2136e; color: #fff; border-radius: 50px; padding: 10px 30px; text-decoration: none; display: block; margin-top: 25px; font-weight: bold; transition: 0.3s; }
            .btn-dash:hover { background: #b10f56; color: #fff; }
        </style>
    </head>
    <body>
        <div class="success-card">
            <div class="check-icon"><i class="fas fa-check"></i></div>
            <h3 class="fw-bold text-success">পেমেন্ট সফল হয়েছে!</h3>
            <p class="text-muted small">আপনার অ্যাকাউন্টটি সফলভাবে রিনিউ করা হয়েছে।</p>
            
            <div class="trx-box">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">TrxID:</span>
                    <span class="fw-bold small text-primary"><?= $trxID ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted small">পরিমাণ:</span>
                    <span class="fw-bold small">৳ <?= number_format($amount, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small">নতুন মেয়াদ:</span>
                    <span class="fw-bold small text-danger"><?= date('d M, Y', strtotime($new_expiry)) ?></span>
                </div>
            </div>

            <a href="../admin/dashboard.php" class="btn btn-dash">ড্যাশবোর্ডে ফিরে যান</a>
            <p class="mt-3 text-muted" style="font-size: 11px;">৫ সেকেন্ডের মধ্যে অটোমেটিক রিডাইরেক্ট হবে...</p>
        </div>

        <script>
            setTimeout(() => { window.location.href = '../admin/dashboard.php'; }, 5000);
        </script>
    </body>
    </html>
    <?php
} else {
    echo "<div style='text-align:center;padding:50px;'><h2>পেমেন্ট এক্সিকিউট ব্যর্থ!</h2><p>".($res['statusMessage'] ?? 'Unknown Error')."</p><a href='../renew_account.php'>আবার চেষ্টা করুন</a></div>";
}
?>