<?php
namespace VeriBits\Utils;

class Jwt {
    public static function sign(array $payload, string $secret, string $alg = 'HS256'): string {
        $header = ['typ' => 'JWT', 'alg' => $alg];
        $segments = [
            self::b64(json_encode($header)),
            self::b64(json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = self::b64($signature);
        return implode('.', $segments);
    }

    public static function verify(string $jwt, string $secret): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $expected = self::b64(hash_hmac('sha256', $h.'.'.$p, $secret, true));
        if (!hash_equals($expected, $s)) return null;
        $payload = json_decode(self::ub64($p), true);
        if (!is_array($payload)) return null;
        if (isset($payload['exp']) && time() > $payload['exp']) return null;
        return $payload;
    }

    private static function b64(string $raw): string {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
    private static function ub64(string $enc): string {
        return base64_decode(strtr($enc, '-_', '+/'));
    }
}
