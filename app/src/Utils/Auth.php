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

    /**
     * Optional authentication - allows anonymous users but enforces rate limits
     * Returns ['authenticated' => false, 'user_id' => null] for anonymous users
     */
    public static function optionalAuth(): array {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        // Check if bearer token is present
        if (preg_match('/Bearer\s+(.*)/i', $hdr, $m)) {
            $token = trim($m[1]);
            $secret = Config::getRequired('JWT_SECRET');
            $payload = Jwt::verify($token, $secret);

            if ($payload) {
                // Valid token - user is authenticated
                Logger::debug('Authenticated request', ['user_id' => $payload['sub'] ?? 'unknown']);
                return [
                    'authenticated' => true,
                    'user_id' => $payload['sub'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'claims' => $payload
                ];
            }
        }

        // Check API key
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
        if ($apiKey) {
            $keyData = self::validateApiKey($apiKey);
            if ($keyData) {
                Logger::debug('Authenticated via API key', ['user_id' => $keyData['user_id']]);
                return [
                    'authenticated' => true,
                    'user_id' => $keyData['user_id'],
                    'email' => $keyData['email'] ?? null,
                    'api_key_data' => $keyData
                ];
            }
        }

        // Anonymous user - check rate limits
        $clientIp = RateLimit::getClientIp();
        $limitCheck = RateLimit::checkAnonymous($clientIp);

        if (!$limitCheck['allowed']) {
            Logger::security('Anonymous rate limit exceeded', [
                'ip' => $clientIp,
                'reason' => $limitCheck['reason']
            ]);
            Response::json([
                'error' => 'Rate limit exceeded',
                'message' => $limitCheck['reason'] === 'hourly_limit_exceeded'
                    ? "You have exceeded the hourly limit of {$limitCheck['limit']} requests. Please register for higher limits."
                    : "You have exceeded the daily limit of {$limitCheck['limit']} requests. Please register for higher limits.",
                'limit' => $limitCheck['limit'],
                'reset_in_seconds' => $limitCheck['reset_in'],
                'upgrade_message' => 'Create a free account to get 100 requests per day and 1000 per month.'
            ], 429);
            exit;
        }

        Logger::debug('Anonymous request', [
            'ip' => $clientIp,
            'hourly_remaining' => $limitCheck['hourly_remaining'],
            'daily_remaining' => $limitCheck['daily_remaining']
        ]);

        return [
            'authenticated' => false,
            'user_id' => null,
            'ip_address' => $clientIp,
            'rate_limit' => $limitCheck
        ];
    }

    /**
     * Check if a feature/endpoint requires authentication
     */
    public static function requiresAuth(string $endpoint): bool {
        $authRequired = [
            '/api/v1/verify/malware',
            '/api/v1/inspect/archive',
            '/api/v1/verify/id',
            '/api/v1/verify/file-signature',
            '/api/v1/webhooks',
            '/api/v1/billing',
            '/api/v1/auth/profile',
            '/api/v1/auth/logout'
        ];

        foreach ($authRequired as $pattern) {
            if (str_starts_with($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private static function getClientIp(): string {
        return RateLimit::getClientIp();
    }
}
