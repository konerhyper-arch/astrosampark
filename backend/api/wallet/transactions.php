<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

$auth   = Auth::requireAuth();
$userId = (int) $auth['sub'];

$pdo = Database::getInstance()->getConnection();

$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$stmtCount = $pdo->prepare(
    'SELECT COUNT(*) FROM wallet_transactions WHERE user_id = :uid'
);
$stmtCount->execute([':uid' => $userId]);
$total = (int) $stmtCount->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT id, amount, type, ref_type, ref_id, created_at
     FROM wallet_transactions
     WHERE user_id = :uid
     ORDER BY created_at DESC
     LIMIT :lim OFFSET :off'
);
$stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

successResponse([
    'transactions' => $rows,
    'pagination'   => [
        'page'      => $page,
        'per_page'  => $perPage,
        'total'     => $total,
        'last_page' => (int) ceil($total / $perPage),
    ],
]);
