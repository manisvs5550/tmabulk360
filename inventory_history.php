<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db();
$rows = $db->query(
    'SELECT username, item_no, item_name, qty_requested, submitted_at FROM inventory_submissions ORDER BY submitted_at DESC, item_no'
)->fetchAll();

$page_title = 'Inventory Submission History';
$current_page = 'inventory_history';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-clock-rotate-left"></i> Submission History</h1>
            <p class="subtitle">All inventory ROB submissions</p>
        </div>

        <div class="inv-body">
            <div class="inv-actions-top">
                <a href="inventory.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Back to Inventory Form
                </a>
            </div>

            <?php if (!empty($rows)): ?>
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Username</th>
                            <th class="col-no">Item No</th>
                            <th>Item Name</th>
                            <th>Qty (ROB)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['submitted_at']) ?></td>
                            <td><?= e($row['username']) ?></td>
                            <td class="cell-no"><?= (int)$row['item_no'] ?></td>
                            <td><?= e($row['item_name']) ?></td>
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
