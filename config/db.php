<?php
date_default_timezone_set('Asia/Dhaka');
$host = "localhost";
$dbname = "inventory_db";
$username = "root";
$password = "Srb@12345";

try {
    // PDO দিয়ে ডাটাবেস কানেক্ট করছি (এটি সিকিউর)
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // এরর মোড সেট করা
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

function checkAccess($conn) {
    if (isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sr')) {
        $gid = $_SESSION['godown_id'];
        $stmt = $conn->prepare("SELECT expiry_date, status FROM godowns WHERE id = ?");
        $stmt->execute([$gid]);
        $godown = $stmt->fetch();

        // আজকের তারিখের সাথে তুলনা
        if (!$godown || $godown['expiry_date'] < date('Y-m-d') || $godown['status'] == 'inactive') {
            // যদি ইউজার অলরেডি পেমেন্ট পেজে না থাকে, তবে তাকে সেখানে পাঠান
            if (basename($_SERVER['PHP_SELF']) != 'payment_required.php') {
                header("Location: ../payment_required.php");
                exit();
            }
        }
    }
}
?>