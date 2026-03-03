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

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$amount = isset($body['amount']) ? (float) $body['amount'] : 0;

if ($amount < 1) {
    errorResponse('Minimum recharge amount is ₹1');
}

$razorpayKey    = getenv('RAZORPAY_KEY_ID') ?: '';
$razorpaySecret = getenv('RAZORPAY_KEY_SECRET') ?: '';

if ($razorpayKey && $razorpaySecret) {
    // Real Razorpay order creation
    $orderData = [
        'amount'   => (int) round($amount * 100), // paise
        'currency' => 'INR',
        'receipt'  => 'wallet_' . $userId . '_' . time(),
        'notes'    => ['user_id' => $userId],
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($orderData),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => "$razorpayKey:$razorpaySecret",
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        errorResponse('Failed to create Razorpay order', 502);
    }

    $rzpOrder = json_decode($response, true);
    successResponse([
        'order_id'  => $rzpOrder['id'],
        'amount'    => $amount,
        'currency'  => 'INR',
        'key_id'    => $razorpayKey,
    ], 'Order created');
} else {
    // Mock order for development / testing
    $mockOrderId = 'order_mock_' . bin2hex(random_bytes(8));
    successResponse([
        'order_id'  => $mockOrderId,
        'amount'    => $amount,
        'currency'  => 'INR',
        'mock'      => true,
    ], 'Mock order created (no Razorpay keys set)');
}
