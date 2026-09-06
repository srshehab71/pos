<?php
session_start();
include '../config/db.php';

// সুপার অ্যাডমিন চেক
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'super_admin') {
    header("Location: ../index.php");
    exit();
}

require 'mailer/Exception.php';
require 'mailer/PHPMailer.php';
require 'mailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = "";

// ১. ম্যানুয়াল ব্যাকআপ লজিক
if (isset($_POST['trigger_backup'])) {
    $db_user = "root";
    $db_pass = "Srb@12345"; 
    $db_name = "inventory_db";
    $mysqldump_path = "X:/xampp/mysql/bin/mysqldump.exe";
    
    $filename = "manual_backup_" . date("Y-m-d_H-i-s") . ".sql";
    $path = "backup_database/" . $filename;

    $command = "\"$mysqldump_path\" --user=$db_user --password=$db_pass $db_name > \"$path\"";
    exec($command, $output, $return_var);

    if ($return_var === 0) {
        // ইমেইল পাঠানো
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
            $mail->Subject = 'Manual Backup - ' . date("Y-m-d");
            $mail->Body    = 'ম্যানুয়াল ব্যাকআপ ফাইলটি পাঠানো হলো।';
            $mail->send();

            // লগ সেভ করা
            $ins = $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status, location) VALUES (?, 'Manual', 'Success', 'Email')");
            $ins->execute([$filename]);
            
            $message = "<div class='alert alert-success shadow-sm'>ব্যাকআপ সফল এবং ইমেইল পাঠানো হয়েছে!</div>";
            unlink($path);
        } catch (Exception $e) {
    $ins = $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status, location) VALUES (?, 'Manual', 'Failed', 'Local')");
    $ins->execute([$filename]);

    $message = "<div class='alert alert-danger'>ব্যাকআপ হয়েছে কিন্তু ইমেইল যায়নি।</div>";
}
    } else {
        $conn->prepare("INSERT INTO backup_logs (filename, backup_type, status) VALUES (?, 'Manual', 'Failed')")->execute([$filename]);
        $message = "<div class='alert alert-danger'>ব্যাকআপ নিতে ব্যর্থ হয়েছে!</div>";
    }
}

// ২. ডাটাবেজ রিস্টোর লজিক
if (isset($_POST['restore_db'])) {
    $db_user = "root";
    $db_pass = "Srb@12345";
    $db_name = "inventory_db";
    $mysql_path = "X:/xampp/mysql/bin/mysql.exe";

    $file = $_FILES['sql_file'];
    $file_path = $file['tmp_name'];

    if ($file['size'] > 0) {
        // রিস্টোর কমান্ড
        $command = "\"$mysql_path\" --user=$db_user --password=$db_pass $db_name < \"$file_path\"";
        exec($command, $output, $return_var);

        if ($return_var === 0) {
            $message = "<div class='alert alert-success shadow-sm'>ডাটাবেজ সফলভাবে রিস্টোর হয়েছে!</div>";
        } else {
            $message = "<div class='alert alert-danger'>রিস্টোর করতে সমস্যা হয়েছে। ফাইলটি চেক করুন।</div>";
        }
    }
}

// ৩. লগের তালিকা আনা
$logs = $conn->query("SELECT * FROM backup_logs ORDER BY id DESC")->fetchAll();

$page_title = "ব্যাকআপ ও রিস্টোর সেন্টার";
include 'includes/header.php';
?>

<style>
    .backup-card { border-radius: 20px; border: none; background: #fff; transition: 0.3s; }
    [data-bs-theme="dark"] .backup-card { background: #1b1e22; border: 1px solid #313539; }
    .status-badge { border-radius: 10px; font-size: 11px; padding: 5px 10px; }
</style>

<div class="container-fluid py-4">
    <div class="row g-4">
        <!-- ব্যাকআপ সেকশন -->
        <div class="col-md-4">
            <div class="backup-card shadow-sm p-4 mb-4 text-center">
                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                <h5 class="fw-bold">ম্যানুয়াল ব্যাকআপ</h5>
                <p class="text-muted small">এক ক্লিকেই বর্তমান ডাটাবেজ ব্যাকআপ নিয়ে ইমেইলে পাঠান।</p>
                <form method="POST">
                    <button type="submit" name="trigger_backup" class="btn btn-primary w-100 rounded-pill fw-bold">
                        ব্যাকআপ শুরু করুন
                    </button>
                </form>
            </div>

            <!-- রিস্টোর সেকশন -->
            <div class="backup-card shadow-sm p-4 text-center border-top border-warning border-5">
                <i class="fas fa-history fa-3x text-warning mb-3"></i>
                <h5 class="fw-bold">ডাটাবেজ রিস্টোর</h5>
                <p class="text-muted small text-danger">সতর্কতা: রিস্টোর করলে বর্তমান ডাটা মুছে ফাইল অনুযায়ী আপডেট হবে!</p>
                <form method="POST" enctype="multipart/form-data">
                    <input type="file" name="sql_file" class="form-control mb-3" accept=".sql" required>
                    <button type="submit" name="restore_db" class="btn btn-warning w-100 rounded-pill fw-bold" onclick="return confirm('আপনি কি নিশ্চিত? এটি বর্তমান ডাটা মুছে ফেলবে!')">
                        রিস্টোর কনফার্ম করুন
                    </button>
                </form>
            </div>
        </div>

        <!-- হিস্ট্রি সেকশন -->
        <div class="col-md-8">
            <div class="backup-card shadow-sm p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="fas fa-list me-2 text-info"></i> ব্যাকআপ হিস্ট্রি ও লগ</h5>
                    <?php echo $message; ?>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>তারিখ ও সময়</th>
                                <th>ফাইলের নাম</th>
                                <th>টাইপ</th>
                                <th>অবস্থা</th>
                                <th>লোকেশন</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td class="small fw-bold"><?= date('d M, Y h:i A', strtotime($log['created_at'])) ?></td>
                                <td class="small text-muted"><?= $log['filename'] ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= $log['backup_type'] ?></span>
                                </td>
                                <td>
                                    <?php if($log['status'] == 'Success'): ?>
                                        <span class="status-badge bg-success-soft text-success"><i class="fas fa-check-circle me-1"></i> Success</span>
                                    <?php else: ?>
                                        <span class="status-badge bg-danger-soft text-danger"><i class="fas fa-times-circle me-1"></i> Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
    <?php if($log['location'] == 'Email'): ?>
        <span class="badge bg-primary">
            <i class="fas fa-envelope me-1"></i> Email
        </span>
    <?php elseif($log['location'] == 'Local'): ?>
        <span class="badge bg-warning text-dark">
            <i class="fas fa-folder me-1"></i> Local
        </span>
    <?php endif; ?>
</td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(count($logs) == 0): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">কোনো রেকর্ড পাওয়া যায়নি।</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </tab