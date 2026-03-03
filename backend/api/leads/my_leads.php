<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/quality_scorer.php';

corsHeaders();

$auth         = Auth::requireAuth();
$astrologerId = (int) $auth['sub'];

$pdo = Database::getInstance()->getConnection();

$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$stmtCount = $pdo->prepare(
    'SELECT COUNT(*) FROM lead_purchases WHERE astrologer_id = :aid'
);
$stmtCount->execute([':aid' => $astrologerId]);
$total = (int) $stmtCount->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT lp.id AS purchase_id, lp.lead_id, lp.purchase_price, lp.purchased_at,
            lp.reveal_at, lp.lead_state, lp.refund_status,
            l.category, l.city, l.state, l.language, l.budget_range,
            l.name, l.phone, l.email, l.quality_score, l.notes
     FROM lead_purchases lp
     JOIN leads l ON l.id = lp.lead_id
     WHERE lp.astrologer_id = :aid
     ORDER BY lp.purchased_at DESC
     LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':aid', $astrologerId, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$now  = time();
$data = [];
foreach ($rows as $row) {
    $revealed = strtotime($row['reveal_at']) <= $now;
    $score    = (int) $row['quality_score'];
    $entry    = [
        'purchase_id'    => (int) $row['purchase_id'],
        'lead_id'        => (int) $row['lead_id'],
        'category'       => $row['category'],
        'city'           => $row['city'],
        'state'          => $row['state'],
        'language'       => $row['language'],
        'budget_range'   => $row['budget_range'],
        'quality_score'  => $score,
        'badge'          => QualityScorer::badge($score),
        'purchase_price' => (float) $row['purchase_price'],
        'purchased_at'   => $row['purchased_at'],
        'lead_state'     => $row['lead_state'],
        'refund_status'  => $row['refund_status'],
        'revealed'       => $revealed,
        'notes'          => $row['notes'],
    ];
    if ($revealed) {
        $entry['name']  = $row['name'];
        $entry['phone'] = $row['phone'];
        $entry['email'] = $row['email'];
    } else {
        $entry['reveal_at'] = $row['reveal_at'];
    }
    $data[] = $entry;
}

successResponse([
    'purchases'  => $data,
    'pagination' => [
        'page'      => $page,
        'per_page'  => $perPage,
        'total'     => $total,
        'last_page' => (int) ceil($total / $perPage),
    ],
]);
