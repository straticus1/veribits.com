<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class SSLGeneratorController
{
    /**
     * Generate SSL Certificate Signing Request (CSR) and Private Key
     */
    public function generate(): void
    {
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

        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['country', 'state', 'city', 'organization', 'common_name'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                Response::error("Field '$field' is required", 400);
                return;
            }
        }

        $country = substr($input['country'], 0, 2); // 2-letter country code
        $state = $input['state'];
        $city = $input['city'];
        $organization = $input['organization'];
        $organizationalUnit = $input['organizational_unit'] ?? '';
        $commonName = $input['common_name'];
        $email = $input['email'] ?? '';
        $keySize = (int)($input['key_size'] ?? 2048);

        // Validate key size
        if (!in_array($keySize, [2048, 3072, 4096])) {
            $keySize = 2048;
        }

        try {
            // Generate private key
            $privateKey = openssl_pkey_new([
                'private_key_bits' => $keySize,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if (!$privateKey) {
                throw new \Exception('Failed to generate private key');
            }

            // Export private key
            openssl_pkey_export($privateKey, $privateKeyPEM);

            // Build distinguished name
            $dn = [
                'countryName' => $country,
                'stateOrProvinceName' => $state,
                'localityName' => $city,
                'organizationName' => $organization,
                'commonName' => $commonName
            ];

            if (!empty($organizationalUnit)) {
                $dn['organizationalUnitName'] = $organizationalUnit;
            }

            if (!empty($email)) {
                $dn['emailAddress'] = $email;
            }

            // Generate CSR
            $csr = openssl_csr_new($dn, $privateKey, [
                'digest_alg' => 'sha256',
                'req_extensions' => 'v3_req'
            ]);

            if (!$csr) {
                throw new \Exception('Failed to generate CSR');
            }

            // Export CSR
            openssl_csr_export($csr, $csrPEM);

            // Parse CSR to get subject info
            $csrDetails = openssl_csr_get_subject($csr);

            // Create unique filename
            $timestamp = date('YmdHis');
            $safeCommonName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $commonName);
            $filenameBase = "ssl_{$safeCommonName}_{$timestamp}";

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('SSL CSR and Private Key generated successfully', [
                'csr' => $csrPEM,
                'private_key' => $privateKeyPEM,
                'subject' => $csrDetails,
                'key_size' => $keySize,
                'algorithm' => 'RSA',
                'signature_algorithm' => 'SHA-256',
                'filename_base' => $filenameBase,
                'files' => [
                    'csr' => $filenameBase . '.csr',
                    'key' => $filenameBase . '.key',
                    'zip' => $filenameBase . '.zip'
                ]
            ]);

        } catch (\Exception $e) {
            Response::error('Failed to generate SSL CSR: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate and parse CSR file
     */
    public function validateCSR(): void
    {
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

        if (!isset($_FILES['csr_file']) || $_FILES['csr_file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('CSR file is required', 400);
            return;
        }

        $file = $_FILES['csr_file'];
        $csrContent = file_get_contents($file['tmp_name']);

        // Clean up file
        @unlink($file['tmp_name']);

        try {
            // Read CSR
            $csr = openssl_csr_get_subject($csrContent, false);

            if ($csr === false) {
                throw new \Exception('Invalid CSR format');
            }

            // Get public key from CSR
            $publicKey = openssl_csr_get_public_key($csrContent);
            $keyDetails = openssl_pkey_get_details($publicKey);

            // Parse CSR details
            $csrDetails = openssl_csr_get_subject($csrContent);

            // Extract signature algorithm and other details
            $result = [
                'is_valid' => true,
                'subject' => $csrDetails,
                'public_key' => [
                    'algorithm' => $this->getKeyType($keyDetails['type']),
                    'bits' => $keyDetails['bits'],
                    'key' => $keyDetails['key']
                ],
                'signature_algorithm' => 'SHA-256 with RSA', // Default, can be extracted if needed
                'extensions' => []
            ];

            // Common name
            if (isset($csrDetails['CN'])) {
                $result['common_name'] = $csrDetails['CN'];
            }

            // Organization
            if (isset($csrDetails['O'])) {
                $result['organization'] = $csrDetails['O'];
            }

            // Country
            if (isset($csrDetails['C'])) {
                $result['country'] = $csrDetails['C'];
            }

            // State
            if (isset($csrDetails['ST'])) {
                $result['state'] = $csrDetails['ST'];
            }

            // City
            if (isset($csrDetails['L'])) {
                $result['city'] = $csrDetails['L'];
            }

            // Organizational Unit
            if (isset($csrDetails['OU'])) {
                $result['organizational_unit'] = $csrDetails['OU'];
            }

            // Email
            if (isset($csrDetails['emailAddress'])) {
                $result['email'] = $csrDetails['emailAddress'];
            }

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('CSR validated successfully', $result);

        } catch (\Exception $e) {
            Response::error('Invalid CSR: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get key type name from OpenSSL constant
     */
    private function getKeyType(int $type): string
    {
        switch ($type) {
            case OPENSSL_KEYTYPE_RSA:
                return 'RSA';
            case OPENSSL_KEYTYPE_DSA:
                return 'DSA';
            case OPENSSL_KEYTYPE_DH:
                return 'DH';
            case OPENSSL_KEYTYPE_EC:
                return 'EC';
            default:
                return 'Unknown';
        }
    }
}
