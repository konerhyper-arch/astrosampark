<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/quality_scorer.php';

corsHeaders();

Auth::requireAdmin();

$pdo = Database::getInstance()->getConnection();

$status  = trim($_GET['status'] ?? '');
$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$allowed = ['pending', 'approved', 'rejected', 'sold', 'expired', ''];
if (!in_array($status, $allowed, true)) {
    errorResponse('Invalid status filter');
}

$where  = [];
$params = [];
if ($status !== '') {
    $where[]          = 'status = :status';
    $params[':status'] = $status;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM leads $whereSQL");
$stmtCount->execute($params);
$total = (int) $stmtCount->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT id, source, category, name, phone, email, city, state, language,
            budget_range, quality_score, status, created_at, approved_at, expires_at
     FROM leads
     $whereSQL
     ORDER BY created_at DESC
     LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$data = array_map(function (array $row): array {
    $row['badge'] = QualityScorer::badge((int) $row['quality_score']);
    return $row;
}, $rows);

successResponse([
    'leads'      => $data,
    'pagination' => [
        'page'      => $page,
        'per_page'  => $perPage,
        'total'     => $total,
        'last_page' => (int) ceil($total / $perPage),
    ],
]);
