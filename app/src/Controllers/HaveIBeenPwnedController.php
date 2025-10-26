<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class HaveIBeenPwnedController {
    private const HIBP_API_KEY = 'd42250dee04a4d5b847c03a98c75ba21';
    private const HIBP_API_BASE = 'https://haveibeenpwned.com/api/v3';
    private const PWNED_PASSWORDS_API = 'https://api.pwnedpasswords.com';
    private const CACHE_TTL_SECONDS = 86400; // 1 day
    private const RATE_LIMIT_ANONYMOUS = 5; // per minute
    private const RATE_LIMIT_AUTHENTICATED = 50; // per minute

    /**
     * Check if an email address has been found in any data breaches
     */
    public function checkEmail(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('email')->email('email');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $email = strtolower(trim($validator->sanitize('email')));

        // Determine if user is authenticated
        $claims = Auth::extractBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;
        $isAuthenticated = $userId !== null;

        // Apply rate limiting
        if (!$this->checkRateLimit($email, $isAuthenticated)) {
            Response::error('Rate limit exceeded. ' .
                ($isAuthenticated ? 'Maximum 50 lookups per minute.' : 'Maximum 5 lookups per minute. Please register for higher limits.'),
                429
            );
            return;
        }

        // Check cache first
        $cached = $this->getCachedEmailResult($email);
        if ($cached !== null) {
            Logger::info('HIBP email check - cache hit', ['email' => $email]);
            Response::success([
                'email' => $email,
                'breaches' => $cached['breaches'],
                'breach_count' => count($cached['breaches']),
                'cached' => true,
                'checked_at' => $cached['checked_at']
            ]);
            return;
        }

        // Make API request to HIBP
        try {
            $startTime = microtime(true);
            $breaches = $this->fetchBreachesForEmail($email);
            $checkTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // Cache the result
            $this->cacheEmailResult($email, $breaches, $userId, $apiKeyId);

            // Increment usage quota
            if ($isAuthenticated) {
                RateLimit::incrementUsage($userId, 'monthly');
            }

            Logger::info('HIBP email check completed', [
                'email' => $email,
                'breach_count' => count($breaches),
                'time_ms' => $checkTimeMs
            ]);

            Response::success([
                'email' => $email,
                'breaches' => $breaches,
                'breach_count' => count($breaches),
                'cached' => false,
                'checked_at' => date('c'),
                'check_time_ms' => $checkTimeMs
            ]);

        } catch (\Exception $e) {
            Logger::error('HIBP email check failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to check email: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check if a password has been found in any data breaches
     * Uses k-anonymity model - only first 5 chars of SHA-1 hash sent to API
     */
    public function checkPassword(): void {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('password')->string('password', 1, 1000);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $password = $body['password'];

        // Determine if user is authenticated
        $claims = Auth::extractBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;
        $isAuthenticated = $userId !== null;

        // Apply rate limiting based on hash (not actual password for privacy)
        $passwordHash = strtoupper(sha1($password));
        if (!$this->checkRateLimit('pwd_' . substr($passwordHash, 0, 10), $isAuthenticated)) {
            Response::error('Rate limit exceeded. ' .
                ($isAuthenticated ? 'Maximum 50 lookups per minute.' : 'Maximum 5 lookups per minute. Please register for higher limits.'),
                429
            );
            return;
        }

        // Check cache first (using full hash)
        $cached = $this->getCachedPasswordResult($passwordHash);
        if ($cached !== null) {
            Logger::info('HIBP password check - cache hit', ['hash_prefix' => substr($passwordHash, 0, 5)]);
            Response::success([
                'pwned' => $cached['pwned'],
                'occurrences' => $cached['occurrences'],
                'cached' => true,
                'checked_at' => $cached['checked_at']
            ]);
            return;
        }

        // Use k-anonymity: only send first 5 chars of hash
        try {
            $startTime = microtime(true);
            $prefix = substr($passwordHash, 0, 5);
            $suffix = substr($passwordHash, 5);

            $occurrences = $this->fetchPasswordOccurrences($prefix, $suffix);
            $checkTimeMs = (int)((microtime(true) - $startTime) * 1000);

            $isPwned = $occurrences > 0;

            // Cache the result
            $this->cachePasswordResult($passwordHash, $isPwned, $occurrences, $userId, $apiKeyId);

            // Increment usage quota
            if ($isAuthenticated) {
                RateLimit::incrementUsage($userId, 'monthly');
            }

            Logger::info('HIBP password check completed', [
                'hash_prefix' => $prefix,
                'pwned' => $isPwned,
                'occurrences' => $occurrences,
                'time_ms' => $checkTimeMs
            ]);

            Response::success([
                'pwned' => $isPwned,
                'occurrences' => $occurrences,
                'cached' => false,
                'checked_at' => date('c'),
                'check_time_ms' => $checkTimeMs,
                'message' => $isPwned ?
                    "This password has been seen $occurrences times in data breaches. Do not use it!" :
                    "This password has not been found in any known data breaches."
            ]);

        } catch (\Exception $e) {
            Logger::error('HIBP password check failed', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to check password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get breach statistics for an email
     */
    public function getStats(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        try {
            $db = Database::getConnection();

            // Get user's breach check history
            $stmt = $db->prepare("
                SELECT
                    COUNT(*) as total_checks,
                    COUNT(CASE WHEN breach_count > 0 THEN 1 END) as breached_emails,
                    SUM(breach_count) as total_breaches_found
                FROM hibp_email_checks
                WHERE user_id = $1
            ");
            $stmt->execute([$userId]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get recent checks
            $stmt = $db->prepare("
                SELECT email, breach_count, checked_at
                FROM hibp_email_checks
                WHERE user_id = $1
                ORDER BY checked_at DESC
                LIMIT 10
            ");
            $stmt->execute([$userId]);
            $recentChecks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            Response::success([
                'stats' => $stats,
                'recent_checks' => $recentChecks
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get HIBP stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to get statistics', 500);
        }
    }

    /**
     * Fetch breaches for an email from HIBP API
     */
    private function fetchBreachesForEmail(string $email): array {
        $url = self::HIBP_API_BASE . '/breachedaccount/' . urlencode($email) . '?truncateResponse=false';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'hibp-api-key: ' . self::HIBP_API_KEY,
                'User-Agent: VeriBits-Security-Tools/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \Exception("HIBP API request failed: $curlError");
        }

        // 404 means no breaches found
        if ($httpCode === 404) {
            return [];
        }

        if ($httpCode !== 200) {
            throw new \Exception("HIBP API returned status code: $httpCode");
        }

        $breaches = json_decode($response, true);
        if (!is_array($breaches)) {
            throw new \Exception("Invalid response from HIBP API");
        }

        return $breaches;
    }

    /**
     * Fetch password occurrences using k-anonymity model
     */
    private function fetchPasswordOccurrences(string $prefix, string $suffix): int {
        $url = self::PWNED_PASSWORDS_API . '/range/' . $prefix;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: VeriBits-Security-Tools/1.0'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \Exception("Pwned Passwords API request failed: $curlError");
        }

        if ($httpCode !== 200) {
            throw new \Exception("Pwned Passwords API returned status code: $httpCode");
        }

        // Parse response - format is "SUFFIX:COUNT\r\n"
        $lines = explode("\r\n", trim($response));
        foreach ($lines as $line) {
            $parts = explode(':', $line);
            if (count($parts) === 2 && strtoupper($parts[0]) === $suffix) {
                return (int)$parts[1];
            }
        }

        return 0; // Not found in breaches
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(string $identifier, bool $isAuthenticated): bool {
        $limit = $isAuthenticated ? self::RATE_LIMIT_AUTHENTICATED : self::RATE_LIMIT_ANONYMOUS;
        $key = 'hibp_rate_' . ($isAuthenticated ? 'auth' : 'anon') . '_' . md5($identifier);

        $db = Database::getConnection();

        try {
            // Clean up old rate limit records (older than 1 minute)
            $db->exec("DELETE FROM hibp_rate_limits WHERE expires_at < NOW()");

            // Count requests in last minute
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM hibp_rate_limits
                WHERE rate_key = $1 AND expires_at > NOW()
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result['count'] >= $limit) {
                return false;
            }

            // Add this request
            $stmt = $db->prepare("
                INSERT INTO hibp_rate_limits (rate_key, expires_at)
                VALUES ($1, NOW() + INTERVAL '1 minute')
            ");
            $stmt->execute([$key]);

            return true;

        } catch (\Exception $e) {
            Logger::error('Rate limit check failed', ['error' => $e->getMessage()]);
            return true; // Fail open
        }
    }

    /**
     * Get cached email result
     */
    private function getCachedEmailResult(string $email): ?array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT breach_data, breach_count, checked_at
                FROM hibp_email_checks
                WHERE email = $1
                AND checked_at > NOW() - INTERVAL '1 day'
                ORDER BY checked_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return [
                    'breaches' => json_decode($result['breach_data'], true),
                    'checked_at' => $result['checked_at']
                ];
            }

            return null;

        } catch (\Exception $e) {
            Logger::error('Cache lookup failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cache email result
     */
    private function cacheEmailResult(string $email, array $breaches, ?string $userId, ?string $apiKeyId): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO hibp_email_checks (user_id, api_key_id, email, breach_data, breach_count, checked_at)
                VALUES ($1, $2, $3, $4, $5, NOW())
            ");
            $stmt->execute([
                $userId,
                $apiKeyId,
                $email,
                json_encode($breaches),
                count($breaches)
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to cache email result', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get cached password result
     */
    private function getCachedPasswordResult(string $hash): ?array {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT pwned, occurrences, checked_at
                FROM hibp_password_checks
                WHERE password_hash = $1
                AND checked_at > NOW() - INTERVAL '1 day'
                ORDER BY checked_at DESC
                LIMIT 1
            ");
            $stmt->execute([$hash]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return [
                    'pwned' => $result['pwned'],
                    'occurrences' => $result['occurrences'],
                    'checked_at' => $result['checked_at']
                ];
            }

            return null;

        } catch (\Exception $e) {
            Logger::error('Cache lookup failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cache password result
     */
    private function cachePasswordResult(string $hash, bool $pwned, int $occurrences, ?string $userId, ?string $apiKeyId): void {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                INSERT INTO hibp_password_checks (user_id, api_key_id, password_hash, pwned, occurrences, checked_at)
                VALUES ($1, $2, $3, $4, $5, NOW())
            ");
            $stmt->execute([
                $userId,
                $apiKeyId,
                $hash,
                $pwned ? 't' : 'f',
                $occurrences
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to cache password result', ['error' => $e->getMessage()]);
        }
    }
}
