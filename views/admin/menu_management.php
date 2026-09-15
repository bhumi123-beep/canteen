<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/MenuController.php';
requireMenuManagement();

$database = new Database();
$db = $database->connect();
$menuController = new MenuController($db);

$editItem = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        flash('error', 'Security token expired. Please try again.');
        redirect('views/admin/menu_management.php');
    }
    if (isset($_POST['save_item'])) {
        $imagePath = null;
        if (!empty($_POST['item_id'])) {
            $existingItem = $menuController->get((int)$_POST['item_id']);
            $imagePath = $existingItem['image'] ?? null;
        }
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['image']['tmp_name']) || $_FILES['image']['size'] > 5 * 1024 * 1024) {
                flash('error', 'Image upload failed. Please use an image smaller than 5 MB.');
                redirect('views/admin/menu_management.php');
            }
            $imageInfo = @getimagesize($_FILES['image']['tmp_name']);
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            if (!$imageInfo || !isset($allowedTypes[$imageInfo['mime']])) {
                flash('error', 'Invalid image type. Use JPG, PNG, WEBP, or GIF.');
                redirect('views/admin/menu_management.php');
            }
            $imagePath = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$imageInfo['mime']];
            $imageDirectory = __DIR__ . '/../../public/images';
            if (!is_dir($imageDirectory)) {
                mkdir($imageDirectory, 0755, true);
            }
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imageDirectory . '/' . $imagePath)) {
                flash('error', 'The image could not be stored.');
                redirect('views/admin/menu_management.php');
            }
        }
        $data = [
            'category_id' => $_POST['category_id'] ?? null,
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'price' => $_POST['price'] ?? null,
            'current_stock' => $_POST['current_stock'] ?? null,
            'reorder_level' => $_POST['reorder_level'] ?? null,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'image' => $imagePath,
        ];
        if (!empty($_POST['item_id'])) {
            $saved = $menuController->update($_POST['item_id'], $data);
        } else {
            $saved = $menuController->create($data, $imagePath);
        }
        if ($saved === false) {
            flash('error', 'Please provide valid menu details and an existing category.');
        }
        redirect('views/admin/menu_management.php');
    } elseif (isset($_POST['toggle_id'])) {
        $menuController->toggle((int)$_POST['toggle_id']);
        redirect('views/admin/menu_management.php');
    } elseif (isset($_POST['delete_id'])) {
        $menuController->delete((int)$_POST['delete_id']);
        redirect('views/admin/menu_management.php');
    }
}

if (!empty($_GET['edit'])) {
    $editItem = $menuController->get((int)$_GET['edit']);
}

$items = $menuController->listAll();
$categories = $menuController->categoryModel->all();
$counts = $menuController->menuItemModel->counts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Menu Management - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <h1>Menu Management</h1>
    </header>
    <p class="muted">Manage your canteen's food items, prices, and availability.</p>

    <div class="stat-grid three">
        <div class="stat-card"><span class="muted small">TOTAL ITEMS</span><h2><?= $counts['total'] ?></h2></div>
        <div class="stat-card"><span class="muted small">LOW STOCK</span><h2><?= $counts['low'] ?></h2></div>
        <div class="stat-card"><span class="muted small">UNAVAILABLE</span><h2><?= $counts['unavailable'] ?></h2></div>
    </div>

    <div class="card">
        <h3><?= $editItem ? 'Edit Item' : 'New Menu Item' ?></h3>
        <form method="POST" enctype="multipart/form-data" class="grid-form">
            <?= csrfField() ?>
            <?php if ($editItem): ?><input type="hidden" name="item_id" value="<?= $editItem['id'] ?>"><?php endif; ?>
            <div>
                <label>Name</label>
                <input type="text" name="name" required value="<?= e($editItem['name'] ?? '') ?>">
            </div>
            <div>
                <label>Category</label>
                <select name="category_id">
                    <option value="">Uncategorized</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= (($editItem['category_id'] ?? null) == $cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Price ($)</label>
                <input type="number" step="0.01" name="price" required value="<?= e($editItem['price'] ?? '') ?>">
            </div>
            <div>
                <label>Stock</label>
                <input type="number" name="current_stock" required value="<?= e($editItem['current_stock'] ?? 0) ?>">
            </div>
            <div>
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" required value="<?= e($editItem['reorder_level'] ?? 5) ?>">
            </div>
            <div class="full-width">
                <label>Description</label>
                <textarea name="description" rows="2"><?= e($editItem['description'] ?? '') ?></textarea>
            </div>
            <div class="full-width">
                <label>Item Photo</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <span class="muted small">Use a JPG, PNG, WEBP, or GIF image up to 5 MB.</span>
            </div>
            <div>
                <label class="checkbox"><input type="checkbox" name="is_active" <?= (!$editItem || $editItem['is_active']) ? 'checked' : '' ?>> Available</label>
            </div>
            <div class="full-width">
                <button type="submit" name="save_item" class="btn-primary"><?= $editItem ? 'Update Item' : '+ New Menu Item' ?></button>
                <?php if ($editItem): ?><a href="<?= BASE_URL ?>/views/admin/menu_management.php" class="btn-secondary">Cancel</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
            <tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Availability</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong <?= !$item['is_active'] ? 'style="text-decoration:line-through;color:#999"' : '' ?>><?= e($item['name']) ?></strong>
                        <div class="muted small"><?= e(mb_strimwidth($item['description'] ?? '', 0, 40, '...')) ?></div>
                    </td>
                    <td><span class="tag"><?= e($item['category_name'] ?? 'Uncategorized') ?></span></td>
                    <td>$<?= number_format($item['price'], 2) ?></td>
                    <td class="<?= $item['current_stock'] <= $item['reorder_level'] ? 'danger' : '' ?>"><?= (int)$item['current_stock'] ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="toggle_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="toggle-switch <?= $item['is_active'] ? 'on' : '' ?>"></button>
                        </form>
                    </td>
                    <td>
                        <a href="?edit=<?= $item['id'] ?>" class="link small">Edit</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this item?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="delete_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="link-danger small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?><tr><td colspan="6" class="muted center">No menu items yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
