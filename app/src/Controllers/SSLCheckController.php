<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class SSLCheckController {
    private const UPLOAD_DIR = '/tmp/veribits-ssl';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    /**
     * Generic SSL validation - supports anonymous with rate limiting
     */
    public function validate(): void {
        // Optional auth - supports anonymous users with rate limiting
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            // Check anonymous scan limits
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429, [
                    'reason' => $scanCheck['reason'],
                    'upgrade_url' => '/pricing.html'
                ]);
                return;
            }
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? '';

        if (empty($domain)) {
            Response::error('Domain is required', 400);
            return;
        }

        $port = $body['port'] ?? 443;

        // Remove protocol if present
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Remove path if present
        $domain = explode('/', $domain)[0];
        // Remove port if present in domain
        $domain = explode(':', $domain)[0];

        try {
            $startTime = microtime(true);

            // Get SSL certificate from website
            $certInfo = $this->getWebsiteCertificate($domain, (int)$port);

            $checkTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success([
                'domain' => $domain,
                'port' => $port,
                'certificate' => $certInfo,
                'check_time_ms' => $checkTimeMs
            ]);

        } catch (\Exception $e) {
            Logger::error('SSL validation failed', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            Response::error('SSL validation failed: ' . $e->getMessage(), 400);
        }
    }

    public function checkWebsite(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('domain')->string('domain', 3, 255);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $domain = strtolower(trim($validator->sanitize('domain')));
        $port = $body['port'] ?? 443;

        // Remove protocol if present
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Remove path if present
        $domain = explode('/', $domain)[0];
        // Remove port if present in domain
        $domain = explode(':', $domain)[0];

        try {
            $startTime = microtime(true);

            // Get SSL certificate from website
            $certInfo = $this->getWebsiteCertificate($domain, (int)$port);

            $checkTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // Analyze security
            $securityAnalysis = $this->analyzeCertificateSecurity($certInfo);

            // Generate badge ID
            $badgeId = 'ssl_' . substr(md5($domain . time()), 0, 16);

            // Store in database
            $this->storeCertificateCheck(
                $userId,
                $apiKeyId,
                'website',
                $domain,
                $certInfo,
                $securityAnalysis,
                $badgeId
            );

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('SSL website check completed', [
                'user_id' => $userId,
                'domain' => $domain,
                'security_score' => $securityAnalysis['score']
            ]);

            Response::success([
                'type' => 'ssl_website_check',
                'domain' => $domain,
                'port' => $port,
                'certificate' => $certInfo,
                'security_score' => $securityAnalysis['score'],
                'security_grade' => $this->getSecurityGrade($securityAnalysis['score']),
                'warnings' => $securityAnalysis['warnings'],
                'check_time_ms' => $checkTimeMs,
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'checked_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('SSL website check failed', [
                'user_id' => $userId,
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            Response::error('SSL check failed: ' . $e->getMessage(), 500);
        }
    }

    public function checkCertificate(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        // Handle certificate file upload
        if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Certificate file upload required', 400);
            return;
        }

        $certFile = $_FILES['certificate'];

        if ($certFile['size'] > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 10MB)', 413);
            return;
        }

        try {
            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            $tempPath = self::UPLOAD_DIR . '/' . uniqid('cert_', true);
            if (!move_uploaded_file($certFile['tmp_name'], $tempPath)) {
                throw new \Exception('Failed to save uploaded file');
            }

            // Parse certificate
            $certInfo = $this->parseCertificateFile($tempPath);

            // Clean up
            @unlink($tempPath);

            // Analyze security
            $securityAnalysis = $this->analyzeCertificateSecurity($certInfo);

            // Generate badge ID
            $badgeId = 'ssl_' . substr(md5($certInfo['subject_key_identifier'] ?? uniqid()), 0, 16);

            // Store in database
            $this->storeCertificateCheck(
                $userId,
                $apiKeyId,
                'certificate',
                $certInfo['subject']['CN'] ?? null,
                $certInfo,
                $securityAnalysis,
                $badgeId
            );

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('SSL certificate check completed', [
                'user_id' => $userId,
                'subject' => $certInfo['subject']['CN'] ?? 'unknown'
            ]);

            Response::success([
                'type' => 'ssl_certificate_check',
                'certificate' => $certInfo,
                'security_score' => $securityAnalysis['score'],
                'security_grade' => $this->getSecurityGrade($securityAnalysis['score']),
                'warnings' => $securityAnalysis['warnings'],
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'checked_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('SSL certificate check failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            Response::error('Certificate check failed: ' . $e->getMessage(), 500);
        }
    }

    public function verifyKeyMatch(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        // Require both certificate and key
        if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Certificate file upload required', 400);
            return;
        }

        if (!isset($_FILES['private_key']) || $_FILES['private_key']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Private key file upload required', 400);
            return;
        }

        $certFile = $_FILES['certificate'];
        $keyFile = $_FILES['private_key'];

        try {
            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            $certPath = self::UPLOAD_DIR . '/' . uniqid('cert_', true);
            $keyPath = self::UPLOAD_DIR . '/' . uniqid('key_', true);

            if (!move_uploaded_file($certFile['tmp_name'], $certPath)) {
                throw new \Exception('Failed to save certificate file');
            }

            if (!move_uploaded_file($keyFile['tmp_name'], $keyPath)) {
                @unlink($certPath);
                throw new \Exception('Failed to save key file');
            }

            // Parse certificate
            $certInfo = $this->parseCertificateFile($certPath);

            // Verify key match
            $matchResult = $this->verifyKeyMatchInternal($certPath, $keyPath);

            // Clean up
            @unlink($certPath);
            @unlink($keyPath);

            // Generate badge ID
            $badgeId = 'ssl_match_' . substr(md5($certInfo['subject_key_identifier'] ?? uniqid()), 0, 16);

            // Store in database
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO ssl_checks
                (user_id, api_key_id, check_type, domain, certificate_info,
                 issuer_info, validity_info, subject_key_identifier,
                 authority_key_identifier, key_match_result, security_score, warnings, badge_id)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
                RETURNING id
            ');

            $stmt->execute([
                $userId,
                $apiKeyId,
                'key_match',
                $certInfo['subject']['CN'] ?? null,
                json_encode($certInfo),
                json_encode($certInfo['issuer'] ?? null),
                json_encode($certInfo['validity'] ?? null),
                $certInfo['subject_key_identifier'] ?? null,
                $certInfo['authority_key_identifier'] ?? null,
                json_encode($matchResult),
                $matchResult['match'] ? 100 : 0,
                json_encode([]),
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('SSL key match verification completed', [
                'user_id' => $userId,
                'match' => $matchResult['match']
            ]);

            Response::success([
                'type' => 'ssl_key_match',
                'match' => $matchResult['match'],
                'verification_method' => $matchResult['method'],
                'details' => $matchResult['details'],
                'certificate_subject' => $certInfo['subject'],
                'subject_key_identifier' => $certInfo['subject_key_identifier'] ?? null,
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'checked_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('SSL key match verification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            if (isset($certPath) && file_exists($certPath)) {
                @unlink($certPath);
            }
            if (isset($keyPath) && file_exists($keyPath)) {
                @unlink($keyPath);
            }

            Response::error('Key match verification failed: ' . $e->getMessage(), 500);
        }
    }

    private function getWebsiteCertificate(string $domain, int $port): array {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $client = @stream_socket_client(
            "ssl://{$domain}:{$port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            throw new \Exception("Failed to connect to {$domain}:{$port} - {$errstr}");
        }

        $params = stream_context_get_params($client);
        fclose($client);

        if (!isset($params['options']['ssl']['peer_certificate'])) {
            throw new \Exception('No SSL certificate found');
        }

        $cert = $params['options']['ssl']['peer_certificate'];
        return $this->parseCertificate($cert);
    }

    private function parseCertificateFile(string $path): array {
        $certContent = file_get_contents($path);
        $cert = openssl_x509_read($certContent);

        if ($cert === false) {
            throw new \Exception('Invalid certificate file');
        }

        return $this->parseCertificate($cert);
    }

    private function parseCertificate($cert): array {
        $certData = openssl_x509_parse($cert);

        if ($certData === false) {
            throw new \Exception('Failed to parse certificate');
        }

        // Extract extensions
        $extensions = [];
        if (isset($certData['extensions'])) {
            foreach ($certData['extensions'] as $key => $value) {
                $extensions[$key] = $value;
            }
        }

        // Extract Subject Key Identifier
        $subjectKeyId = null;
        if (isset($extensions['subjectKeyIdentifier'])) {
            $subjectKeyId = str_replace(':', '', $extensions['subjectKeyIdentifier']);
        }

        // Extract Authority Key Identifier
        $authorityKeyId = null;
        if (isset($extensions['authorityKeyIdentifier'])) {
            // Parse "keyid:XX:XX:XX..." format
            if (preg_match('/keyid:([A-F0-9:]+)/i', $extensions['authorityKeyIdentifier'], $matches)) {
                $authorityKeyId = str_replace(':', '', $matches[1]);
            }
        }

        // Extract Subject Alternative Names
        $san = [];
        if (isset($extensions['subjectAltName'])) {
            $san = array_map('trim', explode(',', $extensions['subjectAltName']));
        }

        return [
            'subject' => $certData['subject'],
            'issuer' => $certData['issuer'],
            'validity' => [
                'valid_from' => date('c', $certData['validFrom_time_t']),
                'valid_to' => date('c', $certData['validTo_time_t']),
                'valid_from_timestamp' => $certData['validFrom_time_t'],
                'valid_to_timestamp' => $certData['validTo_time_t'],
                'is_valid' => time() >= $certData['validFrom_time_t'] && time() <= $certData['validTo_time_t'],
                'days_until_expiry' => (int)(($certData['validTo_time_t'] - time()) / 86400)
            ],
            'serial_number' => $certData['serialNumber'] ?? null,
            'signature_algorithm' => $certData['signatureTypeSN'] ?? null,
            'subject_key_identifier' => $subjectKeyId,
            'authority_key_identifier' => $authorityKeyId,
            'subject_alt_names' => $san,
            'extensions' => $extensions,
            'version' => $certData['version'] ?? null
        ];
    }

    private function verifyKeyMatchInternal(string $certPath, string $keyPath): array {
        $result = [
            'match' => false,
            'method' => 'modulus_comparison',
            'details' => []
        ];

        // Method 1: Compare modulus from cert and key
        $certModulus = null;
        $keyModulus = null;

        // Get certificate modulus
        $output = [];
        $returnCode = 0;
        $escapedCertPath = escapeshellarg($certPath);
        @exec("openssl x509 -noout -modulus -in $escapedCertPath 2>&1", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/Modulus=([A-F0-9]+)/i', $line, $matches)) {
                    $certModulus = $matches[1];
                    break;
                }
            }
        }

        // Get key modulus
        $output = [];
        $returnCode = 0;
        $escapedKeyPath = escapeshellarg($keyPath);
        @exec("openssl rsa -noout -modulus -in $escapedKeyPath 2>&1", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/Modulus=([A-F0-9]+)/i', $line, $matches)) {
                    $keyModulus = $matches[1];
                    break;
                }
            }
        }

        $result['details']['certificate_modulus'] = $certModulus ? substr($certModulus, 0, 64) . '...' : 'unavailable';
        $result['details']['key_modulus'] = $keyModulus ? substr($keyModulus, 0, 64) . '...' : 'unavailable';

        if ($certModulus && $keyModulus) {
            $result['match'] = ($certModulus === $keyModulus);
            $result['details']['modulus_match'] = $result['match'];
        }

        // Method 2: Try to use the key with the certificate (additional verification)
        if (!$result['match']) {
            // Try reading key with certificate
            $cert = @file_get_contents($certPath);
            $key = @file_get_contents($keyPath);

            if ($cert && $key) {
                $certRes = @openssl_x509_read($cert);
                $keyRes = @openssl_pkey_get_private($key);

                if ($certRes !== false && $keyRes !== false) {
                    $pubkey = @openssl_pkey_get_public($certRes);

                    if ($pubkey !== false) {
                        $pubDetails = openssl_pkey_get_details($pubkey);
                        $privDetails = openssl_pkey_get_details($keyRes);

                        if ($pubDetails && $privDetails) {
                            // Compare the public key from cert with public key from private key
                            if (isset($pubDetails['key']) && isset($privDetails['key'])) {
                                $result['match'] = ($pubDetails['key'] === $privDetails['key']);
                                $result['method'] = 'public_key_comparison';
                                $result['details']['public_key_match'] = $result['match'];
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function analyzeCertificateSecurity(array $certInfo): array {
        $score = 100;
        $warnings = [];

        // Check expiry
        $daysUntilExpiry = $certInfo['validity']['days_until_expiry'];

        if (!$certInfo['validity']['is_valid']) {
            $score -= 50;
            $warnings[] = 'Certificate is expired or not yet valid';
        } elseif ($daysUntilExpiry <= 30) {
            $score -= 30;
            $warnings[] = "Certificate expires in {$daysUntilExpiry} days";
        } elseif ($daysUntilExpiry <= 60) {
            $score -= 15;
            $warnings[] = "Certificate expires in {$daysUntilExpiry} days";
        }

        // Check signature algorithm
        $sigAlg = $certInfo['signature_algorithm'] ?? '';
        if (stripos($sigAlg, 'sha1') !== false) {
            $score -= 20;
            $warnings[] = 'Using weak signature algorithm (SHA1)';
        } elseif (stripos($sigAlg, 'md5') !== false) {
            $score -= 30;
            $warnings[] = 'Using very weak signature algorithm (MD5)';
        }

        // Check if self-signed
        if (isset($certInfo['subject']['CN']) && isset($certInfo['issuer']['CN'])) {
            if ($certInfo['subject']['CN'] === $certInfo['issuer']['CN']) {
                $score -= 10;
                $warnings[] = 'Self-signed certificate';
            }
        }

        return [
            'score' => max(0, $score),
            'warnings' => $warnings
        ];
    }

    private function storeCertificateCheck(
        $userId,
        $apiKeyId,
        string $checkType,
        ?string $domain,
        array $certInfo,
        array $securityAnalysis,
        string $badgeId
    ): void {
        $db = Database::getConnection();
        $stmt = $db->prepare('
            INSERT INTO ssl_checks
            (user_id, api_key_id, check_type, domain, certificate_info,
             issuer_info, validity_info, subject_key_identifier,
             authority_key_identifier, security_score, warnings, badge_id)
            VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
            RETURNING id
        ');

        $stmt->execute([
            $userId,
            $apiKeyId,
            $checkType,
            $domain,
            json_encode($certInfo),
            json_encode($certInfo['issuer'] ?? null),
            json_encode($certInfo['validity'] ?? null),
            $certInfo['subject_key_identifier'] ?? null,
            $certInfo['authority_key_identifier'] ?? null,
            $securityAnalysis['score'],
            json_encode($securityAnalysis['warnings']),
            $badgeId
        ]);
    }

    private function getSecurityGrade(int $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}
