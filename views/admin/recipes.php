<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Ingredient.php';
require_once __DIR__ . '/../../models/MenuItem.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$ingredients = new Ingredient($db);
$menu = new MenuItem($db);
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = 'Security token expired. Please try again.';
    } else try {
        if (isset($_POST['add_ingredient']) && $ingredients->add($_POST['name'] ?? '', $_POST['unit'] ?? '', $_POST['current_stock'] ?? 0, $_POST['reorder_level'] ?? 0)) {
            $message = 'Ingredient added.';
        } elseif (isset($_POST['save_recipe']) && $ingredients->saveRecipe($_POST['menu_item_id'] ?? 0, $_POST['ingredient_id'] ?? 0, $_POST['quantity_per_item'] ?? 0)) {
            $message = 'Recipe mapping saved.';
        } else {
            $error = 'Enter valid ingredient and recipe details.';
        }
    } catch (PDOException $exception) {
        $error = 'That ingredient or recipe mapping already exists.';
    }
}
$items = $menu->all(false);
$ingredientRows = $ingredients->all();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Recipes and Ingredients - CanteenPro</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css"></head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar"><h1>Recipes and ingredients</h1></header>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="card"><h2>Add ingredient</h2><form method="POST" class="grid-form"><?= csrfField() ?><input name="name" placeholder="Ingredient name" required><input name="unit" placeholder="Unit, e.g. kg" required><input type="number" step="0.001" min="0" name="current_stock" placeholder="Current stock" required><input type="number" step="0.001" min="0" name="reorder_level" placeholder="Reorder level" required><button class="btn-primary" name="add_ingredient">Add ingredient</button></form></div>
    <div class="card"><h2>Map ingredients to menu items</h2><form method="POST" class="grid-form"><?= csrfField() ?><select name="menu_item_id" required><option value="">Menu item</option><?php foreach ($items as $item): ?><option value="<?= (int)$item['id'] ?>"><?= e($item['name']) ?></option><?php endforeach; ?></select><select name="ingredient_id" required><option value="">Ingredient</option><?php foreach ($ingredientRows as $ingredient): ?><option value="<?= (int)$ingredient['id'] ?>"><?= e($ingredient['name']) ?> (<?= e($ingredient['unit']) ?>)</option><?php endforeach; ?></select><input type="number" step="0.001" min="0.001" name="quantity_per_item" placeholder="Quantity per item" required><button class="btn-primary" name="save_recipe">Save mapping</button></form></div>
    <div class="card"><table class="data-table"><thead><tr><th>Menu item</th><th>Ingredient</th><th>Per item</th><th>Available stock</th></tr></thead><tbody><?php foreach ($items as $item): foreach ($ingredients->recipesForMenuItem($item['id']) as $recipe): ?><tr><td><?= e($item['name']) ?></td><td><?= e($recipe['name']) ?></td><td><?= e($recipe['quantity_per_item']) ?> <?= e($recipe['unit']) ?></td><td><?= e($recipe['current_stock']) ?> <?= e($recipe['unit']) ?></td></tr><?php endforeach; endforeach; ?></tbody></table></div>
</main>
</body>
</html>
