<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Database;
use VeriBits\Utils\Redis;
use VeriBits\Utils\Logger;

class HealthController {
    public function status(): void {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'filesystem' => $this->checkFilesystem(),
            'php_extensions' => $this->checkPHPExtensions()
        ];

        $allHealthy = !in_array(false, array_column($checks, 'healthy'), true);

        $response = [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'service' => 'veribits',
            'time' => gmdate('c'),
            'checks' => $checks
        ];

        // Return 503 if any critical checks fail
        $code = $allHealthy ? 200 : 503;
        Response::json($response, $code);
    }

    private function checkDatabase(): array {
        try {
            $start = microtime(true);
            $db = Database::connect();
            $stmt = $db->query('SELECT 1');
            $result = $stmt->fetchColumn();
            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($result == 1) {
                return [
                    'healthy' => true,
                    'message' => 'Database connection OK',
                    'response_time_ms' => $duration
                ];
            }

            return [
                'healthy' => false,
                'message' => 'Database query returned unexpected result',
                'response_time_ms' => $duration
            ];
        } catch (\Exception $e) {
            Logger::error('Health check: Database failed', ['error' => $e->getMessage()]);
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkRedis(): array {
        if (!class_exists('Redis')) {
            return [
                'healthy' => true, // Not critical if Redis not available
                'message' => 'Redis extension not installed (optional)',
                'available' => false
            ];
        }

        try {
            $start = microtime(true);
            $redis = Redis::connect();
            $testKey = 'health_check_' . time();
            $redis->setex($testKey, 10, 'ok');
            $value = $redis->get($testKey);
            $redis->del($testKey);
            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($value === 'ok') {
                return [
                    'healthy' => true,
                    'message' => 'Redis connection OK',
                    'response_time_ms' => $duration,
                    'available' => true
                ];
            }

            return [
                'healthy' => false,
                'message' => 'Redis test failed',
                'response_time_ms' => $duration,
                'available' => true
            ];
        } catch (\Exception $e) {
            Logger::warning('Health check: Redis failed', ['error' => $e->getMessage()]);
            return [
                'healthy' => true, // Not critical
                'message' => 'Redis unavailable (non-critical): ' . $e->getMessage(),
                'available' => false
            ];
        }
    }

    private function checkFilesystem(): array {
        $testDirs = [
            '/var/www/logs' => 'Logs directory',
            '/tmp/veribits-scans' => 'Scans directory',
            '/tmp/veribits-archives' => 'Archives directory'
        ];

        $results = [];
        $allHealthy = true;

        foreach ($testDirs as $dir => $description) {
            $testFile = $dir . '/health_check_' . time() . '.tmp';

            if (!is_dir($dir)) {
                $results[$description] = [
                    'healthy' => false,
                    'message' => 'Directory does not exist',
                    'path' => $dir
                ];
                $allHealthy = false;
                continue;
            }

            if (!is_writable($dir)) {
                $results[$description] = [
                    'healthy' => false,
                    'message' => 'Directory not writable',
                    'path' => $dir
                ];
                $allHealthy = false;
                continue;
            }

            try {
                file_put_contents($testFile, 'test');
                if (file_exists($testFile)) {
                    unlink($testFile);
                    $results[$description] = [
                        'healthy' => true,
                        'message' => 'Read/write OK',
                        'path' => $dir
                    ];
                } else {
                    $results[$description] = [
                        'healthy' => false,
                        'message' => 'File write failed',
                        'path' => $dir
                    ];
                    $allHealthy = false;
                }
            } catch (\Exception $e) {
                $results[$description] = [
                    'healthy' => false,
                    'message' => 'Filesystem error: ' . $e->getMessage(),
                    'path' => $dir
                ];
                $allHealthy = false;
            }
        }

        return [
            'healthy' => $allHealthy,
            'message' => $allHealthy ? 'All filesystem checks passed' : 'Some filesystem checks failed',
            'directories' => $results
        ];
    }

    private function checkPHPExtensions(): array {
        $required = ['pdo', 'pdo_pgsql', 'zip', 'json'];
        $optional = ['redis', 'opcache', 'curl'];

        $missing = [];
        $available = [];

        foreach ($required as $ext) {
            if (extension_loaded($ext)) {
                $available[] = $ext;
            } else {
                $missing[] = $ext;
            }
        }

        $optionalAvailable = [];
        foreach ($optional as $ext) {
            if (extension_loaded($ext)) {
                $optionalAvailable[] = $ext;
            }
        }

        return [
            'healthy' => empty($missing),
            'message' => empty($missing) ? 'All required extensions loaded' : 'Missing required extensions',
            'required' => $available,
            'missing' => $missing,
            'optional' => $optionalAvailable
        ];
    }
}
