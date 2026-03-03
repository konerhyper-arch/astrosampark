<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/quality_scorer.php';

corsHeaders();

$auth         = Auth::requireAuth();
$astrologerId = (int) $auth['sub'];

// Extract lead ID from the URL path: /leads/{id}/preview
$leadId = (int) ($_GET['lead_id'] ?? 0);
if ($leadId <= 0) {
    errorResponse('Invalid lead ID');
}

$pdo = Database::getInstance()->getConnection();

// Fetch lead + listing
$stmt = $pdo->prepare(
    'SELECT l.id, l.category, l.city, l.state, l.language, l.budget_range,
            l.notes, l.quality_score, l.created_at, l.status,
            ll.price, ll.active
     FROM leads l
     JOIN lead_listings ll ON ll.lead_id = l.id
     WHERE l.id = :id AND l.status = "approved" AND ll.active = 1
     LIMIT 1'
);
$stmt->execute([':id' => $leadId]);
$lead = $stmt->fetch();

if (!$lead) {
    errorResponse('Lead not found or not available', 404);
}

// Astrologer wallet balance
$stmtW = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = :uid');
$stmtW->execute([':uid' => $astrologerId]);
$wallet = $stmtW->fetch();

$score   = (int) $lead['quality_score'];
$summary = !empty($lead['notes']) ? mb_substr($lead['notes'], 0, 100) : '';

successResponse([
    'id'            => (int) $lead['id'],
    'category'      => $lead['category'],
    'city'          => $lead['city'],
    'state'         => $lead['state'],
    'language'      => $lead['language'],
    'budget_range'  => $lead['budget_range'],
    'notes_summary' => $summary,
    'quality_score' => $score,
    'badge'         => QualityScorer::badge($score),
    'price'         => (float) $lead['price'],
    'wallet_balance'=> $wallet ? (float) $wallet['balance'] : 0.0,
]);
