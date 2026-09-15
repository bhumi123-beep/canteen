<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$admin = new AdminController($db);
$allowedRanges = [7, 30, 90];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowedRanges, true)) {
    $days = 30;
}
$report = $admin->reportData($days);

$dailyByDate = [];
foreach ($report['daily'] as $row) {
    $dailyByDate[$row['report_date']] = $row;
}
$chartCategories = [];
$chartOrders = [];
$chartRevenue = [];
for ($offset = $days - 1; $offset >= 0; $offset--) {
    $date = date('Y-m-d', strtotime('-' . $offset . ' days'));
    $chartCategories[] = date('M j', strtotime($date));
    $chartOrders[] = (int)($dailyByDate[$date]['order_count'] ?? 0);
    $chartRevenue[] = round((float)($dailyByDate[$date]['revenue'] ?? 0), 2);
}
$totalOrders = array_sum($chartOrders);
$totalRevenue = array_sum($chartRevenue);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
<script src="https://code.highcharts.com/highcharts.js"></script>
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar row-between">
        <div><p class="muted small">ADMIN / PERFORMANCE</p><h1>Reports</h1><p class="muted">Track sales, orders, and menu performance.</p></div>
        <form method="GET" class="report-filter">
            <label for="days">Period</label>
            <select id="days" name="days" onchange="this.form.submit()">
                <?php foreach ($allowedRanges as $range): ?><option value="<?= $range ?>" <?= $days === $range ? 'selected' : '' ?>>Last <?= $range ?> days</option><?php endforeach; ?>
            </select>
        </form>
    </header>

    <div class="stat-grid three">
        <div class="stat-card"><span class="muted small">Revenue</span><h2>$<?= number_format($totalRevenue, 2) ?></h2><span class="muted small">Non-cancelled orders</span></div>
        <div class="stat-card"><span class="muted small">Orders</span><h2><?= number_format($totalOrders) ?></h2><span class="muted small">All order statuses</span></div>
        <div class="stat-card"><span class="muted small">Average order</span><h2>$<?= number_format($totalOrders ? $totalRevenue / $totalOrders : 0, 2) ?></h2><span class="muted small">For selected period</span></div>
    </div>

    <div class="report-chart-grid">
        <section class="card"><h2>Revenue trend</h2><div id="revenue-chart" class="report-chart"></div></section>
        <section class="card"><h2>Order volume</h2><div id="orders-chart" class="report-chart"></div></section>
    </div>

    <div class="report-detail-grid">
        <section class="card">
            <h2>Order status</h2>
            <div id="status-chart" class="report-chart report-chart-small"></div>
            <?php if (!$report['statuses']): ?><p class="muted small">No orders in this period.</p><?php endif; ?>
        </section>
        <section class="card">
            <h2>Best-selling items</h2>
            <table class="data-table">
                <thead><tr><th>Item</th><th>Qty sold</th><th>Sales</th></tr></thead>
                <tbody>
                <?php foreach ($report['top_items'] as $item): ?>
                    <tr><td><?= e($item['name']) ?></td><td><?= (int)$item['quantity_ordered'] ?></td><td>$<?= number_format((float)$item['sales_amount'], 2) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$report['top_items']): ?><tr><td colspan="3" class="muted center">No item sales in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <section class="card report-table-card">
        <h2>Daily breakdown</h2>
        <table class="data-table">
            <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php for ($index = count($chartCategories) - 1; $index >= 0; $index--): ?>
                <tr><td><?= e($chartCategories[$index]) ?></td><td><?= $chartOrders[$index] ?></td><td>$<?= number_format($chartRevenue[$index], 2) ?></td></tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </section>
</main>
<script>
const chartCategories = <?= json_encode($chartCategories, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const chartOrders = <?= json_encode($chartOrders) ?>;
const chartRevenue = <?= json_encode($chartRevenue) ?>;
const statusData = <?= json_encode(array_map(static function ($row) {
    return ['name' => ucfirst($row['status']), 'y' => (int)$row['order_count']];
}, $report['statuses']), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const chartColors = ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444'];

Highcharts.setOptions({
    chart: { style: { fontFamily: 'Plus Jakarta Sans, sans-serif' } },
    colors: chartColors,
    credits: { enabled: false }
});
Highcharts.chart('revenue-chart', {
    chart: { type: 'areaspline' }, title: { text: null }, xAxis: { categories: chartCategories },
    yAxis: { title: { text: 'Revenue ($)' }, min: 0 }, tooltip: { valuePrefix: '$' },
    legend: { enabled: false }, series: [{ name: 'Revenue', data: chartRevenue, color: '#10b981', fillOpacity: 0.16 }]
});
Highcharts.chart('orders-chart', {
    chart: { type: 'column' }, title: { text: null }, xAxis: { categories: chartCategories },
    yAxis: { title: { text: 'Orders' }, min: 0, allowDecimals: false }, tooltip: { valueSuffix: ' orders' },
    legend: { enabled: false }, series: [{ name: 'Orders', data: chartOrders, color: '#3b82f6' }]
});
Highcharts.chart('status-chart', {
    chart: { type: 'pie' }, title: { text: null }, tooltip: { pointFormat: '<b>{point.y}</b> orders' },
    plotOptions: { pie: { innerSize: '58%', dataLabels: { enabled: true, format: '{point.name}: {point.y}' } } },
    series: [{ name: 'Orders', data: statusData }]
});
</script>
</body>
</html>