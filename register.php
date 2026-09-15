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
        $error = 'Your registration form expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        $validName = Validator::name($name);
        $validEmail = Validator::email($email);
        $validPhone = Validator::phone($phone);
        $validPassword = Validator::password($password);
        if ($validName !== false && $validEmail !== false && $validPhone !== false && $validPassword !== false) {
            $result = $auth->register($validName, $validEmail, $validPassword, $validPhone);
            if ($result['success']) {
                flash('success', 'Account created! Please sign in.');
                redirect('login.php');
            } else {
                $error = $result['message'];
            }
        } else {
            $error = 'Enter a valid name, email, phone number, and password of at least 8 characters.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CanteenPro - Register</title>
<link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-caption">
            <h2>🍴 CanteenPro</h2>
            <p>Create an account to order food, reserve tables, and track your orders in real time.</p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Create account</h1>
            <p class="muted">Register as a customer to get started.</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="register.php">
                <?= csrfField() ?>
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Jane Doe" required value="<?= e($_POST['name'] ?? '') ?>">

                <label>Email</label>
                <input type="email" name="email" placeholder="you@university.edu" required value="<?= e($_POST['email'] ?? '') ?>">

                <label>Phone</label>
                <input type="text" name="phone" placeholder="98XXXXXXXX" value="<?= e($_POST['phone'] ?? '') ?>">

                <label>Password</label>
                <input type="password" name="password" placeholder="Create a password" required>

                <button type="submit" class="btn-primary btn-block">Register &rarr;</button>
            </form>

            <p class="center muted">Already have an account? <a href="login.php" class="link">Sign in</a></p>
        </div>
    </div>
</div>
</body>
</html>
