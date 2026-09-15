<?php
require_once __DIR__ . '/../models/MenuItem.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Table.php';
require_once __DIR__ . '/../models/Promo.php';
require_once __DIR__ . '/../models/InventoryLog.php';

class AdminController {
    private $conn;
    private $menuItemModel;
    private $orderModel;
    private $userModel;
    private $reservationModel;
    private $promoModel;
    private $inventoryLogModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->menuItemModel = new MenuItem($db);
        $this->orderModel = new Order($db);
        $this->userModel = new User($db);
        $this->reservationModel = new Reservation($db);
        $this->promoModel = new Promo($db);
        $this->inventoryLogModel = new InventoryLog($db);
    }

    public function reportData($days) {
        requireAdmin();
        $days = in_array((int)$days, [7, 30, 90], true) ? (int)$days : 30;
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d');

        $stmt = $this->conn->prepare("SELECT DATE(order_at) AS report_date, COUNT(*) AS order_count,
                COALESCE(SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END), 0) AS revenue
                FROM orders WHERE DATE(order_at) BETWEEN ? AND ? GROUP BY DATE(order_at) ORDER BY report_date");
        $stmt->execute([$from, $to]);
        $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->conn->prepare("SELECT status, COUNT(*) AS order_count FROM orders
            WHERE DATE(order_at) BETWEEN ? AND ? GROUP BY status ORDER BY status");
        $stmt->execute([$from, $to]);
        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $this->conn->prepare("SELECT mi.name, SUM(oi.quantity) AS quantity_ordered,
                SUM(oi.total_price) AS sales_amount
                FROM order_items oi JOIN orders o ON oi.order_id = o.id
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE DATE(o.order_at) BETWEEN ? AND ? AND o.status <> 'cancelled'
                GROUP BY mi.id, mi.name ORDER BY quantity_ordered DESC, sales_amount DESC LIMIT 10");
        $stmt->execute([$from, $to]);
        $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['daily' => $daily, 'statuses' => $statuses, 'top_items' => $topItems];
    }

    public function dashboardData() {
        requireAdmin();
        return [
            'menu_counts' => $this->menuItemModel->counts(),
            'order_stats' => $this->orderModel->todayStats(),
            'customers' => $this->userModel->countActiveCustomers(),
            'recent_orders' => $this->orderModel->recent(5),
            'low_stock' => $this->menuItemModel->lowStock(),
            'vendor_reorders' => $this->menuItemModel->reorderQueue(),
            'inventory_logs' => $this->inventoryLogModel->recent(10),
            'reservations' => $this->reservationModel->all(),
            'promo_claims' => $this->promoModel->all(),
            'most_ordered_today' => $this->orderModel->mostOrdered('today'),
            'most_ordered_week' => $this->orderModel->mostOrdered('week'),
        ];
    }

    public function autoReorder($id) {
        requireAdmin();
        $item = $this->menuItemModel->find($id);
        if (!$item) {
            return false;
        }

        $previousStock = (int)($item['current_stock'] ?? 0);
        $qty = $this->menuItemModel->recommendedRestockQty($previousStock, (int)($item['reorder_level'] ?? 0));
        $result = $this->menuItemModel->autoReorder($id, $qty);

        if ($result) {
            $this->inventoryLogModel->add($id, 'vendor_reorder', $qty, $previousStock, $previousStock + $qty, 'Automated vendor reorder');
        }

        return $result;
    }

    public function updateReservationStatus($id, $status) {
        requireAdmin();
        return $this->reservationModel->updateStatus($id, $status);
    }
}
