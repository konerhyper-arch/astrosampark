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

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$reason = trim($body['reason'] ?? '');
$allowedReasons = ['invalid_number', 'wrong_category', 'duplicate'];

if (!in_array($reason, $allowedReasons, true)) {
    errorResponse('reason must be one of: ' . implode(', ', $allowedReasons));
}

$pdo = Database::getInstance()->getConnection();

// Fetch the purchase
$stmt = $pdo->prepare(
    'SELECT id, purchased_at, refund_status
     FROM lead_purchases
     WHERE lead_id = :lid AND astrologer_id = :aid
     LIMIT 1'
);
$stmt->execute([':lid' => $leadId, ':aid' => $astrologerId]);
$purchase = $stmt->fetch();

if (!$purchase) {
    errorResponse('Purchase record not found', 404);
}

// Only within 24 hours
$purchasedAt = strtotime($purchase['purchased_at']);
if (time() - $purchasedAt > 86400) {
    errorResponse('Refund window (24 hours) has expired');
}

if ($purchase['refund_status'] !== 'none') {
    errorResponse('A refund has already been requested for this purchase');
}

$pdo->prepare(
    'UPDATE lead_purchases
     SET refund_status = "requested", refund_reason = :reason
     WHERE id = :pid'
)->execute([':reason' => $reason, ':pid' => $purchase['id']]);

// Log activity
$pdo->prepare(
    'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
     VALUES (:lid, "astrologer", :aid, "refund_request", :meta)'
)->execute([
    ':lid'  => $leadId,
    ':aid'  => $astrologerId,
    ':meta' => json_encode(['reason' => $reason, 'purchase_id' => $purchase['id']]),
]);

successResponse(['purchase_id' => (int) $purchase['id']], 'Refund request submitted');
