<?php
declare(strict_types=1);

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function errorResponse(string $message, int $status = 400): never
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

function successResponse(mixed $data = [], string $message = 'Success'): never
{
    jsonResponse(['success' => true, 'message' => $message, 'data' => $data]);
}

function corsHeaders(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}
