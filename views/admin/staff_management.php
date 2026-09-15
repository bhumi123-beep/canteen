<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$userModel = new User($db);

function validatePhoneNumber($phone) {
    return $phone === '' || preg_match('/^(\+?\d{1,3}[-.\s]?)?(\(?\d{2,4}\)?[-.\s]?)?\d{3}[-.\s]?\d{4,6}$/', $phone) === 1;
}

function validateEmailAddress($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateSalary($salary) {
    return is_numeric($salary) && (float)$salary >= 0 && (float)$salary <= 1000000;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['staff_csrf_token'] ?? '', (string)$submittedToken)) {
        $response = ['success' => false, 'message' => 'Security token expired. Please refresh and try again.'];
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        flash('error', 'Security token expired. Please refresh and try again.');
        redirect('views/admin/staff_management.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'add_staff') {
        $response = ['success' => false, 'message' => 'Invalid staff submission.'];
        $name = trim($_POST['add_staff_name'] ?? '');
        $email = trim($_POST['add_staff_email'] ?? '');
        $salary = $_POST['add_staff_salary'] ?? 0;
        $password = (string)($_POST['add_staff_password'] ?? '');
        $role = trim($_POST['add_staff_role'] ?? 'Service Staff');
        $department = trim($_POST['add_staff_department'] ?? 'Operations');
        $phone = trim($_POST['add_staff_phone'] ?? '');
        $shift = trim($_POST['add_staff_shift'] ?? 'Morning');
        $status = $_POST['add_staff_status'] ?? 'on_duty';

        if ($name === '' || !validateEmailAddress($email) || strlen($password) < 8 || !validateSalary($salary) || !validatePhoneNumber($phone)) {
            $response['message'] = 'Please provide a valid name, email, password (at least 8 characters), phone number, and monthly salary.';
        } elseif ($userModel->findByEmail($email)) {
            $response['message'] = 'A staff member with this email already exists.';
        } else {
            $createdStaffId = $userModel->create($name, $email, $password, 'staff', $phone, (float)$salary, $status, $role, $shift, $department);
            $createdUser = $createdStaffId ? $userModel->findByEmail($email) : null;
            if ($createdStaffId === false || !$createdUser) {
                $response['message'] = 'The staff account could not be created. Check the submitted details.';
            } else {
                $response = [
                    'success' => true,
                    'staff_id' => $createdStaffId,
                    'username' => $createdUser['username'] ?? $email,
                    'message' => 'Staff member added successfully. Login ID: ' . ($createdUser['username'] ?? $email) . '.',
                ];
            }
        }

        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        if ($response['success']) {
            redirect('views/admin/staff_management.php');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_staff') {
        $response = ['success' => false, 'message' => 'Invalid update request.'];
        $staffId = (int)($_POST['update_staff_id'] ?? 0);
        $name = trim($_POST['edit_staff_name'] ?? '');
        $email = trim($_POST['edit_staff_email'] ?? '');
        $salary = $_POST['edit_staff_salary'] ?? 0;
        $role = trim($_POST['edit_staff_role'] ?? 'Service Staff');
        $department = trim($_POST['edit_staff_department'] ?? 'Operations');
        $phone = trim($_POST['edit_staff_phone'] ?? '');
        $shift = trim($_POST['edit_staff_shift'] ?? 'Morning');
        $status = $_POST['edit_staff_status'] ?? 'on_duty';

        if ($staffId <= 0 || $name === '' || !validateEmailAddress($email) || !validateSalary($salary) || !validatePhoneNumber($phone)) {
            $response['message'] = 'Please enter valid staff details before saving.';
        } else {
            $userModel->updateStaff($staffId, $name, $email, (float)$salary, $role, $phone, $shift, $status, $department);
            $response = ['success' => true, 'message' => 'Staff details updated successfully.'];
        }

        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        if ($response['success']) {
            redirect('views/admin/staff_management.php');
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update_staff_status') {
        $response = ['success' => false, 'message' => 'Unable to update staff status.'];
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $status = $_POST['status'] ?? 'on_duty';

        if ($staffId > 0 && in_array($status, ['on_duty', 'on_leave'], true)) {
            $userModel->setStaffStatus($staffId, $status);
            $response = ['success' => true, 'message' => 'Staff status updated successfully.'];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['remove_staff_id'])) {
        $userModel->deleteStaff((int)$_POST['remove_staff_id']);
        redirect('views/admin/staff_management.php');
    }
}

if (empty($_SESSION['staff_csrf_token'])) {
    $_SESSION['staff_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['staff_csrf_token'];

$searchTerm = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
if (!in_array($statusFilter, ['all', 'on_duty', 'on_leave'], true)) {
    $statusFilter = 'all';
}

$staffMembers = $userModel->listByRole('staff');

$filteredStaff = array_values(array_filter($staffMembers, function ($staff) use ($searchTerm, $statusFilter) {
    $matchStatus = $statusFilter === 'all' || (($staff['status'] ?? 'on_duty') === $statusFilter);
    if (!$matchStatus) {
        return false;
    }

    if ($searchTerm === '') {
        return true;
    }

    $haystack = strtolower(trim(implode(' ', [
        $staff['name'] ?? '',
        $staff['email'] ?? '',
        $staff['role'] ?? '',
        $staff['phone'] ?? '',
        $staff['shift'] ?? '',
    ])));

    return strpos($haystack, strtolower($searchTerm)) !== false;
}));

$onDuty = $userModel->countStaffByStatus('on_duty');
$onLeave = $userModel->countStaffByStatus('on_leave');
$totalMonthlyPayroll = $userModel->getTotalMonthlyPayroll();
$staffRoles = ['Manager', 'Chef', 'Cook', 'Waiter', 'Cashier', 'Inventory Manager', 'Finance', 'Cleaner', 'Service Staff'];
$staffDepartments = ['Operations', 'Inventory', 'Finance'];
$staffShifts = ['Morning', 'Evening', 'Night'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Accounts - CanteenPro</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
    <style>
        .add-staff-form {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            align-items: end;
        }

        .add-staff-form .full-width {
            grid-column: span 1;
        }

        .add-staff-form .submit-cell {
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
        }

        .staff-status-select {
            appearance: none;
            border: none;
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            color: #1f2937;
            background: #dcfce7;
            box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.18);
            min-width: 110px;
        }

        .staff-status-select[data-status="on_leave"] {
            background: #fef3c7;
            box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.2);
        }

        .data-table tbody tr {
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(14, 165, 233, 0.04);
        }

        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            min-width: 260px;
            max-width: 320px;
            padding: 12px 14px;
            border-radius: 12px;
            color: #fff;
            font-size: 14px;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
            opacity: 0;
            transform: translateX(18px);
            animation: toastIn 0.25s ease forwards;
        }

        .toast.success { background: #16a34a; }
        .toast.error { background: #dc2626; }

        @keyframes toastIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @media (max-width: 980px) {
            .add-staff-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 620px) {
            .add-staff-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-body">
<div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <h1>Staff Accounts</h1>
        <div class="admin-toolbar-actions">
            <button type="button" id="exportStaffCsv" class="btn-secondary btn-small">Export CSV</button>
            <button type="button" id="printStaffSummary" class="btn-primary btn-small">Print Summary</button>
        </div>
    </header>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="muted small">Total Staff</span>
            <h2><?= count($staffMembers) ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">On Duty</span>
            <h2><?= $onDuty ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">On Leave</span>
            <h2><?= $onLeave ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Total Monthly Payroll</span>
            <h2>$<?= number_format($totalMonthlyPayroll, 2) ?></h2>
        </div>
    </div>

    <div class="card">
        <h3>Add New Staff</h3>
        <div id="addStaffMessage" class="alert" style="display:none;"></div>
        <form id="addStaffForm" method="POST" class="add-staff-form">
            <input type="hidden" name="action" value="add_staff">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div>
                <label>Staff Name</label>
                <input type="text" name="add_staff_name" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="add_staff_email" required>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="add_staff_password" minlength="8" required autocomplete="new-password">
            </div>
            <div>
                <label>Role / Designation</label>
                <select name="add_staff_role">
                    <?php foreach ($staffRoles as $role): ?>
                        <option value="<?= e($role) ?>"><?= e($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Department</label>
                <select name="add_staff_department">
                    <?php foreach ($staffDepartments as $department): ?><option value="<?= e($department) ?>"><?= e($department) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Phone Number</label>
                <input type="tel" name="add_staff_phone">
            </div>
            <div>
                <label>Shift</label>
                <select name="add_staff_shift">
                    <?php foreach ($staffShifts as $shift): ?>
                        <option value="<?= e($shift) ?>"><?= e($shift) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Monthly Salary</label>
                <input type="number" step="0.01" min="0" name="add_staff_salary" required>
            </div>
            <div>
                <label>Status</label>
                <select name="add_staff_status">
                    <option value="on_duty">On Duty</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>
            <div class="submit-cell">
                <button type="submit" class="btn-primary">+ Add Staff</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="staff-toolbar">
            <h3>Staff Management</h3>
            <form method="GET" class="staff-filter-bar">
                <input id="staffSearch" type="text" name="q" value="<?= e($searchTerm) ?>" placeholder="Search staff...">
                <select name="status">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="on_duty" <?= $statusFilter === 'on_duty' ? 'selected' : '' ?>>On Duty</option>
                    <option value="on_leave" <?= $statusFilter === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
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
                    <th>Role</th>
                    <th>Phone</th>
                    <th>Shift</th>
                    <th>Salary</th>
                    <th>Performance</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filteredStaff as $staff): ?>
                <?php
                $perf = (float)($staff['performance_rating'] ?? 0);
                $perfLabel = $perf > 0 ? number_format($perf, 2) . '/5' : '—';
                $ordersHandled = $userModel->countOrdersHandledByStaff((int)($staff['id'] ?? 0));
                $statusValue = (string)($staff['status'] ?? 'on_duty');
                ?>
                <tr data-staff-id="<?= (int)($staff['id'] ?? 0) ?>">
                    <td>
                        <div class="staff-name-cell">
                            <span class="staff-avatar" aria-hidden="true"><?= e(substr(trim((string)($staff['name'] ?? '')), 0, 1) ?: 'S') ?></span>
                            <span><?= e($staff['name']) ?></span>
                        </div>
                    </td>
                    <td><?= e($staff['username'] ?? 'Not assigned') ?></td>
                    <td><?= e($staff['email']) ?></td>
                    <td><?= e($staff['role'] ?? 'Service Staff') ?></td>
                    <td><?= e($staff['phone'] ?? '—') ?></td>
                    <td><?= e($staff['shift'] ?? 'Morning') ?></td>
                    <td>$<?= number_format((float)($staff['salary'] ?? 0), 2) ?></td>
                    <td>
                        <div><?= e($perfLabel) ?></div>
                        <small class="muted small">Orders handled: <?= (int)$ordersHandled ?></small>
                    </td>
                    <td>
                        <select class="staff-status-select" data-staff-id="<?= (int)($staff['id'] ?? 0) ?>" data-status="<?= e($statusValue) ?>" aria-label="Update staff status">
                            <option value="on_duty" <?= $statusValue === 'on_duty' ? 'selected' : '' ?>>On Duty</option>
                            <option value="on_leave" <?= $statusValue === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
                        </select>
                    </td>
                    <td>
                        <div class="staff-actions">
                            <button type="button" class="btn-small btn-primary" data-edit-staff='<?= htmlspecialchars(json_encode([
                                "id" => (int)$staff['id'],
                                "name" => $staff['name'] ?? '',
                                "username" => $staff['username'] ?? '',
                                "email" => $staff['email'] ?? '',
                                "salary" => (float)($staff['salary'] ?? 0),
                                "role" => $staff['role'] ?? 'Service Staff',
                                "department" => $staff['department'] ?? 'Operations',
                                "phone" => $staff['phone'] ?? '',
                                "shift" => $staff['shift'] ?? 'Morning',
                                "status" => $statusValue,
                            ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove this staff member?');" class="inline-form">
                                <input type="hidden" name="remove_staff_id" value="<?= (int)$staff['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <button type="submit" class="btn-small btn-cancel">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filteredStaff)): ?>
                <tr><td colspan="10" class="muted center">No staff members found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div class="modal-backdrop" id="editStaffModal" aria-hidden="true">
    <div class="modal-card">
        <div class="modal-header row-between">
            <h3>Edit Staff Details</h3>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">×</button>
        </div>

        <form id="updateStaffForm" method="POST" class="grid-form modal-form">
            <input type="hidden" name="action" value="update_staff">
            <input type="hidden" name="update_staff_id" id="edit_staff_id">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <div>
                <label>Staff Name</label>
                <input type="text" name="edit_staff_name" id="edit_staff_name" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="edit_staff_email" id="edit_staff_email" required>
            </div>
            <div>
                <label>Monthly Salary</label>
                <input type="number" step="0.01" min="0" name="edit_staff_salary" id="edit_staff_salary" required>
            </div>
            <div>
                <label>Role / Designation</label>
                <select name="edit_staff_role" id="edit_staff_role">
                    <?php foreach ($staffRoles as $role): ?>
                        <option value="<?= e($role) ?>"><?= e($role) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Department</label>
                <select name="edit_staff_department" id="edit_staff_department">
                    <?php foreach ($staffDepartments as $department): ?><option value="<?= e($department) ?>"><?= e($department) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Phone Number</label>
                <input type="tel" name="edit_staff_phone" id="edit_staff_phone">
            </div>
            <div>
                <label>Shift</label>
                <select name="edit_staff_shift" id="edit_staff_shift">
                    <?php foreach ($staffShifts as $shift): ?>
                        <option value="<?= e($shift) ?>"><?= e($shift) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Status</label>
                <select name="edit_staff_status" id="edit_staff_status">
                    <option value="on_duty">On Duty</option>
                    <option value="on_leave">On Leave</option>
                </select>
            </div>
            <div class="full-width modal-actions">
                <button type="button" class="btn-secondary" data-close-modal>Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('editStaffModal');
    const modalCloseButtons = document.querySelectorAll('[data-close-modal]');
    const addStaffForm = document.getElementById('addStaffForm');
    const updateStaffForm = document.getElementById('updateStaffForm');
    const addStaffMessage = document.getElementById('addStaffMessage');
    const staffTableBody = document.querySelector('.data-table tbody');
    const toastContainer = document.getElementById('toastContainer');
    const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function getInitials(name) {
        const trimmed = String(name || '').trim();
        if (!trimmed) return 'S';
        const parts = trimmed.split(/\s+/).filter(Boolean);
        if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function showToast(message, type = 'success') {
        if (!toastContainer) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        toastContainer.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 2600);
    }

    function showMessage(element, type, message) {
        if (!element) return;
        element.className = 'alert alert-' + type;
        element.textContent = message;
        element.style.display = 'block';
    }

    function validatePhone(phone) {
        return /^(\+?\d{1,3}[-.\s]?)?(\(?\d{2,4}\)?[-.\s]?)?\d{3}[-.\s]?\d{4,6}$/.test(phone || '');
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((email || '').trim());
    }

    function validateSalary(value) {
        const numericValue = Number(value);
        return Number.isFinite(numericValue) && numericValue >= 0 && numericValue <= 1000000;
    }

    function makeStatusMarkup(status, staffId) {
        const normalizedStatus = status === 'on_leave' ? 'on_leave' : 'on_duty';
        return `<select class="staff-status-select" data-staff-id="${Number(staffId || 0)}" data-status="${normalizedStatus}" aria-label="Update staff status">
            <option value="on_duty" ${normalizedStatus === 'on_duty' ? 'selected' : ''}>On Duty</option>
            <option value="on_leave" ${normalizedStatus === 'on_leave' ? 'selected' : ''}>On Leave</option>
        </select>`;
    }

    function makeStaffRowMarkup(staff) {
        const id = Number(staff.id || 0);
        const name = staff.name || '';
        const username = staff.username || 'Not assigned';
        const email = staff.email || '';
        const role = staff.role || 'Service Staff';
        const department = staff.department || 'Operations';
        const phone = staff.phone || '—';
        const shift = staff.shift || 'Morning';
        const salary = Number(staff.salary || 0);
        const status = staff.status || 'on_duty';
        const performance = Number(staff.performance_rating || 0);
        const perfLabel = performance > 0 ? `${performance.toFixed(2)}/5` : 'No rating yet';
        const ordersHandled = Number(staff.orders_handled || 0);
        const initials = getInitials(name);
        const rowData = JSON.stringify({
            id: id,
            name: name,
            email: email,
            salary: salary,
            role: role,
            department: department,
            phone: phone === '—' ? '' : phone,
            shift: shift,
            status: status
        }).replace(/</g, '\\u003c').replace(/>/g, '\\u003e');

        return `
            <tr data-staff-id="${id}">
                <td>
                    <div class="staff-name-cell">
                        <span class="staff-avatar" aria-hidden="true">${escapeHtml(initials)}</span>
                        <span>${escapeHtml(name)}</span>
                    </div>
                </td>
                <td>${escapeHtml(username)}</td>
                <td>${escapeHtml(email)}</td>
                <td>${escapeHtml(role)}</td>
                <td>${escapeHtml(phone)}</td>
                <td>${escapeHtml(shift)}</td>
                <td>$${salary.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td>
                    <div>${escapeHtml(perfLabel)}</div>
                    <small class="muted small">Orders handled: ${ordersHandled}</small>
                </td>
                <td>${makeStatusMarkup(status, id)}</td>
                <td>
                    <div class="staff-actions">
                        <button type="button" class="btn-small btn-primary" data-edit-staff='${rowData}'>Edit</button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this staff member?');" class="inline-form">
                            <input type="hidden" name="remove_staff_id" value="${id}">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <button type="submit" class="btn-small btn-cancel">Remove</button>
                        </form>
                    </div>
                </td>
            </tr>
        `;
    }

    async function submitStaffForm(form, type) {
        const formData = new FormData(form);
        if (csrfToken) {
            formData.set('csrf_token', csrfToken);
        }
        const name = (formData.get(type === 'add' ? 'add_staff_name' : 'edit_staff_name') || '').toString().trim();
        const email = (formData.get(type === 'add' ? 'add_staff_email' : 'edit_staff_email') || '').toString().trim();
        const salary = (formData.get(type === 'add' ? 'add_staff_salary' : 'edit_staff_salary') || '').toString();
        const phone = (formData.get(type === 'add' ? 'add_staff_phone' : 'edit_staff_phone') || '').toString().trim();
        const password = (formData.get('add_staff_password') || '').toString();

        if (!name || !validateEmail(email) || (type === 'add' && password.length < 8) || !validateSalary(salary) || !validatePhone(phone)) {
            const message = 'Please enter a valid name, email, password (at least 8 characters), phone number, and monthly salary.';
            if (type === 'add') {
                showMessage(addStaffMessage, 'error', message);
            }
            return;
        }

        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        const payload = await response.json();

        if (!payload.success) {
            const message = payload.message || 'Unable to complete the request.';
            if (type === 'add') {
                showMessage(addStaffMessage, 'error', message);
            }
            showToast(message, 'error');
            return;
        }

        const rowValues = {
            id: type === 'add' ? Number(payload.staff_id || 0) : Number(formData.get('update_staff_id') || 0),
            name: name,
            username: type === 'add' ? (payload.username || email) : (document.querySelector(`tr[data-staff-id="${Number(formData.get('update_staff_id') || 0)}"] td:nth-child(2)`)?.textContent.trim() || 'Not assigned'),
            email: email,
            role: (formData.get(type === 'add' ? 'add_staff_role' : 'edit_staff_role') || 'Service Staff').toString(),
            department: (formData.get(type === 'add' ? 'add_staff_department' : 'edit_staff_department') || 'Operations').toString(),
            phone: phone,
            shift: (formData.get(type === 'add' ? 'add_staff_shift' : 'edit_staff_shift') || 'Morning').toString(),
            salary: Number(salary || 0),
            status: (formData.get(type === 'add' ? 'add_staff_status' : 'edit_staff_status') || 'on_duty').toString(),
            performance_rating: 0,
            orders_handled: 0
        };

        if (type === 'add') {
            form.reset();
            if (staffTableBody) {
                staffTableBody.insertAdjacentHTML('beforeend', makeStaffRowMarkup(rowValues));
                bindStatusSelect(staffTableBody.querySelector('tr:last-child .staff-status-select'));
            }
            showMessage(addStaffMessage, 'success', payload.message || 'Staff member added successfully.');
            showToast(payload.message || 'Staff member added successfully.', 'success');
        } else {
            const row = document.querySelector(`tr[data-staff-id="${rowValues.id}"]`);
            if (row) {
                row.outerHTML = makeStaffRowMarkup(rowValues);
                bindStatusSelect(document.querySelector(`tr[data-staff-id="${rowValues.id}"] .staff-status-select`));
            }
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            showToast(payload.message || 'Staff details updated successfully.', 'success');
        }
    }

    if (addStaffForm) {
        addStaffForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await submitStaffForm(addStaffForm, 'add');
        });
    }

    if (updateStaffForm) {
        updateStaffForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await submitStaffForm(updateStaffForm, 'update');
        });
    }

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-edit-staff]');
        if (!button) return;

        const staff = JSON.parse(button.dataset.editStaff);
        document.getElementById('edit_staff_id').value = staff.id;
        document.getElementById('edit_staff_name').value = staff.name || '';
        document.getElementById('edit_staff_email').value = staff.email || '';
        document.getElementById('edit_staff_salary').value = Number(staff.salary || 0);
        document.getElementById('edit_staff_role').value = staff.role || 'Service Staff';
        document.getElementById('edit_staff_department').value = staff.department || 'Operations';
        document.getElementById('edit_staff_phone').value = staff.phone || '';
        document.getElementById('edit_staff_shift').value = staff.shift || 'Morning';
        document.getElementById('edit_staff_status').value = staff.status || 'on_duty';
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
    });

    modalCloseButtons.forEach((button) => {
        button.addEventListener('click', () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        });
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    const searchInput = document.getElementById('staffSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');
            rows.forEach((row) => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    function bindStatusSelect(select) {
        const applyStatusClass = (value) => {
            select.dataset.status = value;
            select.classList.toggle('status-on-leave', value === 'on_leave');
            select.classList.toggle('status-on-duty', value === 'on_duty');
            select.style.background = value === 'on_leave' ? '#fef3c7' : '#dcfce7';
        };

        applyStatusClass(select.value);

        select.addEventListener('change', async () => {
            const staffId = select.dataset.staffId;
            const status = select.value;
            const oldValue = select.dataset.status || 'on_duty';

            try {
                const formData = new URLSearchParams({ action: 'update_staff_status', staff_id: staffId, status });
                formData.set('csrf_token', csrfToken);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData.toString()
                });

                const payload = await response.json();
                if (!payload.success) {
                    select.value = oldValue;
                    showToast(payload.message || 'Status update failed.', 'error');
                    return;
                }

                applyStatusClass(status);
                showToast('Staff status updated.', 'success');
            } catch (error) {
                select.value = oldValue;
                showToast('Status update failed.', 'error');
            }
        });
    }

    document.querySelectorAll('.staff-status-select').forEach(bindStatusSelect);

    document.getElementById('exportStaffCsv')?.addEventListener('click', () => {
        const rows = Array.from(document.querySelectorAll('.data-table tbody tr')).filter((row) => row.style.display !== 'none');
        const headers = ['Name', 'Login ID', 'Email', 'Role', 'Phone', 'Shift', 'Salary', 'Performance', 'Status'];
        const csvRows = [headers.join(',')];

        rows.forEach((row) => {
            const cells = Array.from(row.querySelectorAll('td'));
            const rowValues = cells.slice(0, 9).map((cell) => {
                const text = cell.textContent.replace(/\r?\n/g, ' ').replace(/"/g, '""').trim();
                return `"${text}"`;
            });
            csvRows.push(rowValues.join(','));
        });

        const csvContent = csvRows.join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'staff_summary.csv';
        link.click();
        URL.revokeObjectURL(link.href);
        showToast('Staff CSV exported.', 'success');
    });

    document.getElementById('printStaffSummary')?.addEventListener('click', () => {
        window.print();
    });
</script>
</body>
</html>
