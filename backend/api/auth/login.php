<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../config/auth.php';
require_once __DIR__ . '/../../config/database.php';

corsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$login    = trim($body['phone'] ?? $body['email'] ?? '');
$password = $body['password'] ?? '';

if (!$login || !$password) {
    errorResponse('phone/email and password are required');
}

$pdo = Database::getInstance()->getConnection();

// Find user by phone or email
$stmt = $pdo->prepare(
    'SELECT id, name, phone, email, password_hash, role, wallet_balance, status
     FROM users
     WHERE (phone = :login OR email = :login)
     LIMIT 1'
);
$stmt->execute([':login' => $login]);
$user = $stmt->fetch();

if (!$user) {
    errorResponse('Invalid credentials', 401);
}
if ($user['status'] !== 'active') {
    errorResponse('Account is suspended or not active', 403);
}
if (!password_verify($password, $user['password_hash'])) {
    errorResponse('Invalid credentials', 401);
}

$token = Auth::generateToken((int) $user['id'], $user['role']);

successResponse([
    'token' => $token,
    'user'  => [
        'id'             => (int) $user['id'],
        'name'           => $user['name'],
        'phone'          => $user['phone'],
        'email'          => $user['email'],
        'role'           => $user['role'],
        'wallet_balance' => (float) $user['wallet_balance'],
    ],
], 'Login successful');
