<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $name = strtoupper(trim($_POST['name'] ?? ''));
        $pool_type = $_POST['pool_type'] ?? 'TMA Bulk Pool';
        $dwt = is_numeric($_POST['dwt'] ?? '') ? (float)$_POST['dwt'] : null;
        $draft = is_numeric($_POST['draft'] ?? '') ? (float)$_POST['draft'] : null;
        $built = is_numeric($_POST['built'] ?? '') ? (int)$_POST['built'] : null;
        $yard = trim($_POST['yard'] ?? '') ?: null;
        $grain = is_numeric($_POST['grain'] ?? '') ? (float)$_POST['grain'] : null;
        $bale = is_numeric($_POST['bale'] ?? '') ? (float)$_POST['bale'] : null;
        $cranes = trim($_POST['cranes'] ?? '') ?: null;
        $has_semi_box = isset($_POST['has_semi_box']) ? true : false;
        $has_open_hatch = isset($_POST['has_open_hatch']) ? true : false;
        $has_electric_vent = isset($_POST['has_electric_vent']) ? true : false;
        $has_a60 = isset($_POST['has_a60']) ? true : false;
        $has_grabber = isset($_POST['has_grabber']) ? true : false;

        // Validate pool type
        $valid_pools = ['TMA Bulk Pool', 'MPP Tonnage', 'Non Pool Vessels'];
        if (!in_array($pool_type, $valid_pools, true)) {
            $pool_type = 'TMA Bulk Pool';
        }

        if ($name === '') {
            $error = t('error');
        } elseif ($action === 'create') {
            $stmt = $db->prepare('INSERT INTO vessels (name, pool_type, dwt, draft, built, yard, grain, bale, cranes, has_semi_box, has_open_hatch, has_electric_vent, has_a60, has_grabber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $pool_type, $dwt, $draft, $built, $yard, $grain, $bale, $cranes, $has_semi_box, $has_open_hatch, $has_electric_vent, $has_a60, $has_grabber]);
            $success = t('vessel_created');
        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare('UPDATE vessels SET name = ?, pool_type = ?, dwt = ?, draft = ?, built = ?, yard = ?, grain = ?, bale = ?, cranes = ?, has_semi_box = ?, has_open_hatch = ?, has_electric_vent = ?, has_a60 = ?, has_grabber = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$name, $pool_type, $dwt, $draft, $built, $yard, $grain, $bale, $cranes, $has_semi_box, $has_open_hatch, $has_electric_vent, $has_a60, $has_grabber, $id]);
                $success = t('vessel_updated');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM vessels WHERE id = ?');
            $stmt->execute([$id]);
            $success = t('vessel_deleted');
        }
    }
}

// Pool filter
$pool_filter = $_GET['pool'] ?? '';
if ($pool_filter && in_array($pool_filter, ['TMA Bulk Pool', 'MPP Tonnage', 'Non Pool Vessels'], true)) {
    $stmt = $db->prepare('SELECT * FROM vessels WHERE pool_type = ? ORDER BY name');
    $stmt->execute([$pool_filter]);
    $vessels_list = $stmt->fetchAll();
} else {
    $pool_filter = '';
    $vessels_list = $db->query('SELECT * FROM vessels ORDER BY name')->fetchAll();
}

// Feature filter
$feature_filter = $_GET['feature'] ?? '';
$valid_features = ['semi_box', 'open_hatch', 'electric_vent', 'a60', 'grabber'];
if ($feature_filter && in_array($feature_filter, $valid_features, true)) {
    $col = 'has_' . $feature_filter;
    // Re-query with feature filter
    if ($pool_filter) {
        $stmt = $db->prepare("SELECT * FROM vessels WHERE pool_type = ? AND $col = TRUE ORDER BY name");
        $stmt->execute([$pool_filter]);
    } else {
        $stmt = $db->prepare("SELECT * FROM vessels WHERE $col = TRUE ORDER BY name");
        $stmt->execute();
    }
    $vessels_list = $stmt->fetchAll();
} else {
    $feature_filter = '';
}

$page_title = t('manage_vessels') . ' — TMA Operations 360';
$current_page = 'vessels';
require_once __DIR__ . '/includes/header.php';

