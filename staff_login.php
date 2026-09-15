<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';

$database = new Database();
$db = $database->connect();
$auth = new AuthController($db);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Your sign-in form expired. Please try again.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = $auth->login($login, $password, 'staff');

        if ($result['success']) {
            redirect(staffDashboardPath());
        }

        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Login - CanteenPro</title>
<link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-caption">
            <h2>🍴 CanteenPro Staff</h2>
            <p>Sign in to view incoming orders and keep the canteen service moving.</p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Staff login</h1>
            <p class="muted">Use the login ID and temporary password given by your manager.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="staff_login.php">
                <?= csrfField() ?>
                <label for="login">Staff ID or email</label>
                <input id="login" type="text" name="login" placeholder="Enter your staff ID or email" required value="<?= e($_POST['login'] ?? '') ?>">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" required>

                <button type="submit" class="btn-primary btn-block">Sign In &rarr;</button>
            </form>

            <p class="center muted"><a href="login.php" class="link">Back to customer/admin login</a></p>
        </div>
    </div>
</div>
</body>
</html>
