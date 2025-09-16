<?php
namespace VeriBits\Utils;
use VeriBits\Utils\Response;

class Auth {
    public static function requireBearer(): array {
        $clientIp = self::getClientIp();

        if (!RateLimit::check("auth:$clientIp", 30, 60)) {
            Logger::security('Authentication rate limit exceeded', ['ip' => $clientIp]);
            Response::json(['error' => 'Too many authentication attempts'], 429);
            exit;
        }

        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.*)/i', $hdr, $m)) {
            Logger::security('Missing bearer token', ['ip' => $clientIp]);
            Response::json(['error' => 'Missing bearer token'], 401);
            exit;
        }

        $token = trim($m[1]);
        $secret = Config::getRequired('JWT_SECRET');

        if ($secret === 'dev-secret' || $secret === 'change-this-in-production') {
            Logger::critical('Using default JWT secret in production');
            Response::json(['error' => 'Server configuration error'], 500);
            exit;
        }

        $payload = Jwt::verify($token, $secret);
        if (!$payload) {
            Logger::security('Invalid or expired token', ['ip' => $clientIp]);
            Response::json(['error' => 'Invalid or expired token'], 401);
            exit;
        }

        Logger::debug('Bearer token verified', [
            'sub' => $payload['sub'] ?? 'unknown',
            'jti' => $payload['jti'] ?? 'unknown'
        ]);

        return $payload;
    }

    public static function hashPassword(string $password): string {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }

        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function generateApiKey(): string {
        return 'vb_' . bin2hex(random_bytes(24));
    }

    public static function validateApiKey(string $apiKey): ?array {
        if (!preg_match('/^vb_[a-f0-9]{48}$/', $apiKey)) {
            return null;
        }

        try {
            $sql = "SELECT ak.*, u.email, u.status as user_status
                    FROM api_keys ak
                    JOIN users u ON ak.user_id = u.id
                    WHERE ak.key = :key AND ak.revoked = false AND u.status = 'active'";

            $result = Database::fetch($sql, ['key' => $apiKey]);

            if ($result) {
                Logger::debug('API key validated', ['user_id' => $result['user_id']]);
            } else {
                Logger::security('Invalid API key used', ['key_prefix' => substr($apiKey, 0, 8)]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('API key validation failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public static function requireApiKey(): array {
        $clientIp = self::getClientIp();

        if (!RateLimit::check("api_key:$clientIp", 100, 60)) {
            Logger::security('API key rate limit exceeded', ['ip' => $clientIp]);
            Response::json(['error' => 'Rate limit exceeded'], 429);
            exit;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

        if (!$apiKey) {
            Logger::security('Missing API key', ['ip' => $clientIp]);
            Response::json(['error' => 'API key required'], 401);
            exit;
        }

        $keyData = self::validateApiKey($apiKey);
        if (!$keyData) {
            Logger::security('Invalid API key', ['ip' => $clientIp, 'key_prefix' => substr($apiKey, 0, 8)]);
            Response::json(['error' => 'Invalid API key'], 401);
            exit;
        }

        return $keyData;
    }

    public static function logout(array $claims): void {
        if (isset($claims['jti'])) {
            Jwt::blacklistToken($claims['jti']);
        }
    }

    private static function getClientIp(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
