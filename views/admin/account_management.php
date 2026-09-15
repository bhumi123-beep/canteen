<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$userModel = new User($db);

if (empty($_SESSION['account_csrf_token'])) {
    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['account_csrf_token'];

$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrfToken, (string)$submittedToken)) {
        $message = 'Security token expired. Please refresh and try again.';
        $messageType = 'error';
    } elseif (($_POST['action'] ?? '') === 'deactivate_account') {
        if ($userModel->deactivateAccount((int)($_POST['account_id'] ?? 0))) {
            flash('success', 'Account deactivated successfully.');
        } else {
            flash('error', 'This account cannot be deactivated.');
        }
        redirect('views/admin/account_management.php');
    } elseif (($_POST['action'] ?? '') === 'create_account') {
        $name = trim($_POST['account_name'] ?? '');
        $email = trim($_POST['account_email'] ?? '');
        $password = (string)($_POST['account_password'] ?? '');
        $role = $_POST['account_role'] ?? '';
        $phone = trim($_POST['account_phone'] ?? '');
        $salary = $_POST['account_salary'] ?? 0;
        $designation = trim($_POST['account_designation'] ?? 'Service Staff');
        $shift = $_POST['account_shift'] ?? 'Morning';
        $status = $_POST['account_status'] ?? 'on_duty';

        $validRole = in_array($role, ['admin', 'staff'], true);
        $validSalary = is_numeric($salary) && (float)$salary >= 0 && (float)$salary <= 1000000;
        $validPhone = $phone === '' || preg_match('/^(\+?\d{1,3}[-.\s]?)?(\(?\d{2,4}\)?[-.\s]?)?\d{3}[-.\s]?\d{4,6}$/', $phone) === 1;

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8 || !$validRole || !$validSalary || !$validPhone) {
            $message = 'Enter a valid name, email, password of at least 8 characters, phone number, and salary.';
            $messageType = 'error';
        } elseif ($existingAccount = $userModel->findByEmail($email)) {
            if ($role === 'staff' && ($existingAccount['role'] ?? '') === 'customer' && $userModel->promoteToStaff($existingAccount, $password, (float)$salary, $status, $designation, $shift, $phone)) {
                flash('success', 'Existing customer account promoted to staff successfully.');
                redirect('views/admin/account_management.php');
            }

            $message = 'An account with this email already exists. Only customer accounts can be promoted to staff.';
            $messageType = 'error';
        } else {
            $created = $userModel->create($name, $email, $password, $role, $phone, (float)$salary, $status, $designation, $shift);
            if ($created === false) {
                $message = 'The account could not be created. Check the submitted details.';
                $messageType = 'error';
            } else {
                flash('success', ucfirst($role) . ' account created successfully.');
                redirect('views/admin/account_management.php');
            }
        }
    }
}

$flashSuccess = flash('success');
$flashError = flash('error');

$roleFilter = $_GET['role'] ?? 'all';
$searchTerm = trim($_GET['q'] ?? '');
if (!in_array($roleFilter, ['all', 'customer', 'staff', 'admin'], true)) {
    $roleFilter = 'all';
}

$accounts = $userModel->listActiveAccounts($roleFilter, $searchTerm);
$totalAccounts = count($userModel->listActiveAccounts());
$totalCustomers = count($userModel->listActiveAccounts('customer'));
$totalStaff = count($userModel->listActiveAccounts('staff'));
$totalAdmins = count($userModel->listActiveAccounts('admin'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Management - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <div class="row-between">
            <div>
                <h1>Account Management</h1>
                <p class="muted">View all active customer and staff accounts.</p>
            </div>
            <a href="#createAccount" class="btn-primary btn-small">+ Create Account</a>
        </div>
    </header>

    <div class="stat-grid three">
        <div class="stat-card">
            <span class="muted small">Active accounts</span>
            <h2><?= $totalAccounts ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Customer accounts</span>
            <h2><?= $totalCustomers ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Staff accounts</span>
            <h2><?= $totalStaff ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Admin accounts</span>
            <h2><?= $totalAdmins ?></h2>
        </div>
    </div>

    <?php if ($message || $flashSuccess || $flashError): ?>
        <div class="alert alert-<?= e($messageType ?: ($flashError ? 'error' : 'success')) ?>">
            <?= e($message ?: $flashSuccess ?: $flashError) ?>
        </div>
    <?php endif; ?>

    <div class="card" id="createAccount">
        <h2>Create admin or staff account</h2>
        <form method="POST" class="grid-form">
            <input type="hidden" name="action" value="create_account">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div>
                <label>Full name</label>
                <input type="text" name="account_name" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="account_email" required>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="account_password" minlength="8" required>
            </div>
            <div>
                <label>Account type</label>
                <select name="account_role" required>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div>
                <label>Phone</label>
                <input type="tel" name="account_phone">
            </div>
            <div>
                <label>Monthly salary</label>
                <input type="number" name="account_salary" min="0" step="0.01" value="0" required>
            </div>
            <div>
                <label>Staff designation</label>
                <input type="text" name="account_designation" value="Service Staff">
            </div>
            <div>
                <label>Staff shift</label>
                <select name="account_shift">
                    <option>Morning</option>
                    <option>Evening</option>
                    <option>Night</option>
                </select>
            </div>
            <div>
                <label>Staff status</label>
                <select name="account_status">
                    <option value="on_duty">On Duty</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>
            <div class="full-width">
                <button type="submit" class="btn-primary">Create Account</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="staff-toolbar">
            <div>
                <h2>Active user accounts</h2>
                <p class="muted small">Staff accounts are created and managed from Staff Accounts.</p>
            </div>
            <form method="GET" class="staff-filter-bar">
                <input type="search" name="q" value="<?= e($searchTerm) ?>" placeholder="Search name, email, or login ID" aria-label="Search accounts">
                <select name="role" aria-label="Filter accounts by role">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All accounts</option>
                    <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Customers</option>
                    <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admins</option>
                </select>
                <button type="submit" class="btn-primary btn-small">Filter</button>
            </form>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Login ID</th>
                    <th>Email</th>
                    <th>Account Type</th>
                    <th>Account Status</th>
                    <th>Last Login</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($accounts as $account): ?>
                <?php
                $isStaff = $account['role'] === 'staff';
                $status = $account['status'] ?: 'active';
                $statusLabel = $isStaff ? ($status === 'on_leave' ? 'On Leave' : 'On Duty') : 'Active';
                $statusClass = $status === 'on_leave' ? 'status-pending' : 'status-confirmed';
                ?>
                <tr>
                    <td>
                        <strong><?= e($account['name']) ?></strong>
                        <?php if ($isStaff && !empty($account['staff_role'])): ?>
                            <div class="muted small"><?= e($account['staff_role']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($account['username'] ?: $account['email']) ?></td>
                    <td><?= e($account['email']) ?></td>
                    <td><?= $isStaff ? 'Staff' : 'Customer' ?></td>
                    <td><span class="status-pill <?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                    <td><?= $account['last_login_at'] ? date('M d, Y g:i A', strtotime($account['last_login_at'])) : 'Never' ?></td>
                    <td><?= date('M d, Y', strtotime($account['created_at'])) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Deactivate this account?');">
                            <input type="hidden" name="action" value="deactivate_account">
                            <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                            <button type="submit" class="btn-small btn-cancel">Deactivate</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($accounts)): ?>
                <tr><td colspan="8" class="muted center">No active accounts found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
