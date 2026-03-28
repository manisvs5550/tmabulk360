<?php
/**
 * TMA Operations 360 — Configuration
 * Database connection, session setup, and shared constants.
 */

session_start();

// --- Database (MySQL) ---
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'tmabulk360');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'admin');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// --- Auth helper ---
function is_logged_in(): bool {
    return !empty($_SESSION['logged_in']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

function is_admin(): bool {
    return ($_SESSION['username'] ?? '') === 'Admin';
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        header('Location: inventory.php');
        exit;
    }
}

function generate_inventory_id(PDO $db): string {
    $prefix = 'INV-' . date('Ymd') . '-';
    $stmt = $db->prepare("SELECT COUNT(DISTINCT inventory_id) FROM inventory_submissions WHERE inventory_id LIKE ?");
    $stmt->execute([$prefix . '%']);
    $seq = (int)$stmt->fetchColumn() + 1;
    return $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// --- Language helper ---
define('SUPPORTED_LANGUAGES', ['en', 'es', 'fr', 'de']);
define('LANGUAGE_LABELS', [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
]);

function get_lang(): string {
    $lang = $_SESSION['language'] ?? 'en';
    return in_array($lang, SUPPORTED_LANGUAGES, true) ? $lang : 'en';
}

function load_translations(): array {
    $lang = get_lang();
    $file = __DIR__ . '/lang/' . $lang . '.php';
    if (file_exists($file)) {
        return require $file;
    }
    return require __DIR__ . '/lang/en.php';
}

function t(string $key): string {
    static $translations = null;
    if ($translations === null) {
        $translations = load_translations();
    }
    return $translations[$key] ?? $key;
}

// Handle language switch
if (isset($_GET['set_lang']) && in_array($_GET['set_lang'], SUPPORTED_LANGUAGES, true)) {
    $_SESSION['language'] = $_GET['set_lang'];
    // Update user's language in DB if logged in
    if (is_logged_in() && !empty($_SESSION['user_id'])) {
        try {
            $db = get_db();
            $stmt = $db->prepare('UPDATE users SET language = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$_SESSION['language'], $_SESSION['user_id']]);
        } catch (Exception $ex) {
            // silently fail
        }
    }
    // Redirect to same page without the query param
    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirect);
    exit;
}
