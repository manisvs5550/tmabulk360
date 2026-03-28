<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db();

// Non-admin users can only see their assigned vessel
if (is_admin()) {
    $vessels_list = $db->query('SELECT id, name FROM vessels ORDER BY name')->fetchAll();
} else {
    $ship_name = $_SESSION['ship_assigned'] ?? '';
    $vstmt = $db->prepare('SELECT id, name FROM vessels WHERE name = ? ORDER BY name');
    $vstmt->execute([$ship_name]);
    $vessels_list = $vstmt->fetchAll();
}

$inv_items = $db->query('SELECT * FROM inventory_items WHERE is_active = 1 ORDER BY item_no')->fetchAll();

$success = isset($_GET['success']);
$pdf_ship = $_GET['ship'] ?? '';
$pdf_ts   = $_GET['ts'] ?? '';
$page_title = t('inventory') . ' — ' . t('tools_cleaning_maintenance');
$current_page = 'inventory';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-clipboard-list"></i> <?= e(t('tools_cleaning_maintenance')) ?><?php if (!is_admin() && !empty($vessels_list)): ?> — <?= e($vessels_list[0]['name']) ?><?php endif; ?></h1>
            <p class="subtitle"><?= e(t('enter_rob_quantities')) ?></p>
        </div>

        <?php if ($success): ?>
        <div class="inv-alert inv-alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= e(t('inventory_submitted')) ?>
        </div>
        <?php endif; ?>

        <div class="inv-body">
            <div class="inv-actions-top">
                <a href="inventory_history.php" class="btn btn-outline">
                    <i class="fa-solid fa-clock-rotate-left"></i> <?= e(t('view_history')) ?>
                </a>
                <?php if (is_admin()): ?>
                <a href="inventory_items.php" class="btn btn-outline">
                    <i class="fa-solid fa-boxes-stacked"></i> <?= e(t('manage_items')) ?>
                </a>
                <?php endif; ?>
                <span id="inv-pending-badge" class="pending-badge" style="display:none"></span>
            </div>

            <form method="POST" action="inventory_submit.php">
                <?php if (is_admin()): ?>
                <div class="inv-vessel-select">
                    <label for="ship_id"><i class="fa-solid fa-ship"></i> <?= e(t('select_vessel_for_inventory')) ?></label>
                    <select name="ship_id" id="ship_id" class="form-select" required>
                        <option value=""><?= e(t('select_ship')) ?></option>
                        <?php foreach ($vessels_list as $vsl): ?>
                        <option value="<?= $vsl['id'] ?>"><?= e($vsl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <input type="hidden" name="ship_id" value="<?= !empty($vessels_list) ? (int)$vessels_list[0]['id'] : '' ?>">
                <?php endif; ?>
                <div class="inv-table-wrap">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th class="col-no"><?= e(t('item_no')) ?></th>
                                <th class="col-item"><?= e(t('item_name')) ?></th>
                                <th class="col-min"><?= e(t('min_required')) ?></th>
                                <th class="col-rob"><?= e(t('rob_qty')) ?></th>
                                <th class="col-remarks"><?= e(t('remarks')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="inv-category-row">
                                <td colspan="5"><i class="fa-solid fa-wrench" style="margin-right:6px;opacity:.6"></i> <?= e(t('tools_cleaning_maintenance')) ?></td>
                            </tr>
                            <?php foreach ($inv_items as $item): ?>
                            <tr>
                                <td class="cell-no"><?= (int)$item['item_no'] ?></td>
                                <td class="cell-item"><?= e(t('inv_item_' . $item['item_no']) !== 'inv_item_' . $item['item_no'] ? t('inv_item_' . $item['item_no']) : $item['item_name']) ?></td>
                                <td class="cell-min">
                                    <?php if ($item['min_qty'] !== null): ?>
                                        <span class="min-badge"><?= (int)$item['min_qty'] ?></span>
                                    <?php else: ?>
                                        <span class="min-badge min-badge--empty">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-rob">
                                    <input
                                        type="number"
                                        name="rob_<?= (int)$item['item_no'] ?>"
                                        min="0"
                                        class="rob-input"
                                        placeholder="0"
                                        <?php if ($item['min_qty'] !== null): ?>data-min="<?= (int)$item['min_qty'] ?>"<?php endif; ?>
                                        oninput="validateRob(this)"
                                    >
                                    <div class="rob-error" id="err_<?= (int)$item['item_no'] ?>"></div>
                                </td>
                                <td class="cell-remarks"><?= e(t('inv_remark_' . $item['item_no']) !== 'inv_remark_' . $item['item_no'] ? t('inv_remark_' . $item['item_no']) : $item['remarks']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="inv-actions">
                    <button type="submit" id="inv-submit-btn" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-paper-plane"></i> <?= e(t('submit_inventory')) ?>
                    </button>
                    <?php if ($success && $pdf_ship && $pdf_ts): ?>
                    <a href="inventory_pdf.php?ship=<?= (int)$pdf_ship ?>&ts=<?= urlencode($pdf_ts) ?>" target="_blank" class="btn btn-outline btn-lg btn-pdf-active">
                        <i class="fa-solid fa-file-pdf"></i> <?= e(t('print_pdf')) ?>
                    </a>
                    <?php else: ?>
                    <button type="button" class="btn btn-outline btn-lg btn-pdf-disabled" disabled title="<?= e(t('submit_first')) ?>">
                        <i class="fa-solid fa-file-pdf"></i> <?= e(t('print_pdf')) ?>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Disable submit button on form submit to prevent double-click
var invForm = document.querySelector('form[action="inventory_submit.php"]');
if (invForm) {
    invForm.addEventListener('submit', function() {
        var btn = document.getElementById('inv-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting\u2026';
        }
    });
}

function validateRob(input) {
    var val = input.value.trim();
    var minQty = input.dataset.min ? parseInt(input.dataset.min) : null;
    var errEl = input.parentElement.querySelector('.rob-error');

    if (val === '' || val === '0') {
        input.classList.remove('rob-input--error');
        errEl.textContent = '';
        return;
    }

    var num = parseInt(val);
    if (isNaN(num) || num < 0) {
        input.classList.add('rob-input--error');
        errEl.textContent = 'Must be 0 or above';
        return;
    }

    if (minQty !== null && num > 0 && num < minQty) {
        input.classList.add('rob-input--error');
        errEl.textContent = 'Min ' + minQty;
        return;
    }

    input.classList.remove('rob-input--error');
    errEl.textContent = '';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
