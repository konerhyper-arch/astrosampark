<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

Auth::requireAdmin();

$pdo = Database::getInstance()->getConnection();

// Overall sales
$stmtSales = $pdo->query(
    'SELECT COUNT(*) AS total_sales,
            SUM(purchase_price) AS total_revenue,
            AVG(purchase_price) AS avg_price
     FROM lead_purchases'
);
$sales = $stmtSales->fetch();

// Sales by category
$stmtCat = $pdo->query(
    'SELECT l.category, COUNT(*) AS count, SUM(lp.purchase_price) AS revenue
     FROM lead_purchases lp
     JOIN leads l ON l.id = lp.lead_id
     GROUP BY l.category
     ORDER BY revenue DESC'
);
$byCategory = $stmtCat->fetchAll();

// Sales last 30 days (daily)
$stmtDaily = $pdo->query(
    'SELECT DATE(purchased_at) AS date, COUNT(*) AS sales, SUM(purchase_price) AS revenue
     FROM lead_purchases
     WHERE purchased_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(purchased_at)
     ORDER BY date ASC'
);
$daily = $stmtDaily->fetchAll();

// Refund stats
$stmtRefund = $pdo->query(
    "SELECT refund_status, COUNT(*) AS count
     FROM lead_purchases
     WHERE refund_status != 'none'
     GROUP BY refund_status"
);
$refunds = $stmtRefund->fetchAll();

// Lead status distribution
$stmtStatus = $pdo->query(
    'SELECT status, COUNT(*) AS count FROM leads GROUP BY status'
);
$leadStatus = $stmtStatus->fetchAll();

// Top astrologers by purchases
$stmtTop = $pdo->query(
    'SELECT u.id, u.name, COUNT(lp.id) AS purchases, SUM(lp.purchase_price) AS spent
     FROM lead_purchases lp
     JOIN users u ON u.id = lp.astrologer_id
     GROUP BY u.id, u.name
     ORDER BY purchases DESC
     LIMIT 10'
);
$topAstrologers = $stmtTop->fetchAll();

successResponse([
    'summary'          => $sales,
    'by_category'      => $byCategory,
    'daily_last_30d'   => $daily,
    'refund_stats'     => $refunds,
    'lead_status_dist' => $leadStatus,
    'top_astrologers'  => $topAstrologers,
]);
