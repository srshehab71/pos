<?php
// bKash API Credentials
define("BKASH_APP_KEY", "N3gcEaUWq2pxhTomqTicBCS2tc");
define("BKASH_APP_SECRET", "AZ9AKjEGrnhYW9yHvnRveZY3kDGDStaZHUIeVUVgn8qZRsYFLQMN");
define("BKASH_USERNAME", "01331826365");
define("BKASH_PASSWORD", "{1VoEx&CO0p");

define("BKASH_BASE_URL", "https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized");

// ডাইনামিক হোস্ট ডিটেকশন
$host = $_SERVER['HTTP_HOST']; // এটি ব্রাউজারের লেখা অনুযায়ী localhost বা IP নিয়ে নেবে
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";

define("CALLBACK_URL", $protocol . "://" . $host . "/inventory_system/bkash/execute_payment.php");
?>