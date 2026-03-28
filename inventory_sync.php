<?php
/**
 * Inventory Sync API — accepts JSON POST from offline queue.
 * Returns JSON response so the service worker / JS can confirm success.
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in (session cookie)
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['ship_id']) || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
    exit;
}

$ship_id  = filter_var($input['ship_id'], FILTER_VALIDATE_INT);
$username = $_SESSION['username'] ?? 'Admin';
$now      = date('Y-m-d H:i:s');

if (!$ship_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid ship_id']);
    exit;
}

$db   = get_db();

// Generate a tracking inventory ID
$inventory_id = generate_inventory_id($db);

$stmt = $db->prepare(
    'INSERT INTO inventory_submissions (inventory_id, ship_id, username, item_no, item_name, qty_requested, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$saved = 0;
foreach ($input['items'] as $row) {
    $item_no = filter_var($row['item_no'] ?? 0, FILTER_VALIDATE_INT);
    $qty     = filter_var($row['qty'] ?? 0, FILTER_VALIDATE_INT);
    $name    = trim($row['item_name'] ?? '');

    if (!$item_no || $qty === false || $qty < 0 || $name === '') {
        continue;
    }

    $stmt->execute([$inventory_id, $ship_id, $username, $item_no, $name, $qty, $now]);
    $saved++;
}

echo json_encode([
    'ok'    => true,
    'saved' => $saved,
    'ts'    => $now,
]);
