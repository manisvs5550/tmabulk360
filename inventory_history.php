<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db();

// Load vessels for filter dropdown — non-admin users only see their assigned vessel
if (is_admin()) {
    $vessels_list = $db->query('SELECT id, name FROM vessels ORDER BY name')->fetchAll();
} else {
    $ship_name = $_SESSION['ship_assigned'] ?? '';
    $vstmt = $db->prepare('SELECT id, name FROM vessels WHERE name = ? ORDER BY name');
    $vstmt->execute([$ship_name]);
    $vessels_list = $vstmt->fetchAll();
}

$filter_ship = filter_var($_GET['ship'] ?? '', FILTER_VALIDATE_INT);

// Non-admin users: enforce their vessel filter
if (!is_admin() && !empty($vessels_list)) {
    $filter_ship = (int)$vessels_list[0]['id'];
}

$filter_ts   = trim($_GET['ts'] ?? '');
$has_ts_param = array_key_exists('ts', $_GET);

// Load distinct submission timestamps for the selected vessel
$ts_list = [];
if ($filter_ship) {
    $ts_stmt = $db->prepare(
        'SELECT DISTINCT submitted_at FROM inventory_submissions WHERE ship_id = ? ORDER BY submitted_at DESC'
    );
    $ts_stmt->execute([$filter_ship]);
    $ts_list = $ts_stmt->fetchAll(PDO::FETCH_COLUMN);

    // Default to latest submission only if user hasn't explicitly chosen "All Dates"
    if (!$filter_ts && !$has_ts_param && !empty($ts_list)) {
        $filter_ts = $ts_list[0];
    }
}

if ($filter_ship && $filter_ts) {
    $stmt = $db->prepare(
        'SELECT s.inventory_id, s.ship_id, s.username, s.item_no, s.item_name, s.qty_requested, s.submitted_at, v.name AS vessel_name '
        . 'FROM inventory_submissions s LEFT JOIN vessels v ON s.ship_id = v.id '
        . 'WHERE s.ship_id = ? AND s.submitted_at = ? ORDER BY s.item_no'
    );
    $stmt->execute([$filter_ship, $filter_ts]);
    $rows = $stmt->fetchAll();
} elseif ($filter_ship) {
    $stmt = $db->prepare(
        'SELECT s.inventory_id, s.ship_id, s.username, s.item_no, s.item_name, s.qty_requested, s.submitted_at, v.name AS vessel_name '
        . 'FROM inventory_submissions s LEFT JOIN vessels v ON s.ship_id = v.id '
        . 'WHERE s.ship_id = ? ORDER BY s.submitted_at DESC, s.item_no'
    );
    $stmt->execute([$filter_ship]);
    $rows = $stmt->fetchAll();
} else {
    $rows = $db->query(
        'SELECT s.inventory_id, s.ship_id, s.username, s.item_no, s.item_name, s.qty_requested, s.submitted_at, v.name AS vessel_name '
        . 'FROM inventory_submissions s LEFT JOIN vessels v ON s.ship_id = v.id '
        . 'ORDER BY s.submitted_at DESC, s.item_no'
    )->fetchAll();
}

$page_title = t('submission_history');
$current_page = 'inventory_history';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-clock-rotate-left"></i> <?= e(t('submission_history')) ?><?php if (!is_admin() && !empty($vessels_list)): ?> — <?= e($vessels_list[0]['name']) ?><?php endif; ?></h1>
            <p class="subtitle"><?= e(t('all_submissions')) ?></p>
        </div>

        <div class="inv-body">
            <div class="inv-actions-top">
                <a href="inventory.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> <?= e(t('back_to_inventory')) ?>
                </a>

                <div class="inv-filters-row">
                    <?php if (is_admin()): ?>
                    <form method="GET" class="inv-filter-form">
                        <select name="ship" class="form-select" onchange="this.form.submit()">
                            <option value=""><?= e(t('all_vessels')) ?></option>
                            <?php foreach ($vessels_list as $vsl): ?>
                            <option value="<?= $vsl['id'] ?>"<?= $filter_ship == $vsl['id'] ? ' selected' : '' ?>><?= e($vsl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>

                    <?php if ($filter_ship && !empty($ts_list)): ?>
                    <form method="GET" class="inv-filter-form">
                        <input type="hidden" name="ship" value="<?= (int)$filter_ship ?>">
                        <select name="ts" class="form-select" onchange="this.form.submit()">
                            <option value=""><?= e(t('all_dates')) ?></option>
                            <?php foreach ($ts_list as $ts_val): ?>
                            <option value="<?= e($ts_val) ?>"<?= $filter_ts === $ts_val ? ' selected' : '' ?>><?= e(date('d M Y, H:i', strtotime($ts_val))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>

                    <?php if ($filter_ship && !empty($rows)): ?>
                    <?php
                    $pdf_ts_param = $filter_ts ?: ($rows[0]['submitted_at'] ?? '');
                    ?>
                    <a href="inventory_pdf.php?ship=<?= (int)$filter_ship ?>&ts=<?= urlencode($pdf_ts_param) ?>"
                       target="_blank"
                       class="btn btn-outline btn-pdf-active">
                        <i class="fa-solid fa-file-pdf"></i> <?= e(t('print_pdf')) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($rows)): ?>
            <?php $show_date_col = !$filter_ts; ?>
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <?php if ($show_date_col): ?><th><?= e(t('date_time')) ?></th><?php endif; ?>
                            <th>Inv. ID</th>
                            <th class="col-no"><?= e(t('item_no')) ?></th>
                            <th><?= e(t('item_name')) ?></th>
                            <th><?= e(t('qty_rob')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $prev_inv_id = ''; foreach ($rows as $row): ?>
                        <tr>
                            <?php if ($show_date_col): ?><td><?= e(date('d M Y, H:i', strtotime($row['submitted_at']))) ?></td><?php endif; ?>
                            <td class="cell-inv-id"><?php if ($row['inventory_id'] !== $prev_inv_id): ?><span class="inv-id-badge"><?= e($row['inventory_id']) ?></span><?php endif; $prev_inv_id = $row['inventory_id']; ?></td>
                            <td class="cell-no"><?= (int)$row['item_no'] ?></td>
                            <td><?php $tkey = 'inv_item_' . $row['item_no']; echo e(t($tkey) !== $tkey ? t($tkey) : $row['item_name']); ?></td>
                            <td class="cell-min"><?= (int)$row['qty_requested'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="inv-empty">
                <i class="fa-solid fa-box-open"></i>
                <p>No submissions yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
