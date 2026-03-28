<?php
require_once __DIR__ . '/config.php';
require_admin();

$db = get_db();
$error = '';
$success = '';

// Fetch all vessels for ship assignment dropdown
$vessels = $db->query('SELECT id, name FROM vessels ORDER BY name')->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '') ?: null;
        $ship_assigned = trim($_POST['ship_assigned'] ?? '');

        if ($username === '' || $password === '' || $email === '' || $ship_assigned === '') {
            $error = t('error');
        } elseif (strlen($password) < 6) {
            $error = t('password_min_length');
        } else {
            // Check if username exists
            $check = $db->prepare('SELECT id FROM users WHERE username = ?');
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = t('username_exists');
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare('INSERT INTO users (username, password, email, contact_number, ship_assigned) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $email, $contact_number, $ship_assigned]);
                $success = t('user_created');
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $contact_number = trim($_POST['contact_number'] ?? '') ?: null;
        $ship_assigned = trim($_POST['ship_assigned'] ?? '');

        if ($id <= 0 || $username === '' || $email === '' || $ship_assigned === '') {
            $error = t('error');
        } else {
            // Check uniqueness excluding current user
            $check = $db->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
            $check->execute([$username, $id]);
            if ($check->fetch()) {
                $error = t('username_exists');
            } else {
                if ($password !== '') {
                    if (strlen($password) < 6) {
                        $error = t('password_min_length');
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $db->prepare('UPDATE users SET username = ?, password = ?, email = ?, contact_number = ?, ship_assigned = ?, updated_at = NOW() WHERE id = ?');
                        $stmt->execute([$username, $hash, $email, $contact_number, $ship_assigned, $id]);
                        $success = t('user_updated');
                    }
                } else {
                    $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, contact_number = ?, ship_assigned = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$username, $email, $contact_number, $ship_assigned, $id]);
                    $success = t('user_updated');
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $success = t('user_deleted');
        }
    }
}

// Fetch users
$users = $db->query('SELECT u.id, u.username, u.email, u.contact_number, u.ship_assigned, u.created_at, u.updated_at FROM users u ORDER BY u.id')->fetchAll();

$page_title = t('manage_users') . ' — TMA Operations 360';
$current_page = 'users';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-users"></i> <?= e(t('manage_users')) ?></h1>
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
            <!-- Add User Button -->
            <div class="inv-actions-top">
                <button class="btn btn-primary btn-md" onclick="openModal('addUserModal')">
                    <i class="fa-solid fa-plus"></i> <?= e(t('add_user')) ?>
                </button>
            </div>

            <?php if (!empty($users)): ?>
            <div class="inv-table-wrap">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= e(t('username')) ?></th>
                            <th><?= e(t('email')) ?></th>
                            <th><?= e(t('contact_number')) ?></th>
                            <th><?= e(t('ship_assigned')) ?></th>
                            <th><?= e(t('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $user): ?>
                        <tr>
                            <td class="cell-no"><?= $i + 1 ?></td>
                            <td><strong><?= e($user['username']) ?></strong></td>
                            <td><?= $user['email'] ? e($user['email']) : '<span style="color:var(--slate-400)">—</span>' ?></td>
                            <td><?= $user['contact_number'] ? e($user['contact_number']) : '<span style="color:var(--slate-400)">—</span>' ?></td>
                            <td><?= $user['ship_assigned'] ? e($user['ship_assigned']) : '<span style="color:var(--slate-400)">—</span>' ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon btn-icon--edit" title="<?= e(t('edit')) ?>"
                                        onclick="openEditUser(<?= $user['id'] ?>, '<?= e(addslashes($user['username'])) ?>', '<?= e(addslashes($user['email'] ?? '')) ?>', '<?= e(addslashes($user['contact_number'] ?? '')) ?>', '<?= e(addslashes($user['ship_assigned'] ?? '')) ?>')">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="btn-icon btn-icon--delete" title="<?= e(t('delete')) ?>">
                                            <i class="fa-solid fa-trash"></i>
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
                <i class="fa-solid fa-users"></i>
                <p><?= e(t('no_users')) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> <?= e(t('add_user')) ?></h3>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="add-username"><?= e(t('username')) ?></label>
                <input type="text" id="add-username" name="username" required placeholder="<?= e(t('enter_username')) ?>">
            </div>
            <div class="form-group">
                <label for="add-password"><?= e(t('password')) ?></label>
                <input type="password" id="add-password" name="password" required minlength="6" placeholder="<?= e(t('enter_password')) ?>">
            </div>
            <div class="form-group">
                <label for="add-email"><?= e(t('email')) ?></label>
                <input type="email" id="add-email" name="email" required placeholder="<?= e(t('enter_email')) ?>">
            </div>
            <div class="form-group">
                <label for="add-contact"><?= e(t('contact_number')) ?></label>
                <input type="text" id="add-contact" name="contact_number" placeholder="<?= e(t('enter_contact_number')) ?>">
            </div>
            <div class="form-group">
                <label for="add-ship"><?= e(t('ship_assigned')) ?></label>
                <select id="add-ship" name="ship_assigned" class="form-select" required>
                    <option value=""><?= e(t('select_ship')) ?></option>
                    <?php foreach ($vessels as $v): ?>
                    <option value="<?= e($v['name']) ?>"><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-md" onclick="closeModal('addUserModal')"><?= e(t('cancel')) ?></button>
                <button type="submit" class="btn btn-primary btn-md"><i class="fa-solid fa-check"></i> <?= e(t('save')) ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-pen"></i> <?= e(t('edit_user')) ?></h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div class="form-group">
                <label for="edit-username"><?= e(t('username')) ?></label>
                <input type="text" id="edit-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="edit-password"><?= e(t('password')) ?></label>
                <input type="password" id="edit-password" name="password" minlength="6" placeholder="<?= e(t('leave_blank_keep')) ?>">
            </div>
            <div class="form-group">
                <label for="edit-email"><?= e(t('email')) ?></label>
                <input type="email" id="edit-email" name="email" required placeholder="<?= e(t('enter_email')) ?>">
            </div>
            <div class="form-group">
                <label for="edit-contact"><?= e(t('contact_number')) ?></label>
                <input type="text" id="edit-contact" name="contact_number" placeholder="<?= e(t('enter_contact_number')) ?>">
            </div>
            <div class="form-group">
                <label for="edit-ship"><?= e(t('ship_assigned')) ?></label>
                <select id="edit-ship" name="ship_assigned" class="form-select" required>
                    <option value=""><?= e(t('select_ship')) ?></option>
                    <?php foreach ($vessels as $v): ?>
                    <option value="<?= e($v['name']) ?>"><?= e($v['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline btn-md" onclick="closeModal('editUserModal')"><?= e(t('cancel')) ?></button>
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
function openEditUser(id, username, email, contact, ship) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-username').value = username;
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-email').value = email;
    document.getElementById('edit-contact').value = contact;
    var sel = document.getElementById('edit-ship');
    sel.value = ship;
    openModal('editUserModal');
}
// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('active');
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
