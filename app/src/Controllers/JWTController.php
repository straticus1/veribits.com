<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Jwt;

class JWTController
{
    /**
     * Validate JWT token (alias for decode)
     */
    public function validate(): void
    {
        $this->decode();
    }

    /**
     * Decode and validate JWT token
     */
    public function decode(): void
    {
        // Optional auth - supports anonymous users with rate limiting
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429, [
                    'reason' => $scanCheck['reason'],
                    'upgrade_url' => '/pricing.html'
                ]);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        $secret = $input['secret'] ?? '';
        $verifySignature = $input['verify_signature'] ?? false;

        if (empty($token)) {
            Response::error('JWT token is required', 400);
            return;
        }

        try {
            // Split JWT into parts
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new \Exception('Invalid JWT format - must have 3 parts (header.payload.signature)');
            }

            [$headerB64, $payloadB64, $signatureB64] = $parts;

            // Decode header
            $header = json_decode($this->base64UrlDecode($headerB64), true);
            if ($header === null) {
                throw new \Exception('Invalid header encoding');
            }

            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payloadB64), true);
            if ($payload === null) {
                throw new \Exception('Invalid payload encoding');
            }

            // Decode signature (raw bytes)
            $signature = $this->base64UrlDecode($signatureB64);

            $result = [
                'is_valid' => true,
                'header' => $header,
                'payload' => $payload,
                'signature' => $signatureB64,
                'algorithm' => $header['alg'] ?? 'unknown',
                'type' => $header['typ'] ?? 'JWT',
                'claims' => []
            ];

            // Parse standard claims
            if (isset($payload['iss'])) {
                $result['claims']['issuer'] = $payload['iss'];
            }
            if (isset($payload['sub'])) {
                $result['claims']['subject'] = $payload['sub'];
            }
            if (isset($payload['aud'])) {
                $result['claims']['audience'] = $payload['aud'];
            }
            if (isset($payload['exp'])) {
                $result['claims']['expiration'] = date('Y-m-d H:i:s', $payload['exp']);
                $result['claims']['expired'] = time() > $payload['exp'];
            }
            if (isset($payload['nbf'])) {
                $result['claims']['not_before'] = date('Y-m-d H:i:s', $payload['nbf']);
                $result['claims']['not_yet_valid'] = time() < $payload['nbf'];
            }
            if (isset($payload['iat'])) {
                $result['claims']['issued_at'] = date('Y-m-d H:i:s', $payload['iat']);
            }
            if (isset($payload['jti'])) {
                $result['claims']['jwt_id'] = $payload['jti'];
            }

            // Verify signature if requested and secret provided
            if ($verifySignature) {
                if (empty($secret)) {
                    $result['signature_verified'] = false;
                    $result['signature_error'] = 'Secret key required for signature verification';
                } else {
                    try {
                        $verified = Jwt::verify($token, $secret);
                        $result['signature_verified'] = $verified !== false;
                    } catch (\Exception $e) {
                        $result['signature_verified'] = false;
                        $result['signature_error'] = $e->getMessage();
                    }
                }
            }

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('JWT decoded successfully', $result);

        } catch (\Exception $e) {
            Response::error('Invalid JWT: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Sign/Generate new JWT token
     */
    public function sign(): void
    {
        // Optional auth - supports anonymous users with rate limiting
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429, [
                    'reason' => $scanCheck['reason'],
                    'upgrade_url' => '/pricing.html'
                ]);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $payload = $input['payload'] ?? [];
        $secret = $input['secret'] ?? '';
        $algorithm = $input['algorithm'] ?? 'HS256';
        $expiresIn = (int)($input['expires_in'] ?? 3600); // Default 1 hour

        if (empty($secret)) {
            Response::error('Secret key is required', 400);
            return;
        }

        if (empty($payload)) {
            Response::error('Payload is required', 400);
            return;
        }

        try {
            // Add standard claims if not present
            if (!isset($payload['iat'])) {
                $payload['iat'] = time();
            }
            if (!isset($payload['exp']) && $expiresIn > 0) {
                $payload['exp'] = time() + $expiresIn;
            }

            // Generate token
            $token = Jwt::encode($payload, $secret);

            // Decode to show what was created
            $parts = explode('.', $token);
            $header = json_decode($this->base64UrlDecode($parts[0]), true);

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('JWT token generated successfully', [
                'token' => $token,
                'header' => $header,
                'payload' => $payload,
                'algorithm' => $algorithm,
                'expires_in' => $expiresIn,
                'expires_at' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to generate JWT: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
