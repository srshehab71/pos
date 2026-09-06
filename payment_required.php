<?php
session_start();
include 'config/db.php';

// ইউজার লগইন না থাকলে বা রোল এডমিন না হলে তাকে বের করে দাও
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// ডাটাবেস থেকে প্যাকেজগুলো নিয়ে আসা (যা আপনি শুরুতে তৈরি করেছিলেন)
$packages = $conn->query("SELECT * FROM packages ORDER BY price ASC")->fetchAll();
$user_name = $_SESSION['user_name'];
$shop_name = $_SESSION['shop_name'];
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>একাউন্ট রিনিউ করুন | SRB Digital</title>
    <!-- Bootstrap 5 & Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: #f4f7fe;
            font-family: 'Poppins', 'Hind Siliguri', sans-serif;
        }
        .renew-container {
            max-width: 1000px;
            margin: 50px auto;
        }
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        .header-section h2 {
            font-weight: 700;
            color: #2d3436;
        }
        .package-card {
            background: #fff;
            border: none;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
            height: 100%;
        }
        .package-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(78, 115, 223, 0.15);
        }
        .package-card.popular {
            border: 2px solid #4e73df;
        }
        .popular-badge {
            position: absolute;
            top: 10px;
            right: -30px;
            background: #4e73df;
            color: #fff;
            padding: 5px 40px;
            transform: rotate(45deg);
            font-size: 12px;
            font-weight: bold;
        }
        .price-tag {
            font-size: 40px;
            font-weight: 700;
            color: #4e73df;
            margin: 20px 0;
        }
        .price-tag span {
            font-size: 16px;
            color: #636e72;
        }
        .features-list {
            list-style: none;
            padding: 0;
            margin-bottom: 30px;
            text-align: left;
        }
        .features-list li {
            margin-bottom: 12px;
            color: #636e72;
            font-size: 14px;
        }
        .features-list li i {
            color: #27ae60;
            margin-right: 10px;
        }
        .btn-renew {
            background: linear-gradient(135deg, #4e73df, #224abe);
            color: #fff;
            border: none;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
            transition: 0.3s;
        }
        .btn-renew:hover {
            background: linear-gradient(135deg, #224abe, #1a3a96);
            color: #fff;
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
        }
        .bkash-logo {
            width: 80px;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container renew-container">
    <div class="header-section">
        <div class="mb-3">
            <i class="fas fa-exclamation-circle text-danger fa-4x"></i>
        </div>
        <h2>দুঃখিত, আপনার এ্যাডমিন প্যানেল ডিএ্যাক্টিভ!</h2>
        <p class="text-muted">প্রিয় <strong><?= htmlspecialchars($user_name) ?></strong>, আপনার প্রতিষ্ঠান <strong><?= htmlspecialchars($shop_name) ?></strong> এর সেবা ডিএ্যাক্টিভ করা হয়েছে, এ সেবা সচল রাখতে প্রতিনিধির সাথে যোগাযোগ করুন</p>
    </div>



    <div class="text-center mt-5">
        <p class="text-muted">যেকোনো সমস্যায় কল করুন: <a href="tel:01780432441" class="text-primary fw-bold text-decoration-none">০১৭৮০-৪৩২৪৪১</a></p>
        <a href="logout.php" class="btn btn-link text-secondary text-decoration-none"><i class="fas fa-sign-out-alt me-1"></i> লগআউট করুন</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>