<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/response.php';

/**
 * Pure PHP HS256 JWT – no external library required.
 */
class Auth
{
    private static function secret(): string
    {
        $secret = getenv('JWT_SECRET');
        if (!$secret) {
            throw new \RuntimeException('JWT_SECRET environment variable is not set. Refusing to generate insecure tokens.');
        }
        return $secret;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode($padded);
    }

    public static function generateToken(int $userId, string $role): string
    {
        $header  = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::base64UrlEncode(json_encode([
            'sub'  => $userId,
            'role' => $role,
            'iat'  => time(),
            'exp'  => time() + 86400 * 7,   // 7-day expiry
        ]));
        $sig = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", self::secret(), true));
        return "$header.$payload.$sig";
    }

    /**
     * Validates the Bearer token from the Authorization header.
     *
     * @return array{sub:int, role:string, iat:int, exp:int}|null
     */
    public static function validateToken(): ?array
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            return null;
        }
        $parts = explode('.', $m[1]);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $sig] = $parts;
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", self::secret(), true)
        );
        // Constant-time compare to prevent timing attacks
        if (!hash_equals($expectedSig, $sig)) {
            return null;
        }
        $data = json_decode(self::base64UrlDecode($payload), true);
        if (!$data || !isset($data['exp']) || $data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    /**
     * Validates auth or sends 401 and exits.
     *
     * @return array{sub:int, role:string, iat:int, exp:int}
     */
    public static function requireAuth(): array
    {
        $payload = self::validateToken();
        if (!$payload) {
            errorResponse('Unauthorized', 401);
        }
        return $payload;
    }

    /**
     * Validates auth + admin role or exits with 403.
     *
     * @return array{sub:int, role:string, iat:int, exp:int}
     */
    public static function requireAdmin(): array
    {
        $payload = self::requireAuth();
        if ($payload['role'] !== 'admin') {
            errorResponse('Forbidden: admin only', 403);
        }
        return $payload;
    }
}
