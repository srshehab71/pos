<?php
include 'db.php';

// ডাটাবেস থেকে সেটিংস আনা
$stmt = $conn->query("SELECT * FROM saas_settings WHERE id = 1");
$bkash_sets = $stmt->fetch();

define("BKASH_APP_KEY", $bkash_sets['N3gcEaUWq2pxhTomqTicBCS2tc']);
define("BKASH_APP_SECRET", $bkash_sets['AZ9AKjEGrnhYW9yHvnRveZY3kDGDStaZHUIeVUVgn8qZRsYFLQMN']);
define("BKASH_USERNAME", $bkash_sets['01331826365']);
define("BKASH_PASSWORD", $bkash_sets['{1VoEx&CO0p']);
define("BKASH_BASE_URL", ($bkash_sets['is_bkash_live'] == 'yes') ? "https://pay.bka.sh/v1.2.0-beta" : "https://sandbox.bka.sh/v1.2.0-beta");