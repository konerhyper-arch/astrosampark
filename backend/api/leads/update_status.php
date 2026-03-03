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

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$leadState  = trim($body['lead_state'] ?? '');
$allowed    = ['contacted', 'converted', 'not_interested'];

if (!in_array($leadState, $allowed, true)) {
    errorResponse('lead_state must be one of: ' . implode(', ', $allowed));
}

$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->prepare(
    'UPDATE lead_purchases
     SET lead_state = :state
     WHERE lead_id = :lid AND astrologer_id = :aid'
);
$stmt->execute([
    ':state' => $leadState,
    ':lid'   => $leadId,
    ':aid'   => $astrologerId,
]);

if ($stmt->rowCount() === 0) {
    errorResponse('Purchase record not found or not yours', 404);
}

// Log activity
$pdo->prepare(
    'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
     VALUES (:lid, "astrologer", :aid, "status_update", :meta)'
)->execute([
    ':lid'  => $leadId,
    ':aid'  => $astrologerId,
    ':meta' => json_encode(['lead_state' => $leadState]),
]);

successResponse(['lead_state' => $leadState], 'Status updated');
