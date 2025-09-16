<?php
namespace VeriBits\Utils;

class Jwt {
    private static array $blacklist = [];

    public static function sign(array $payload, string $secret, string $alg = 'HS256'): string {
        if (empty($secret) || $secret === 'dev-secret') {
            throw new \InvalidArgumentException('JWT secret must be properly configured');
        }

        $header = ['typ' => 'JWT', 'alg' => $alg];

        $now = time();
        $payload = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'jti' => bin2hex(random_bytes(16))
        ], $payload);

        $segments = [
            self::b64(json_encode($header)),
            self::b64(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::b64($signature);

        Logger::debug('JWT token created', ['sub' => $payload['sub'] ?? 'unknown', 'jti' => $payload['jti']]);

        return implode('.', $segments);
    }

    public static function verify(string $jwt, string $secret): ?array {
        if (empty($secret) || $secret === 'dev-secret') {
            Logger::security('JWT verification attempted with weak secret');
            return null;
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            Logger::security('Invalid JWT format received');
            return null;
        }

        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', $h.'.'.$p, $secret, true));

        if (!hash_equals($expected, $s)) {
            Logger::security('JWT signature verification failed');
            return null;
        }

        $payload = json_decode(self::ub64($p), true);
        if (!is_array($payload)) {
            Logger::security('Invalid JWT payload format');
            return null;
        }

        $now = time();

        if (isset($payload['exp']) && $now > $payload['exp']) {
            Logger::info('JWT token expired', ['jti' => $payload['jti'] ?? 'unknown']);
            return null;
        }

        if (isset($payload['nbf']) && $now < $payload['nbf']) {
            Logger::security('JWT token not yet valid');
            return null;
        }

        if (isset($payload['jti']) && self::isBlacklisted($payload['jti'])) {
            Logger::security('JWT token is blacklisted', ['jti' => $payload['jti']]);
            return null;
        }

        return $payload;
    }

    public static function blacklistToken(string $jti): void {
        if (!empty($jti)) {
            self::$blacklist[$jti] = time();

            try {
                $redis = Redis::connect();
                $redis->setex("jwt_blacklist:$jti", 86400, time());
            } catch (\Exception $e) {
                Logger::warning('Failed to blacklist token in Redis', ['jti' => $jti, 'error' => $e->getMessage()]);
            }

            Logger::info('JWT token blacklisted', ['jti' => $jti]);
        }
    }

    private static function isBlacklisted(string $jti): bool {
        if (isset(self::$blacklist[$jti])) {
            return true;
        }

        try {
            $redis = Redis::connect();
            return $redis->exists("jwt_blacklist:$jti");
        } catch (\Exception $e) {
            Logger::warning('Failed to check token blacklist in Redis', ['jti' => $jti]);
            return false;
        }
    }

    private static function b64(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function ub64(string $enc): string {
        return base64_decode(strtr($enc, '-_', '+/'));
    }
}
