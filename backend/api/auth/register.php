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
$name     = trim($body['name']     ?? '');
$phone    = trim($body['phone']    ?? '');
$email    = trim($body['email']    ?? '');
$password = $body['password']      ?? '';
$role     = trim($body['role']     ?? 'astrologer');

if (!$name || !$phone || !$password) {
    errorResponse('name, phone and password are required');
}
if (!preg_match('/^\+?[0-9]{7,15}$/', $phone)) {
    errorResponse('Invalid phone number format');
}
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('Invalid email format');
}
if (!in_array($role, ['astrologer', 'admin'], true)) {
    $role = 'astrologer';
}
if (strlen($password) < 8) {
    errorResponse('Password must be at least 8 characters');
}

$pdo = Database::getInstance()->getConnection();

// Check phone/email uniqueness
$stmtCheck = $pdo->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
$stmtCheck->execute([':phone' => $phone]);
if ($stmtCheck->fetch()) {
    errorResponse('Phone number already registered', 409);
}
if ($email) {
    $stmtEmailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmtEmailCheck->execute([':email' => $email]);
    if ($stmtEmailCheck->fetch()) {
        errorResponse('Email already registered', 409);
    }
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $pdo->beginTransaction();

    $stmtUser = $pdo->prepare(
        'INSERT INTO users (name, phone, email, password_hash, role, wallet_balance, status)
         VALUES (:name, :phone, :email, :hash, :role, 0.00, "active")'
    );
    $stmtUser->execute([
        ':name'  => $name,
        ':phone' => $phone,
        ':email' => $email ?: null,
        ':hash'  => $hash,
        ':role'  => $role,
    ]);
    $userId = (int) $pdo->lastInsertId();

    // Create wallet entry
    $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (:uid, 0.00)')
        ->execute([':uid' => $userId]);

    $pdo->commit();

    $token = Auth::generateToken($userId, $role);

    successResponse([
        'token' => $token,
        'user'  => [
            'id'    => $userId,
            'name'  => $name,
            'phone' => $phone,
            'email' => $email,
            'role'  => $role,
        ],
    ], 'Registration successful');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('register error: ' . $e->getMessage());
    errorResponse('Registration failed', 500);
}
