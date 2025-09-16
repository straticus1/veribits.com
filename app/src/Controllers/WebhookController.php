<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Services\WebhookService;

class WebhookController {
    private WebhookService $webhookService;

    public function __construct() {
        $this->webhookService = new WebhookService();
    }

    public function register(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('url')->url('url')
                  ->string('events', 0, 1000);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $url = $validator->sanitize('url');
        $events = !empty($body['events']) ? explode(',', $body['events']) : ['*'];

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Response::error('Invalid webhook URL', 400);
            return;
        }

        $parsedUrl = parse_url($url);
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            Response::error('Webhook URL must use HTTP or HTTPS', 400);
            return;
        }

        try {
            $webhookId = $this->webhookService->registerWebhook($userId, $url, $events);

            Logger::info('Webhook registered via API', [
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'url' => $url,
                'events' => $events
            ]);

            Response::success([
                'webhook_id' => $webhookId,
                'url' => $url,
                'events' => $events,
                'secret' => 'Check your webhook endpoint for the secret in headers'
            ], 'Webhook registered successfully');

        } catch (\Exception $e) {
            Logger::error('Webhook registration failed', [
                'user_id' => $userId,
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to register webhook', 500);
        }
    }

    public function list(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        try {
            $webhooks = Database::fetchAll(
                "SELECT id, url, events, active, created_at FROM webhooks WHERE user_id = :user_id ORDER BY created_at DESC",
                ['user_id' => $userId]
            );

            foreach ($webhooks as &$webhook) {
                $webhook['events'] = json_decode($webhook['events'], true);
                $webhook['stats'] = $this->webhookService->getWebhookStats($webhook['id']);
            }

            Response::success([
                'webhooks' => $webhooks,
                'total' => count($webhooks)
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list webhooks', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to retrieve webhooks', 500);
        }
    }

    public function update(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $webhookId = $_GET['id'] ?? null;
        if (!$webhookId) {
            Response::error('Webhook ID required', 400);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        if (isset($body['url'])) {
            $validator->url('url');
        }

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        try {
            $webhook = Database::fetch(
                "SELECT * FROM webhooks WHERE id = :id AND user_id = :user_id",
                ['id' => $webhookId, 'user_id' => $userId]
            );

            if (!$webhook) {
                Response::error('Webhook not found', 404);
                return;
            }

            $updateData = [];
            if (isset($body['url'])) {
                $updateData['url'] = $validator->sanitize('url');
            }
            if (isset($body['events'])) {
                $events = is_array($body['events']) ? $body['events'] : explode(',', $body['events']);
                $updateData['events'] = json_encode($events);
            }
            if (isset($body['active'])) {
                $updateData['active'] = (bool)$body['active'];
            }

            if (!empty($updateData)) {
                Database::update('webhooks', $updateData, ['id' => $webhookId, 'user_id' => $userId]);
            }

            Logger::info('Webhook updated', [
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'updates' => array_keys($updateData)
            ]);

            Response::success([], 'Webhook updated successfully');

        } catch (\Exception $e) {
            Logger::error('Webhook update failed', [
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to update webhook', 500);
        }
    }

    public function delete(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $webhookId = $_GET['id'] ?? null;
        if (!$webhookId) {
            Response::error('Webhook ID required', 400);
            return;
        }

        try {
            $deleted = Database::delete('webhooks', [
                'id' => $webhookId,
                'user_id' => $userId
            ]);

            if ($deleted === 0) {
                Response::error('Webhook not found', 404);
                return;
            }

            Logger::info('Webhook deleted', [
                'user_id' => $userId,
                'webhook_id' => $webhookId
            ]);

            Response::success([], 'Webhook deleted successfully');

        } catch (\Exception $e) {
            Logger::error('Webhook deletion failed', [
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to delete webhook', 500);
        }
    }

    public function test(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        $webhookId = $_GET['id'] ?? null;
        if (!$webhookId) {
            Response::error('Webhook ID required', 400);
            return;
        }

        try {
            $webhook = Database::fetch(
                "SELECT * FROM webhooks WHERE id = :id AND user_id = :user_id AND active = true",
                ['id' => $webhookId, 'user_id' => $userId]
            );

            if (!$webhook) {
                Response::error('Active webhook not found', 404);
                return;
            }

            $this->webhookService->deliverEvent('webhook.test', [
                'message' => 'This is a test webhook delivery',
                'webhook_id' => $webhookId,
                'timestamp' => date('c')
            ], $userId);

            Logger::info('Test webhook triggered', [
                'user_id' => $userId,
                'webhook_id' => $webhookId
            ]);

            Response::success([], 'Test webhook queued for delivery');

        } catch (\Exception $e) {
            Logger::error('Test webhook failed', [
                'user_id' => $userId,
                'webhook_id' => $webhookId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to test webhook', 500);
        }
    }
}
