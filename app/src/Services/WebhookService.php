<?php
namespace VeriBits\Services;

use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Redis;

class WebhookService {
    private const MAX_RETRIES = 3;
    private const TIMEOUT = 10;

    public function registerWebhook(string $userId, string $url, array $events = ['*']): string {
        $secret = bin2hex(random_bytes(32));

        $webhookId = Database::insert('webhooks', [
            'user_id' => $userId,
            'url' => $url,
            'secret' => $secret,
            'events' => json_encode($events),
            'active' => true
        ]);

        Logger::info('Webhook registered', [
            'user_id' => $userId,
            'webhook_id' => $webhookId,
            'url' => $url
        ]);

        return $webhookId;
    }

    public function deliverEvent(string $eventType, array $payload, string $userId = null): void {
        $webhooks = $this->getActiveWebhooks($userId, $eventType);

        foreach ($webhooks as $webhook) {
            $this->queueWebhookDelivery($webhook, $eventType, $payload);
        }
    }

    public function processWebhookQueue(): void {
        $pendingEvents = Database::fetchAll(
            "SELECT we.*, w.url, w.secret
             FROM webhook_events we
             JOIN webhooks w ON we.webhook_id = w.id
             WHERE we.delivered = false AND w.active = true
             ORDER BY we.created_at
             LIMIT 100"
        );

        foreach ($pendingEvents as $event) {
            $this->deliverWebhookEvent($event);
        }
    }

    private function getActiveWebhooks(string $userId = null, string $eventType = null): array {
        $sql = "SELECT * FROM webhooks WHERE active = true";
        $params = [];

        if ($userId) {
            $sql .= " AND user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $webhooks = Database::fetchAll($sql, $params);

        return array_filter($webhooks, function($webhook) use ($eventType) {
            $events = json_decode($webhook['events'], true) ?? ['*'];
            return in_array('*', $events) || in_array($eventType, $events);
        });
    }

    private function queueWebhookDelivery(array $webhook, string $eventType, array $payload): void {
        $eventData = [
            'webhook_id' => $webhook['id'],
            'event_type' => $eventType,
            'payload' => json_encode([
                'event' => $eventType,
                'data' => $payload,
                'timestamp' => date('c'),
                'webhook_id' => $webhook['id']
            ]),
            'delivered' => false
        ];

        Database::insert('webhook_events', $eventData);

        Logger::debug('Webhook event queued', [
            'webhook_id' => $webhook['id'],
            'event_type' => $eventType
        ]);
    }

    private function deliverWebhookEvent(array $event): void {
        $payload = $event['payload'];
        $signature = $this->generateSignature($payload, $event['secret']);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: VeriBits-Webhook/1.0',
            'X-VeriBits-Event: ' . $event['event_type'],
            'X-VeriBits-Signature: ' . $signature,
            'X-VeriBits-Delivery: ' . $event['id']
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true
            ]
        ]);

        $startTime = microtime(true);
        $response = @file_get_contents($event['url'], false, $context);
        $duration = microtime(true) - $startTime;

        $httpCode = 0;
        if (isset($http_response_header)) {
            $statusLine = $http_response_header[0] ?? '';
            preg_match('/\d{3}/', $statusLine, $matches);
            $httpCode = (int)($matches[0] ?? 0);
        }

        $success = $response !== false && $httpCode >= 200 && $httpCode < 300;

        if ($success) {
            Database::update('webhook_events', [
                'delivered' => true,
                'delivered_at' => date('Y-m-d H:i:s'),
                'response_code' => $httpCode,
                'response_time_ms' => round($duration * 1000)
            ], ['id' => $event['id']]);

            Logger::info('Webhook delivered successfully', [
                'webhook_id' => $event['webhook_id'],
                'event_id' => $event['id'],
                'url' => $event['url'],
                'response_code' => $httpCode,
                'duration_ms' => round($duration * 1000)
            ]);
        } else {
            $this->handleWebhookFailure($event, $httpCode, $response);
        }
    }

    private function handleWebhookFailure(array $event, int $httpCode, $response): void {
        $retryCount = $this->getRetryCount($event['id']);

        if ($retryCount < self::MAX_RETRIES) {
            $this->scheduleRetry($event['id'], $retryCount + 1);

            Logger::warning('Webhook delivery failed, will retry', [
                'webhook_id' => $event['webhook_id'],
                'event_id' => $event['id'],
                'url' => $event['url'],
                'response_code' => $httpCode,
                'retry_count' => $retryCount + 1
            ]);
        } else {
            Database::update('webhook_events', [
                'delivered' => true,
                'delivered_at' => date('Y-m-d H:i:s'),
                'response_code' => $httpCode,
                'error' => substr((string)$response, 0, 1000)
            ], ['id' => $event['id']]);

            $this->disableFailingWebhook($event['webhook_id']);

            Logger::error('Webhook delivery failed permanently', [
                'webhook_id' => $event['webhook_id'],
                'event_id' => $event['id'],
                'url' => $event['url'],
                'response_code' => $httpCode,
                'final_retry' => true
            ]);
        }
    }

    private function getRetryCount(string $eventId): int {
        $key = "webhook_retry:$eventId";
        return (int)Redis::get($key) ?: 0;
    }

    private function scheduleRetry(string $eventId, int $retryCount): void {
        $key = "webhook_retry:$eventId";
        $delay = min(300, pow(2, $retryCount) * 10); // Exponential backoff, max 5 minutes

        Redis::set($key, (string)$retryCount, $delay);

        Logger::debug('Webhook retry scheduled', [
            'event_id' => $eventId,
            'retry_count' => $retryCount,
            'delay_seconds' => $delay
        ]);
    }

    private function disableFailingWebhook(string $webhookId): void {
        $failureCount = $this->getWebhookFailureCount($webhookId);

        if ($failureCount >= 10) { // Disable after 10 consecutive failures
            Database::update('webhooks', ['active' => false], ['id' => $webhookId]);

            Logger::warning('Webhook disabled due to repeated failures', [
                'webhook_id' => $webhookId,
                'failure_count' => $failureCount
            ]);
        }
    }

    private function getWebhookFailureCount(string $webhookId): int {
        $key = "webhook_failures:$webhookId";
        $count = Redis::increment($key);
        Redis::expire($key, 86400); // Reset count after 24 hours
        return $count;
    }

    private function generateSignature(string $payload, string $secret): string {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    public function verifySignature(string $payload, string $signature, string $secret): bool {
        $expectedSignature = $this->generateSignature($payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function getWebhookStats(string $webhookId): array {
        $sql = "SELECT
                    COUNT(*) as total_events,
                    SUM(CASE WHEN delivered = true THEN 1 ELSE 0 END) as delivered_events,
                    AVG(response_time_ms) as avg_response_time,
                    MAX(delivered_at) as last_delivery
                FROM webhook_events
                WHERE webhook_id = :webhook_id";

        return Database::fetch($sql, ['webhook_id' => $webhookId]) ?? [
            'total_events' => 0,
            'delivered_events' => 0,
            'avg_response_time' => 0,
            'last_delivery' => null
        ];
    }
}