<?php
require_once __DIR__ . '/config.php';
require_admin();

$db = get_db();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $item_name = trim($_POST['item_name'] ?? '');
        $min_qty   = ($_POST['min_qty'] ?? '') !== '' ? filter_var($_POST['min_qty'], FILTER_VALIDATE_INT) : null;
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($item_name === '') {
            $error = t('item_name_required');
        } else {
            // Get next item_no
            $max = $db->query('SELECT COALESCE(MAX(item_no), 0) FROM inventory_items')->fetchColumn();
            $next_no = (int)$max + 1;

            $stmt = $db->prepare('INSERT INTO inventory_items (item_no, item_name, min_qty, remarks) VALUES (?, ?, ?, ?)');
            $stmt->execute([$next_no, $item_name, $min_qty, $remarks]);
            $success = t('item_added');
        }
    }

    if ($action === 'update') {
        $id        = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $item_name = trim($_POST['item_name'] ?? '');
        $min_qty   = ($_POST['min_qty'] ?? '') !== '' ? filter_var($_POST['min_qty'], FILTER_VALIDATE_INT) : null;
        $remarks   = trim($_POST['remarks'] ?? '');

        if (!$id || $item_name === '') {
            $error = t('item_name_required');
        } else {
            $stmt = $db->prepare('UPDATE inventory_items SET item_name = ?, min_qty = ?, remarks = ? WHERE id = ?');
            $stmt->execute([$item_name, $min_qty, $remarks, $id]);
            $success = t('item_updated');
        }
    }

    if ($action === 'delete') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare('DELETE FROM inventory_items WHERE id = ?');
            $stmt->execute([$id]);
            $success = t('item_deleted');
        }
    }
}

// Load all items ordered by item_no
$items = $db->query('SELECT * FROM inventory_items WHERE is_active = 1 ORDER BY item_no')->fetchAll();

$page_title = t('manage_inventory_items');
$current_page = 'inventory_items';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-boxes-stacked"></i> <?= e(t('manage_inventory_items')) ?></h1>
            <p class="subtitle"><?= e(t('manage_items_subtitle')) ?></p>
        </div>

        <?php if ($error): ?>
        <div class="inv-alert inv-alert-error">
            <i class="fa-solid fa-circle-exclamation"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="inv-alert inv-alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= e($success) ?>
        </div>
        <?php endif; ?>

        <div class="inv-body">
            <div class="inv-actions-top">
                <a href="inventory.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> <?= e(t('back_to_inventory')) ?>
                </a>
                <button type="button" class="btn btn-primary" onclick="openModal('addItemModal')">
                    <i class="fa-solid fa-plus"></i> <?= e(t('add_item')) ?>
                </button>
            </div>

            <?php if (!empty($items)): ?>
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th class="col-no">#</th>
                            <th><?= e(t('item_name')) ?></th>
                            <th style="width:100px;text-align:center"><?= e(t('min_qty')) ?></th>
                            <th style="width:200px"><?= e(t('remarks')) ?></th>
                            <th style="width:120px;text-align:center"><?= e(t('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="cell-no"><?= (int)$item['item_no'] ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td class="cell-min">
                                <?php if ($item['min_qty'] !== null): ?>
                                    <span class="min-badge"><?= (int)$item['min_qty'] ?></span>
                                <?php else: ?>
                                    <span class="min-badge min-badge--empty">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-remarks"><?= e($item['remarks']) ?></td>
                            <td style="text-align:center">
                                <button type="button" class="btn-icon btn-icon-edit" title="<?= e(t('edit')) ?>"
                                    onclick="openEditModal(<?= (int)$item['id'] ?>, <?= (int)$item['item_no'] ?>, <?= e(json_encode($item['item_name'])) ?>, <?= $item['min_qty'] !== null ? (int)$item['min_qty'] : 'null' ?>, <?= e(json_encode($item['remarks'])) ?>)">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('<?= e(t('confirm_delete_item')) ?>')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                    <button type="submit" class="btn-icon btn-icon-delete" title="<?= e(t('delete')) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="inv-empty">
                <i class="fa-solid fa-box-open"></i>
                <p><?= e(t('no_items')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal-overlay" id="addItemModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus"></i> <?= e(t('add_item')) ?></h3>
            <button class="modal-close" onclick="closeModal('addItemModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label><?= e(t('item_name')) ?> <span class="required">*</span></label>
                <input type="text" name="item_name" class="form-input" required maxlength="500">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= e(t('min_qty')) ?> <span class="required">*</span></label>
                    <input type="number" name="min_qty" class="form-input" min="0" value="0" required>
                </div>
                <div class="form-group">
                    <label><?= e(t('remarks')) ?></label>
                    <input type="text" name="remarks" class="form-input" maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addItemModal')"><?= e(t('cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> <?= e(t('add_item')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal-overlay" id="editItemModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen-to-square"></i> <?= e(t('edit_item')) ?></h3>
            <button class="modal-close" onclick="closeModal('editItemModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label><?= e(t('item_no')) ?></label>
                <input type="text" id="edit_item_no" class="form-input" disabled>
            </div>
            <div class="form-group">
                <label><?= e(t('item_name')) ?> <span class="required">*</span></label>
                <input type="text" name="item_name" id="edit_item_name" class="form-input" required maxlength="500">
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= e(t('min_qty')) ?></label>
                    <input type="number" name="min_qty" id="edit_min_qty" class="form-input" min="0" placeholder="<?= e(t('optional')) ?>">
                </div>
                <div class="form-group">
                    <label><?= e(t('remarks')) ?></label>
                    <input type="text" name="remarks" id="edit_remarks" class="form-input" maxlength="500">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editItemModal')"><?= e(t('cancel')) ?></button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> <?= e(t('save_changes')) ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById(id).classList.add('active');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
function openEditModal(id, itemNo, name, minQty, remarks) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_item_no').value = itemNo;
    document.getElementById('edit_item_name').value = name;
    document.getElementById('edit_min_qty').value = minQty !== null ? minQty : '';
    document.getElementById('edit_remarks').value = remarks || '';
    openModal('editItemModal');
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) closeModal(el.id);
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
