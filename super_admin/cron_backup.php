<?php
// ১. ডাটাবেজ কানেকশন ইনক্লুড করা (লগ সেভ করার জন্য)
require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

// ২. পাথ সেটিংস
$mailer_path = __DIR__ . DIRECTORY_SEPARATOR . 'mailer' . DIRECTORY_SEPARATOR;
require $mailer_path . 'Exception.php';
require $mailer_path . 'PHPMailer.php';
require $mailer_path . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ৩. ডাটাবেজ তথ্য
$db_user = "root";
$db_pass = "Srb@12345"; // আপনার সঠিক পাসওয়ার্ড
$db_name = "inventory_db";
$mysqldump_path = "X:/xampp/mysql/bin/mysqldump.exe"; // আপনার X: ড্রাইভ অনুযায়ী

$filename = "auto_backup_" . date("Y-m-d_H-i-s") . ".sql";
$path = "backup_database/" . $filename;

// ৪. ব্যাকআপ ফাইল তৈরি করা
$command = "\"$mysqldump_path\" --user=$db_user --password=$db_pass $db_name > \"$path\"";
exec($command, $output, $return_var);

if ($return_var === 0 && file_exists($path)) {
    $mail = new PHPMailer(true);
    try {
        // SMTP কনফিগারেশন
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'srb.backupfile@gmail.com';
        $mail->Password   = 'rtyl bjzg sxdb bifq'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('srb.backupfile@gmail.com', 'StockPro Auto-Backup');
        $mail->addAddress('srb.backupfile@gmail.com'); 
        $mail->addAttachment($path); 

        $mail->isHTML(true);
        $mail->Subject = 'Automatic Daily Backup - ' . date("Y-m-d");
        $mail->Body    = 'সিস্টেম থেকে আজকের অটোমেটিক ডাটাবেজ ব্যাকআপ ফাইলটি পাঠানো হয়েছে।';

        if($mail->send()){
            // ৫. ডাটাবেজে সফল ব্যাকআপের লগ সেভ করা
            $stmt = $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status) VALUES (?, 'Automatic', 'Success')");
            $stmt->execute([$filename]);
            
            unlink($path); // পাঠানোর পর পিসি থেকে ডিলিট
            echo "Success: Backup sent and logged at " . date("H:i:s");
        }
        
    } catch (Exception $e) {
        // ইমেইল পাঠাতে ব্যর্থ হলে লগ সেভ
        $stmt = $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status) VALUES (?, 'Automatic', 'Failed')");
        $stmt->execute([$filename]);
        echo "Mail Error: " . $mail->ErrorInfo;
    }
} else {
    // ব্যাকআপ ফাইল তৈরি করতে ব্যর্থ হলে লগ সেভ
    $stmt = $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status) VALUES (?, 'Automatic', 'Failed')");
    $stmt->execute([$filename]);
    echo "Backup Failed!";
}
?>