<?php
/**
 * TMA Operations 360 — Configuration
 * Database connection, session setup, and shared constants.
 */

session_start();

// --- Database (PostgreSQL) ---
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'tmabulk360');
define('DB_USER', 'postgres');
define('DB_PASS', 'postgres');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// --- Inventory items ---
define('INVENTORY_ITEMS', [
    ['no' => 1,  'item' => 'HIGH PRESSURE WATER JET-200 Bar', 'min_qty' => 1, 'remarks' => '200Bar'],
    ['no' => 2,  'item' => 'High pressure water jet -500 Bar', 'min_qty' => 1, 'remarks' => 'Reconditioned Old Equipment with broken nozzle or gun or similar issues'],
    ['no' => 3,  'item' => 'HIGH PRESSURE WATER JET- SET OF SPARE PARTS FOR PUMPING ELEMENT', 'min_qty' => null, 'remarks' => ''],
    ['no' => 4,  'item' => 'HOLD CLEANING GUN (Combi gun etc)', 'min_qty' => 2, 'remarks' => ''],
    ['no' => 5,  'item' => 'STAND FOR HOLD CLEANING GUN', 'min_qty' => 2, 'remarks' => ''],
    ['no' => 6,  'item' => 'CHEMICAL APPLICATOR UNIT', 'min_qty' => 1, 'remarks' => 'Air Operated Chemical Pump'],
    ['no' => 7,  'item' => 'TELESCOPIC POLE FOR REACHING HIGH AREAS BY THE USE OF CHEMICAL APPLICATOR', 'min_qty' => 1, 'remarks' => 'Mention how many meters long'],
    ['no' => 8,  'item' => 'SPRAY FOAM SYSTEM WITH MINI GUN (CHEMICAL APPLICATION)', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 9,  'item' => 'ALLUMINIUM/ STEEL SCAFFOLDING TOWER or SIMILAR EQUIPMENT', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 10, 'item' => 'MAN CAGE/BASKET/SIMILAR EQUIPMENT LIKE MOVABLE PLATFORMS, LADDER ETC USED TO REACH UPPER PARTS OF CARGO HOLDS BY THE USE OF SHIP\'S CRANE', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 11, 'item' => 'WOODEN STAGES', 'min_qty' => 2, 'remarks' => 'Gondola'],
    ['no' => 12, 'item' => 'TELESCOPIC LADDER', 'min_qty' => 2, 'remarks' => 'Maximum 6mtrs'],
    ['no' => 13, 'item' => 'AIRLESS PAINT SPRAY MACHINE', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 14, 'item' => 'EXTENSION POLE FOR PAINT SPRAY MACHINE', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 15, 'item' => 'HEAVY DUTY DESCALING MACHINES FOR TANK TOPS (Rustibus, Scatol etc)', 'min_qty' => 1, 'remarks' => 'Reconditioned Old Equipment'],
    ['no' => 16, 'item' => 'PNEUMATIC SCALING HAMMER', 'min_qty' => 4, 'remarks' => ''],
    ['no' => 17, 'item' => 'TELESCOPIC POLE', 'min_qty' => 4, 'remarks' => '5mtrs'],
    ['no' => 18, 'item' => 'FIXED AIR COMPRESSOR (For Deck use)', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 19, 'item' => 'ELECTRICAL SUBMERSIBLE PUMP capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 20, 'item' => 'WILDEN PUMP (diaphragm air pump) capable of transferring cargo hold wash water from tanktop to overboard or in wash water tank', 'min_qty' => 1, 'remarks' => ''],
    ['no' => 21, 'item' => 'CHEMICAL PROTECTION SUIT', 'min_qty' => 3, 'remarks' => ''],
    ['no' => 22, 'item' => 'RESPIRATION FACE MASK', 'min_qty' => 5, 'remarks' => ''],
    ['no' => 23, 'item' => 'SPARE FILTER FOR FULL FACE MASK', 'min_qty' => 4, 'remarks' => ''],
]);

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
