#!/usr/bin/env php
<?php
// © After Dark Systems
// Webhook Worker - Processes webhook deliveries from the queue

declare(strict_types=1);

require_once __DIR__ . '/../app/public/index.php';

use VeriBits\Services\WebhookService;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;

class WebhookWorker {
    private WebhookService $webhookService;
    private bool $running = true;

    public function __construct() {
        $this->webhookService = new WebhookService();

        // Handle graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
    }

    public function run(): void {
        Logger::info('Webhook worker started');

        while ($this->running) {
            try {
                $this->webhookService->processWebhookQueue();

                // Check for signals
                pcntl_signal_dispatch();

                // Sleep for 5 seconds before next iteration
                sleep(5);
            } catch (\Exception $e) {
                Logger::error('Webhook worker error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                // Sleep longer on error to avoid rapid failures
                sleep(30);
            }
        }

        Logger::info('Webhook worker stopped');
    }

    public function shutdown(): void {
        Logger::info('Webhook worker received shutdown signal');
        $this->running = false;
    }
}

// Run the worker
$worker = new WebhookWorker();
$worker->run();