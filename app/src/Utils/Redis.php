<?php
namespace VeriBits\Utils;

class Redis {
    private static ?\Redis $connection = null;

    public static function connect(): \Redis {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $host = Config::get('REDIS_HOST', 'localhost');
        $port = Config::getInt('REDIS_PORT', 6379);
        $password = Config::get('REDIS_PASSWORD');
        $database = Config::getInt('REDIS_DATABASE', 0);

        try {
            self::$connection = new \Redis();
            self::$connection->connect($host, $port, 2.0);

            if (!empty($password)) {
                self::$connection->auth($password);
            }

            self::$connection->select($database);

            return self::$connection;
        } catch (\RedisException $e) {
            Logger::error('Redis connection failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Redis connection failed: ' . $e->getMessage());
        }
    }

    public static function get(string $key): ?string {
        try {
            $redis = self::connect();
            $value = $redis->get($key);
            return $value === false ? null : $value;
        } catch (\Exception $e) {
            Logger::warning('Redis GET failed', ['key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public static function set(string $key, string $value, int $ttl = 0): bool {
        try {
            $redis = self::connect();
            if ($ttl > 0) {
                return $redis->setex($key, $ttl, $value);
            }
            return $redis->set($key, $value);
        } catch (\Exception $e) {
            Logger::warning('Redis SET failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function delete(string $key): bool {
        try {
            $redis = self::connect();
            return $redis->del($key) > 0;
        } catch (\Exception $e) {
            Logger::warning('Redis DELETE failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function exists(string $key): bool {
        try {
            $redis = self::connect();
            return $redis->exists($key) > 0;
        } catch (\Exception $e) {
            Logger::warning('Redis EXISTS failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function increment(string $key, int $value = 1): int {
        try {
            $redis = self::connect();
            return $redis->incrBy($key, $value);
        } catch (\Exception $e) {
            Logger::warning('Redis INCREMENT failed', ['key' => $key, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    public static function expire(string $key, int $ttl): bool {
        try {
            $redis = self::connect();
            return $redis->expire($key, $ttl);
        } catch (\Exception $e) {
            Logger::warning('Redis EXPIRE failed', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function flushDatabase(): bool {
        try {
            $redis = self::connect();
            return $redis->flushDB();
        } catch (\Exception $e) {
            Logger::warning('Redis FLUSH failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}