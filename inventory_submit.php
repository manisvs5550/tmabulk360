<?php
require_once __DIR__ . '/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: inventory.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$now = date('Y-m-d H:i:s');
$db = get_db();

$stmt = $db->prepare(
    'INSERT INTO inventory_submissions (username, item_no, item_name, qty_requested, submitted_at) VALUES (?, ?, ?, ?, ?)'
);

foreach (INVENTORY_ITEMS as $item) {
    $field = 'rob_' . $item['no'];
    $value = trim($_POST[$field] ?? '');

    if ($value === '') {
        continue;
    }

    if (!ctype_digit($value) && $value !== '0') {
        // Allow negative check: filter_var
        $qty = filter_var($value, FILTER_VALIDATE_INT);
        if ($qty === false) {
            continue;
        }
    } else {
        $qty = (int)$value;
    }

    if ($qty < 0) {
        continue;
    }

    $min_qty = $item['min_qty'];
    if ($min_qty !== null && $qty !== 0 && $qty < $min_qty) {
        continue;
    }

    $stmt->execute([$username, $item['no'], $item['item'], $qty, $now]);
}

header('Location: inventory.php?success=1');
exit;
