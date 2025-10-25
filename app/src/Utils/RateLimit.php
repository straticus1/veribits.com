<?php
namespace VeriBits\Utils;

class RateLimit {
    private const WINDOW_SIZE = 60; // 1 minute window
    private const MAX_REQUESTS = 60; // 60 requests per minute default

    // Anonymous user limits (trial model)
    private const ANONYMOUS_FREE_SCANS = 5; // 5 free scans then must register
    private const ANONYMOUS_MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB max file size
    private const ANONYMOUS_SCAN_WINDOW = 2592000; // 30 days (lifetime trial)

    // Whitelist cache
    private static ?array $whitelistCache = null;
    private static ?int $whitelistCacheTime = null;
    private const WHITELIST_CACHE_TTL = 300; // 5 minutes

    /**
     * Check if identifier is whitelisted for rate limit bypass
     */
    private static function isWhitelisted(string $identifier): bool {
        // Load whitelist (with caching)
        $whitelist = self::loadWhitelist();

        if (empty($whitelist)) {
            return false;
        }

        // Check if identifier matches any whitelist entry
        foreach ($whitelist as $entry) {
            // Match by IP address (v4 or v6)
            if (isset($entry['ip_address4']) && $entry['ip_address4'] === $identifier) {
                Logger::info('Rate limit bypassed - whitelisted IPv4', ['ip' => $identifier]);
                return true;
            }
            if (isset($entry['ip_address6']) && $entry['ip_address6'] === $identifier) {
                Logger::info('Rate limit bypassed - whitelisted IPv6', ['ip' => $identifier]);
                return true;
            }
            // Match by email
            if (isset($entry['account_email']) && $entry['account_email'] === $identifier) {
                Logger::info('Rate limit bypassed - whitelisted email', ['email' => $identifier]);
                return true;
            }
            // Match by user ID
            if (isset($entry['id']) && 'user_' . $entry['id'] === $identifier) {
                Logger::info('Rate limit bypassed - whitelisted user', ['user_id' => $identifier]);
                return true;
            }
        }

        return false;
    }

