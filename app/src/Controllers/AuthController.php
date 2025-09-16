<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Jwt;

class AuthController {
    public function token(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $user = $body['user'] ?? 'anonymous';
        $token  = Jwt::sign([
            'sub' => $user,
            'scopes' => ['verify:*'],
            'exp' => time() + 3600
        ], getenv('JWT_SECRET') ?: 'dev-secret');
        Response::json([
            'api_key' => bin2hex(random_bytes(16)),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600
        ]);
    }
}
