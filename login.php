<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $db = get_db();
        $stmt = $db->prepare('SELECT id, username, password, language, ship_assigned FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['language'] = $user['language'] ?? 'en';
            $_SESSION['ship_assigned'] = $user['ship_assigned'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = t('invalid_credentials');
        }
    } catch (Exception $ex) {
        $error = 'Database connection error: ' . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — TMA Operations 360</title>
    <link rel="stylesheet" href="static/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--navy);
            position: relative;
        }
        .login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 30% 50%, rgba(99, 102, 241, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 70% 30%, rgba(249, 112, 102, 0.08) 0%, transparent 50%);
        }
        .login-card {
            position: relative;
            z-index: 1;
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: var(--shadow-lg);
        }
        .login-card .nav-logo {
            color: var(--navy);
            justify-content: center;
            margin-bottom: 32px;
            font-size: 1.3rem;
        }
        .login-card h2 {
            text-align: center;
            font-size: 1.4rem;
            margin-bottom: 8px;
        }
        .login-card .login-sub {
            text-align: center;
            color: var(--slate-500);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }
        .login-card .btn-primary { margin-top: 8px; }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <a href="index.php" class="nav-logo">
                <img src="static/images/logo.svg" alt="TMA ops360" class="logo-img logo-img-dark">
            </a>
            <h2>Welcome Back</h2>
            <p class="login-sub">Sign in to access your fleet dashboard</p>
            <?php if (!empty($error)): ?>
                <p class="error" style="color: red; text-align: center;"><?= e($error) ?></p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="login-username">Username</label>
                    <input type="text" id="login-username" name="username" required placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="login-pass">Password</label>
                    <input type="password" id="login-pass" name="password" required placeholder="Enter your password">
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>
