<?php
include 'config/db.php';

// নতুন পাসওয়ার্ড '123456' এর জন্য হ্যাশ তৈরি
$new_password = password_hash('123456', PASSWORD_DEFAULT);

try {
    // আগের সুপার এডমিন থাকলে তা মুছে ফেলা (যাতে ডুপ্লিকেট না হয়)
    $conn->exec("DELETE FROM users WHERE email = 'superadmin@gmail.com'");
    
    // নতুন সুপার এডমিন ইনসার্ট করা
    // সুপার এডমিনের কোনো godown_id নেই, তাই NULL
    $stmt = $conn->prepare("INSERT INTO users (godown_id, name, email, password, role) VALUES (NULL, 'সুপার এডমিন', 'superadmin@gmail.com', :pass, 'super_admin')");
    $stmt->execute(['pass' => $new_password]);

    echo "<h3>সুপার এডমিন একাউন্ট সফলভাবে তৈরি হয়েছে!</h3>";
    echo "ইমেইল: <b>superadmin@gmail.com</b><br>";
    echo "পাসওয়ার্ড: <b>123456</b><br><br>";
    echo "<a href='index.php'>এখন লগইন করুন</a>";

} catch(PDOException $e) {
    echo "এরর হয়েছে: " . $e->getMessage();
}
?>