<?php
require_once __DIR__ . '/Validator.php';
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

(function () {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $trimmed, 2), 2, '');
        $key = trim((string)$key);
        $value = trim((string)$value, " \t\n\r\0\x0B\"'");

        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
})();

$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$baseUrl = preg_replace('#/views(?:/[^/]+){1,2}$#', '', $scriptDirectory);
define('BASE_URL', $baseUrl === '/' ? '' : rtrim($baseUrl, '/'));

function redirect($path) {
    header("Location: " . BASE_URL . "/" . ltrim($path, '/'));
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function isStaff() {
    return isLoggedIn() && $_SESSION['role'] === 'staff';
}

function normalizeStaffRole($role) {
    $role = strtolower(trim((string)$role));
    $role = str_replace(['_', '-'], ' ', $role);
    $aliases = [
        'chef' => 'cook',
        'service staff' => 'waiter',
        'server' => 'waiter',
        'stock' => 'inventory',
        'stock manager' => 'inventory',
        'inventory manager' => 'inventory',
        'accountant' => 'finance',
    ];
    return $aliases[$role] ?? $role;
}

function currentStaffRole() {
    return normalizeStaffRole($_SESSION['staff_role'] ?? '');
}

function staffRoleIs($roles) {
    if (!isStaff()) {
        return false;
    }
    $roles = array_map('normalizeStaffRole', (array)$roles);
    return in_array(currentStaffRole(), $roles, true);
}

function staffHasRole($roles) {
    return isAdmin() || staffRoleIs(['manager', 'admin']) || staffRoleIs($roles);
}

function staffDashboardPath() {
    if (isAdmin()) {
        return 'views/admin/dashboard.php';
    }
    $paths = [
        'manager' => 'views/staff/role_workspace.php',
        'cashier' => 'views/staff/cashier.php',
        'cook' => 'views/staff/chef.php',
        'waiter' => 'views/staff/waiter.php',
        'inventory' => 'views/staff/inventory.php',
        'finance' => 'views/staff/finance.php',
        'cleaner' => 'views/staff/cleaner.php',
    ];
    return $paths[currentStaffRole()] ?? 'views/staff/dashboard.php';
}

function requireLogin() {
    if (!isLoggedIn()) redirect('login.php');
}

function requireAdmin() {
    if (!isAdmin()) redirect(isStaff() ? 'views/staff/dashboard.php' : 'login.php');
}

function canManageMenu() {
    return isAdmin() || staffRoleIs(['finance']);
}

function requireMenuManagement() {
    if (!canManageMenu()) {
        redirect(isLoggedIn() ? staffDashboardPath() : 'login.php');
    }
}

function requireStaff() {
    if (!isStaff()) redirect('staff_login.php');
}

function requireStaffRole($roles) {
    if (isAdmin()) {
        return;
    }
    requireStaff();
    if (!staffHasRole($roles)) {
        redirect('views/staff/dashboard.php');
    }
}

function canAccessCashierPayments() {
    return isAdmin() || staffRoleIs(['cashier']);
}

function requireCashierPayments() {
    if (!canAccessCashierPayments()) {
        redirect(isLoggedIn() ? staffDashboardPath() : 'staff_login.php');
    }
}

function canManageReservations() {
    return isAdmin() || staffRoleIs(['manager']);
}

function requireReservationManagement() {
    if (!canManageReservations()) {
        redirect(isLoggedIn() ? staffDashboardPath() : 'staff_login.php');
    }
}

function esewaConfig() {
    return [
        'merchant_code' => getenv('CANTEEN_ESEWA_MERCHANT_CODE') ?: 'EPAYTEST',
        'secret_key' => getenv('CANTEEN_ESEWA_SECRET_KEY') ?: '',
        'payment_url' => getenv('CANTEEN_ESEWA_PAYMENT_URL') ?: 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
        'status_url' => getenv('CANTEEN_ESEWA_STATUS_URL') ?: 'https://rc-epay.esewa.com.np/api/epay/transaction/status/',
    ];
}

function mailConfig() {
    return [
        'host' => getenv('CANTEEN_MAIL_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('CANTEEN_MAIL_PORT') ?: '587',
        'username' => getenv('CANTEEN_MAIL_USERNAME') ?: 'okrohit409@gmail.com',
        'password' => getenv('CANTEEN_MAIL_PASSWORD') ?:'ipfiinimkcwbxemv',
        'from_email' => getenv('CANTEEN_MAIL_FROM') ?: 'okrohit409@gmail.com',
        'from_name' => getenv('CANTEEN_MAIL_FROM_NAME') ?: 'Canteen',
    ];
}

function requireCustomer() {
    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'customer') {
        redirect(isStaff() || isAdmin() ? staffDashboardPath() : 'login.php');
    }
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash($key, $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $val = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    return null;
}

function csrfToken($key = 'csrf_token') {
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function csrfField($key = 'csrf_token') {
    return '<input type="hidden" name="' . e($key) . '" value="' . e(csrfToken($key)) . '">';
}

function verifyCsrf($key = 'csrf_token') {
    return !empty($_SESSION[$key]) && hash_equals($_SESSION[$key], (string)($_POST[$key] ?? ''));
}
