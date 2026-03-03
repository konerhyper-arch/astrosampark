<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$auth   = Auth::requireAuth();
$userId = (int) $auth['sub'];

$body      = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId   = trim($body['razorpay_order_id']   ?? '');
$paymentId = trim($body['razorpay_payment_id'] ?? '');
$signature = trim($body['razorpay_signature']  ?? '');

if (!$orderId || !$paymentId || !$signature) {
    errorResponse('razorpay_order_id, razorpay_payment_id and razorpay_signature are required');
}

$razorpaySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';

// Signature verification
if ($razorpaySecret) {
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, $razorpaySecret);
    if (!hash_equals($expectedSignature, $signature)) {
        errorResponse('Invalid payment signature', 400);
    }
} elseif (!str_starts_with($orderId, 'order_mock_')) {
    // In mock mode, only allow mock order IDs
    errorResponse('Payment verification failed (mock mode)', 400);
}

// Fetch order amount from Razorpay or derive from mock
$amount = 0.0;
if ($razorpaySecret) {
    $rzpKey = getenv('RAZORPAY_KEY_ID') ?: '';
    $ch = curl_init("https://api.razorpay.com/v1/orders/$orderId");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => "$rzpKey:$razorpaySecret",
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        errorResponse('Could not fetch order details from Razorpay', 502);
    }
    $orderDetails = json_decode($resp, true);
    $amount = (float) $orderDetails['amount'] / 100; // convert paise -> INR
} else {
    // Mock: parse amount from a stored session or just use body
    $amount = isset($body['amount']) ? (float) $body['amount'] : 100.0;
}

if ($amount <= 0) {
    errorResponse('Invalid payment amount');
}

$pdo = Database::getInstance()->getConnection();

try {
    $pdo->beginTransaction();

    // Idempotency: reject duplicate payment_ids
    $stmtCheck = $pdo->prepare(
        "SELECT id FROM wallet_transactions
         WHERE ref_type = :ref_type AND type = 'credit' LIMIT 1"
    );
    $stmtCheck->execute([':ref_type' => 'razorpay:' . $paymentId]);
    if ($stmtCheck->fetch()) {
        $pdo->rollBack();
        errorResponse('Payment already processed', 409);
    }

    // Credit wallet
    $stmtCredit = $pdo->prepare(
        'UPDATE wallets SET balance = balance + :amount WHERE user_id = :uid'
    );
    $stmtCredit->execute([':amount' => $amount, ':uid' => $userId]);

    $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + :amount WHERE id = :uid')
        ->execute([':amount' => $amount, ':uid' => $userId]);

    // Log transaction – store payment_id in ref_type for traceability
    $pdo->prepare(
        "INSERT INTO wallet_transactions (user_id, amount, type, ref_type)
         VALUES (:uid, :amount, 'credit', :ref_type)"
    )->execute([
        ':uid'      => $userId,
        ':amount'   => $amount,
        ':ref_type' => 'razorpay:' . $paymentId,
    ]);

    $pdo->commit();

    successResponse([
        'credited_amount' => $amount,
        'payment_id'      => $paymentId,
    ], 'Wallet recharged successfully');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('recharge_verify error: ' . $e->getMessage());
    errorResponse('Recharge failed', 500);
}
