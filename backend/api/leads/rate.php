<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$auth         = Auth::requireAuth();
$astrologerId = (int) $auth['sub'];

$leadId = (int) ($_GET['lead_id'] ?? 0);
if ($leadId <= 0) {
    errorResponse('Invalid lead ID');
}

$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$rating  = isset($body['rating']) ? (int) $body['rating'] : 0;
$comment = trim($body['comment'] ?? '');

if ($rating < 1 || $rating > 5) {
    errorResponse('Rating must be between 1 and 5');
}

$pdo = Database::getInstance()->getConnection();

// Verify this astrologer purchased this lead
$stmtP = $pdo->prepare(
    'SELECT id FROM lead_purchases WHERE lead_id = :lid AND astrologer_id = :aid LIMIT 1'
);
$stmtP->execute([':lid' => $leadId, ':aid' => $astrologerId]);
if (!$stmtP->fetch()) {
    errorResponse('You must purchase this lead before rating it', 403);
}

// Fetch lead to get original source user (if applicable)
// In this marketplace leads have no specific "owner" user;
// rating goes toward the admin/platform aggregate.
// We store the rating action in activity log and recalculate astrologer's own rating average
// based on leads they submitted (future feature). For now, update astrologer rating average.

// Re-calculate average rating for astrologer (simulated: use lead_activity_logs count as proxy)
$stmtAvg = $pdo->prepare(
    "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta_json, '$.rating')) AS DECIMAL(3,1))) AS avg_rating
     FROM lead_activity_logs
     WHERE actor_type = 'astrologer' AND actor_id = :aid AND action = 'rate_lead'"
);
$stmtAvg->execute([':aid' => $astrologerId]);
$avgRow    = $stmtAvg->fetch();
$currentAvg = $avgRow['avg_rating'] !== null ? (float) $avgRow['avg_rating'] : 0.0;

// Log the rating
$pdo->prepare(
    'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
     VALUES (:lid, "astrologer", :aid, "rate_lead", :meta)'
)->execute([
    ':lid'  => $leadId,
    ':aid'  => $astrologerId,
    ':meta' => json_encode(['rating' => $rating, 'comment' => $comment]),
]);

// Recalculate and persist new average
$stmtAvg->execute([':aid' => $astrologerId]);
$avgRow    = $stmtAvg->fetch();
$newAvg    = $avgRow['avg_rating'] !== null ? round((float) $avgRow['avg_rating'], 2) : (float) $rating;

$pdo->prepare('UPDATE users SET rating = :rating WHERE id = :id')
    ->execute([':rating' => $newAvg, ':id' => $astrologerId]);

successResponse(['new_rating_average' => $newAvg], 'Rating submitted');
