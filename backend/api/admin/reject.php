<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$adminAuth = Auth::requireAdmin();
$adminId   = (int) $adminAuth['sub'];

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$leadId = (int) ($body['lead_id'] ?? 0);
$reason = trim($body['reason'] ?? '');

if ($leadId <= 0) {
    errorResponse('lead_id is required');
}

$pdo = Database::getInstance()->getConnection();

$stmt = $pdo->prepare(
    'UPDATE leads SET status = "rejected" WHERE id = :id AND status = "pending"'
);
$stmt->execute([':id' => $leadId]);

if ($stmt->rowCount() === 0) {
    errorResponse('Lead not found or already processed', 404);
}

$pdo->prepare(
    'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
     VALUES (:lid, "admin", :aid, "rejected", :meta)'
)->execute([
    ':lid'  => $leadId,
    ':aid'  => $adminId,
    ':meta' => json_encode(['reason' => $reason]),
]);

successResponse(['lead_id' => $leadId], 'Lead rejected');
