<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Database;
use VeriBits\Utils\Response;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class CodeSigningController {
    private const CERT_PATH = '/etc/veribits/certs';
    private const SIGNING_DIR = '/tmp/veribits-signing';
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB

    // Tier limits
    private const TIER_LIMITS = [
        'free' => 1,
        'monthly' => 500,
        'annual' => 2500,
        'enterprise' => 10000
    ];

    public function sign(): void {
        try {
            // Check if file was uploaded
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Response::error('No file uploaded or upload error', 400);
                return;
            }

            $file = $_FILES['file'];
            $fileSize = $file['size'];
            $fileName = basename($file['name']);
            $tmpPath = $file['tmp_name'];

            // Validate file size
            if ($fileSize > self::MAX_FILE_SIZE) {
                Response::error('File too large. Maximum size is 100MB', 400);
                return;
            }

            // Detect file type
            $fileType = $this->detectFileType($fileName);
            if (!$fileType) {
                Response::error('Unsupported file type. Supported: .exe, .dll, .msi, .jar', 400);
                return;
            }

            // Check quota
            $userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userId = null; // TODO: Get from session when auth is implemented

            $canSign = $this->checkQuota($userId, $userIp);
            if (!$canSign) {
                Response::error('Code signing quota exceeded. Upgrade your plan for more signings.', 429);
                return;
            }

            // Generate unique ID for this operation
            $operationId = bin2hex(random_bytes(16));
            $fileHash = hash_file('sha256', $tmpPath);

            // Create working directory
            $workDir = self::SIGNING_DIR . '/' . $operationId;
            if (!mkdir($workDir, 0755, true)) {
                Response::error('Failed to create working directory', 500);
                return;
            }

            // Copy file to working directory
            $inputFile = $workDir . '/' . $fileName;
            $outputFile = $workDir . '/signed_' . $fileName;

            if (!copy($tmpPath, $inputFile)) {
                Response::error('Failed to process file', 500);
                return;
            }

            // Sign the file based on type
            $signResult = match($fileType) {
                'exe', 'dll', 'msi' => $this->signPE($inputFile, $outputFile),
                'jar' => $this->signJAR($inputFile, $outputFile),
                default => ['success' => false, 'error' => 'Unsupported file type']
            };

            if (!$signResult['success']) {
                $this->cleanup($workDir);
                Response::error($signResult['error'] ?? 'Code signing failed', 500);
                return;
            }

            // Verify signature
            $verified = $this->verifySignature($outputFile, $fileType);

            // Read signed file
            $signedContent = file_get_contents($outputFile);
            $signedHash = hash('sha256', $signedContent);

            // Increment usage
            $this->incrementUsage($userId, $userIp);

            // Record operation
            $badgeId = $this->recordOperation([
                'user_id' => $userId,
                'operation_type' => $fileType,
                'file_hash' => $fileHash,
                'file_size_bytes' => $fileSize,
                'original_filename' => $fileName,
                'certificate_type' => 'test',
                'certificate_subject' => 'CN=After Dark Systems Object Signing Certificate, OU=VeriBits, O=After Dark Systems LLC',
                'signing_status' => 'success',
                'signature_verified' => $verified,
                'user_ip' => $userIp
            ]);

            // Cleanup
            $this->cleanup($workDir);

            // Return signed file
            Response::success([
                'operation_id' => $operationId,
                'badge_id' => $badgeId,
                'original_filename' => $fileName,
                'signed_filename' => 'signed_' . $fileName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'original_hash' => $fileHash,
                'signed_hash' => $signedHash,
                'signature_verified' => $verified,
                'certificate_info' => [
                    'type' => 'test',
                    'subject' => 'CN=After Dark Systems Object Signing Certificate, OU=VeriBits, O=After Dark Systems LLC',
                    'issuer' => 'CN=After Dark Systems Object Signing CA, O=After Dark Systems LLC',
                    'note' => 'This is a test certificate for evaluation purposes only. Not trusted by operating systems.'
                ],
                'signed_file' => base64_encode($signedContent),
                'download_note' => 'Decode the base64 signed_file content to download'
            ]);

        } catch (\Exception $e) {
            Logger::error('Code signing error', ['error' => $e->getMessage()]);
            Response::error('Internal server error during code signing', 500);
        }
    }

    private function signPE(string $inputFile, string $outputFile): array {
        try {
            $chainFile = self::CERT_PATH . '/codesign-chain.pem';
            $keyFile = self::CERT_PATH . '/codesign-key.pem';

            // Use osslsigncode to sign PE files with certificate chain
            $cmd = sprintf(
                'osslsigncode sign -certs %s -key %s -n "Signed by After Dark Systems" -i "https://veribits.com" -in %s -out %s 2>&1',
                escapeshellarg($chainFile),
                escapeshellarg($keyFile),
                escapeshellarg($inputFile),
                escapeshellarg($outputFile)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($outputFile)) {
                return [
                    'success' => false,
                    'error' => 'Failed to sign PE file: ' . implode("\n", $output)
                ];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function signJAR(string $inputFile, string $outputFile): array {
        try {
            $keystore = self::CERT_PATH . '/veribits.jks';
            $storepass = 'veribits2024';
            $alias = 'veribits';

            // Copy input to output first (jarsigner modifies in place)
            copy($inputFile, $outputFile);

            // Use jarsigner to sign JAR files
            $cmd = sprintf(
                'jarsigner -keystore %s -storepass %s -keypass %s %s %s 2>&1',
                escapeshellarg($keystore),
                escapeshellarg($storepass),
                escapeshellarg($storepass),
                escapeshellarg($outputFile),
                escapeshellarg($alias)
            );

            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                return [
                    'success' => false,
                    'error' => 'Failed to sign JAR file: ' . implode("\n", $output)
                ];
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function verifySignature(string $file, string $type): bool {
        try {
            if ($type === 'jar') {
                // Verify JAR signature
                exec(sprintf('jarsigner -verify %s 2>&1', escapeshellarg($file)), $output, $returnCode);
                return $returnCode === 0 && strpos(implode("\n", $output), 'jar verified') !== false;
            } else {
                // Verify PE signature
                exec(sprintf('osslsigncode verify %s 2>&1', escapeshellarg($file)), $output, $returnCode);
                return $returnCode === 0;
            }
        } catch (\Exception $e) {
            Logger::error('Signature verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function detectFileType(string $filename): ?string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match($ext) {
            'exe', 'dll', 'msi' => $ext,
            'jar' => 'jar',
            default => null
        };
    }

    private function checkQuota(?string $userId, string $userIp): bool {
        if ($userId) {
            // Check authenticated user quota
            $quota = Database::fetch(
                "SELECT * FROM code_signing_quotas WHERE user_id = :user_id AND period = 'month' AND resets_at > NOW()",
                ['user_id' => $userId]
            );

            if (!$quota) {
                // Create initial quota (free tier)
                Database::insert('code_signing_quotas', [
                    'user_id' => $userId,
                    'plan_type' => 'free',
                    'period' => 'month',
                    'allowance' => self::TIER_LIMITS['free'],
                    'used' => 0,
                    'resets_at' => date('Y-m-d H:i:s', strtotime('+1 month'))
                ]);
                return true;
            }

            return $quota['used'] < $quota['allowance'];
        } else {
            // Check anonymous/IP-based quota
            $anonymousQuota = Database::fetch(
                "SELECT * FROM anonymous_code_signing WHERE ip_address = :ip AND period_end > NOW()",
                ['ip' => $userIp]
            );

            if (!$anonymousQuota) {
                // Create new period
                Database::insert('anonymous_code_signing', [
                    'ip_address' => $userIp,
                    'signings_used' => 0,
                    'period_start' => date('Y-m-d H:i:s'),
                    'period_end' => date('Y-m-d H:i:s', strtotime('+30 days'))
                ]);
                return true;
            }

            return $anonymousQuota['signings_used'] < 1; // Free tier: 1 signing
        }
    }

    private function incrementUsage(?string $userId, string $userIp): void {
        if ($userId) {
            Database::query(
                "UPDATE code_signing_quotas SET used = used + 1 WHERE user_id = :user_id AND period = 'month' AND resets_at > NOW()",
                ['user_id' => $userId]
            );
        } else {
            Database::query(
                "UPDATE anonymous_code_signing SET signings_used = signings_used + 1 WHERE ip_address = :ip AND period_end > NOW()",
                ['ip' => $userIp]
            );
        }
    }

    private function recordOperation(array $data): string {
        $badgeId = 'cs_' . bin2hex(random_bytes(8));
        $data['badge_id'] = $badgeId;

        // Set expiration for free tier (30 days)
        if (!isset($data['user_id']) || empty($data['user_id'])) {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        Database::insert('code_signing_operations', $data);
        return $badgeId;
    }

    private function cleanup(string $dir): void {
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }
    }

    public function getQuota(): void {
        $userId = null; // TODO: Get from session
        $userIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($userId) {
            $quota = Database::fetch(
                "SELECT * FROM code_signing_quotas WHERE user_id = :user_id AND period = 'month' AND resets_at > NOW()",
                ['user_id' => $userId]
            );

            if (!$quota) {
                $quota = [
                    'plan_type' => 'free',
                    'allowance' => self::TIER_LIMITS['free'],
                    'used' => 0,
                    'remaining' => self::TIER_LIMITS['free']
                ];
            } else {
                $quota['remaining'] = max(0, $quota['allowance'] - $quota['used']);
            }
        } else {
            $anonymousQuota = Database::fetch(
                "SELECT * FROM anonymous_code_signing WHERE ip_address = :ip AND period_end > NOW()",
                ['ip' => $userIp]
            );

            $used = $anonymousQuota['signings_used'] ?? 0;
            $quota = [
                'plan_type' => 'anonymous',
                'allowance' => 1,
                'used' => $used,
                'remaining' => max(0, 1 - $used),
                'period_end' => $anonymousQuota['period_end'] ?? date('Y-m-d H:i:s', strtotime('+30 days'))
            ];
        }

        Response::success([
            'quota' => $quota,
            'tier_limits' => self::TIER_LIMITS
        ]);
    }
}
