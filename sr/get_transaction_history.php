<?php
session_start();
include '../config/db.php';
$gid = $_SESSION['godown_id'];
$id = $_POST['id'] ?? ''; // কাস্টমার ফোন নম্বর
$history = [];

// ১. ইনভয়েস এবং ওই সময়ের নগদ জমার তথ্য আনা
$sales_stmt = $conn->prepare("
    SELECT id, payable_amount as bill, paid_amount as total_paid_in_table, created_at as date,
    (SELECT GROUP_CONCAT(p.product_name SEPARATOR '|') FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = sales.id) as item_list
    FROM sales 
    WHERE customer_phone = ? AND godown_id = ?
");
$sales_stmt->execute([$id, $gid]);
$all_sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($all_sales as $s) {
    // এই ইনভয়েসের বিপরীতে পরবর্তীতে নেওয়া পেমেন্টগুলো চেক করা
    $p_stmt = $conn->prepare("SELECT SUM(amount_paid) as total_collected FROM customer_payments WHERE sale_id = ?");
    $p_stmt->execute([$s['id']]);
    $collected = $p_stmt->fetch();
    $total_collected_later = (float)($collected['total_collected'] ?? 0);

    // ইনভয়েস তৈরির সময়কার নগদ জমা
    $initial_paid = (float)$s['total_paid_in_table'] - $total_collected_later;

    $history[] = [
        'id' => $s['id'],
        'bill' => (float)$s['bill'],
        'paid' => $initial_paid,
        'date' => $s['date'],
        'mode' => 'Invoice',
        'items' => $s['item_list'],
        'p_id' => null // ইনভয়েসের জন্য আলাদা পেমেন্ট আইডি নেই
    ];
}

// ২. পরবর্তীতে নেওয়া কালেকশনগুলো আনা
$pays_stmt = $conn->prepare("
    SELECT cp.id as p_id, cp.amount_paid as amt, cp.payment_date as date, s.id as inv 
    FROM customer_payments cp 
    JOIN sales s ON cp.sale_id = s.id 
    WHERE s.customer_phone = ? AND cp.godown_id = ?
");
$pays_stmt->execute([$id, $gid]);
while($p = $pays_stmt->fetch(PDO::FETCH_ASSOC)) {
    $history[] = [
        'id' => $p['inv'],
        'bill' => 0,
        'paid' => (float)$p['amt'],
        'date' => $p['date'],
        'mode' => 'Collection',
        'p_id' => $p['p_id']
    ];
}

// তারিখ অনুযায়ী ছোট থেকে বড় সাজানো (ব্যালেন্সের জন্য)
usort($history, function($a, $b) { return strtotime($a['date']) - strtotime($b['date']); });

$temp_balance = 0;
$total_bill = 0;
$total_paid = 0;

foreach($history as &$row) {
    $temp_balance += ($row['bill'] - $row['paid']);
    $row['current_row_balance'] = $temp_balance;
    $total_bill += $row['bill'];
    $total_paid += $row['paid'];
}
unset($row);

// উল্টো করে নেওয়া (নতুন উপরে দেখাবে)
$display_history = array_reverse($history);
?>

<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light text-uppercase small" style="font-size: 11px;">
            <tr>
                <th class="ps-3">তারিখ</th>
                <th>বিবরণ ও পণ্যসমূহ</th>
                <th class="text-end">বিল (+)</th>
                <th class="text-end">জমা (-)</th>
                <th class="text-end text-primary">ব্যালেন্স</th>
                <th class="text-center">অ্যাকশন</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($display_history as $h): ?>
            <tr style="border-bottom: 1px solid #f1f1f1;">
                <td class="ps-3 small text-nowrap">
                    <div class="fw-bold"><?= date('d M, y', strtotime($h['date'])) ?></div>
                    <div class="text-muted" style="font-size: 10px;"><?= date('h:i A', strtotime($h['date'])) ?></div>
                </td>
                <td>
                    <?php if($h['mode'] == 'Invoice'): ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 mb-1">📦 ইনভয়েস #<?= $h['id'] ?></span>
                        <?php if(!empty($h['items'])): ?>
                            <ul class="list-unstyled mb-0 ms-2" style="font-size: 11px; color: #666;">
                                <?php foreach(explode('|', $h['items']) as $item): ?>
                                    <li><i class="fas fa-circle text-primary me-2" style="font-size: 5px;"></i><?= htmlspecialchars($item) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">💰 নগদ আদায় (ইনভয়েস #<?= $h['id'] ?>) মেমো থেকে সমন্বয় করা হয়েছে</span>
                    <?php endif; ?>
                </td>
                <td class="text-end fw-bold text-danger"><?= $h['bill'] > 0 ? '৳'.number_format($h['bill'], 0) : '-' ?></td>
                <td class="text-end fw-bold text-success"><?= $h['paid'] > 0 ? '৳'.number_format($h['paid'], 0) : '-' ?></td>
                <td class="text-end fw-bold text-dark">৳<?= number_format($h['current_row_balance'], 0) ?></td>
                <td class="text-center">
                    <div class="btn-group">
                    <?php if($h['mode'] == 'Invoice'): ?>
                        <!-- ইনভয়েস এডিট ও ডিলিট -->
                        <a href="edit_sale.php?id=<?= $h['id'] ?>" class="btn btn-sm text-warning" title="এডিট"><i class="fas fa-edit"></i></a>
                        <a href="delete_sale.php?id=<?= $h['id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('ইনভয়েস ডিলিট করলে স্টক ফেরত যাবে। নিশ্চিত?')" title="ডিলিট"><i class="fas fa-trash-alt"></i></a>
                    <?php else: ?>
                        <!-- পেমেন্ট এডিট ও ডিলিট -->
                        <a href="edit_payment.php?id=<?= $h['p_id'] ?>" class="btn btn-sm text-warning" title="এডিট"><i class="fas fa-edit"></i></a>
                        <a href="delete_payment.php?id=<?= $h['p_id'] ?>" class="btn btn-sm text-danger" onclick="return confirm('পেমেন্টটি ডিলিট করলে বাকী আবার বেড়ে যাবে। নিশ্চিত?')" title="ডিলিট"><i class="fas fa-trash-alt"></i></a>
                    <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-light fw-bold border-top">
            <tr>
                <td colspan="2" class="ps-3 text-dark">সর্বমোট হিসাব:</td>
                <td class="text-end text-danger">৳<?= number_format($total_bill, 0) ?></td>
                <td class="text-end text-success">৳<?= number_format($total_paid, 0) ?></td>
                <td class="text-end text-primary" style="font-size: 15px;">৳<?= number_format($total_bill - $total_paid, 0) ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>