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

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$leadId     = (int) ($body['lead_id'] ?? 0);
$price      = isset($body['price']) ? (float) $body['price'] : 0.0;
$visibility = trim($body['visibility'] ?? 'public');
$minRating  = isset($body['min_rating_required']) ? (float) $body['min_rating_required'] : 0.0;

if ($leadId <= 0) {
    errorResponse('lead_id is required');
}
if ($price <= 0) {
    errorResponse('price must be greater than 0');
}
if (!in_array($visibility, ['public', 'private'], true)) {
    errorResponse('visibility must be public or private');
}

$pdo = Database::getInstance()->getConnection();

// Verify lead is approved
$stmtLead = $pdo->prepare('SELECT id, status FROM leads WHERE id = :id');
$stmtLead->execute([':id' => $leadId]);
$lead = $stmtLead->fetch();

if (!$lead) {
    errorResponse('Lead not found', 404);
}
if ($lead['status'] !== 'approved') {
    errorResponse('Only approved leads can be listed');
}

// Deactivate any existing listing
$pdo->prepare('UPDATE lead_listings SET active = 0 WHERE lead_id = :lid')
    ->execute([':lid' => $leadId]);

// Create new listing
$stmtInsert = $pdo->prepare(
    'INSERT INTO lead_listings (lead_id, price, visibility, min_rating_required, active)
     VALUES (:lid, :price, :vis, :min_rating, 1)'
);
$stmtInsert->execute([
    ':lid'        => $leadId,
    ':price'      => $price,
    ':vis'        => $visibility,
    ':min_rating' => $minRating,
]);
$listingId = (int) $pdo->lastInsertId();

$pdo->prepare(
    'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
     VALUES (:lid, "admin", :aid, "listing_created", :meta)'
)->execute([
    ':lid'  => $leadId,
    ':aid'  => $adminId,
    ':meta' => json_encode(['listing_id' => $listingId, 'price' => $price, 'visibility' => $visibility]),
]);

successResponse(['listing_id' => $listingId], 'Listing created');
