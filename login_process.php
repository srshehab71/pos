<?php
// ১ বছরের জন্য কুকি সেট করা
session_set_cookie_params(31536000);
ini_set('session.gc_maxlifetime', 31536000);
session_start();
require_once 'config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // trim() ব্যবহার করছি যাতে ইমেইলের আগে-পরে কোনো স্পেস থাকলে তা কেটে যায়
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        /**
         * ১. প্রথমে 'users' টেবিলে চেক করা
         * এই টেবিলে সাধারণত সুপার এডমিন এবং এসআর (SR) থাকে।
         */
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (password_verify($password, $user['password'])) {
                // সেশন ডাটা সেট করা
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['godown_id'] = $user['godown_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'];
                
                /** 
                 * গ্রুপ ভিত্তিক সিস্টেমের জন্য group_id সেশনে রাখা হচ্ছে।
                 * যদি ইউজার SR হয়, তবে সে শুধু তার গ্রুপের প্রোডাক্ট দেখতে পাবে।
                 */
                $_SESSION['group_id']  = isset($user['group_id']) ? $user['group_id'] : null;

                // রোল অনুযায়ী রিডাইরেক্ট
                if ($user['role'] == 'super_admin') {
                    header("Location: super_admin/index.php");
                } elseif ($user['role'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    // এসআর (SR) প্যানেলে পাঠানো
                    header("Location: sr/dashboard.php");
                }
                exit();
            } else {
                echo "<script>alert('ভুল পাসওয়ার্ড!'); window.location='index.php';</script>";
                exit();
            }
        }

        /**
         * ২. যদি 'users' টেবিলে না পাওয়া যায়, তবে 'godowns' টেবিলে চেক করা
         * এখানে আপনার নতুন তৈরি করা এডমিনরা (দোকান মালিক) থাকে।
         */
        $stmt_g = $conn->prepare("SELECT * FROM godowns WHERE email = :email LIMIT 1");
        $stmt_g->execute(['email' => $email]);
        $godown = $stmt_g->fetch(PDO::FETCH_ASSOC);

        if ($godown) {
            if (password_verify($password, $godown['password'])) {
                
                // সেশন সেট করা (পেমেন্ট পেজে এই তথ্যগুলো লাগবে)
                $_SESSION['user_id']   = $godown['id'];
                $_SESSION['godown_id'] = $godown['id']; 
                $_SESSION['user_name'] = $godown['owner_name'];
                $_SESSION['shop_name'] = $godown['shop_name'];
                $_SESSION['role']      = 'admin';

                // মেয়াদ (Expiry) চেক করা
                $today = date('Y-m-d');
                if ($godown['expiry_date'] < $today && $godown['expiry_date'] != NULL) {
                    // মেয়াদ শেষ, তাই পেমেন্ট পেজে পাঠানো হচ্ছে
                    echo "<script>
                        alert('আপনার দোকানের মেয়াদ শেষ হয়ে গেছে! অনুগ্রহ করে রিনিউ করার জন্য পেমেন্ট সম্পন্ন করুন।');
                        window.location='renew_account.php'; 
                    </script>";
                    exit();
                }

                // মেয়াদ থাকলে সরাসরি ড্যাশবোর্ড
                header("Location: admin/dashboard.php"); 
                exit();
            } else {
                echo "<script>alert('ভুল পাসওয়ার্ড!'); window.location='index.php';</script>";
                exit();
            }
        } else {
            // কোনো টেবিলেই ইমেইল পাওয়া যায়নি
            echo "<script>alert('দুঃখিত, এই ইমেইলটি আমাদের সিস্টেমে নেই।'); window.location='index.php';</script>";
            exit();
        }

    } catch(PDOException $e) {
        // এরর হলে লগ ফাইলে রাখা ভালো, ইউজারকে শুধু একটি মেসেজ দিন
        error_log($e->getMessage());
        die("সিস্টেম এরর হয়েছে। দয়া করে পরে চেষ্টা করুন। " . $e->getMessage());
    }
}
?>