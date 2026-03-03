<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

$auth   = Auth::requireAuth();
$userId = (int) $auth['sub'];

$pdo = Database::getInstance()->getConnection();

$stmtW = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = :uid');
$stmtW->execute([':uid' => $userId]);
$wallet = $stmtW->fetch();

// Recent 5 transactions
$stmtT = $pdo->prepare(
    'SELECT id, amount, type, ref_type, ref_id, created_at
     FROM wallet_transactions
     WHERE user_id = :uid
     ORDER BY created_at DESC
     LIMIT 5'
);
$stmtT->execute([':uid' => $userId]);
$transactions = $stmtT->fetchAll();

successResponse([
    'balance'      => $wallet ? (float) $wallet['balance'] : 0.0,
    'transactions' => $transactions,
]);
