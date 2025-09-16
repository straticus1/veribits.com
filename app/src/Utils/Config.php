<?php
namespace VeriBits\Utils;

class Config {
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) return;

        $envFile = __DIR__ . '/../../config/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                [$key, $value] = explode('=', $line, 2);
                self::$config[trim($key)] = trim($value);
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        self::load();
        return $_ENV[$key] ?? self::$config[$key] ?? $default;
    }

    public static function getRequired(string $key): string {
        $value = self::get($key);
        if (empty($value)) {
            throw new \RuntimeException("Required configuration key '$key' is missing");
        }
        return $value;
    }

    public static function getBool(string $key, bool $default = false): bool {
        $value = strtolower(self::get($key));
        if ($value === '') return $default;
        return in_array($value, ['true', '1', 'yes', 'on']);
    }

    public static function getInt(string $key, int $default = 0): int {
        $value = self::get($key);
        return $value === '' ? $default : (int)$value;
    }

    public static function isProduction(): bool {
        return self::get('APP_ENV') === 'production';
    }

    public static function isDevelopment(): bool {
        return self::get('APP_ENV') === 'local' || self::get('APP_ENV') === 'development';
    }
}