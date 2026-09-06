<?php
// ডাটাবেস কানেকশন এবং হেডার ফাইল ইনক্লুড করা হয়েছে
include 'includes/header.php';

// সব সক্রিয় নোটিশকে ইনঅ্যাক্টিভ (বন্ধ) করার কুয়েরি
$update = $conn->query("UPDATE broadcasts SET status = 'inactive'");

if($update){
    // নোটিশ বন্ধ হওয়ার পর আবার নোটিশ বোর্ডের পেজে ফেরত নিয়ে যাবে
    // এখানে 'notice_board.php' বলতে আপনার মূল ফাইলটিকে বোঝানো হয়েছে
    echo "<script>
            alert('সফলভাবে নোটিশ বন্ধ করা হয়েছে।');
            window.location.href = 'index.php'; // বা আপনার নোটিশ ম্যানেজমেন্ট পেজের নাম দিন
          </script>";
} else {
    echo "দুঃখিত, নোটিশ বন্ধ করা সম্ভব হয়নি।";
}

include 'includes/footer.php';
?>