<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;

class ApiKeyController
{
    /**
     * List all API keys for authenticated user
     */
    public function list(): void
    {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('User ID not found in token', 401);
            return;
        }

        try {
            $sql = "SELECT id, key, name, created_at, revoked
                    FROM api_keys
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC";

            $keys = Database::fetchAll($sql, ['user_id' => $userId]);

            // Mask the keys for security (show only first 8 and last 4 characters)
            foreach ($keys as &$key) {
                if (!$key['revoked']) {
                    $fullKey = $key['key'];
                    $key['key'] = substr($fullKey, 0, 8) . '...' . substr($fullKey, -4);
                }
            }

            Response::success([
                'api_keys' => $keys,
                'total' => count($keys)
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list API keys', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to list API keys', 500);
        }
    }

    /**
     * Create a new API key for authenticated user
     */
    public function create(): void
    {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('User ID not found in token', 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $name = $input['name'] ?? 'API Key';

        try {
            // Generate a secure random API key
            $apiKey = 'vb_' . bin2hex(random_bytes(32)); // vb_<64 hex chars>

            // Insert into database
            $keyId = Database::insert('api_keys', [
                'user_id' => $userId,
                'key' => $apiKey,
                'name' => $name,
                'revoked' => false
            ]);

            Logger::info('API key created', [
                'user_id' => $userId,
                'key_id' => $keyId,
                'name' => $name
            ]);

            Response::success([
                'api_key' => $apiKey,
                'key_id' => $keyId,
                'name' => $name,
                'message' => 'API key created successfully. Save this key - it will not be shown again.'
            ], 201);

        } catch (\Exception $e) {
            Logger::error('Failed to create API key', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to create API key', 500);
        }
    }

    /**
     * Revoke an API key
     */
    public function revoke(string $keyId): void
    {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('User ID not found in token', 401);
            return;
        }

        try {
            // Verify the key belongs to this user
            $sql = "SELECT id FROM api_keys WHERE id = :key_id AND user_id = :user_id";
            $key = Database::fetch($sql, [
                'key_id' => $keyId,
                'user_id' => $userId
            ]);

            if (!$key) {
                Response::error('API key not found', 404);
                return;
            }

            // Revoke the key
            $updated = Database::update('api_keys',
                ['revoked' => true],
                ['id' => $keyId, 'user_id' => $userId]
            );

            if ($updated > 0) {
                Logger::info('API key revoked', [
                    'user_id' => $userId,
                    'key_id' => $keyId
                ]);

                Response::success([
                    'message' => 'API key revoked successfully',
                    'key_id' => $keyId
                ]);
            } else {
                Response::error('Failed to revoke API key', 500);
            }

        } catch (\Exception $e) {
            Logger::error('Failed to revoke API key', [
                'user_id' => $userId,
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to revoke API key', 500);
        }
    }
}
