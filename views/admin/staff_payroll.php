<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$userModel = new User($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrf() && isset($_POST['staff_id'], $_POST['deduction_amount'])) {
        $staffId = (int)$_POST['staff_id'];
        $amount = max((float)$_POST['deduction_amount'], 0);

        if ($amount > 0) {
            $staff = $userModel->findById($staffId);
            if ($staff && $staff['role'] === 'staff') {
                $current = isset($staff['salary']) ? (float)$staff['salary'] : 0;
                $newTotal = max($current - $amount, 0);
                $db->prepare("UPDATE users SET salary = ? WHERE id = ?")->execute([$newTotal, $staffId]);
            }
        }
        redirect('views/admin/staff_payroll.php');
    }
}

$staffMembers = $userModel->listByRole('staff');
$staffCount = $userModel->countByRole('staff');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Payroll - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <h1>Staff Payroll</h1>
    </header>

    <div class="stat-grid three">
        <div class="stat-card">
            <span class="muted small">Staff Members</span>
            <h2><?= $staffCount ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Total Payroll</span>
            <h2>$<?= number_format(array_sum(array_map(fn($s) => (float)($s['salary'] ?? 0), $staffMembers)), 2) ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Pending Deductions</span>
            <h2>$<?= number_format(array_sum(array_map(fn($s) => max((float)($s['salary'] ?? 0) * 0.05, 0), $staffMembers)), 2) ?></h2>
        </div>
    </div>

    <div class="card">
        <h3>Automated Payroll Deduction</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Monthly Salary</th>
                    <th>Deduction</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($staffMembers as $staff): ?>
                <tr>
                    <td><?= e($staff['name']) ?></td>
                    <td><?= e($staff['email']) ?></td>
                    <td>$<?= number_format((float)($staff['salary'] ?? 0), 2) ?></td>
                    <td>$<?= number_format(min((float)($staff['salary'] ?? 0) * 0.05, 200), 2) ?></td>
                    <td>
                        <form method="POST" style="display:flex; gap:8px; align-items:center;">
                            <?= csrfField() ?>
                            <input type="hidden" name="staff_id" value="<?= (int)$staff['id'] ?>">
                            <input type="number" step="0.01" min="0" name="deduction_amount" value="<?= number_format(min((float)($staff['salary'] ?? 0) * 0.05, 200), 2, '.', '') ?>" style="width:100px; padding:6px; border:1px solid var(--border); border-radius:6px;">
                            <button type="submit" class="btn-small btn-primary">Deduct</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($staffMembers)): ?><tr><td colspan="5" class="muted center">No staff accounts found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
