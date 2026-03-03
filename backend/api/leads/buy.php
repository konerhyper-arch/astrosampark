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

$pdo = Database::getInstance()->getConnection();

try {
    $pdo->beginTransaction();

    // 1. Lock lead row
    $stmtLead = $pdo->prepare(
        'SELECT l.id, l.status, l.name, l.phone, l.email,
                ll.id AS listing_id, ll.price, ll.active
         FROM leads l
         JOIN lead_listings ll ON ll.lead_id = l.id
         WHERE l.id = :id
         FOR UPDATE'
    );
    $stmtLead->execute([':id' => $leadId]);
    $lead = $stmtLead->fetch();

    if (!$lead) {
        $pdo->rollBack();
        errorResponse('Lead not found', 404);
    }

    // 2. Business rule checks
    if ($lead['status'] !== 'approved') {
        $pdo->rollBack();
        errorResponse('Lead is not available for purchase');
    }
    if ((int) $lead['active'] !== 1) {
        $pdo->rollBack();
        errorResponse('Lead listing is no longer active');
    }

    // Check not already purchased by this astrologer
    $stmtDup = $pdo->prepare(
        'SELECT id FROM lead_purchases WHERE lead_id = :lid AND astrologer_id = :aid'
    );
    $stmtDup->execute([':lid' => $leadId, ':aid' => $astrologerId]);
    if ($stmtDup->fetch()) {
        $pdo->rollBack();
        errorResponse('You have already purchased this lead');
    }

    $price = (float) $lead['price'];

    // 3. Check astrologer wallet balance
    $stmtWallet = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = :uid FOR UPDATE');
    $stmtWallet->execute([':uid' => $astrologerId]);
    $wallet = $stmtWallet->fetch();

    if (!$wallet || (float) $wallet['balance'] < $price) {
        $pdo->rollBack();
        errorResponse('Insufficient wallet balance', 402);
    }

    $revealAt = date('Y-m-d H:i:s'); // Reveal immediately

    // 4. Create lead_purchases
    $stmtPurchase = $pdo->prepare(
        'INSERT INTO lead_purchases (lead_id, astrologer_id, purchase_price, purchased_at, reveal_at, lead_state)
         VALUES (:lid, :aid, :price, NOW(), :reveal_at, "new")'
    );
    $stmtPurchase->execute([
        ':lid'       => $leadId,
        ':aid'       => $astrologerId,
        ':price'     => $price,
        ':reveal_at' => $revealAt,
    ]);
    $purchaseId = (int) $pdo->lastInsertId();

    // 5. Create escrow_holds
    $stmtEscrow = $pdo->prepare(
        'INSERT INTO escrow_holds (purchase_id, amount, status) VALUES (:pid, :amount, "hold")'
    );
    $stmtEscrow->execute([':pid' => $purchaseId, ':amount' => $price]);

    // 6. Debit wallet
    $stmtDebit = $pdo->prepare(
        'UPDATE wallets SET balance = balance - :price WHERE user_id = :uid AND balance >= :price'
    );
    $stmtDebit->execute([':price' => $price, ':uid' => $astrologerId]);
    if ($stmtDebit->rowCount() === 0) {
        $pdo->rollBack();
        errorResponse('Wallet debit failed – concurrent modification', 409);
    }

    // Sync wallet_balance on users table
    $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - :price WHERE id = :uid')
        ->execute([':price' => $price, ':uid' => $astrologerId]);

    // Log wallet transaction
    $stmtWT = $pdo->prepare(
        'INSERT INTO wallet_transactions (user_id, amount, type, ref_type, ref_id)
         VALUES (:uid, :amount, "debit", "lead_purchase", :ref)'
    );
    $stmtWT->execute([':uid' => $astrologerId, ':amount' => $price, ':ref' => $purchaseId]);

    // 7. Mark lead as sold, deactivate listing
    $pdo->prepare('UPDATE leads SET status = "sold" WHERE id = :id')
        ->execute([':id' => $leadId]);
    $pdo->prepare('UPDATE lead_listings SET active = 0 WHERE id = :id')
        ->execute([':id' => $lead['listing_id']]);

    // 8. Activity log
    $stmtLog = $pdo->prepare(
        'INSERT INTO lead_activity_logs (lead_id, actor_type, actor_id, action, meta_json)
         VALUES (:lid, "astrologer", :aid, "purchased", :meta)'
    );
    $stmtLog->execute([
        ':lid'  => $leadId,
        ':aid'  => $astrologerId,
        ':meta' => json_encode(['purchase_id' => $purchaseId, 'price' => $price]),
    ]);

    // 9. Commit
    $pdo->commit();

    successResponse([
        'purchase_id'  => $purchaseId,
        'reveal_at'    => $revealAt,
        'contact'      => [
            'name'  => $lead['name'],
            'phone' => $lead['phone'],
            'email' => $lead['email'],
        ],
    ], 'Lead purchased successfully');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('buy.php error: ' . $e->getMessage());
    errorResponse('Purchase failed: ' . $e->getMessage(), 500);
}