// Feature icon mapping
$feature_icons = [
    'semi_box' => 'fa-solid fa-box-open',
    'open_hatch' => 'fa-solid fa-box-archive',
    'electric_vent' => 'fa-solid fa-fan',
    'a60' => 'fa-solid fa-fire-flame-curved',
    'grabber' => 'fa-solid fa-hand-back-fist',
];
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-ship"></i> <?= e(t('manage_vessels')) ?></h1>
        </div>

        <?php if ($success): ?>
        <div class="inv-alert inv-alert-success">
            <i class="fa-solid fa-circle-check"></i> <?= e($success) ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="inv-alert inv-alert-error">
            <i class="fa-solid fa-circle-xmark"></i> <?= e($error) ?>
        </div>
        <?php endif; ?>

        <div class="inv-body">
            <!-- Pool Type Tabs -->
            <div class="vessel-tabs">
                <a href="vessels.php" class="vessel-tab<?= $pool_filter === '' ? ' active' : '' ?>"><?= e(t('all_pools')) ?></a>
                <a href="vessels.php?pool=TMA+Bulk+Pool<?= $feature_filter ? '&feature=' . e($feature_filter) : '' ?>" class="vessel-tab<?= $pool_filter === 'TMA Bulk Pool' ? ' active' : '' ?>"><?= e(t('tma_bulk_pool')) ?></a>
                <a href="vessels.php?pool=MPP+Tonnage<?= $feature_filter ? '&feature=' . e($feature_filter) : '' ?>" class="vessel-tab<?= $pool_filter === 'MPP Tonnage' ? ' active' : '' ?>"><?= e(t('mpp_tonnage')) ?></a>
                <a href="vessels.php?pool=Non+Pool+Vessels<?= $feature_filter ? '&feature=' . e($feature_filter) : '' ?>" class="vessel-tab<?= $pool_filter === 'Non Pool Vessels' ? ' active' : '' ?>"><?= e(t('non_pool_vessels')) ?></a>
            </div>

            <!-- Feature Filter Buttons -->
            <div class="vessel-features-filter">
                <?php foreach ($valid_features as $feat): ?>
                <a href="vessels.php?<?= $pool_filter ? 'pool=' . urlencode($pool_filter) . '&' : '' ?>feature=<?= $feat ?>"
                   class="feature-btn<?= $feature_filter === $feat ? ' active' : '' ?>">
                    <i class="<?= $feature_icons[$feat] ?>"></i> <?= e(t($feat)) ?>
                </a>
                <?php endforeach; ?>

                <?php if ($feature_filter || $pool_filter): ?>
                <a href="vessels.php" class="filter-clear">
                    <i class="fa-solid fa-circle-xmark"></i> <?= e(t('filter')) ?>
                </a>
                <?php endif; ?>
            </div>

            <!-- Add Vessel Button -->
            <div class="inv-actions-top">
                <button class="btn btn-primary btn-md" onclick="openModal('addVesselModal')">
                    <i class="fa-solid fa-plus"></i> <?= e(t('add_vessel')) ?>
                </button>
            </div>

            <?php if (!empty($vessels_list)): ?>
            <div class="inv-table-wrap">
                <table class="inv-table vessels-table">
                    <thead>
                        <tr>
                            <th style="width:40px"></th>
                            <th><?= e(t('vessels')) ?></th>
                            <th><?= e(t('dwt_mt')) ?></th>
                            <th><?= e(t('draft_m')) ?></th>
                            <th><?= e(t('built')) ?></th>
                            <th><?= e(t('yard')) ?></th>
                            <th><?= e(t('grain_bale')) ?></th>
                            <th><?= e(t('cranes')) ?></th>
                            <th><?= e(t('features')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vessels_list as $v): ?>
                        <tr>
                            <td class="cell-expand">
                                <button class="btn-expand" onclick="toggleVesselActions(this)" title="Actions">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </td>
                            <td><strong class="vessel-name"><?= e($v['name']) ?></strong>
                                <?php if ($v['pool_type'] === 'MPP Tonnage'): ?>
                                    <span class="pool-badge pool-mpp">MPP</span>
                                <?php elseif ($v['pool_type'] === 'Non Pool Vessels'): ?>
                                    <span class="pool-badge pool-non">Non Pool</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $v['dwt'] ? number_format((float)$v['dwt'], 0, '.', ',') : '—' ?></td>
                            <td><?= $v['draft'] !== null ? number_format((float)$v['draft'], 2) : '—' ?></td>
                            <td><?= $v['built'] ?? '—' ?></td>
                            <td><?= $v['yard'] ? e($v['yard']) : '—' ?></td>
                            <td><?= ($v['grain'] ? number_format((float)$v['grain'], 0, '.', ',') : '—') . ' / ' . ($v['bale'] ? number_format((float)$v['bale'], 0, '.', ',') : '—') ?></td>
                            <td class="cell-cranes"><?= $v['cranes'] ? e($v['cranes']) : '—' ?></td>
                            <td class="cell-features">
                                <?php if ($v['has_semi_box']): ?><span class="feature-icon-badge" title="<?= e(t('semi_box')) ?>"><i class="<?= $feature_icons['semi_box'] ?>"></i></span><?php endif; ?>
                                <?php if ($v['has_open_hatch']): ?><span class="feature-icon-badge" title="<?= e(t('open_hatch')) ?>"><i class="<?= $feature_icons['open_hatch'] ?>"></i></span><?php endif; ?>
                                <?php if ($v['has_electric_vent']): ?><span class="feature-icon-badge" title="<?= e(t('electric_vent')) ?>"><i class="<?= $feature_icons['electric_vent'] ?>"></i></span><?php endif; ?>
                                <?php if ($v['has_a60']): ?><span class="feature-icon-badge" title="<?= e(t('a60')) ?>"><i class="<?= $feature_icons['a60'] ?>"></i></span><?php endif; ?>
                                <?php if ($v['has_grabber']): ?><span class="feature-icon-badge" title="<?= e(t('grabber')) ?>"><i class="<?= $feature_icons['grabber'] ?>"></i></span><?php endif; ?>
                            </td>
                        </tr>
                        <!-- Hidden action row -->
                        <tr class="vessel-action-row" style="display:none">
                            <td colspan="9">
                                <div class="vessel-action-bar">
                                    <button class="btn btn-outline btn-sm" onclick='openEditVessel(<?= json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                        <i class="fa-solid fa-pen-to-square"></i> <?= e(t('edit')) ?>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm btn-danger">
                                            <i class="fa-solid fa-trash"></i> <?= e(t('delete')) ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="inv-empty">
                <i class="fa-solid fa-ship"></i>
                <p><?= e(t('no_vessels')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Vessel Modal -->
<div class="modal-overlay" id="addVesselModal">
    <div class="modal-card modal-card--wide">
        <div class="modal-header">
            <h3><i class="fa-solid fa-ship"></i> <?= e(t('add_vessel')) ?></h3>
            <button class="modal-close" onclick="closeModal('addVesselModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div class="form-group">
                    <label for="add-v-name"><?= e(t('vessel_name')) ?></label>
                    <input type="text" id="add-v-name" name="name" required placeholder="e.g. USUKI">
                </div>
                <div class="form-group">
                    <label for="add-v-pool"><?= e(t('pool_type')) ?></label>
                    <select id="add-v-pool" name="pool_type" class="form-select">
                        <option value="TMA Bulk Pool"><?= e(t('tma_bulk_pool')) ?></option>
                        <option value="MPP Tonnage"><?= e(t('mpp_tonnage')) ?></option>
                        <option value="Non Pool Vessels"><?= e(t('non_pool_vessels')) ?></option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add-v-dwt"><?= e(t('dwt_mt')) ?></label>
                    <input type="number" id="add-v-dwt" name="dwt" step="0.01" placeholder="e.g. 43300">
                </div>
                <div class="form-group">
                    <label for="add-v-draft"><?= e(t('draft_m')) ?></label>
                    <input type="number" id="add-v-draft" name="draft" step="0.01" placeholder="e.g. 11.0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add-v-built"><?= e(t('built')) ?></label>
                    <input type="number" id="add-v-built" name="built" min="1900" max="2100" placeholder="e.g. 2026">
                </div>
                <div class="form-group">
                    <label for="add-v-yard"><?= e(t('yard')) ?></label>
                    <input type="text" id="add-v-yard" name="yard" placeholder="e.g. Huanghai">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="add-v-grain"><?= e(t('grain')) ?></label>
                    <input type="number" id="add-v-grain" name="grain" step="0.01" placeholder="e.g. 53591">
                </div>
                <div class="form-group">
                    <label for="add-v-bale"><?= e(t('bale')) ?></label>
                    <input type="number" id="add-v-bale" name="bale" step="0.01" placeholder="e.g. 53591">
                </div>
            </div>
            <div class="form-group">
                <label for="add-v-cranes"><?= e(t('cranes')) ?></label>
                <input type="text" id="add-v-cranes" name="cranes" placeholder="e.g. 4 x 36.0 ts (grabs)">
            </div>
            <div class="form-group">
                <label><?= e(t('features')) ?></label>
                <div class="features-checkboxes">
                    <label class="feature-check"><input type="checkbox" name="has_semi_box" value="1"> <i class="<?= $feature_icons['semi_box'] ?>"></i> <?= e(t('semi_box')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_open_hatch" value="1"> <i class="<?= $feature_icons['open_hatch'] ?>"></i> <?= e(t('open_hatch')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_electric_vent" value="1"> <i class="<?= $feature_icons['electric_vent'] ?>"></i> <?= e(t('electric_vent')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_a60" value="1"> <i class="<?= $feature_icons['a60'] ?>"></i> <?= e(t('a60')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_grabber" value="1"> <i class="<?= $feature_icons['grabber'] ?>"></i> <?= e(t('grabber')) ?></label>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-md" onclick="closeModal('addVesselModal')"><?= e(t('cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-md"><i class="fa-solid fa-check"></i> <?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Vessel Modal -->
<div class="modal-overlay" id="editVesselModal">
    <div class="modal-card modal-card--wide">
        <div class="modal-header">
            <h3><i class="fa-solid fa-ship"></i> <?= e(t('edit_vessel')) ?></h3>
            <button class="modal-close" onclick="closeModal('editVesselModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-v-id">
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-v-name"><?= e(t('vessel_name')) ?></label>
                    <input type="text" id="edit-v-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit-v-pool"><?= e(t('pool_type')) ?></label>
                    <select id="edit-v-pool" name="pool_type" class="form-select">
                        <option value="TMA Bulk Pool"><?= e(t('tma_bulk_pool')) ?></option>
                        <option value="MPP Tonnage"><?= e(t('mpp_tonnage')) ?></option>
                        <option value="Non Pool Vessels"><?= e(t('non_pool_vessels')) ?></option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-v-dwt"><?= e(t('dwt_mt')) ?></label>
                    <input type="number" id="edit-v-dwt" name="dwt" step="0.01">
                </div>
                <div class="form-group">
                    <label for="edit-v-draft"><?= e(t('draft_m')) ?></label>
                    <input type="number" id="edit-v-draft" name="draft" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-v-built"><?= e(t('built')) ?></label>
                    <input type="number" id="edit-v-built" name="built" min="1900" max="2100">
                </div>
                <div class="form-group">
                    <label for="edit-v-yard"><?= e(t('yard')) ?></label>
                    <input type="text" id="edit-v-yard" name="yard">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="edit-v-grain"><?= e(t('grain')) ?></label>
                    <input type="number" id="edit-v-grain" name="grain" step="0.01">
                </div>
                <div class="form-group">
                    <label for="edit-v-bale"><?= e(t('bale')) ?></label>
                    <input type="number" id="edit-v-bale" name="bale" step="0.01">
                </div>
            </div>
            <div class="form-group">
                <label for="edit-v-cranes"><?= e(t('cranes')) ?></label>
                <input type="text" id="edit-v-cranes" name="cranes">
            </div>
            <div class="form-group">
                <label><?= e(t('features')) ?></label>
                <div class="features-checkboxes">
                    <label class="feature-check"><input type="checkbox" name="has_semi_box" value="1" id="edit-v-semi_box"> <i class="<?= $feature_icons['semi_box'] ?>"></i> <?= e(t('semi_box')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_open_hatch" value="1" id="edit-v-open_hatch"> <i class="<?= $feature_icons['open_hatch'] ?>"></i> <?= e(t('open_hatch')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_electric_vent" value="1" id="edit-v-electric_vent"> <i class="<?= $feature_icons['electric_vent'] ?>"></i> <?= e(t('electric_vent')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_a60" value="1" id="edit-v-a60"> <i class="<?= $feature_icons['a60'] ?>"></i> <?= e(t('a60')) ?></label>
                    <label class="feature-check"><input type="checkbox" name="has_grabber" value="1" id="edit-v-grabber"> <i class="<?= $feature_icons['grabber'] ?>"></i> <?= e(t('grabber')) ?></label>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-md" onclick="closeModal('editVesselModal')"><?= e(t('cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-md"><i class="fa-solid fa-check"></i> <?= e(t('save')) ?></button>
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
function toggleVesselActions(btn) {
    var row = btn.closest('tr').nextElementSibling;
    var icon = btn.querySelector('i');
    if (row.style.display === 'none') {
        row.style.display = '';
        icon.className = 'fa-solid fa-minus';
    } else {
        row.style.display = 'none';
        icon.className = 'fa-solid fa-plus';
    }
}
function openEditVessel(v) {
    document.getElementById('edit-v-id').value = v.id;
    document.getElementById('edit-v-name').value = v.name || '';
    document.getElementById('edit-v-pool').value = v.pool_type || 'TMA Bulk Pool';
    document.getElementById('edit-v-dwt').value = v.dwt || '';
    document.getElementById('edit-v-draft').value = v.draft || '';
    document.getElementById('edit-v-built').value = v.built || '';
    document.getElementById('edit-v-yard').value = v.yard || '';
    document.getElementById('edit-v-grain').value = v.grain || '';
    document.getElementById('edit-v-bale').value = v.bale || '';
    document.getElementById('edit-v-cranes').value = v.cranes || '';
    document.getElementById('edit-v-semi_box').checked = v.has_semi_box;
    document.getElementById('edit-v-open_hatch').checked = v.has_open_hatch;
    document.getElementById('edit-v-electric_vent').checked = v.has_electric_vent;
    document.getElementById('edit-v-a60').checked = v.has_a60;
    document.getElementById('edit-v-grabber').checked = v.has_grabber;
    openModal('editVesselModal');
}
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('active');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
