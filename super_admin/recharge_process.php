<?php
session_start();
include '../config/db.php';

if (isset($_POST['recharge'])) {
    $gid = $_POST['godown_id'];
    $days = (int)$_POST['days'];
    $amount = (float)$_POST['amount'];

    $stmt = $conn->prepare("SELECT expiry_date FROM godowns WHERE id = ?");
    $stmt->execute([$gid]);
    $current_expiry = $stmt->fetchColumn();

    $today = date('Y-m-d');
    $base_date = ($current_expiry < $today || !$current_expiry) ? $today : $current_expiry;
    $new_expiry = date('Y-m-d', strtotime($base_date . " + $days days"));

    $update = $conn->prepare("UPDATE godowns SET expiry_date = ?, total_recharged = total_recharged + ? WHERE id = ?");
    if ($update->execute([$new_expiry, $amount, $gid])) {
        header("Location: index.php?msg=success");
    }
}