<?php
require_once __DIR__ . '/config.php';
require_login();

$db = get_db();

if (is_admin()) {
    // Admin stats
    $total_vessels = (int)$db->query('SELECT COUNT(*) FROM vessels')->fetchColumn();
    $total_inventories = (int)$db->query('SELECT COUNT(DISTINCT inventory_id) FROM inventory_submissions WHERE inventory_id != ""')->fetchColumn();
    $total_users = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $latest_submissions = $db->query(
        'SELECT s.inventory_id, v.name AS vessel_name, MAX(s.submitted_at) AS submitted_at, s.username '
        . 'FROM inventory_submissions s LEFT JOIN vessels v ON s.ship_id = v.id '
        . 'WHERE s.inventory_id != "" '
        . 'GROUP BY s.inventory_id, v.name, s.username ORDER BY submitted_at DESC LIMIT 5'
    )->fetchAll();
} else {
    // Normal user stats
    $username = $_SESSION['username'] ?? '';
    $ship_name = $_SESSION['ship_assigned'] ?? '';

    // Count inventories for the user's assigned vessel
    $vstmt = $db->prepare('SELECT id FROM vessels WHERE name = ?');
    $vstmt->execute([$ship_name]);
    $user_vessel = $vstmt->fetch();
    $user_vessel_id = $user_vessel ? (int)$user_vessel['id'] : 0;

    $stmt = $db->prepare('SELECT COUNT(DISTINCT inventory_id) FROM inventory_submissions WHERE ship_id = ? AND inventory_id != ""');
    $stmt->execute([$user_vessel_id]);
    $user_inventories = (int)$stmt->fetchColumn();

    $last_stmt = $db->prepare(
        'SELECT s.inventory_id, MAX(s.submitted_at) AS submitted_at '
        . 'FROM inventory_submissions s '
        . 'WHERE s.ship_id = ? AND s.inventory_id != "" '
        . 'GROUP BY s.inventory_id ORDER BY submitted_at DESC LIMIT 1'
    );
    $last_stmt->execute([$user_vessel_id]);
    $last_submission = $last_stmt->fetch();
}

$page_title = 'Dashboard — TMA Operations 360';
$current_page = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="dash-container">
        <div class="dash-welcome">
            <h1><?= e(sprintf(t('welcome_user'), $_SESSION['username'] ?? 'User')) ?></h1>
            <?php if (!is_admin() && $ship_name): ?>
            <p class="dash-vessel-label"><i class="fa-solid fa-ship"></i> <?= e($ship_name) ?></p>
            <?php endif; ?>
        </div>

        <?php if (is_admin()): ?>
        <!-- ===== ADMIN DASHBOARD ===== -->
        <div class="dash-cards">
            <div class="dash-card dash-card--vessels">
                <div class="dash-card-icon"><i class="fa-solid fa-ship"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value"><?= $total_vessels ?></span>
                    <span class="dash-card-label"><?= e(t('vessels_label')) ?></span>
                </div>
            </div>
            <div class="dash-card dash-card--inventories">
                <div class="dash-card-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value"><?= $total_inventories ?></span>
                    <span class="dash-card-label"><?= e(t('inventories_received')) ?></span>
                </div>
            </div>
            <div class="dash-card dash-card--users">
                <div class="dash-card-icon"><i class="fa-solid fa-users"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value"><?= $total_users ?></span>
                    <span class="dash-card-label"><?= e(t('users_label')) ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($latest_submissions)): ?>
        <div class="dash-recent">
            <h2><i class="fa-solid fa-clock-rotate-left"></i> <?= e(t('recent_submissions')) ?></h2>
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th><?= e(t('inv_id')) ?></th>
                            <th><?= e(t('vessel')) ?></th>
                            <th><?= e(t('submitted_by')) ?></th>
                            <th><?= e(t('date')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($latest_submissions as $sub): ?>
                        <tr>
                            <td><span class="inv-id-badge"><?= e($sub['inventory_id']) ?></span></td>
                            <td><?= e($sub['vessel_name'] ?? '—') ?></td>
                            <td><?= e($sub['username']) ?></td>
                            <td><?= e(date('d M Y, H:i', strtotime($sub['submitted_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ===== NORMAL USER DASHBOARD ===== -->
        <div class="dash-cards">
            <div class="dash-card dash-card--inventories">
                <div class="dash-card-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value"><?= $user_inventories ?></span>
                    <span class="dash-card-label"><?= e(t('inventories_submitted')) ?></span>
                </div>
            </div>
            <div class="dash-card dash-card--offline" id="dash-offline-card">
                <div class="dash-card-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value" id="dash-offline-count">0</span>
                    <span class="dash-card-label"><?= e(t('pending_offline')) ?></span>
                </div>
            </div>
            <div class="dash-card dash-card--last">
                <div class="dash-card-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="dash-card-body">
                    <span class="dash-card-value dash-card-value--sm"><?= $last_submission ? e(date('d M Y', strtotime($last_submission['submitted_at']))) : '—' ?></span>
                    <span class="dash-card-label"><?= e(t('last_submission')) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Update offline pending count on dashboard
(function() {
    var el = document.getElementById('dash-offline-count');
    if (!el) return;
    var DB_NAME = 'tmaops360_inventory';
    var STORE = 'pending_submissions';
    var req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = function() {
        var db = req.result;
        if (!db.objectStoreNames.contains(STORE)) {
            db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
        }
    };
    req.onsuccess = function() {
        var db = req.result;
        if (!db.objectStoreNames.contains(STORE)) { el.textContent = '0'; return; }
        var tx = db.transaction(STORE, 'readonly');
        var countReq = tx.objectStore(STORE).count();
        countReq.onsuccess = function() { el.textContent = countReq.result; };
        countReq.onerror = function() { el.textContent = '0'; };
    };
    req.onerror = function() { el.textContent = '0'; };
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
