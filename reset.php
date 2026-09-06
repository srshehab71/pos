<?php
include 'config/db.php';

// পাসওয়ার্ড এনক্রিপ্ট করছি
$pass = password_hash('123456', PASSWORD_DEFAULT);

try {
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $conn->exec("TRUNCATE TABLE users;");
    $conn->exec("TRUNCATE TABLE godowns;");
    
    // ১ নম্বর গোডাউন তৈরি
    $conn->exec("INSERT INTO godowns (id, shop_name, owner_name) VALUES (1, 'Test Shop', 'Admin')");
    
    // ইউজার তৈরি (পাসওয়ার্ড ১২৩৪৫৬)
    $stmt = $conn->prepare("INSERT INTO users (godown_id, name, email, password, role) VALUES (1, 'Admin', 'admin@gmail.com', :p, 'admin')");
    $stmt->execute(['p' => $pass]);

    echo "সব ঠিকঠাক আছে! এখন <a href='index.php'>এখানে ক্লিক করে</a> লগইন করুন। <br> ইমেইল: admin@gmail.com <br> পাসওয়ার্ড: 123456";
    
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1;");
} catch(Exception $e) {
    echo "ভুল: " . $e->getMessage();
}
?>