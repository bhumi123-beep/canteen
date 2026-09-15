<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$admin = new AdminController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        flash('error', 'Security token expired. Please try again.');
        redirect('views/admin/inventory.php');
    }
    if (isset($_POST['reorder_item_id'])) {
        $itemId = Validator::menuItemId($_POST['reorder_item_id']);
        if ($itemId !== false) {
            $admin->autoReorder($itemId);
        }
        redirect('views/admin/inventory.php');
    }

    if (isset($_POST['adjust_item_id'], $_POST['adjust_qty'])) {
        $itemId = Validator::menuItemId($_POST['adjust_item_id']);
        $qty = Validator::signedInteger($_POST['adjust_qty']);
        if ($itemId === false || $qty === false) {
            flash('error', 'Enter a valid inventory item and adjustment quantity.');
            redirect('views/admin/inventory.php');
        }
        $item = $db->prepare("SELECT current_stock, reorder_level FROM menu_items WHERE id = ?");
        $item->execute([$itemId]);
        $itemData = $item->fetch(PDO::FETCH_ASSOC);

        if ($itemData) {
            $previousStock = (int)$itemData['current_stock'];
            $newStock = max($previousStock + $qty, 0);
            $db->prepare("UPDATE menu_items SET current_stock = ? WHERE id = ?")->execute([$newStock, $itemId]);

            try {
                $db->prepare("INSERT INTO inventory_logs (menu_item_id, action, quantity, previous_stock, new_stock, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())")->execute([
                    $itemId,
                    $qty >= 0 ? 'restock' : 'adjustment',
                    $qty,
                    $previousStock,
                    $newStock,
                    $qty >= 0 ? 'Manual stock restock' : 'Manual stock adjustment'
                ]);
            } catch (PDOException $e) {
                // Ignore missing table until schema is applied to the database.
            }
        }
        redirect('views/admin/inventory.php');
    }

    if (isset($_POST['reorder_level_item_id'], $_POST['reorder_level'])) {
        $itemId = Validator::menuItemId($_POST['reorder_level_item_id']);
        $level = Validator::stock($_POST['reorder_level']);
        if ($itemId === false || $level === false) {
            flash('error', 'Enter a valid reorder level.');
            redirect('views/admin/inventory.php');
        }
        $db->prepare("UPDATE menu_items SET reorder_level = ? WHERE id = ?")->execute([$level, $itemId]);
        redirect('views/admin/inventory.php');
    }
}

$data = $admin->dashboardData();
$itemsStmt = $db->prepare("SELECT mi.*, c.name AS category_name FROM menu_items mi LEFT JOIN categories c ON mi.category_id = c.id ORDER BY mi.name ASC");
$itemsStmt->execute();
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Inventory Management - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <h1>Inventory Management</h1>
    </header>

    <div class="stat-grid three">
        <div class="stat-card">
            <span class="muted small">Total Inventory Items</span>
            <h2><?= count($items) ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Low Stock</span>
            <h2><?= $data['menu_counts']['low'] ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Pending Reorders</span>
            <h2><?= count($data['vendor_reorders']) ?></h2>
        </div>
    </div>

    <div class="card">
        <div class="row-between">
            <h3>Automated Vendor Reordering</h3>
            <span class="muted small"><?= count($data['vendor_reorders']) ?> items below threshold</span>
        </div>

        <?php if (!empty($data['vendor_reorders'])): ?>
            <?php foreach ($data['vendor_reorders'] as $item): ?>
                <div class="row-between low-stock-line">
                    <div>
                        <strong><?= e($item['name']) ?></strong>
                        <div class="muted small">Current stock: <?= (int)$item['current_stock'] ?> · Reorder level: <?= (int)$item['reorder_level'] ?></div>
                    </div>
                    <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="reorder_item_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn-small btn-primary">Restock +<?= (int)$item['reorder_level'] + 10 ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="muted small">All inventory is currently above its reorder thresholds.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="row-between">
            <h3>Inventory Ledger</h3>
            <a href="<?= BASE_URL ?>/views/admin/menu_management.php" class="link">Manage menu items</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Current Stock</th>
                    <th>Reorder Level</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= e($item['name']) ?></strong>
                        <div class="muted small"><?= e($item['description'] ?? '') ?></div>
                    </td>
                    <td><?= e($item['category_name'] ?? 'Uncategorized') ?></td>
                    <td>$<?= number_format((float)$item['price'], 2) ?></td>
                    <td class="<?= (int)$item['current_stock'] <= (int)$item['reorder_level'] ? 'danger' : '' ?>">
                        <?= (int)$item['current_stock'] ?>
                    </td>
                    <td><?= (int)$item['reorder_level'] ?></td>
                    <td>
                        <div class="reservation-actions">
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="adjust_item_id" value="<?= (int)$item['id'] ?>">
                                <input type="number" name="adjust_qty" min="-1000" value="10" style="width:70px; padding:6px; margin-right:6px; border:1px solid var(--border); border-radius:6px;">
                                <button type="submit" class="btn-small btn-primary">Adjust</button>
                            </form>
                        </div>
                        <div class="reservation-actions" style="margin-top:8px;">
                            <form method="POST" style="display:inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="reorder_level_item_id" value="<?= (int)$item['id'] ?>">
                                <input type="number" name="reorder_level" min="0" value="<?= (int)$item['reorder_level'] ?>" style="width:70px; padding:6px; margin-right:6px; border:1px solid var(--border); border-radius:6px;">
                                <button type="submit" class="btn-small btn-secondary">Set Level</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?><tr><td colspan="6" class="muted center">No inventory items yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="row-between">
            <h3>Inventory History</h3>
            <span class="muted small">Latest 10 events</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Action</th>
                    <th>Qty</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['inventory_logs'] as $log): ?>
                <tr>
                    <td><?= e($log['item_name'] ?? 'Unknown Item') ?></td>
                    <td><span class="status-pill status-<?= $log['action'] === 'vendor_reorder' ? 'confirmed' : 'pending' ?>"><?= e($log['action']) ?></span></td>
                    <td><?= (int)$log['quantity'] ?></td>
                    <td><?= (int)$log['previous_stock'] ?></td>
                    <td><?= (int)$log['new_stock'] ?></td>
                    <td><?= date('M d, Y g:i A', strtotime($log['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($data['inventory_logs'])): ?><tr><td colspan="6" class="muted center">No stock history yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
