<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_token'])) {
    header('Location: /admin/index.php');
    exit;
}

// Generate CSRF token if absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Call backend API.
 */
function apiCall(string $method, string $endpoint, array $data = [], bool $auth = true): array
{
    $baseUrl = getenv('API_BASE_URL') ?: 'http://localhost/api';
    $ch = curl_init($baseUrl . $endpoint);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($auth) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['admin_token'];
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

/**
 * Upload a file via multipart form to the backend.
 */
function apiUpload(string $endpoint, string $filePath, string $fieldName = 'csv'): array
{
    $baseUrl = getenv('API_BASE_URL') ?: 'http://localhost/api';
    $ch = curl_init($baseUrl . $endpoint);
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $_SESSION['admin_token'],
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [$fieldName => new CURLFile($filePath)]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?? [];
}

/**
 * Validate CSRF token from POST.
 */
function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}

/**
 * Return admin's display initial.
 */
function adminInitial(): string
{
    $name = $_SESSION['admin_name'] ?? 'A';
    return strtoupper(substr($name, 0, 1));
}

/**
 * Format currency in INR.
 */
function formatInr(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

/**
 * Return badge HTML for a quality score integer.
 */
function qualityBadge(int $score, string $badgeStr = ''): string
{
    if ($badgeStr === '') {
        if ($score >= 80) $badgeStr = 'Premium';
        elseif ($score >= 60) $badgeStr = 'Verified';
        else $badgeStr = 'Basic';
    }
    $cls = 'badge-' . strtolower($badgeStr);
    return "<span class=\"badge {$cls}\">" . htmlspecialchars($badgeStr) . "</span>";
}

/**
 * Return status pill HTML.
 */
function statusPill(string $status): string
{
    $cls = 'status-' . strtolower($status);
    return "<span class=\"badge {$cls}\">" . htmlspecialchars(ucfirst($status)) . "</span>";
}
