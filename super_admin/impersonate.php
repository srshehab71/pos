<?php
session_start();
include '../config/db.php';

// নিরাপত্তা চেক: শুধুমাত্র সুপার এডমিন এই পেজে এক্সেস পাবে
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// ইউআরএল থেকে গোডাউন আইডি (gid) নেওয়া
$gid = isset($_GET['gid']) ? (int)$_GET['gid'] : 0;

if ($gid > 0) {
    try {
        // ১. godowns টেবিল থেকে ওই এডমিনের ডাটা খুঁজে বের করা
        $stmt = $conn->prepare("SELECT * FROM godowns WHERE id = ? LIMIT 1");
        $stmt->execute([$gid]);
        $godown = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($godown) {
            /** 
             * ২. সেশন ওভাররাইট করা (ইমপারসোনেশন)
             * এখানে সুপার এডমিনের সেশন পরিবর্তন করে ওই এডমিনের সেশন সেট করা হচ্ছে
             */
            
            // সুপার এডমিন হিসেবে ফেরার জন্য একটি ফ্ল্যাগ রাখা (ঐচ্ছিক)
            $_SESSION['is_impersonating'] = true;
            $_SESSION['admin_origin_id'] = $_SESSION['user_id']; // সুপার এডমিনের অরিজিনাল আইডি রাখা

            // এডমিন সেশন ডাটা
            $_SESSION['user_id']   = $godown['id'];
            $_SESSION['godown_id'] = $godown['id']; // ড্যাশবোর্ড এই আইডি চেক করে
            $_SESSION['user_name'] = $godown['owner_name'];
            $_SESSION['shop_name'] = $godown['shop_name'];
            $_SESSION['role']      = 'admin'; // রোল 'admin' করে দেওয়া হচ্ছে যাতে সে এডমিন প্যানেলে ঢুকতে পারে

            // ৩. সরাসরি এডমিন ড্যাশবোর্ডে পাঠিয়ে দেওয়া
            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            echo "<script>alert('দুঃখিত, এই গোডাউন এডমিনকে খুঁজে পাওয়া যায়নি!'); window.location='index.php';</script>";
        }

    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}