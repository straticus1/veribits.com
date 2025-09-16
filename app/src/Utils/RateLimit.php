<?php
namespace VeriBits\Utils;

class RateLimit {
    private const WINDOW_SIZE = 60; // 1 minute window
    private const MAX_REQUESTS = 60; // 60 requests per minute default

    public static function check(string $identifier, int $maxRequests = self::MAX_REQUESTS, int $windowSize = self::WINDOW_SIZE): bool {
        $key = "rate_limit:$identifier";
        $now = time();
        $windowStart = $now - $windowSize;

        try {
            $redis = Redis::connect();

            $redis->multi();
            $redis->zRemRangeByScore($key, 0, $windowStart);
            $redis->zCard($key);
            $redis->zAdd($key, $now, $now . ':' . uniqid());
            $redis->expire($key, $windowSize);
            $result = $redis->exec();

            $currentRequests = $result[1] ?? 0;

            if ($currentRequests >= $maxRequests) {
                Logger::security('Rate limit exceeded', [
                    'identifier' => $identifier,
                    'requests' => $currentRequests,
                    'limit' => $maxRequests
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Logger::warning('Rate limiting check failed', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    public static function checkUserQuota(string $userId, string $period = 'monthly'): bool {
        try {
            $sql = "SELECT allowance, used FROM quotas WHERE user_id = :user_id AND period = :period";
            $quota = Database::fetch($sql, ['user_id' => $userId, 'period' => $period]);

            if (!$quota) {
                $defaultQuota = self::getDefaultQuota($period);
                Database::insert('quotas', [
                    'user_id' => $userId,
                    'period' => $period,
                    'allowance' => $defaultQuota,
                    'used' => 0
                ]);
                return true;
            }

            if ($quota['used'] >= $quota['allowance']) {
                Logger::info('User quota exceeded', [
                    'user_id' => $userId,
                    'period' => $period,
                    'used' => $quota['used'],
                    'allowance' => $quota['allowance']
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Quota check failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    public static function incrementUserQuota(string $userId, string $period = 'monthly', int $increment = 1): void {
        try {
            $sql = "UPDATE quotas SET used = used + :increment WHERE user_id = :user_id AND period = :period";
            Database::query($sql, [
                'increment' => $increment,
                'user_id' => $userId,
                'period' => $period
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to increment user quota', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public static function getRemaining(string $identifier, int $maxRequests = self::MAX_REQUESTS, int $windowSize = self::WINDOW_SIZE): int {
        $key = "rate_limit:$identifier";
        $now = time();
        $windowStart = $now - $windowSize;

        try {
            $redis = Redis::connect();
            $redis->zRemRangeByScore($key, 0, $windowStart);
            $current = $redis->zCard($key);

            return max(0, $maxRequests - $current);
        } catch (\Exception $e) {
            Logger::warning('Failed to get rate limit remaining', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
            return $maxRequests;
        }
    }

    public static function reset(string $identifier): void {
        try {
            Redis::delete("rate_limit:$identifier");
        } catch (\Exception $e) {
            Logger::warning('Failed to reset rate limit', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);
        }
    }

    private static function getDefaultQuota(string $period): int {
        return match($period) {
            'daily' => 100,
            'monthly' => 1000,
            'yearly' => 10000,
            default => 1000
        };
    }
}