    /**
     * Load whitelist from JSON file with caching
     */
    private static function loadWhitelist(): array {
        // Check cache first
        if (self::$whitelistCache !== null &&
            self::$whitelistCacheTime !== null &&
            (time() - self::$whitelistCacheTime) < self::WHITELIST_CACHE_TTL) {
            return self::$whitelistCache;
        }

        $whitelistPaths = [
            '/tmp/access_veribits.json',
            '/var/www/config/whitelist.json',
            __DIR__ . '/../../../config/whitelist.json'
        ];

        foreach ($whitelistPaths as $path) {
            if (file_exists($path)) {
                try {
                    $content = file_get_contents($path);
                    $data = json_decode($content, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Normalize to array of entries
                        $whitelist = isset($data[0]) ? $data : [$data];

                        self::$whitelistCache = $whitelist;
                        self::$whitelistCacheTime = time();

                        Logger::info('Rate limit whitelist loaded', [
                            'path' => $path,
                            'entries' => count($whitelist)
                        ]);

                        return $whitelist;
                    } else {
                        Logger::warning('Failed to parse whitelist JSON', [
                            'path' => $path,
                            'error' => json_last_error_msg()
                        ]);
                    }
                } catch (\Exception $e) {
                    Logger::warning('Failed to load whitelist', [
                        'path' => $path,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Cache empty result to avoid repeated file checks
        self::$whitelistCache = [];
        self::$whitelistCacheTime = time();

        return [];
    }

    public static function check(string $identifier, int $maxRequests = self::MAX_REQUESTS, int $windowSize = self::WINDOW_SIZE): bool {
        // Check whitelist first
        if (self::isWhitelisted($identifier)) {
            return true;
        }
        // Check if Redis extension is available
        if (!class_exists('Redis')) {
            Logger::warning('Redis extension not available, rate limiting disabled');
            return true; // Fail open for non-critical rate limiting
        }

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
            return true; // Fail open on Redis connection errors
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

    /**
     * Check if anonymous user can perform a scan (file verification)
     * Enforces 5 free scans under 50MB, then requires registration + payment
     */
    public static function checkAnonymousScan(string $ipAddress, int $fileSize = 0): array {
        $scanKey = "anon_scans:$ipAddress";

        // Check file size limit
        if ($fileSize > self::ANONYMOUS_MAX_FILE_SIZE) {
            Logger::security('Anonymous file size limit exceeded', [
                'ip' => $ipAddress,
                'file_size' => $fileSize,
                'limit' => self::ANONYMOUS_MAX_FILE_SIZE
            ]);
            return [
                'allowed' => false,
                'reason' => 'file_too_large',
                'message' => 'File exceeds 50MB limit for anonymous users. Please register to scan larger files.',
                'max_file_size' => self::ANONYMOUS_MAX_FILE_SIZE,
                'max_file_size_mb' => self::ANONYMOUS_MAX_FILE_SIZE / 1024 / 1024
            ];
        }

        try {
            // Try Redis first
            if (class_exists('Redis')) {
                try {
                    $redis = Redis::connect();
                    $scanCount = (int)$redis->get($scanKey) ?: 0;

                    if ($scanCount >= self::ANONYMOUS_FREE_SCANS) {
                        Logger::security('Anonymous scan limit exceeded (Redis)', [
                            'ip' => $ipAddress,
                            'scans_used' => $scanCount,
                            'limit' => self::ANONYMOUS_FREE_SCANS
                        ]);
                        return [
                            'allowed' => false,
                            'reason' => 'trial_limit_exceeded',
                            'message' => 'You have used all 5 free scans. Please create an account and add payment to continue.',
                            'scans_used' => $scanCount,
                            'scans_limit' => self::ANONYMOUS_FREE_SCANS,
                            'remaining' => 0
                        ];
                    }

                    return [
                        'allowed' => true,
                        'scans_used' => $scanCount,
                        'scans_remaining' => self::ANONYMOUS_FREE_SCANS - $scanCount,
                        'scans_limit' => self::ANONYMOUS_FREE_SCANS,
                        'max_file_size' => self::ANONYMOUS_MAX_FILE_SIZE,
                        'max_file_size_mb' => self::ANONYMOUS_MAX_FILE_SIZE / 1024 / 1024
                    ];
                } catch (\Exception $redisError) {
                    Logger::warning('Redis unavailable, falling back to database', [
                        'ip' => $ipAddress,
                        'error' => $redisError->getMessage()
                    ]);
                    // Fall through to database fallback
                }
            }

            // Fallback to database if Redis unavailable
            $sql = "SELECT scans_used, period_end FROM anonymous_scans
                    WHERE ip_address = :ip AND period_end > NOW()
                    ORDER BY created_at DESC LIMIT 1";
            $record = Database::fetch($sql, ['ip' => $ipAddress]);

            if (!$record) {
                // No record - create one
                Database::insert('anonymous_scans', [
                    'ip_address' => $ipAddress,
                    'scans_used' => 0,
                    'period_start' => date('Y-m-d H:i:s'),
                    'period_end' => date('Y-m-d H:i:s', time() + self::ANONYMOUS_SCAN_WINDOW)
                ]);
                return [
                    'allowed' => true,
                    'scans_used' => 0,
                    'scans_remaining' => self::ANONYMOUS_FREE_SCANS,
                    'scans_limit' => self::ANONYMOUS_FREE_SCANS,
                    'max_file_size' => self::ANONYMOUS_MAX_FILE_SIZE,
                    'max_file_size_mb' => self::ANONYMOUS_MAX_FILE_SIZE / 1024 / 1024
                ];
            }

            $scanCount = (int)$record['scans_used'];
            if ($scanCount >= self::ANONYMOUS_FREE_SCANS) {
                Logger::security('Anonymous scan limit exceeded (Database)', [
                    'ip' => $ipAddress,
                    'scans_used' => $scanCount
                ]);
                return [
                    'allowed' => false,
                    'reason' => 'trial_limit_exceeded',
                    'message' => 'You have used all 5 free scans. Please create an account and add payment to continue.',
                    'scans_used' => $scanCount,
                    'scans_limit' => self::ANONYMOUS_FREE_SCANS,
                    'remaining' => 0
                ];
            }

            return [
                'allowed' => true,
                'scans_used' => $scanCount,
                'scans_remaining' => self::ANONYMOUS_FREE_SCANS - $scanCount,
                'scans_limit' => self::ANONYMOUS_FREE_SCANS,
                'max_file_size' => self::ANONYMOUS_MAX_FILE_SIZE,
                'max_file_size_mb' => self::ANONYMOUS_MAX_FILE_SIZE / 1024 / 1024
            ];

        } catch (\Exception $e) {
            Logger::error('Anonymous scan check failed completely', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            // FAIL CLOSED for security - deny request on system errors
            return [
                'allowed' => false,
                'reason' => 'system_error',
                'message' => 'Temporary system error. Please try again in a moment.'
            ];
        }
    }

    /**
     * Increment anonymous scan counter
     */
    public static function incrementAnonymousScan(string $ipAddress): void {
        $scanKey = "anon_scans:$ipAddress";

        try {
            // Try Redis first
            if (class_exists('Redis')) {
                try {
                    $redis = Redis::connect();
                    $redis->incr($scanKey);
                    // Set expiry to 30 days if this is the first scan
                    if ($redis->ttl($scanKey) === -1) {
                        $redis->expire($scanKey, self::ANONYMOUS_SCAN_WINDOW);
                    }

                    Logger::info('Anonymous scan incremented (Redis)', [
                        'ip' => $ipAddress,
                        'total_scans' => $redis->get($scanKey)
                    ]);
                    return;
                } catch (\Exception $redisError) {
                    Logger::warning('Redis unavailable for increment, using database', [
                        'ip' => $ipAddress,
                        'error' => $redisError->getMessage()
                    ]);
                    // Fall through to database
                }
            }

            // Fallback to database
            $sql = "UPDATE anonymous_scans SET scans_used = scans_used + 1
                    WHERE ip_address = :ip AND period_end > NOW()";
            $affected = Database::query($sql, ['ip' => $ipAddress])->rowCount();

            if ($affected === 0) {
                // Create new record if none exists
                Database::insert('anonymous_scans', [
                    'ip_address' => $ipAddress,
                    'scans_used' => 1,
                    'period_start' => date('Y-m-d H:i:s'),
                    'period_end' => date('Y-m-d H:i:s', time() + self::ANONYMOUS_SCAN_WINDOW)
                ]);
            }

            Logger::info('Anonymous scan incremented (Database)', ['ip' => $ipAddress]);

        } catch (\Exception $e) {
            Logger::error('Failed to increment anonymous scan', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check basic rate limit for anonymous users (non-scan requests)
     * More lenient for API calls that don't consume resources
     */
    public static function checkAnonymous(string $ipAddress): array {
        try {
            $key = "anon_basic:$ipAddress";
            $allowed = self::check($key, 100, 3600); // 100 requests per hour for non-scan operations

            if (!$allowed) {
                Logger::security('Anonymous rate limit exceeded', [
                    'ip' => $ipAddress
                ]);
                return [
                    'allowed' => false,
                    'reason' => 'rate_limit_exceeded',
                    'message' => 'Too many requests. Please slow down or register for an account.',
                    'hourly_limit' => 100,
                    'hourly_remaining' => 0
                ];
            }

            return [
                'allowed' => true,
                'hourly_limit' => 100,
                'hourly_remaining' => self::getRemaining($key, 100, 3600)
            ];
        } catch (\Exception $e) {
            Logger::error('Anonymous rate check failed', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
            // Fail open for basic rate limiting (non-critical)
            return ['allowed' => true, 'hourly_remaining' => 100];
        }
    }

    /**
     * Get client IP address (handles proxies and load balancers)
     */
    public static function getClientIp(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',  // Standard proxy header
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'            // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For can have multiple)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Check if a feature is available for anonymous users
     */
    public static function isFeatureAllowedAnonymous(string $feature): bool {
        // Features that require authentication
        $restrictedFeatures = [
            'malware_scan',
            'archive_inspection',
            'id_verification',
            'webhook_register',
            'billing',
            'file_signature',
        ];

        return !in_array($feature, $restrictedFeatures);
    }

    /**
     * Get anonymous user limits info
     */
    public static function getAnonymousLimits(string $ipAddress = null): array {
        $ipAddress = $ipAddress ?: self::getClientIp();

        $scanStatus = self::checkAnonymousScan($ipAddress);

        return [
            'free_scans' => self::ANONYMOUS_FREE_SCANS,
            'max_file_size_mb' => self::ANONYMOUS_MAX_FILE_SIZE / 1024 / 1024,
            'scans_used' => $scanStatus['scans_used'] ?? 0,
            'scans_remaining' => $scanStatus['scans_remaining'] ?? self::ANONYMOUS_FREE_SCANS,
            'trial_period_days' => self::ANONYMOUS_SCAN_WINDOW / 86400,
            'message' => 'Get 5 free scans up to 50MB. After your trial, create an account and add payment to continue scanning.',
            'upgrade_required' => ($scanStatus['scans_remaining'] ?? self::ANONYMOUS_FREE_SCANS) === 0
        ];
    }
}