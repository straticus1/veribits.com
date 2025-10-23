<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class FileSignatureController {
    private const MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
    private const MAX_SIGNATURE_SIZE = 10 * 1024; // 10KB for signature files

    public function verify(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        // Validate input files
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('File upload required', 400);
            return;
        }

        $file = $_FILES['file'];

        if ($file['size'] > self::MAX_FILE_SIZE) {
            Response::error('File too large (max 100MB)', 413);
            return;
        }

        // Detect verification type based on file and signature
        $verificationType = $this->detectVerificationType($file['name']);

        try {
            $result = null;

            // Handle different verification types
            if ($verificationType === 'hash') {
                // Hash file verification
                if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
                    Response::error('Hash file upload required', 400);
                    return;
                }
                $result = $this->verifyHash($file['tmp_name'], $_FILES['signature']['tmp_name'], $file['name']);
            } elseif ($verificationType === 'jar' || $verificationType === 'air') {
                // JAR/AIR embedded signature verification
                $result = $this->verifyJarSignature($file['tmp_name'], $file['name'], $verificationType);
            } elseif ($verificationType === 'macho') {
                // macOS binary signature verification
                $result = $this->verifyMacOSSignature($file['tmp_name'], $file['name']);
            } else {
                // PGP/GPG signature verification
                if (!isset($_FILES['signature']) || $_FILES['signature']['error'] !== UPLOAD_ERR_OK) {
                    Response::error('Signature file upload required', 400);
                    return;
                }
                if ($_FILES['signature']['size'] > self::MAX_SIGNATURE_SIZE) {
                    Response::error('Signature file too large (max 10KB)', 413);
                    return;
                }
                $publicKey = $_POST['public_key'] ?? null;
                if (empty($publicKey)) {
                    Response::error('Public key required for PGP verification', 400);
                    return;
                }
                $result = $this->verifySignature(
                    $file['tmp_name'],
                    $_FILES['signature']['tmp_name'],
                    $publicKey,
                    $file['name']
                );
            }

            // Store verification result
            $badgeId = 'sig_' . substr(md5($file['name'] . time()), 0, 16);

            Database::getConnection()->prepare('
                INSERT INTO file_signature_checks
                (user_id, filename, file_size, file_hash, signature_type, is_valid,
                 signer_info, key_fingerprint, badge_id, created_at)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, NOW())
            ')->execute([
                $userId,
                $file['name'],
                $file['size'],
                $result['file_hash'],
                $result['signature_type'],
                $result['is_valid'] ? 't' : 'f',
                json_encode($result['signer_info']),
                $result['key_fingerprint'] ?? null,
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('File signature verification completed', [
                'user_id' => $userId,
                'filename' => $file['name'],
                'is_valid' => $result['is_valid']
            ]);

            Response::success([
                'type' => 'file_signature_verification',
                'filename' => $file['name'],
                'file_size' => $file['size'],
                'file_hash' => $result['file_hash'],
                'signature_type' => $result['signature_type'],
                'is_valid' => $result['is_valid'],
                'signer_info' => $result['signer_info'],
                'key_fingerprint' => $result['key_fingerprint'] ?? null,
                'verification_details' => $result['details'],
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'verified_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('File signature verification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            Response::error('Verification failed: ' . $e->getMessage(), 500);
        }
    }

    private function verifySignature(
        string $filePath,
        string $signaturePath,
        string $publicKey,
        string $filename
    ): array {
        $fileHash = hash_file('sha256', $filePath);
        $signatureContent = file_get_contents($signaturePath);

        // Detect signature type
        $signatureType = $this->detectSignatureType($signatureContent);

        // Verify using GnuPG if available
        if (function_exists('gnupg_init')) {
            return $this->verifyWithGnuPG($filePath, $signaturePath, $publicKey, $fileHash, $signatureType);
        }

        // Fallback to command-line GPG
        if ($this->isGpgAvailable()) {
            return $this->verifyWithGpgCli($filePath, $signaturePath, $publicKey, $fileHash, $signatureType);
        }

        // If neither available, parse signature info but can't verify
        return $this->parseSignatureOnly($signatureContent, $publicKey, $fileHash, $signatureType);
    }

    private function detectSignatureType(string $content): string {
        if (strpos($content, '-----BEGIN PGP SIGNATURE-----') !== false) {
            return 'PGP Detached Signature';
        }
        if (strpos($content, '-----BEGIN PGP SIGNED MESSAGE-----') !== false) {
            return 'PGP Cleartext Signature';
        }
        if (preg_match('/^[0-9a-fA-F\s]+$/', $content)) {
            return 'Hex Signature';
        }
        return 'Unknown Signature Type';
    }

    private function isGpgAvailable(): bool {
        $output = [];
        $returnCode = 0;
        exec('gpg --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

    private function verifyWithGnuPG(
        string $filePath,
        string $signaturePath,
        string $publicKey,
        string $fileHash,
        string $signatureType
    ): array {
        $gpg = gnupg_init();

        // Import public key
        $keyInfo = gnupg_import($gpg, $publicKey);
        if ($keyInfo === false) {
            throw new \Exception('Failed to import public key');
        }

        // Add key for verification
        $fingerprint = $keyInfo['fingerprint'];
        gnupg_addverifykey($gpg, $fingerprint);

        // Verify signature
        $verifyResult = gnupg_verify($gpg, file_get_contents($filePath), false, file_get_contents($signaturePath));

        if ($verifyResult === false) {
            return [
                'file_hash' => $fileHash,
                'signature_type' => $signatureType,
                'is_valid' => false,
                'signer_info' => [],
                'key_fingerprint' => $fingerprint,
                'details' => 'Signature verification failed'
            ];
        }

        $sigInfo = $verifyResult[0] ?? [];

        return [
            'file_hash' => $fileHash,
            'signature_type' => $signatureType,
            'is_valid' => isset($sigInfo['summary']) && ($sigInfo['summary'] & GNUPG_SIGSUM_VALID),
            'signer_info' => [
                'fingerprint' => $sigInfo['fingerprint'] ?? null,
                'timestamp' => isset($sigInfo['timestamp']) ? date('c', $sigInfo['timestamp']) : null,
                'validity' => $this->getValidityStatus($sigInfo['status'] ?? 0)
            ],
            'key_fingerprint' => $fingerprint,
            'details' => 'Cryptographic signature verified using GnuPG'
        ];
    }

    private function verifyWithGpgCli(
        string $filePath,
        string $signaturePath,
        string $publicKey,
        string $fileHash,
        string $signatureType
    ): array {
        // Create temporary directory for GPG home
        $gpgHome = sys_get_temp_dir() . '/gpg_' . uniqid();
        mkdir($gpgHome, 0700);

        // Create temporary key file
        $keyFile = $gpgHome . '/public.asc';
        file_put_contents($keyFile, $publicKey);

        try {
            // Import key
            $importCmd = sprintf(
                'gpg --homedir %s --import %s 2>&1',
                escapeshellarg($gpgHome),
                escapeshellarg($keyFile)
            );
            exec($importCmd, $importOutput, $importCode);

            // Verify signature
            $verifyCmd = sprintf(
                'gpg --homedir %s --verify %s %s 2>&1',
                escapeshellarg($gpgHome),
                escapeshellarg($signaturePath),
                escapeshellarg($filePath)
            );
            exec($verifyCmd, $verifyOutput, $verifyCode);

            $isValid = $verifyCode === 0;
            $outputText = implode("\n", $verifyOutput);

            // Extract signer info from output
            $signerInfo = $this->parseGpgOutput($outputText);

            return [
                'file_hash' => $fileHash,
                'signature_type' => $signatureType,
                'is_valid' => $isValid,
                'signer_info' => $signerInfo,
                'key_fingerprint' => $signerInfo['fingerprint'] ?? null,
                'details' => $isValid ? 'Valid signature verified' : 'Invalid or unverifiable signature'
            ];

        } finally {
            // Cleanup
            exec('rm -rf ' . escapeshellarg($gpgHome));
        }
    }

    private function parseGpgOutput(string $output): array {
        $info = [];

        // Extract fingerprint
        if (preg_match('/key fingerprint.*?([0-9A-F\s]{40,})/i', $output, $matches)) {
            $info['fingerprint'] = str_replace(' ', '', $matches[1]);
        }

        // Extract date
        if (preg_match('/signature made (.+?) using/i', $output, $matches)) {
            $info['timestamp'] = $matches[1];
        }

        // Extract user ID
        if (preg_match('/Good signature from "(.+?)"/i', $output, $matches)) {
            $info['user_id'] = $matches[1];
        }

        return $info;
    }

    private function parseSignatureOnly(
        string $signatureContent,
        string $publicKey,
        string $fileHash,
        string $signatureType
    ): array {
        // Can't verify without GPG, but parse what we can
        $info = [
            'note' => 'GPG not available on server - signature parsed but not cryptographically verified'
        ];

        // Try to extract basic info from PGP signature
        if (strpos($signatureContent, '-----BEGIN PGP') !== false) {
            $info['format'] = 'ASCII-armored PGP signature detected';
        }

        return [
            'file_hash' => $fileHash,
            'signature_type' => $signatureType,
            'is_valid' => false,
            'signer_info' => $info,
            'details' => 'Server does not have GPG available for signature verification. Please install GPG or enable the gnupg PHP extension.'
        ];
    }

    private function getValidityStatus(int $status): string {
        if ($status === 0) return 'Valid';
        if ($status === 1) return 'Key expired';
        if ($status === 2) return 'Signature expired';
        if ($status === 3) return 'Key revoked';
        return 'Invalid';
    }

    private function detectVerificationType(string $filename): string {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, ['jar', 'war', 'ear'])) {
            return 'jar';
        }
        if (in_array($ext, ['air', 'airi'])) {
            return 'air';
        }
        if (in_array($ext, ['app', 'dylib', 'bundle', 'kext', 'framework'])) {
            return 'macho';
        }
        if (in_array($ext, ['md5', 'sha1', 'sha256', 'sha512'])) {
            return 'hash';
        }

        return 'pgp';
    }

    private function verifyHash(string $filePath, string $hashFilePath, string $filename): array {
        $hashContent = trim(file_get_contents($hashFilePath));
        $fileHash = hash_file('sha256', $filePath);

        // Detect hash type
        $hashType = 'sha256';
        $hashExt = strtolower(pathinfo($hashFilePath, PATHINFO_EXTENSION));

        if (in_array($hashExt, ['md5', 'sha1', 'sha256', 'sha512'])) {
            $hashType = $hashExt;
        } elseif (strlen($hashContent) === 32) {
            $hashType = 'md5';
        } elseif (strlen($hashContent) === 40) {
            $hashType = 'sha1';
        } elseif (strlen($hashContent) === 64) {
            $hashType = 'sha256';
        } elseif (strlen($hashContent) === 128) {
            $hashType = 'sha512';
        }

        // Calculate hash with detected algorithm
        $calculatedHash = hash_file($hashType, $filePath);

        // Parse hash file (may contain filename)
        $expectedHash = $hashContent;
        if (preg_match('/^([a-f0-9]+)\s+/i', $hashContent, $matches)) {
            $expectedHash = $matches[1];
        }

        $isValid = strcasecmp($calculatedHash, $expectedHash) === 0;

        return [
            'file_hash' => $fileHash,
            'signature_type' => strtoupper($hashType) . ' Hash Verification',
            'is_valid' => $isValid,
            'signer_info' => [
                'hash_algorithm' => strtoupper($hashType),
                'expected_hash' => $expectedHash,
                'calculated_hash' => $calculatedHash
            ],
            'details' => $isValid ? 'Hash matches perfectly' : 'Hash mismatch - file may be corrupted or tampered'
        ];
    }

    private function verifyJarSignature(string $filePath, string $filename, string $type): array {
        $fileHash = hash_file('sha256', $filePath);

        // Check if jarsigner is available
        exec('which jarsigner 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            return [
                'file_hash' => $fileHash,
                'signature_type' => strtoupper($type) . ' Embedded Signature',
                'is_valid' => false,
                'signer_info' => ['note' => 'jarsigner not available on server'],
                'details' => 'Java jarsigner tool is required but not installed on the server'
            ];
        }

        // Verify JAR signature
        $cmd = sprintf('jarsigner -verify -verbose -certs %s 2>&1', escapeshellarg($filePath));
        exec($cmd, $verifyOutput, $verifyCode);

        $outputText = implode("\n", $verifyOutput);
        $isValid = $verifyCode === 0 && strpos($outputText, 'jar verified') !== false;

        $signerInfo = $this->parseJarSignerOutput($outputText);

        return [
            'file_hash' => $fileHash,
            'signature_type' => strtoupper($type) . ' Embedded Signature',
            'is_valid' => $isValid,
            'signer_info' => $signerInfo,
            'details' => $isValid ? 'JAR signature verified successfully' : 'JAR signature invalid or missing'
        ];
    }

    private function parseJarSignerOutput(string $output): array {
        $info = [];

        if (preg_match('/Signed by "(.+?)"/i', $output, $matches)) {
            $info['signer'] = $matches[1];
        }

        if (preg_match('/Valid from: (.+?) until: (.+?)$/im', $output, $matches)) {
            $info['valid_from'] = trim($matches[1]);
            $info['valid_until'] = trim($matches[2]);
        }

        if (preg_match('/Signature algorithm: (.+?)$/im', $output, $matches)) {
            $info['algorithm'] = trim($matches[1]);
        }

        if (preg_match('/with a ([0-9]+)-bit/i', $output, $matches)) {
            $info['key_size'] = $matches[1] . ' bits';
        }

        return $info;
    }

    private function verifyMacOSSignature(string $filePath, string $filename): array {
        $fileHash = hash_file('sha256', $filePath);

        // Check if codesign is available (only on macOS)
        exec('which codesign 2>&1', $output, $returnCode);
        if ($returnCode !== 0) {
            return [
                'file_hash' => $fileHash,
                'signature_type' => 'macOS Code Signature',
                'is_valid' => false,
                'signer_info' => ['note' => 'codesign not available (requires macOS)'],
                'details' => 'macOS codesign tool is required but not available on this server'
            ];
        }

        // Verify code signature
        $cmd = sprintf('codesign --verify --deep --strict --verbose=2 %s 2>&1', escapeshellarg($filePath));
        exec($cmd, $verifyOutput, $verifyCode);

        $isValid = $verifyCode === 0;

        // Get signature info
        $cmd = sprintf('codesign -dvvv %s 2>&1', escapeshellarg($filePath));
        exec($cmd, $infoOutput, $infoCode);

        $infoText = implode("\n", $infoOutput);
        $signerInfo = $this->parseCodesignOutput($infoText);

        return [
            'file_hash' => $fileHash,
            'signature_type' => 'macOS Code Signature',
            'is_valid' => $isValid,
            'signer_info' => $signerInfo,
            'details' => $isValid ? 'macOS code signature verified successfully' : 'Code signature invalid or missing'
        ];
    }

    private function parseCodesignOutput(string $output): array {
        $info = [];

        if (preg_match('/Authority=(.+?)$/im', $output, $matches)) {
            $info['authority'] = trim($matches[1]);
        }

        if (preg_match('/TeamIdentifier=(.+?)$/im', $output, $matches)) {
            $info['team_id'] = trim($matches[1]);
        }

        if (preg_match('/Identifier=(.+?)$/im', $output, $matches)) {
            $info['bundle_id'] = trim($matches[1]);
        }

        if (preg_match('/Sealed Resources version ([0-9]+)/i', $output, $matches)) {
            $info['seal_version'] = $matches[1];
        }

        if (preg_match('/Timestamp=(.+?)$/im', $output, $matches)) {
            $info['timestamp'] = trim($matches[1]);
        }

        return $info;
    }
}
