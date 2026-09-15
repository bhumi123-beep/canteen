<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/StaffWorkspace.php';
requireStaff();
$database = new Database();
$db = $database->connect();
$workspace = new StaffWorkspace($db);
$staffId = (int)($_GET['id'] ?? 0);
if (!$staffId) {
    $current = $workspace->currentStaff($_SESSION['user_id']);
    $staffId = (int)($current['id'] ?? 0);
}
$profile = $workspace->profile($staffId);
if (!$profile) {
    redirect('views/staff/directory.php');
}
$breakdown = $workspace->ratingBreakdown($staffId);
$reviews = $workspace->reviews($staffId);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title><?= e($profile['staff_name']) ?> - CanteenPro</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css"></head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <a class="link small" href="<?= BASE_URL ?>/views/staff/directory.php">&larr; Back to directory</a>
    <div class="profile-layout">
        <section class="card staff-profile-hero"><div class="avatar-circle">👤</div><p class="muted small">PROFILE</p><h1><?= e($profile['staff_name']) ?></h1><p class="muted"><?= e($profile['staff_role']) ?> · <?= e($profile['department']) ?></p><span class="status-pill status-<?= e($profile['staff_status']) ?>"><?= e(ucwords(str_replace('_', ' ', $profile['staff_status']))) ?></span><div class="profile-meta"><span><strong><?= e($profile['staff_shift']) ?></strong><small>Shift</small></span><span><strong><?= number_format((float)$profile['average_rating'], 1) ?> / 5</strong><small>Average rating</small></span><span><strong><?= (int)$profile['review_count'] ?></strong><small>Reviews</small></span></div></section>
        <section class="card"><h2>Rating breakdown</h2><?php for ($stars = 5; $stars >= 1; $stars--): ?><div class="rating-bar"><span><?= $stars ?> ★</span><div><i style="width:<?= $profile['review_count'] ? round(($breakdown[$stars] / $profile['review_count']) * 100) : 0 ?>%"></i></div><strong><?= $breakdown[$stars] ?></strong></div><?php endfor; ?></section>
    </div>
    <section class="card reviews-panel"><h2>Customer reviews</h2><?php foreach ($reviews as $review): ?><article class="review-line"><div class="row-between"><strong><?= str_repeat('★', (int)$review['rating']) ?></strong><span class="muted small"><?= date('M d, Y', strtotime($review['created_at'])) ?></span></div><p><?= e($review['comment'] ?: 'No comment provided.') ?></p><span class="muted small"><?= e($review['customer_name']) ?></span></article><?php endforeach; ?><?php if (!$reviews): ?><p class="muted">No reviews have been submitted yet.</p><?php endif; ?></section>
</main>
</body>
</html>
