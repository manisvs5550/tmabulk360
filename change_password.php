<?php
require_once __DIR__ . '/config.php';
require_login();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($new !== $confirm) {
        $error = t('password_mismatch');
    } elseif (strlen($new) < 6) {
        $error = t('password_min_length');
    } else {
        $db = get_db();
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current, $user['password'])) {
            $error = t('wrong_current_password');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT);
            $stmt = $db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$hash, $_SESSION['user_id']]);
            $success = t('password_changed');
        }
    }
}

$page_title = t('change_password') . ' — TMA Operations 360';
$current_page = 'change_password';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <div class="inv-wrapper">
        <div class="inv-header">
            <h1><i class="fa-solid fa-key"></i> <?= e(t('change_password')) ?></h1>
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
            <div class="form-card" style="max-width:480px">
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password"><?= e(t('current_password')) ?></label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password"><?= e(t('new_password')) ?></label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><?= e(t('confirm_new_password')) ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <div class="form-actions" style="margin-top:20px">
                        <button type="submit" class="btn btn-primary btn-lg btn-block">
                            <i class="fa-solid fa-check"></i> <?= e(t('save')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
