<?php
session_start();
include '../config/db.php';

// শুধুমাত্র সুপার অ্যাডমিন এক্সেস চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

// PHPMailer ম্যানুয়ালি ইনক্লুড করা
require 'mailer/Exception.php';
require 'mailer/PHPMailer.php';
require 'mailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

if (isset($_POST['start_backup'])) {
    // ১. ডাটাবেজ তথ্য (আপনার ড্রাইভ X: অনুযায়ী আপডেট করা হয়েছে)
    $db_user = "root";
    $db_pass = "Srb@12345"; // যদি পাসওয়ার্ড না থাকে তবে এটি ফাঁকা রাখুন ""
    $db_name = "inventory_db";
    
    // ২. mysqldump এর সঠিক পাথ (X: ড্রাইভ অনুযায়ী)
    // নিশ্চিত করুন X:\xampp\mysql\bin\mysqldump.exe এই ফাইলটি আছে
    $mysqldump_path = "X:/xampp/mysql/bin/mysqldump.exe"; 
    
    $filename = "backup_" . date("Y-m-d_H-i-s") . ".sql";
    $path = __DIR__ . DIRECTORY_SEPARATOR . $filename;

    // ৩. ব্যাকআপ কমান্ড (উইন্ডোজের জন্য ডাবল কোট ব্যবহার করা হয়েছে)
    $command = "\"$mysqldump_path\" --user=$db_user --password=$db_pass $db_name > \"$path\" 2>&1";
    
    exec($command, $output, $return_var);

    // ৪. চেক করা ফাইল তৈরি হয়েছে কি না
    if ($return_var === 0 && file_exists($path) && filesize($path) > 0) {
        
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'srb.backupfile@gmail.com';
            $mail->Password   = 'rtyl bjzg sxdb bifq'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('srb.backupfile@gmail.com', 'StockPro Backup');
            $mail->addAddress('srb.backupfile@gmail.com'); 
            $mail->addAttachment($path); 

            $mail->isHTML(true);
            $mail->Subject = 'Manual Database Backup - ' . date("Y-m-d H:i:s");
            $mail->Body    = 'আজকের ডাটাবেজ ব্যাকআপ ফাইলটি অ্যাটাচমেন্টে পাঠানো হলো।';

            $mail->send();
            $message = "<div class='alert alert-success border-0 shadow-sm'>সফল! ব্যাকআপ হয়েছে এবং ইমেইলে পাঠানো হয়েছে।</div>";
            
            unlink($path); // পিসি থেকে ডিলিট
            
        } catch (Exception $e) {
            $message = "<div class='alert alert-danger border-0 shadow-sm'>ইমেইল এরর: {$mail->ErrorInfo}</div>";
        }
    } else {
        // এরর ডিটেইলস দেখানোর জন্য
        $err_details = implode("<br>", $output);
        $message = "<div class='alert alert-danger border-0 shadow-sm'>ব্যর্থ হয়েছে! <br><small>সম্ভাব্য কারণ: mysqldump পাথ ভুল বা ডাটাবেজ পাসওয়ার্ড ভুল।</small></div>";
    }
}

$page_title = "ডাটাবেজ ব্যাকআপ টেস্ট";
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-primary text-white p-4 text-center">
                    <i class="fas fa-database fa-3x mb-3"></i>
                    <h4 class="fw-bold mb-0">ডাটাবেজ ব্যাকআপ সিস্টেম</h4>
                </div>
                <div class="card-body p-5 text-center">
                    <?php echo $message; ?>

                    <form method="POST">
                        <button type="submit" name="start_backup" class="btn btn-primary btn-lg w-100 fw-bold rounded-pill shadow-sm py-3 mt-2">
                            <i class="fas fa-paper-plane me-2"></i> ব্যাকআপ নিন ও ইমেইল করুন
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../admin/includes/footer.php'; ?>