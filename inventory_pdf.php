<?php
/**
 * Inventory PDF — print-friendly page that auto-triggers browser Print dialog.
 * Opens in a new tab, user can Save as PDF or print.
 */
require_once __DIR__ . '/config.php';
require_login();

$ship_id = filter_var($_GET['ship'] ?? '', FILTER_VALIDATE_INT);
$ts      = trim($_GET['ts'] ?? '');

if (!$ship_id || !$ts) {
    header('Location: inventory.php');
    exit;
}

$db = get_db();

// Non-admin users can only view PDFs for their assigned vessel
if (!is_admin()) {
    $vcheck = $db->prepare('SELECT name FROM vessels WHERE id = ?');
    $vcheck->execute([$ship_id]);
    $vrow = $vcheck->fetch();
    if (!$vrow || $vrow['name'] !== ($_SESSION['ship_assigned'] ?? '')) {
        header('Location: inventory.php');
        exit;
    }
}

// Get vessel name
$vstmt = $db->prepare('SELECT name FROM vessels WHERE id = ?');
$vstmt->execute([$ship_id]);
$vessel = $vstmt->fetch();
$vessel_name = $vessel ? $vessel['name'] : '—';

// Get the submission rows for this vessel + timestamp
$stmt = $db->prepare(
    'SELECT item_no, item_name, qty_requested FROM inventory_submissions WHERE ship_id = ? AND submitted_at = ? ORDER BY item_no'
);
$stmt->execute([$ship_id, $ts]);
$rows = $stmt->fetchAll();

if (empty($rows)) {
    header('Location: inventory.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$formatted_date = date('d M Y, H:i', strtotime($ts));
?>
<!DOCTYPE html>
<html lang="<?= e(get_lang()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= e(t('inventory')) ?> — <?= e($vessel_name) ?> — <?= e($formatted_date) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a2e;
            padding: 30px 40px;
            background: #fff;
        }
        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #6366f1;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .pdf-header h1 {
            font-size: 18pt;
            font-weight: 700;
            color: #0b1221;
        }
        .pdf-header h1 span {
            color: #6366f1;
        }
        .pdf-meta {
            text-align: right;
            font-size: 9pt;
            color: #555;
            line-height: 1.6;
        }
        .pdf-meta strong {
            color: #1a1a2e;
        }
        .pdf-vessel {
            background: #f1f5f9;
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 18px;
            font-size: 10pt;
        }
        .pdf-vessel strong {
            color: #6366f1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        thead th {
            background: #0b1221;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tbody td {
            padding: 7px 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        .col-no { width: 50px; text-align: center; }
        .col-qty { width: 80px; text-align: center; font-weight: 600; }
        .pdf-footer {
            margin-top: 30px;
            padding-top: 14px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 8.5pt;
            color: #94a3b8;
        }
        .no-print { margin-bottom: 20px; text-align: center; }
        .no-print button {
            padding: 10px 28px;
            font-size: 10pt;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 0 6px;
        }
        .btn-print { background: #6366f1; color: #fff; }
        .btn-print:hover { background: #4f46e5; }
        .btn-close { background: #e2e8f0; color: #475569; }
        .btn-close:hover { background: #cbd5e1; }
        @media print {
            .no-print { display: none; }
            body { padding: 15px 20px; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">
        🖨️ <?= e(t('print_pdf')) ?>
    </button>
    <button class="btn-close" onclick="window.close()">
        ✕ <?= e(t('close')) ?>
    </button>
</div>

<div class="pdf-header">
    <h1><span>TMA</span> Operations 360</h1>
    <div class="pdf-meta">
        <div><strong><?= e(t('date_time')) ?>:</strong> <?= e($formatted_date) ?></div>
        <div><strong><?= e(t('submitted_by')) ?>:</strong> <?= e($username) ?></div>
    </div>
</div>

<div class="pdf-vessel">
    <strong><?= e(t('vessel')) ?>:</strong> <?= e($vessel_name) ?>
    &nbsp;|&nbsp;
    <strong><?= e(t('inventory')) ?></strong> — <?= e(t('tools_cleaning_maintenance')) ?>
    &nbsp;|&nbsp;
    <strong><?= e(t('date_time')) ?>:</strong> <?= e($formatted_date) ?>
</div>

<table>
    <thead>
        <tr>
            <th class="col-no"><?= e(t('item_no')) ?></th>
            <th><?= e(t('item_name')) ?></th>
            <th class="col-qty"><?= e(t('qty_rob')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td class="col-no"><?= (int)$row['item_no'] ?></td>
            <td><?php $tkey = 'inv_item_' . $row['item_no']; echo e(t($tkey) !== $tkey ? t($tkey) : $row['item_name']); ?></td>
            <td class="col-qty"><?= (int)$row['qty_requested'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="pdf-footer">
    <div>TMA Operations 360 — <?= e(t('inventory')) ?></div>
    <div><?= e($formatted_date) ?></div>
</div>

</body>
</html>
