<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class SSLChainResolverController {
    private const UPLOAD_DIR = '/tmp/veribits-ssl-chain';
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_CHAIN_DEPTH = 5;
    private const AIA_FETCH_TIMEOUT = 10; // seconds

    // Known CA certificate repositories
    private const CA_REPOSITORIES = [
        'letsencrypt' => [
            'https://letsencrypt.org/certs/lets-encrypt-r3.pem',
            'https://letsencrypt.org/certs/lets-encrypt-r4.pem',
            'https://letsencrypt.org/certs/isrgrootx1.pem',
            'https://letsencrypt.org/certs/isrg-root-x2.pem'
        ]
    ];

    /**
     * Main endpoint: Analyze certificate chain and identify missing certificates
     */
    public function resolveChain(): void {
        // Optional auth - supports anonymous users with rate limiting
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429, [
                    'reason' => $scanCheck['reason'],
                    'upgrade_url' => '/pricing.html'
                ]);
                return;
            }
        }

        $tempFiles = [];

        try {
            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            $inputType = $_POST['input_type'] ?? 'auto';
            $url = $_POST['url'] ?? null;
            $password = $_POST['password'] ?? null;
            $certificates = [];
            $privateKey = null;
            $domain = null;

            // Handle different input types
            if ($inputType === 'url' || ($inputType === 'auto' && $url)) {
                // Fetch from URL
                if (empty($url)) {
                    Response::error('URL is required', 400);
                    return;
                }

                $domain = $this->extractDomain($url);
                $port = $_POST['port'] ?? 443;

                $certChain = $this->fetchCertificateChainFromUrl($domain, (int)$port);
                $certificates = $certChain['certificates'];

            } elseif (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
                // Handle file upload
                $file = $_FILES['certificate'];

                if ($file['size'] > self::MAX_FILE_SIZE) {
                    Response::error('File too large (max 10MB)', 413);
                    return;
                }

                $tempPath = self::UPLOAD_DIR . '/' . uniqid('cert_', true);
                $tempFiles[] = $tempPath;

                if (!move_uploaded_file($file['tmp_name'], $tempPath)) {
                    throw new \Exception('Failed to save uploaded file');
                }

                // Detect format and parse
                $fileContent = file_get_contents($tempPath);
                $format = $this->detectCertificateFormat($fileContent, $file['name']);

                $parseResult = $this->parseCertificateInput($tempPath, $format, $password);
                $certificates = $parseResult['certificates'];
                $privateKey = $parseResult['private_key'] ?? null;

                // Try to extract domain from certificate
                if (!empty($certificates)) {
                    $parsed = $this->parseCertificateData($certificates[0]);
                    $domain = $parsed['subject']['CN'] ?? null;
                }
            } else {
                Response::error('Either URL or certificate file is required', 400);
                return;
            }

            if (empty($certificates)) {
                Response::error('No certificates found in input', 400);
                return;
            }

            // Build and analyze the certificate chain
            $chainAnalysis = $this->analyzeChain($certificates);

            // Store chain analysis in database
            if ($auth['authenticated']) {
                $this->storeChainResolution(
                    $auth['user_id'],
                    $inputType,
                    $domain,
                    $chainAnalysis
                );
            }

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Logger::info('SSL chain resolution completed', [
                'user_id' => $auth['user_id'],
                'domain' => $domain,
                'chain_complete' => $chainAnalysis['complete']
            ]);

            Response::success([
                'input_type' => $inputType,
                'domain' => $domain,
                'chain' => $chainAnalysis['chain'],
                'complete' => $chainAnalysis['complete'],
                'missing' => $chainAnalysis['missing'],
                'has_private_key' => $privateKey !== null,
                'total_certificates' => count($chainAnalysis['chain']),
                'missing_count' => count($chainAnalysis['missing'])
            ]);

        } catch (\Exception $e) {
            Logger::error('SSL chain resolution failed', [
                'error' => $e->getMessage()
            ]);
            Response::error('Chain resolution failed: ' . $e->getMessage(), 500);
        } finally {
            // Clean up temporary files
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Fetch missing intermediate/root certificates
     */
    public function fetchMissing(): void {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $certificatePem = $body['certificate'] ?? null;

            if (empty($certificatePem)) {
                Response::error('Certificate is required', 400);
                return;
            }

            // Parse the certificate to get AIA extension
            $certResource = openssl_x509_read($certificatePem);
            if ($certResource === false) {
                Response::error('Invalid certificate format', 400);
                return;
            }

            $certData = openssl_x509_parse($certResource);
            $issuerCert = null;

            // Try to fetch from AIA extension
            if (isset($certData['extensions']['authorityInfoAccess'])) {
                $aiaUrls = $this->extractAIAUrls($certData['extensions']['authorityInfoAccess']);

                foreach ($aiaUrls as $url) {
                    $fetched = $this->fetchCertificateFromUrl($url);
                    if ($fetched) {
                        $issuerCert = $fetched;
                        break;
                    }
                }
            }

            if (!$issuerCert) {
                Response::error('Could not fetch issuer certificate', 404, [
                    'message' => 'No AIA extension found or issuer certificate unavailable'
                ]);
                return;
            }

            $parsed = $this->parseCertificateData($issuerCert);

            Response::success([
                'certificate' => $issuerCert,
                'info' => $parsed
            ]);

        } catch (\Exception $e) {
            Logger::error('Fetch missing certificate failed', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to fetch certificate: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Build certificate bundle in requested format
     */
    public function buildBundle(): void {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $tempFiles = [];

        try {
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $certificates = $body['certificates'] ?? [];
            $format = $body['format'] ?? 'pem';
            $password = $body['password'] ?? null;
            $privateKey = $body['private_key'] ?? null;

            if (empty($certificates)) {
                Response::error('Certificates array is required', 400);
                return;
            }

            if (!in_array($format, ['pem', 'pkcs7', 'pkcs12'])) {
                Response::error('Invalid format. Supported: pem, pkcs7, pkcs12', 400);
                return;
            }

            if ($format === 'pkcs12') {
                if (empty($privateKey)) {
                    Response::error('Private key is required for PKCS12 format', 400);
                    return;
                }
                if (empty($password)) {
                    Response::error('Password is required for PKCS12 format', 400);
                    return;
                }
            }

            $bundle = null;
            $filename = 'certificate_bundle';
            $mimeType = 'application/x-pem-file';

            switch ($format) {
                case 'pem':
                    $bundle = $this->buildPEMBundle($certificates);
                    $filename .= '.pem';
                    break;

                case 'pkcs7':
                    $bundle = $this->buildPKCS7Bundle($certificates);
                    $filename .= '.p7b';
                    $mimeType = 'application/pkcs7-mime';
                    break;

                case 'pkcs12':
                    $tempKeyFile = self::UPLOAD_DIR . '/' . uniqid('key_', true);
                    $tempFiles[] = $tempKeyFile;
                    file_put_contents($tempKeyFile, $privateKey);

                    $bundle = $this->buildPKCS12Bundle($certificates, $tempKeyFile, $password);
                    $filename .= '.pfx';
                    $mimeType = 'application/x-pkcs12';

                    // SECURITY: Delete private key immediately
                    @unlink($tempKeyFile);
                    break;
            }

            if (!$bundle) {
                throw new \Exception('Failed to create bundle');
            }

            // Return as base64 encoded data
            Response::success([
                'format' => $format,
                'filename' => $filename,
                'content' => base64_encode($bundle),
                'mime_type' => $mimeType,
                'size' => strlen($bundle)
            ]);

        } catch (\Exception $e) {
            Logger::error('Build bundle failed', [
                'error' => $e->getMessage()
            ]);
            Response::error('Failed to build bundle: ' . $e->getMessage(), 500);
        } finally {
            // SECURITY: Clean up all temporary files including private keys
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Fetch certificate chain from a live website
     */
    private function fetchCertificateChainFromUrl(string $domain, int $port): array {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'capture_peer_cert_chain' => true,
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

        $certificates = [];

        // Get the peer certificate
        if (isset($params['options']['ssl']['peer_certificate'])) {
            openssl_x509_export($params['options']['ssl']['peer_certificate'], $pem);
            $certificates[] = $pem;
        }

        // Get the certificate chain
        if (isset($params['options']['ssl']['peer_certificate_chain'])) {
            foreach ($params['options']['ssl']['peer_certificate_chain'] as $cert) {
                openssl_x509_export($cert, $pem);
                // Avoid duplicates
                if (!in_array($pem, $certificates)) {
                    $certificates[] = $pem;
                }
            }
        }

        return [
            'certificates' => $certificates,
            'domain' => $domain
        ];
    }

    /**
     * Detect certificate format from content and filename
     */
    private function detectCertificateFormat(string $content, string $filename): string {
        // Check for PEM format
        if (strpos($content, '-----BEGIN CERTIFICATE-----') !== false) {
            return 'pem';
        }

        // Check for PKCS12 by extension or binary signature
        if (preg_match('/\.(pfx|p12)$/i', $filename) || substr($content, 0, 2) === "\x30\x82") {
            return 'pkcs12';
        }

        // Check for PKCS7
        if (preg_match('/\.(p7b|p7c)$/i', $filename)) {
            return 'pkcs7';
        }

        // Try to detect DER format
        if (substr($content, 0, 1) === "\x30") {
            return 'der';
        }

        return 'auto';
    }

    /**
     * Parse certificate input based on format
     */
    private function parseCertificateInput(string $path, string $format, ?string $password): array {
        $certificates = [];
        $privateKey = null;
        $content = file_get_contents($path);

        switch ($format) {
            case 'pem':
            case 'auto':
                // Extract all certificates from PEM
                preg_match_all(
                    '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
                    $content,
                    $matches
                );
                $certificates = $matches[0] ?? [];

                // Try to extract private key
                if (preg_match('/-----BEGIN (RSA )?PRIVATE KEY-----.*?-----END (RSA )?PRIVATE KEY-----/s', $content, $keyMatch)) {
                    $privateKey = $keyMatch[0];
                }
                break;

            case 'pkcs12':
                if (empty($password)) {
                    throw new \Exception('Password required for PKCS12 file');
                }

                $certs = [];
                if (!openssl_pkcs12_read($content, $certs, $password)) {
                    throw new \Exception('Failed to read PKCS12 file. Check password.');
                }

                if (isset($certs['cert'])) {
                    $certificates[] = $certs['cert'];
                }
                if (isset($certs['extracerts']) && is_array($certs['extracerts'])) {
                    $certificates = array_merge($certificates, $certs['extracerts']);
                }
                if (isset($certs['pkey'])) {
                    $privateKey = $certs['pkey'];
                }
                break;

            case 'pkcs7':
                $tempOut = self::UPLOAD_DIR . '/' . uniqid('p7b_', true);

                // Export PKCS7 to PEM
                if (openssl_pkcs7_read($content, $certArray)) {
                    foreach ($certArray as $cert) {
                        $certificates[] = $cert;
                    }
                } else {
                    // Try alternative method using command line
                    $tempIn = self::UPLOAD_DIR . '/' . uniqid('p7b_in_', true);
                    file_put_contents($tempIn, $content);

                    $cmd = sprintf(
                        'openssl pkcs7 -print_certs -in %s -out %s 2>&1',
                        escapeshellarg($tempIn),
                        escapeshellarg($tempOut)
                    );

                    @exec($cmd, $output, $returnCode);

                    if ($returnCode === 0 && file_exists($tempOut)) {
                        $pemContent = file_get_contents($tempOut);
                        preg_match_all(
                            '/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',
                            $pemContent,
                            $matches
                        );
                        $certificates = $matches[0] ?? [];
                    }

                    @unlink($tempIn);
                    @unlink($tempOut);
                }
                break;

            case 'der':
                // Convert DER to PEM
                $cert = openssl_x509_read($content);
                if ($cert !== false) {
                    openssl_x509_export($cert, $pem);
                    $certificates[] = $pem;
                } else {
                    throw new \Exception('Failed to read DER certificate');
                }
                break;
        }

        return [
            'certificates' => $certificates,
            'private_key' => $privateKey
        ];
    }

    /**
     * Analyze certificate chain - build chain and identify missing certificates
     */
    private function analyzeChain(array $certificates): array {
        $chain = [];
        $missing = [];

        // Parse all certificates
        $parsedCerts = [];
        foreach ($certificates as $certPem) {
            $parsed = $this->parseCertificateData($certPem);
            $parsed['pem'] = $certPem;
            $parsedCerts[] = $parsed;
        }

        // Build the chain starting from the leaf certificate
        $orderedChain = $this->buildCertificateChain($parsedCerts);

        // Check if chain is complete (ends with a self-signed root)
        $complete = false;
        if (!empty($orderedChain)) {
            $lastCert = end($orderedChain);
            // Root certificate is self-signed
            $complete = $this->isSelfSigned($lastCert);

            // If not complete, try to fetch missing intermediates
            if (!$complete) {
                $missingCerts = $this->findMissingCertificates($orderedChain);
                $missing = $missingCerts;
            }
        }

        return [
            'chain' => $orderedChain,
            'complete' => $complete,
            'missing' => $missing
        ];
    }

    /**
     * Build certificate chain by ordering certificates from leaf to root
     */
    private function buildCertificateChain(array $parsedCerts): array {
        $chain = [];
        $remaining = $parsedCerts;

        // Find the leaf certificate (one that isn't an issuer of any other)
        $leaf = null;
        foreach ($remaining as $idx => $cert) {
            $isIssuer = false;
            foreach ($remaining as $otherIdx => $otherCert) {
                if ($idx === $otherIdx) continue;

                if ($this->isIssuerOf($cert, $otherCert)) {
                    $isIssuer = true;
                    break;
                }
            }

            if (!$isIssuer) {
                $leaf = $cert;
                unset($remaining[$idx]);
                break;
            }
        }

        if (!$leaf) {
            // If we can't find a clear leaf, just use the first certificate
            $leaf = array_shift($remaining);
        }

        $chain[] = $leaf;
        $current = $leaf;

        // Build the chain by following issuer relationships
        $maxDepth = self::MAX_CHAIN_DEPTH;
        while (count($remaining) > 0 && $maxDepth > 0) {
            $found = false;

            foreach ($remaining as $idx => $cert) {
                if ($this->isIssuerOf($cert, $current)) {
                    $chain[] = $cert;
                    $current = $cert;
                    unset($remaining[$idx]);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                break;
            }

            $maxDepth--;
        }

        // Add any remaining certificates at the end
        foreach ($remaining as $cert) {
            $chain[] = $cert;
        }

        return $chain;
    }

    /**
     * Check if certA is the issuer of certB
     */
    private function isIssuerOf(array $certA, array $certB): bool {
        // Method 1: Compare AKI of certB with SKI of certA
        if (!empty($certB['authority_key_identifier']) && !empty($certA['subject_key_identifier'])) {
            if ($certB['authority_key_identifier'] === $certA['subject_key_identifier']) {
                return true;
            }
        }

        // Method 2: Compare issuer DN of certB with subject DN of certA
        if (isset($certB['issuer']) && isset($certA['subject'])) {
            if ($this->compareDN($certB['issuer'], $certA['subject'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Compare two Distinguished Names
     */
    private function compareDN(array $dn1, array $dn2): bool {
        $keys = ['CN', 'O', 'OU', 'C', 'ST', 'L'];

        foreach ($keys as $key) {
            $val1 = $dn1[$key] ?? null;
            $val2 = $dn2[$key] ?? null;

            // If both have the value, they must match
            if ($val1 !== null && $val2 !== null && $val1 !== $val2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if certificate is self-signed (root certificate)
     */
    private function isSelfSigned(array $cert): bool {
        // Self-signed if subject equals issuer
        return $this->compareDN($cert['subject'], $cert['issuer']);
    }

    /**
     * Find missing certificates in the chain
     */
    private function findMissingCertificates(array $chain): array {
        $missing = [];

        if (empty($chain)) {
            return $missing;
        }

        $lastCert = end($chain);

        // If the last certificate is not self-signed, we're missing the issuer
        if (!$this->isSelfSigned($lastCert)) {
            $missing[] = [
                'type' => 'issuer',
                'for_certificate' => $lastCert['subject']['CN'] ?? 'Unknown',
                'issuer_dn' => $lastCert['issuer'],
                'authority_key_identifier' => $lastCert['authority_key_identifier'] ?? null,
                'aia_urls' => $this->getAIAUrls($lastCert)
            ];
        }

        return $missing;
    }

    /**
     * Get AIA URLs from certificate
     */
    private function getAIAUrls(array $cert): array {
        if (!isset($cert['extensions']['authorityInfoAccess'])) {
            return [];
        }

        return $this->extractAIAUrls($cert['extensions']['authorityInfoAccess']);
    }

    /**
     * Extract AIA URLs from extension value
     */
    private function extractAIAUrls(string $aiaExtension): array {
        $urls = [];

        // Parse "CA Issuers - URI:http://..." format
        if (preg_match_all('/CA Issuers - URI:(https?:\/\/[^\s,]+)/i', $aiaExtension, $matches)) {
            $urls = array_merge($urls, $matches[1]);
        }

        // Also try simpler URI: format
        if (preg_match_all('/URI:(https?:\/\/[^\s,]+)/i', $aiaExtension, $matches)) {
            foreach ($matches[1] as $url) {
                if (!in_array($url, $urls)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Fetch certificate from URL
     */
    private function fetchCertificateFromUrl(string $url): ?string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::AIA_FETCH_TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$content) {
            return null;
        }

        // Content might be DER format, convert to PEM
        if (strpos($content, '-----BEGIN CERTIFICATE-----') === false) {
            // Try to read as DER and convert
            $cert = openssl_x509_read($content);
            if ($cert !== false) {
                openssl_x509_export($cert, $pem);
                return $pem;
            }
            return null;
        }

        return $content;
    }

    /**
     * Parse certificate data
     */
    private function parseCertificateData(string $certPem): array {
        $cert = openssl_x509_read($certPem);

        if ($cert === false) {
            throw new \Exception('Failed to parse certificate');
        }

        $certData = openssl_x509_parse($cert);

        if ($certData === false) {
            throw new \Exception('Failed to parse certificate data');
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
            if (preg_match('/keyid:([A-F0-9:]+)/i', $extensions['authorityKeyIdentifier'], $matches)) {
                $authorityKeyId = str_replace(':', '', $matches[1]);
            }
        }

        // Extract Subject Alternative Names
        $san = [];
        if (isset($extensions['subjectAltName'])) {
            $san = array_map('trim', explode(',', $extensions['subjectAltName']));
        }

        // Calculate fingerprints
        openssl_x509_export($cert, $pemOut);
        $fingerprints = [
            'sha256' => hash('sha256', base64_decode(preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s/', '', $pemOut))),
            'sha1' => hash('sha1', base64_decode(preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s/', '', $pemOut)))
        ];

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
            'version' => $certData['version'] ?? null,
            'fingerprints' => $fingerprints,
            'is_ca' => isset($extensions['basicConstraints']) &&
                      strpos($extensions['basicConstraints'], 'CA:TRUE') !== false
        ];
    }

    /**
     * Build PEM bundle from certificates array
     */
    private function buildPEMBundle(array $certificates): string {
        return implode("\n", $certificates);
    }

    /**
     * Build PKCS7 bundle from certificates array
     */
    private function buildPKCS7Bundle(array $certificates): string {
        $tempDir = self::UPLOAD_DIR;
        $tempIn = $tempDir . '/' . uniqid('p7b_in_', true);
        $tempOut = $tempDir . '/' . uniqid('p7b_out_', true);

        try {
            // Write all certificates to a PEM file
            file_put_contents($tempIn, implode("\n", $certificates));

            // Convert to PKCS7
            $cmd = sprintf(
                'openssl crl2pkcs7 -nocrl -certfile %s -out %s 2>&1',
                escapeshellarg($tempIn),
                escapeshellarg($tempOut)
            );

            @exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($tempOut)) {
                throw new \Exception('Failed to create PKCS7 bundle');
            }

            $bundle = file_get_contents($tempOut);

            return $bundle;

        } finally {
            @unlink($tempIn);
            @unlink($tempOut);
        }
    }

    /**
     * Build PKCS12 bundle from certificates and private key
     */
    private function buildPKCS12Bundle(array $certificates, string $keyPath, string $password): string {
        $tempDir = self::UPLOAD_DIR;
        $tempCert = $tempDir . '/' . uniqid('cert_', true);
        $tempOut = $tempDir . '/' . uniqid('p12_', true);
        $tempChain = null;

        try {
            // First certificate is the end-entity cert
            $endEntityCert = $certificates[0];
            file_put_contents($tempCert, $endEntityCert);

            // Remaining certificates form the chain
            $chainCerts = array_slice($certificates, 1);

            if (!empty($chainCerts)) {
                $tempChain = $tempDir . '/' . uniqid('chain_', true);
                file_put_contents($tempChain, implode("\n", $chainCerts));
            }

            // Build PKCS12 using command line (more reliable than PHP function)
            $cmd = sprintf(
                'openssl pkcs12 -export -out %s -inkey %s -in %s %s -password pass:%s 2>&1',
                escapeshellarg($tempOut),
                escapeshellarg($keyPath),
                escapeshellarg($tempCert),
                $tempChain ? '-certfile ' . escapeshellarg($tempChain) : '',
                escapeshellarg($password)
            );

            @exec($cmd, $output, $returnCode);

            if ($returnCode !== 0 || !file_exists($tempOut)) {
                throw new \Exception('Failed to create PKCS12 bundle: ' . implode("\n", $output));
            }

            $bundle = file_get_contents($tempOut);

            return $bundle;

        } finally {
            @unlink($tempCert);
            @unlink($tempOut);
            if ($tempChain) {
                @unlink($tempChain);
            }
        }
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $url): string {
        // Remove protocol
        $domain = preg_replace('#^https?://#i', '', $url);
        // Remove path
        $domain = explode('/', $domain)[0];
        // Remove port
        $domain = explode(':', $domain)[0];

        return $domain;
    }

    /**
     * Verify if a private key matches a certificate
     */
    public function verifyKeyPair(): void {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429, [
                    'reason' => $scanCheck['reason'],
                    'upgrade_url' => '/pricing.html'
                ]);
                return;
            }
        }

        $tempFiles = [];

        try {
            // Require both certificate and private key
            if (!isset($_FILES['certificate']) || $_FILES['certificate']['error'] !== UPLOAD_ERR_OK) {
                Response::error('Certificate file is required', 400);
                return;
            }

            if (!isset($_FILES['private_key']) || $_FILES['private_key']['error'] !== UPLOAD_ERR_OK) {
                Response::error('Private key file is required', 400);
                return;
            }

            // Create upload directory
            if (!is_dir(self::UPLOAD_DIR)) {
                mkdir(self::UPLOAD_DIR, 0755, true);
            }

            // Save uploaded files
            $certPath = self::UPLOAD_DIR . '/' . uniqid('cert_', true);
            $keyPath = self::UPLOAD_DIR . '/' . uniqid('key_', true);
            $tempFiles[] = $certPath;
            $tempFiles[] = $keyPath;

            if (!move_uploaded_file($_FILES['certificate']['tmp_name'], $certPath)) {
                throw new \Exception('Failed to save certificate file');
            }

            if (!move_uploaded_file($_FILES['private_key']['tmp_name'], $keyPath)) {
                throw new \Exception('Failed to save private key file');
            }

            // Perform verification
            $verificationResult = $this->verifyKeyMatchInternal($certPath, $keyPath);

            // Parse certificate for additional info
            $certContent = file_get_contents($certPath);
            $certData = $this->parseCertificateData($certContent);

            // Increment scan count for anonymous users
            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success([
                'match' => $verificationResult['match'],
                'verification_method' => $verificationResult['method'],
                'details' => $verificationResult['details'],
                'certificate_info' => [
                    'subject' => $certData['subject'],
                    'issuer' => $certData['issuer'],
                    'validity' => $certData['validity'],
                    'fingerprints' => $certData['fingerprints'],
                    'key_usage' => $certData['key_usage'] ?? []
                ]
            ]);

        } catch (\Exception $e) {
            Logger::error('Key pair verification failed', [
                'error' => $e->getMessage()
            ]);
            Response::error('Verification failed: ' . $e->getMessage(), 500);
        } finally {
            // Clean up temporary files
            foreach ($tempFiles as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Internal method to verify key match using modulus and public key comparison
     */
    private function verifyKeyMatchInternal(string $certPath, string $keyPath): array {
        $result = [
            'match' => false,
            'method' => 'modulus_comparison',
            'details' => []
        ];

        // Method 1: Compare modulus from cert and key using OpenSSL commands
        $certModulus = null;
        $keyModulus = null;

        // Get certificate modulus
        $output = [];
        $returnCode = 0;
        $cmd = sprintf('openssl x509 -noout -modulus -in %s 2>&1', escapeshellarg($certPath));
        @exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/Modulus=([A-F0-9]+)/i', $line, $matches)) {
                    $certModulus = $matches[1];
                    break;
                }
            }
        }

        // Get key modulus (try RSA first, then EC)
        $output = [];
        $returnCode = 0;
        $cmd = sprintf('openssl rsa -noout -modulus -in %s 2>&1', escapeshellarg($keyPath));
        @exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (preg_match('/Modulus=([A-F0-9]+)/i', $line, $matches)) {
                    $keyModulus = $matches[1];
                    break;
                }
            }
        } else {
            // Try EC key
            $output = [];
            $cmd = sprintf('openssl ec -noout -text -in %s 2>&1', escapeshellarg($keyPath));
            @exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                $result['method'] = 'ec_key_comparison';
            }
        }

        $result['details']['certificate_modulus'] = $certModulus ? substr($certModulus, 0, 64) . '...' : 'unavailable';
        $result['details']['key_modulus'] = $keyModulus ? substr($keyModulus, 0, 64) . '...' : 'unavailable';

        if ($certModulus && $keyModulus) {
            $result['match'] = ($certModulus === $keyModulus);
            $result['details']['modulus_match'] = $result['match'];
        }

        // Method 2: Try to use PHP's OpenSSL functions for public key comparison
        if (!$result['match']) {
            try {
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
            } catch (\Exception $e) {
                // If this method fails, we still have the modulus comparison result
                $result['details']['public_key_comparison_error'] = $e->getMessage();
            }
        }

        // Method 3: Verify using Subject Key Identifier and Authority Key Identifier
        if ($result['match']) {
            try {
                $certContent = file_get_contents($certPath);
                $certData = $this->parseCertificateData($certContent);

                if (isset($certData['subject_key_identifier'])) {
                    $result['details']['subject_key_identifier'] = $certData['subject_key_identifier'];
                }

                if (isset($certData['authority_key_identifier'])) {
                    $result['details']['authority_key_identifier'] = $certData['authority_key_identifier'];
                }
            } catch (\Exception $e) {
                // SKI/AKI extraction is optional
            }
        }

        return $result;
    }

    /**
     * Store chain resolution in database
     */
    private function storeChainResolution(
        string $userId,
        string $inputType,
        ?string $domain,
        array $chainAnalysis
    ): void {
        $db = Database::getConnection();

        $leafCert = !empty($chainAnalysis['chain']) ? $chainAnalysis['chain'][0] : null;
        $fingerprint = $leafCert['fingerprints']['sha256'] ?? null;

        $stmt = $db->prepare('
            INSERT INTO ssl_chain_resolutions
            (user_id, input_type, domain, leaf_cert_fingerprint, missing_count,
             resolved_count, chain_complete, created_at)
            VALUES ($1, $2, $3, $4, $5, $6, $7, NOW())
        ');

        $stmt->execute([
            $userId,
            $inputType,
            $domain,
            $fingerprint,
            count($chainAnalysis['missing']),
            count($chainAnalysis['chain']),
            $chainAnalysis['complete'] ? 't' : 'f'
        ]);
    }
}
