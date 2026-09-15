<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';

$database = new Database();
$db = $database->connect();
$auth = new AuthController($db);

$error = '';
$role = $_POST['role'] ?? 'customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Your sign-in form expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $result = $auth->login($email, $password, $role);
        if ($result['success']) {
            if ($result['role'] === 'admin') {
                redirect('views/admin/dashboard.php');
            }
            if ($result['role'] === 'staff') {
                redirect(staffDashboardPath());
            }
            redirect('views/customer/home.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CanteenPro - Sign In</title>
<link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<div class="auth-page">
    <div class="auth-visual">
        <div class="auth-visual-caption">
            <h2>🍴 CanteenPro</h2>
            <p>Streamlining campus dining with high-speed service, intuitive ordering, and effortless administrative oversight.</p>
        </div>
    </div>
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h1>Welcome back</h1>
            <p class="muted">Please enter your details to sign in.</p>

            <div class="role-tabs">
                <a href="#" class="role-tab <?= $role === 'customer' ? 'active' : '' ?>" data-role="customer">Customer</a>
                <a href="#" class="role-tab <?= $role === 'admin' ? 'active' : '' ?>" data-role="admin">Admin</a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <?= csrfField() ?>
                <input type="hidden" name="role" id="role-input" value="<?= e($role) ?>">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required value="<?= e($_POST['email'] ?? '') ?>">

                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>

                <div class="row-between">
                    <label class="checkbox"><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#" class="link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary btn-block">Sign In &rarr;</button>
            </form>

            <p class="center muted">Staff member? <a href="staff_login.php" class="link">Staff login</a></p>
            <p class="center muted">Don't have an account? <a href="register.php" class="link">Register here</a></p>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('.role-tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('role-input').value = tab.dataset.role;
    });
});
</script>
</body>
</html>
