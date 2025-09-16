<?php
namespace VeriBits\Utils;
use VeriBits\Utils\Response;

class Auth {
    public static function requireBearer(): array {
        $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/Bearer\s+(.*)/i', $hdr, $m)) {
            Response::json(['error'=>'missing bearer token'], 401);
            exit;
        }
        $secret = getenv('JWT_SECRET') ?: 'dev-secret';
        $payload = Jwt::verify(trim($m[1]), $secret);
        if (!$payload) {
            Response::json(['error'=>'invalid or expired token'], 401);
            exit;
        }
        return $payload;
    }
}
