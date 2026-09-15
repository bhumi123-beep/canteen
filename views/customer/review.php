<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/CustomerReview.php';

$database = new Database();
$db = $database->connect();
$review = new CustomerReview($db);
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$order = $review->orderForToken($token);
$decision = $order ? $review->decisionForOrder($order['id']) : null;
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    if (!verifyCsrf()) {
        $error = 'This review form expired. Please try again.';
    } elseif (isset($_POST['skip_review'])) {
        $review->skip($order['id']);
        $decision = 'skipped';
        $message = 'Your review was skipped. Thank you for visiting CanteenPro.';
    } elseif (isset($_POST['submit_reviews'])) {
        $ratings = $_POST['rating'] ?? [];
        $comments = $_POST['comment'] ?? [];
        $saved = 0;
        if (!is_array($ratings) || !is_array($comments)) {
            $ratings = [];
            $comments = [];
        }
        foreach ($ratings as $staffId => $rating) {
            if ($rating === '') {
                continue;
            }
            if ($review->addRating($token, $staffId, $rating, $comments[$staffId] ?? '')) {
                $saved++;
            }
        }
        $review->complete($order['id']);
        $decision = 'completed';
        $message = $saved ? $saved . ' review(s) saved. Thank you for your feedback.' : 'No staff member was rated. Thank you for visiting CanteenPro.';
    }
}
$staffMembers = $order ? $review->staffForOrder($order['id']) : [];
$showForm = $order && !$decision && (($_GET['mode'] ?? '') === 'rate');
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Rate Your Experience - CanteenPro</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css"></head>
<body>
<header class="top-nav"><a href="<?= BASE_URL ?>/views/customer/home.php" class="brand">CanteenPro</a></header>
<main class="container narrow">
    <?php if (!$order): ?>
        <div class="card"><h1>Review unavailable</h1><p class="muted">This review link is invalid or has expired.</p></div>
    <?php elseif ($message || $decision): ?>
        <div class="card center"><h1><?= $decision === 'skipped' ? 'Review skipped' : 'Thank you' ?></h1><p class="muted"><?= e($message ?: 'Your review has already been completed for this order.') ?></p><a class="btn-primary" href="<?= BASE_URL ?>/views/customer/home.php">Back to menu</a></div>
    <?php elseif (!$showForm): ?>
        <div class="card center"><p class="muted">Order #<?= e($order['order_number']) ?> has been paid.</p><h1>Would you like to rate your experience?</h1><div class="row-between"><a class="btn-primary" href="<?= BASE_URL ?>/views/customer/review.php?token=<?= rawurlencode($token) ?>&mode=rate">Rate now</a><form method="POST"><input type="hidden" name="token" value="<?= e($token) ?>"><?= csrfField() ?><button class="btn-secondary" name="skip_review">Skip</button></form></div></div>
    <?php else: ?>
        <div class="card"><p class="muted">Order #<?= e($order['order_number']) ?> has been paid.</p><h1>Rate your experience</h1><p class="muted">Rate anyone who helped with this order. You can leave other staff unrated.</p><?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?><form method="POST"><input type="hidden" name="token" value="<?= e($token) ?>"><?= csrfField() ?><?php foreach ($staffMembers as $staff): ?><div class="review-line"><div class="row-between"><strong><?= e($staff['staff_name']) ?></strong><span class="muted small"><?= e($staff['assignment'] ?: $staff['staff_role']) ?></span></div><label>Rating</label><select name="rating[<?= (int)$staff['id'] ?>]"><option value="">Skip this person</option><option value="5">5 - Excellent</option><option value="4">4 - Very good</option><option value="3">3 - Good</option><option value="2">2 - Fair</option><option value="1">1 - Poor</option></select><label>Comment <span class="muted small">(optional)</span></label><textarea name="comment[<?= (int)$staff['id'] ?>]" rows="2" maxlength="1000"></textarea></div><?php endforeach; ?><?php if (!$staffMembers): ?><p class="muted">No assigned staff were recorded for this order.</p><?php endif; ?><button class="btn-primary" name="submit_reviews">Submit reviews</button></form></div>
    <?php endif; ?>
</main>
</body>
</html>
