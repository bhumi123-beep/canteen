<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
requireReservationManagement();

$database = new Database();
$db = $database->connect();
$reservationController = new ReservationController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['create_table'])) {
        $tableNumber = trim($_POST['table_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        if ($tableNumber !== '' && $capacity > 0) {
            $created = $reservationController->adminCreateTable($tableNumber, $capacity, $location);
            flash($created ? 'success' : 'error', $created ? "Table '{$tableNumber}' created successfully." : "Could not create table.");
        } else {
            flash('error', 'Please provide a valid table number and positive seating capacity.');
        }
    } elseif (isset($_POST['update_table'])) {
        $id = (int)($_POST['table_id'] ?? 0);
        $tableNumber = trim($_POST['table_number'] ?? '');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $updated = $reservationController->adminUpdateTable($id, $tableNumber, $capacity, $location, $isActive);
        flash($updated ? 'success' : 'error', $updated ? "Table updated." : "Failed to update table.");
    } elseif (isset($_POST['toggle_table'])) {
        $id = (int)($_POST['table_id'] ?? 0);
        $toggled = $reservationController->adminToggleTable($id);
        flash($toggled ? 'success' : 'error', $toggled ? "Table status updated." : "Failed to update status.");
    } elseif (isset($_POST['delete_table'])) {
        $id = (int)($_POST['table_id'] ?? 0);
        $deleted = $reservationController->adminDeleteTable($id);
        flash($deleted ? 'success' : 'error', $deleted ? "Table deleted." : "Cannot delete table with active bookings.");
    }
    redirect('views/admin/tables.php');
}

$tables = $reservationController->adminAllTablesWithStatus();
$flashSuccess = flash('success');
$flashError = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Table Management - CanteenPro Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <h1>Table Management</h1>
    </header>
    <p class="muted">Add, edit, and organize dining tables for floor reservations and QR ordering.</p>

    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom: 24px;">
        <h3>Add New Table</h3>
        <form method="POST" class="grid-form staff-inline-form">
            <?= csrfField() ?>
            <input type="text" name="table_number" placeholder="Table No (e.g. T8)" required>
            <input type="number" name="capacity" min="1" max="50" placeholder="Capacity (seats)" required>
            <select name="location" required>
                <option value="Window">Window</option>
                <option value="Center">Center</option>
                <option value="Patio">Patio</option>
                <option value="Terrace">Terrace</option>
                <option value="VIP Section">VIP Section</option>
            </select>
            <button type="submit" name="create_table" class="btn-primary">+ Add Table</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Tables</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Table Number</th>
                    <th>Capacity</th>
                    <th>Location</th>
                    <th>Total Bookings</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tables as $t): ?>
                <tr>
                    <td><strong>Table <?= e($t['table_number']) ?></strong></td>
                    <td><?= (int)$t['capacity'] ?> Seats</td>
                    <td><span class="muted small"><?= e($t['location'] ?: 'Standard') ?></span></td>
                    <td><?= (int)($t['total_bookings'] ?? 0) ?> active</td>
                    <td>
                        <span class="status-pill <?= $t['is_active'] ? 'status-ready' : 'status-cancelled' ?>">
                            <?= $t['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td>
                        <details class="inline-details">
                            <summary class="btn-small btn-secondary">Edit</summary>
                            <form method="POST" class="grid-form staff-inline-form" style="margin-top:10px; padding:12px; background:var(--light-surface); border-radius:8px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="table_id" value="<?= (int)$t['id'] ?>">
                                <input type="text" name="table_number" value="<?= e($t['table_number']) ?>" required>
                                <input type="number" name="capacity" min="1" max="50" value="<?= (int)$t['capacity'] ?>" required>
                                <select name="location" required>
                                    <?php foreach (['Window', 'Center', 'Patio', 'Terrace', 'VIP Section'] as $loc): ?>
                                        <option value="<?= $loc ?>" <?= $t['location'] === $loc ? 'selected' : '' ?>><?= $loc ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label><input type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>> Active</label>
                                <button type="submit" name="update_table" class="btn-small btn-primary">Save</button>
                            </form>
                        </details>
                        <form method="POST" class="inline-form" style="display:inline-block; margin-left:6px;">
                            <?= csrfField() ?>
                            <input type="hidden" name="table_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" name="toggle_table" class="btn-small btn-secondary">
                                <?= $t['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                        <a href="<?= BASE_URL ?>/views/admin/table_qr_codes.php" class="btn-small btn-secondary" style="display:inline-block; margin-left:6px;">QR Code</a>
                        <form method="POST" class="inline-form" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('Delete Table <?= e($t['table_number']) ?>?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="table_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" name="delete_table" class="btn-small btn-cancel">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($tables)): ?>
                <tr><td colspan="6" class="muted center">No tables configured yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
