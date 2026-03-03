<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/masking.php';
require_once __DIR__ . '/../../helpers/quality_scorer.php';

corsHeaders();

$auth = Auth::requireAuth();
$astrologerId = (int) $auth['sub'];

$pdo = Database::getInstance()->getConnection();

// Query parameters
$category    = trim($_GET['category']    ?? '');
$city        = trim($_GET['city']        ?? '');
$language    = trim($_GET['language']    ?? '');
$minPrice    = isset($_GET['min_price']) ? (float) $_GET['min_price'] : null;
$maxPrice    = isset($_GET['max_price']) ? (float) $_GET['max_price'] : null;
$qualityBadge = trim($_GET['quality_badge'] ?? '');
$page        = max(1, (int) ($_GET['page']     ?? 1));
$perPage     = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset      = ($page - 1) * $perPage;

// Fetch astrologer rating for min_rating_required filter
$stmtUser = $pdo->prepare('SELECT rating FROM users WHERE id = ? AND status = "active"');
$stmtUser->execute([$astrologerId]);
$astrologer = $stmtUser->fetch();
if (!$astrologer) {
    errorResponse('Astrologer account not found or inactive', 403);
}
$astrologerRating = (float) $astrologer['rating'];

$where  = ['l.status = "approved"', 'll.active = 1', 'll.min_rating_required <= :rating'];
$params = [':rating' => $astrologerRating];

if ($category !== '') {
    $where[]              = 'l.category = :category';
    $params[':category']  = $category;
}
if ($city !== '') {
    $where[]           = 'l.city = :city';
    $params[':city']   = $city;
}
if ($language !== '') {
    $where[]              = 'l.language = :language';
    $params[':language']  = $language;
}
if ($minPrice !== null) {
    $where[]              = 'll.price >= :min_price';
    $params[':min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $where[]              = 'll.price <= :max_price';
    $params[':max_price'] = $maxPrice;
}
if ($qualityBadge !== '') {
    switch ($qualityBadge) {
        case 'Premium':
            $where[] = 'l.quality_score >= 80';
            break;
        case 'Verified':
            $where[] = 'l.quality_score >= 60 AND l.quality_score < 80';
            break;
        case 'Basic':
            $where[] = 'l.quality_score < 60';
            break;
    }
}

$whereSQL = implode(' AND ', $where);

// Count total
$countSQL  = "SELECT COUNT(*) FROM leads l
              JOIN lead_listings ll ON ll.lead_id = l.id
              WHERE $whereSQL";
$stmtCount = $pdo->prepare($countSQL);
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();

// Fetch page
$sql = "SELECT l.id, l.category, l.city, l.state, l.language, l.budget_range,
               l.quality_score, l.created_at,
               l.name, l.phone, l.email,
               ll.price, ll.id AS listing_id
        FROM leads l
        JOIN lead_listings ll ON ll.lead_id = l.id
        WHERE $whereSQL
        ORDER BY l.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$now  = time();
$data = [];
foreach ($rows as $row) {
    $score    = (int) $row['quality_score'];
    $badge    = QualityScorer::badge($score);
    $age      = $now - strtotime($row['created_at']);
    $time_ago = match (true) {
        $age < 60     => 'just now',
        $age < 3600   => ($m = (int) floor($age / 60))   . ' ' . ($m === 1 ? 'min' : 'mins') . ' ago',
        $age < 86400  => ($h = (int) floor($age / 3600)) . ' ' . ($h === 1 ? 'hr'  : 'hrs')  . ' ago',
        default       => ($d = (int) floor($age / 86400)) . ' ' . ($d === 1 ? 'day' : 'days') . ' ago',
    };

    $data[] = [
        'id'            => (int) $row['id'],
        'listing_id'    => (int) $row['listing_id'],
        'category'      => $row['category'],
        'city'          => $row['city'],
        'state'         => $row['state'],
        'language'      => $row['language'],
        'budget_range'  => $row['budget_range'],
        'quality_score' => $score,
        'badge'         => $badge,
        'price'         => (float) $row['price'],
        'time_ago'      => $time_ago,
        'name'          => Masking::maskName($row['name']),
        'phone'         => Masking::maskPhone($row['phone']),
        'email'         => $row['email'] ? Masking::maskEmail($row['email']) : null,
    ];
}

successResponse([
    'leads'      => $data,
    'pagination' => [
        'page'       => $page,
        'per_page'   => $perPage,
        'total'      => $total,
        'last_page'  => (int) ceil($total / $perPage),
    ],
]);
