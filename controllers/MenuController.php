<?php
require_once __DIR__ . '/../models/MenuItem.php';
require_once __DIR__ . '/../models/Category.php';

class MenuController {
    public $menuItemModel;
    public $categoryModel;

    public function __construct($db) {
        $this->menuItemModel = new MenuItem($db);
        $this->categoryModel = new Category($db);
    }

    public function listActive() {
        return $this->menuItemModel->all(true);
    }

    public function listAll() {
        requireAdmin();
        return $this->menuItemModel->all(false);
    }

    public function get($id) {
        return $this->menuItemModel->find($id);
    }

    public function getActive($id) {
        $id = Validator::menuItemId($id);
        if ($id === false) {
            return null;
        }
        $item = $this->menuItemModel->find($id);
        return $item && (int)$item['is_active'] === 1 ? $item : null;
    }

    public function create($data, $imagePath) {
        requireMenuManagement();
        $data = $this->validatedData($data);
        if ($data === false) {
            return false;
        }
        $data['image'] = $imagePath;
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        return $this->menuItemModel->create($data);
    }

    public function update($id, $data) {
        requireMenuManagement();
        $id = Validator::menuItemId($id);
        $data = $this->validatedData($data);
        if ($id === false || $data === false) {
            return false;
        }
        $data['is_active'] = isset($data['is_active']) ? 1 : 0;
        return $this->menuItemModel->update($id, $data);
    }

    public function toggle($id) {
        requireMenuManagement();
        $id = Validator::menuItemId($id);
        return $id !== false ? $this->menuItemModel->toggleAvailability($id) : false;
    }

    public function delete($id) {
        requireMenuManagement();
        $id = Validator::menuItemId($id);
        return $id !== false ? $this->menuItemModel->delete($id) : false;
    }

    private function validatedData($data) {
        $categoryId = trim((string)($data['category_id'] ?? ''));
        $categoryId = $categoryId === '' ? null : Validator::categoryId($categoryId);
        $name = Validator::text($data['name'] ?? '', true, 150);
        $description = Validator::description($data['description'] ?? '');
        $price = Validator::price($data['price'] ?? null);
        $stock = Validator::stock($data['current_stock'] ?? null);
        $reorderLevel = Validator::stock($data['reorder_level'] ?? null);
        if ($categoryId === false || $name === false || $description === false || $price === false || $stock === false || $reorderLevel === false) {
            return false;
        }
        if ($categoryId !== null && !$this->categoryModel->exists($categoryId)) {
            return false;
        }
        if ($reorderLevel > $stock + 1000000) {
            return false;
        }
        $data['category_id'] = $categoryId;
        $data['name'] = $name;
        $data['description'] = $description;
        $data['price'] = $price;
        $data['current_stock'] = $stock;
        $data['reorder_level'] = $reorderLevel;
        return $data;
    }
}
