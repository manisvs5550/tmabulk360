<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $reason  = trim($_POST['reason'] ?? '');

    // TODO: Process the contact form (e.g., send email, save to DB)
}

header('Location: index.php#contact');
exit;
