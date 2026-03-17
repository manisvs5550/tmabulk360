<?php
require_once __DIR__ . '/config.php';
require_login();

$page_title = 'TMA Bulk Performance';
$current_page = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

    <main class="dashboard-main">
        <div class="welcome-message">
            <h1>Welcome</h1>
            <h2>TMA Bulk Performance</h2>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
