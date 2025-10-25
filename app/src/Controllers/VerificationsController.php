<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;

class VerificationsController
{
    /**
     * List verification history for authenticated user
     */
    public function list(): void
    {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!$userId) {
            Response::error('User ID not found in token', 401);
            return;
        }

        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(100, max(1, (int)$_GET['per_page'])) : 20;
        $kind = $_GET['kind'] ?? null; // Optional filter by verification kind
        $offset = ($page - 1) * $perPage;

        try {
            // Build query
            $where = 'WHERE user_id = :user_id';
            $params = ['user_id' => $userId];

            if ($kind) {
                $where .= ' AND kind = :kind';
                $params['kind'] = $kind;
            }

            // Get total count
            $countSql = "SELECT COUNT(*) FROM verifications $where";
            $total = (int)Database::query($countSql, $params)->fetchColumn();

            // Get verifications
            $sql = "SELECT id, kind, input, result, score, created_at
                    FROM verifications
                    $where
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";

            $params['limit'] = $perPage;
            $params['offset'] = $offset;

            $verifications = Database::fetchAll($sql, $params);

            // Decode JSONB fields
            foreach ($verifications as &$verification) {
                if (is_string($verification['input'])) {
                    $verification['input'] = json_decode($verification['input'], true);
                }
                if (is_string($verification['result'])) {
                    $verification['result'] = json_decode($verification['result'], true);
                }
            }

            $totalPages = ceil($total / $perPage);

            Response::json([
                'success' => true,
                'data' => $verifications,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'timestamp' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list verifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to list verifications', 500);
        }
    }
}
