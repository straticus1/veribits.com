<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;
use VeriBits\Utils\Config;

class IDVerificationController {
    private const UPLOAD_DIR = '/tmp/veribits-id-verify';
    private const MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB
    private const EXTERNAL_API_URL = 'https://idverify.aeims.app';

    public function verify(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;
        $apiKeyId = $claims['key_id'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        // Validate file uploads
        if (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
            Response::error('ID document upload required', 400);
            return;
        }

        if (!isset($_FILES['selfie']) || $_FILES['selfie']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Selfie photo upload required', 400);
            return;
        }

        $idDoc = $_FILES['id_document'];
        $selfie = $_FILES['selfie'];

        // Validate file sizes
        if ($idDoc['size'] > self::MAX_FILE_SIZE) {
            Response::error('ID document too large (max 20MB)', 413);
            return;
        }

        if ($selfie['size'] > self::MAX_FILE_SIZE) {
            Response::error('Selfie too large (max 20MB)', 413);
            return;
        }

        // Validate file types (images only)
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/heic', 'image/webp'];

        $idDocType = mime_content_type($idDoc['tmp_name']);
        $selfieType = mime_content_type($selfie['tmp_name']);

        if (!in_array($idDocType, $allowedTypes)) {
            Response::error('Invalid ID document format. Allowed: JPEG, PNG, HEIC, WebP', 400);
            return;
        }

        if (!in_array($selfieType, $allowedTypes)) {
            Response::error('Invalid selfie format. Allowed: JPEG, PNG, HEIC, WebP', 400);
            return;
        }

        try {
            $startTime = microtime(true);

            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            // Calculate file hashes for record keeping
            $idDocHash = hash_file('sha256', $idDoc['tmp_name']);
            $selfieHash = hash_file('sha256', $selfie['tmp_name']);

            // Move files to temp location
            $idDocPath = self::UPLOAD_DIR . '/' . $idDocHash;
            $selfiePath = self::UPLOAD_DIR . '/' . $selfieHash;

            if (!move_uploaded_file($idDoc['tmp_name'], $idDocPath)) {
                throw new \Exception('Failed to save ID document');
            }

            if (!move_uploaded_file($selfie['tmp_name'], $selfiePath)) {
                @unlink($idDocPath);
                throw new \Exception('Failed to save selfie photo');
            }

            // Submit to external ID verification service
            $verificationResult = $this->submitToExternalAPI($idDocPath, $selfiePath);

            // Clean up temporary files
            @unlink($idDocPath);
            @unlink($selfiePath);

            $verificationTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // Generate badge ID
            $badgeId = 'idverify_' . substr(md5($idDocHash . $selfieHash), 0, 16);

            // Store verification result
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO id_verifications
                (user_id, api_key_id, verification_status, id_document_hash, selfie_hash,
                 external_verification_id, verification_details, confidence_score,
                 face_match_score, document_type, extracted_data, warnings, badge_id)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
                RETURNING id
            ');

            $stmt->execute([
                $userId,
                $apiKeyId,
                $verificationResult['status'],
                $idDocHash,
                $selfieHash,
                $verificationResult['external_id'] ?? null,
                json_encode($verificationResult['details'] ?? null),
                $verificationResult['confidence_score'] ?? null,
                $verificationResult['face_match_score'] ?? null,
                $verificationResult['document_type'] ?? null,
                json_encode($verificationResult['extracted_data'] ?? null),
                json_encode($verificationResult['warnings'] ?? []),
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('ID verification completed', [
                'user_id' => $userId,
                'status' => $verificationResult['status'],
                'confidence_score' => $verificationResult['confidence_score'] ?? null
            ]);

            Response::success([
                'type' => 'id_verification',
                'verification_status' => $verificationResult['status'],
                'verified' => $verificationResult['status'] === 'verified',
                'confidence_score' => $verificationResult['confidence_score'] ?? null,
                'face_match_score' => $verificationResult['face_match_score'] ?? null,
                'document_type' => $verificationResult['document_type'] ?? null,
                'extracted_data' => $verificationResult['extracted_data'] ?? null,
                'warnings' => $verificationResult['warnings'] ?? [],
                'verification_time_ms' => $verificationTimeMs,
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'verified_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('ID verification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            // Clean up temp files on error
            if (isset($idDocPath) && file_exists($idDocPath)) {
                @unlink($idDocPath);
            }
            if (isset($selfiePath) && file_exists($selfiePath)) {
                @unlink($selfiePath);
            }

            Response::error('ID verification failed: ' . $e->getMessage(), 500);
        }
    }

    private function submitToExternalAPI(string $idDocPath, string $selfiePath): array {
        // Prepare multipart/form-data request
        $boundary = '----WebKitFormBoundary' . uniqid();

        $idDocContent = file_get_contents($idDocPath);
        $selfieContent = file_get_contents($selfiePath);

        // Build multipart body
        $body = '';

        // Add ID document
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"id_document\"; filename=\"id.jpg\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= $idDocContent . "\r\n";

        // Add selfie
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"selfie\"; filename=\"selfie.jpg\"\r\n";
        $body .= "Content-Type: image/jpeg\r\n\r\n";
        $body .= $selfieContent . "\r\n";

        $body .= "--{$boundary}--\r\n";

        // Get API key from config if available
        $apiKey = getenv('ID_VERIFY_API_KEY') ?: '';

        // Make request to external API
        $ch = curl_init(self::EXTERNAL_API_URL . '/api/verify');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: multipart/form-data; boundary={$boundary}",
            "Authorization: Bearer {$apiKey}",
            'User-Agent: VeriBits/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::warning('External ID verification API error', [
                'error' => $curlError,
                'http_code' => $httpCode
            ]);

            // Fallback to mock response if external API is unavailable
            return $this->getMockVerificationResult();
        }

        if ($httpCode !== 200) {
            Logger::warning('External ID verification API returned non-200', [
                'http_code' => $httpCode,
                'response' => substr($response, 0, 500)
            ]);

            // Fallback to mock response
            return $this->getMockVerificationResult();
        }

        // Parse response
        $result = json_decode($response, true);

        if (!$result) {
            Logger::warning('Failed to parse external API response', [
                'response' => substr($response, 0, 500)
            ]);

            return $this->getMockVerificationResult();
        }

        // Map external API response to our format
        return [
            'status' => $result['status'] ?? 'pending',
            'external_id' => $result['verification_id'] ?? $result['id'] ?? null,
            'confidence_score' => $result['confidence_score'] ?? $result['confidence'] ?? null,
            'face_match_score' => $result['face_match_score'] ?? $result['face_match'] ?? null,
            'document_type' => $result['document_type'] ?? $result['id_type'] ?? null,
            'extracted_data' => $result['extracted_data'] ?? $result['data'] ?? null,
            'warnings' => $result['warnings'] ?? $result['flags'] ?? [],
            'details' => $result
        ];
    }

    private function getMockVerificationResult(): array {
        // Mock response when external API is unavailable (for testing)
        Logger::info('Using mock ID verification result (external API unavailable)');

        return [
            'status' => 'pending',
            'external_id' => 'mock_' . uniqid(),
            'confidence_score' => 85.5,
            'face_match_score' => 92.3,
            'document_type' => 'drivers_license',
            'extracted_data' => [
                'first_name' => 'JOHN',
                'last_name' => 'DOE',
                'date_of_birth' => '1990-01-01',
                'document_number' => 'REDACTED',
                'expiration_date' => '2028-12-31',
                'issuing_country' => 'USA'
            ],
            'warnings' => [
                'External API unavailable - mock result returned',
                'This is a test verification'
            ],
            'details' => [
                'message' => 'Mock verification result',
                'timestamp' => date('c')
            ]
        ];
    }
}
