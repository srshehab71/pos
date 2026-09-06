<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

/** 
 * সরাসরি পরম পাথ (Absolute Path) ব্যবহার করা হচ্ছে 
 */
$root_dir = dirname(__DIR__); // এটি আপনার inventory_system ফোল্ডারটি খুঁজে নেবে
$db_file = $root_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
$bkash_config_file = $root_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bkash_config.php';

// ১. ডাটাবেস কানেকশন চেক
if (file_exists($db_file)) {
    require_once $db_file;
} else {
    die("Fatal Error: 'config/db.php' ফাইলটি পাওয়া যায়নি! পাথ চেক করুন: " . $db_file);
}

// ২. বিকাশ কনফিগারেশন চেক
if (file_exists($bkash_config_file)) {
    require_once $bkash_config_file;
} else {
    // এখানে আপনার পিসির আসল পাথ দেখাবে, তা দেখে আপনি ফাইলটি সরাতে পারবেন
    die("Fatal Error: 'config/bkash_config.php' ফাইলটি পাওয়া যায়নি! <br>আপনার পিসিতে ফাইলটি এখানে থাকার কথা: <b>" . $bkash_config_file . "</b>");
}

/** 
 * ধাপ ১: Grant Token জেনারেট করা 
 */
$post_token = array(
    'app_key' => BKASH_APP_KEY,
    'app_secret' => BKASH_APP_SECRET
);

$url = BKASH_BASE_URL . "/checkout/token/grant";
$header = array(
    'Content-Type:application/json',
    'password:' . BKASH_PASSWORD,
    'username:' . BKASH_USERNAME
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_token));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$result_data = curl_exec($ch);
curl_close($ch);

$response = json_decode($result_data, true);

if (!isset($response['id_token'])) {
    die("bKash Grant Token Error: " . ($response['statusMessage'] ?? 'বিকাশ ক্রেডেনশিয়াল (Credentials) ভুল হয়েছে।'));
}

$id_token = $response['id_token'];

/** 
 * ধাপ ২: পেমেন্ট ক্রিয়েট করা 
 */
$amount = isset($_GET['amount']) ? $_GET['amount'] : "10";
$invoice = "INV" . rand(100000, 999999);

// সেশন থেকে আইডি সংগ্রহ করা (সেশন লস্ট সমাধান করার জন্য)
$godown_id = $_SESSION['godown_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

$create_payment_body = array(
    'mode' => '0011',
    'payerReference' => $user_id > 0 ? $user_id : 'Guest',
    'callbackURL' => CALLBACK_URL . "?gid=$godown_id&uid=$user_id", // এখানে gid এবং uid যোগ করা হয়েছে
    'amount' => $amount,
    'currency' => 'BDT',
    'intent' => 'sale',
    'merchantInvoiceNumber' => $invoice
);

$create_url = BKASH_BASE_URL . "/checkout/create";
$create_header = array(
    'Content-Type:application/json',
    'Authorization:' . $id_token,
    'X-APP-Key:' . BKASH_APP_KEY
);

$ch = curl_init($create_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $create_header);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($create_payment_body));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
$result_create = curl_exec($ch);
curl_close($ch);

$result_array = json_decode($result_create, true);

if (isset($result_array['bkashURL'])) {
    header("Location: " . $result_array['bkashURL']);
    exit();
} else {
    echo "<h3>পেমেন্ট শুরু করা যায়নি!</h3>";
    echo "বিকাশ থেকে মেসেজ: " . ($result_array['statusMessage'] ?? 'Unknown Error');
}