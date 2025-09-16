<?php
namespace VeriBits\Utils;

class Logger {
    private static string $logPath = '';

    public static function init(): void {
        if (self::$logPath === '') {
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            self::$logPath = $logDir . '/app.log';
        }
    }

    public static function log(string $level, string $message, array $context = []): void {
        self::init();

        $timestamp = date('Y-m-d H:i:s');
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ip = self::getClientIp();

        $logData = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'message' => $message,
            'request_id' => $requestId,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'context' => $context
        ];

        $logLine = json_encode($logData, JSON_UNESCAPED_SLASHES) . "\n";

        error_log($logLine, 3, self::$logPath);

        if (Config::isDevelopment()) {
            error_log("[$level] $message " . json_encode($context));
        }
    }

    public static function debug(string $message, array $context = []): void {
        if (Config::isDevelopment()) {
            self::log('debug', $message, $context);
        }
    }

    public static function info(string $message, array $context = []): void {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log('critical', $message, $context);
    }

    public static function security(string $message, array $context = []): void {
        self::log('security', $message, array_merge($context, [
            'ip' => self::getClientIp(),
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? ''
        ]));
    }

    private static function getClientIp(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}