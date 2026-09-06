<?php
session_start();

/**
 * এই ফাইলের কাজ হলো এডমিন হিসেবে থাকা সেশনগুলো মুছে ফেলা 
 * এবং সুপার এডমিনের অরিজিনাল সেশন ডাটা ফিরিয়ে আনা।
 */

// যদি ইমপারসোনেট মোড সক্রিয় থাকে
if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true) {
    
    // ১. সুপার এডমিনের অরিজিনাল আইডি এবং নাম ফিরিয়ে আনা
    $_SESSION['user_id'] = $_SESSION['admin_origin_id'];
    $_SESSION['user_name'] = $_SESSION['admin_origin_name'];
    $_SESSION['role'] = 'super_admin'; // রোল আবার সুপার এডমিন করে দেওয়া

    // ২. এডমিন প্যানেলের সেশন ভেরিয়েবলগুলো মুছে ফেলা
    unset($_SESSION['is_impersonating']);
    unset($_SESSION['admin_origin_id']);
    unset($_SESSION['admin_origin_name']);
    unset($_SESSION['godown_id']);
    unset($_SESSION['shop_name']);

    // ৩. সুপার এডমিন ড্যাশবোর্ডে রিডাইরেক্ট করা
    header("Location: index.php");
    exit();
} else {
    // যদি কেউ ভুল করে এই পেজে আসে এবং সে ইমপারসোনেট না করে থাকে
    header("Location: ../index.php");
    exit();
}