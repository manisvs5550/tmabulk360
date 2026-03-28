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

// Get the selected vessel
$ship_id = filter_var($_POST['ship_id'] ?? '', FILTER_VALIDATE_INT);
if (!$ship_id) {
    header('Location: inventory.php');
    exit;
}

// Non-admin users can only submit for their assigned vessel
if (!is_admin()) {
    $vcheck = $db->prepare('SELECT name FROM vessels WHERE id = ?');
    $vcheck->execute([$ship_id]);
    $vrow = $vcheck->fetch();
    if (!$vrow || $vrow['name'] !== ($_SESSION['ship_assigned'] ?? '')) {
        header('Location: inventory.php');
        exit;
    }
}

$stmt = $db->prepare(
    'INSERT INTO inventory_submissions (inventory_id, ship_id, username, item_no, item_name, qty_requested, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
);

// Generate a tracking inventory ID
$inventory_id = generate_inventory_id($db);

// Load items from DB
$inv_items = $db->query('SELECT * FROM inventory_items WHERE is_active = 1 ORDER BY item_no')->fetchAll();

foreach ($inv_items as $item) {
    $field = 'rob_' . $item['item_no'];
    $value = trim($_POST[$field] ?? '');

    if ($value === '') {
        continue;
    }

    if (!ctype_digit($value) && $value !== '0') {
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

    $min_qty = $item['min_qty'] !== null ? (int)$item['min_qty'] : null;
    if ($min_qty !== null && $qty !== 0 && $qty < $min_qty) {
        continue;
    }

    $stmt->execute([$inventory_id, $ship_id, $username, $item['item_no'], $item['item_name'], $qty, $now]);
}

header('Location: inventory.php?success=1&ship=' . $ship_id . '&ts=' . urlencode($now));
exit;
