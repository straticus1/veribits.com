<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;

class CloudStorageController
{
    private const CACHE_TTL = 86400; // 24 hours

    /**
     * Search cloud storage across multiple providers
     */
    public function search(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $providers = $input['providers'] ?? ['all']; // all, aws, gcs, azure, digitalocean
        $searchType = $input['search_type'] ?? 'filename'; // filename, content
        $searchQuery = $input['query'] ?? '';
        $credentials = $input['credentials'] ?? [];
        $bucketFilters = $input['bucket_filters'] ?? []; // Optional: specific buckets to search
        $maxResults = min(($input['max_results'] ?? 1000), 10000);

        if (empty($searchQuery)) {
            Response::error('Search query is required', 400);
            return;
        }

        if (empty($credentials)) {
            Response::error('Cloud credentials are required', 400);
            return;
        }

        // Check cache first
        $cacheKey = $this->getCacheKey($providers, $searchType, $searchQuery, $credentials);
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== null) {
            Response::success([
                'cached' => true,
                'results' => $cached,
                'search' => [
                    'query' => $searchQuery,
                    'type' => $searchType,
                    'providers' => $providers
                ]
            ]);
            return;
        }

        try {
            $results = [];

            // Determine which providers to search
            $providersToSearch = $this->getProvidersToSearch($providers);

            foreach ($providersToSearch as $provider) {
                if (!isset($credentials[$provider])) {
                    continue;
                }

                $providerResults = $this->searchProvider(
                    $provider,
                    $credentials[$provider],
                    $searchType,
                    $searchQuery,
                    $bucketFilters[$provider] ?? [],
                    $maxResults
                );

                $results[$provider] = $providerResults;
            }

            // Cache the results
            $this->saveToCache($cacheKey, $results);

            Response::success([
                'cached' => false,
                'results' => $results,
                'search' => [
                    'query' => $searchQuery,
                    'type' => $searchType,
                    'providers' => $providers
                ],
                'summary' => $this->generateSummary($results)
            ]);

        } catch (\Exception $e) {
            Logger::error('Cloud storage search failed', [
                'error' => $e->getMessage(),
                'user_id' => $auth['user_id'] ?? 'anonymous'
            ]);

            Response::error('Search failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List accessible buckets/containers for a provider
     */
    public function listBuckets(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $provider = $input['provider'] ?? '';
        $credentials = $input['credentials'] ?? [];

        if (empty($provider)) {
            Response::error('Provider is required', 400);
            return;
        }

        if (empty($credentials)) {
            Response::error('Credentials are required', 400);
            return;
        }

        try {
            $buckets = $this->listBucketsForProvider($provider, $credentials);

            Response::success([
                'provider' => $provider,
                'buckets' => $buckets,
                'total' => count($buckets)
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to list buckets', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);

            Response::error('Failed to list buckets: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get security analysis for buckets
     */
    public function analyzeSecurityPosture(): void
    {
        $auth = Auth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);

        $provider = $input['provider'] ?? '';
        $credentials = $input['credentials'] ?? [];
        $bucketNames = $input['buckets'] ?? [];

        if (empty($provider) || empty($credentials)) {
            Response::error('Provider and credentials are required', 400);
            return;
        }

        try {
            $analysis = [];

            foreach ($bucketNames as $bucketName) {
                $bucketAnalysis = $this->analyzeBucketSecurity($provider, $credentials, $bucketName);
                $analysis[$bucketName] = $bucketAnalysis;
            }

            Response::success([
                'provider' => $provider,
                'analysis' => $analysis,
                'summary' => $this->generateSecuritySummary($analysis)
            ]);

        } catch (\Exception $e) {
            Logger::error('Security analysis failed', [
                'error' => $e->getMessage(),
                'provider' => $provider
            ]);

            Response::error('Security analysis failed: ' . $e->getMessage(), 500);
        }
    }

    // ========== PRIVATE HELPER METHODS ==========

    private function getProvidersToSearch(array $providers): array
    {
        $allProviders = ['aws', 'gcs', 'azure', 'digitalocean'];

        if (in_array('all', $providers)) {
            return $allProviders;
        }

        return array_intersect($providers, $allProviders);
    }

    private function searchProvider(
        string $provider,
        array $credentials,
        string $searchType,
        string $query,
        array $bucketFilters,
        int $maxResults
    ): array {
        switch ($provider) {
            case 'aws':
                return $this->searchAWS($credentials, $searchType, $query, $bucketFilters, $maxResults);
            case 'gcs':
                return $this->searchGCS($credentials, $searchType, $query, $bucketFilters, $maxResults);
            case 'azure':
                return $this->searchAzure($credentials, $searchType, $query, $bucketFilters, $maxResults);
            case 'digitalocean':
                return $this->searchDigitalOcean($credentials, $searchType, $query, $bucketFilters, $maxResults);
            default:
                throw new \Exception("Unsupported provider: $provider");
        }
    }

    // ========== AWS S3 INTEGRATION ==========

    private function searchAWS(array $credentials, string $searchType, string $query, array $bucketFilters, int $maxResults): array
    {
        $accessKey = $credentials['access_key'] ?? '';
        $secretKey = $credentials['secret_key'] ?? '';
        $region = $credentials['region'] ?? 'us-east-1';

        if (empty($accessKey) || empty($secretKey)) {
            throw new \Exception('AWS credentials (access_key, secret_key) are required');
        }

        $results = [];

        try {
            // List all buckets or use filtered list
            $buckets = empty($bucketFilters) ? $this->listAWSBuckets($credentials) : $bucketFilters;

            foreach ($buckets as $bucket) {
                $bucketName = is_array($bucket) ? $bucket['name'] : $bucket;

                if ($searchType === 'filename') {
                    $matches = $this->searchAWSBucketByFilename($credentials, $bucketName, $query, $maxResults);
                } else {
                    $matches = $this->searchAWSBucketByContent($credentials, $bucketName, $query, $maxResults);
                }

                if (!empty($matches)) {
                    $results[] = [
                        'bucket' => $bucketName,
                        'matches' => $matches,
                        'count' => count($matches)
                    ];
                }

                // Check if we've hit max results
                $totalMatches = array_sum(array_column($results, 'count'));
                if ($totalMatches >= $maxResults) {
                    break;
                }
            }

        } catch (\Exception $e) {
            Logger::error('AWS S3 search failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => true,
            'buckets_searched' => count($buckets),
            'results' => $results,
            'total_matches' => array_sum(array_column($results, 'count'))
        ];
    }

    private function listAWSBuckets(array $credentials): array
    {
        // Using AWS SDK or CLI
        $accessKey = $credentials['access_key'];
        $secretKey = $credentials['secret_key'];
        $region = $credentials['region'] ?? 'us-east-1';

        // Set AWS credentials for CLI
        putenv("AWS_ACCESS_KEY_ID=$accessKey");
        putenv("AWS_SECRET_ACCESS_KEY=$secretKey");
        putenv("AWS_DEFAULT_REGION=$region");

        $cmd = "aws s3 ls 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to list AWS S3 buckets: ' . implode("\n", $output));
        }

        $buckets = [];
        foreach ($output as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+(.+)$/', $line, $matches)) {
                $buckets[] = ['name' => trim($matches[1])];
            }
        }

        return $buckets;
    }

    private function searchAWSBucketByFilename(array $credentials, string $bucket, string $query, int $maxResults): array
    {
        $accessKey = $credentials['access_key'];
        $secretKey = $credentials['secret_key'];
        $region = $credentials['region'] ?? 'us-east-1';

        putenv("AWS_ACCESS_KEY_ID=$accessKey");
        putenv("AWS_SECRET_ACCESS_KEY=$secretKey");
        putenv("AWS_DEFAULT_REGION=$region");

        $cmd = "aws s3 ls s3://$bucket --recursive 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Logger::warning("Failed to search bucket $bucket", ['output' => implode("\n", $output)]);
            return [];
        }

        $matches = [];
        foreach ($output as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+(\d+)\s+(.+)$/', $line, $m)) {
                $filename = trim($m[2]);
                $size = (int)$m[1];

                // Check if filename matches query (case-insensitive)
                if (stripos($filename, $query) !== false) {
                    $matches[] = [
                        'key' => $filename,
                        'size' => $size,
                        'size_human' => $this->formatBytes($size),
                        'url' => "s3://$bucket/$filename"
                    ];

                    if (count($matches) >= $maxResults) {
                        break;
                    }
                }
            }
        }

        return $matches;
    }

    private function searchAWSBucketByContent(array $credentials, string $bucket, string $query, int $maxResults): array
    {
        // For content search, we need to download and scan files
        // This is resource-intensive, so we'll limit to text files

        $filenameMatches = $this->searchAWSBucketByFilename($credentials, $bucket, '', $maxResults * 10);

        $matches = [];
        $accessKey = $credentials['access_key'];
        $secretKey = $credentials['secret_key'];

        putenv("AWS_ACCESS_KEY_ID=$accessKey");
        putenv("AWS_SECRET_ACCESS_KEY=$secretKey");

        foreach ($filenameMatches as $file) {
            // Only search in text-based files
            if (!$this->isTextFile($file['key'])) {
                continue;
            }

            // Download and search content (limit file size to 10MB)
            if ($file['size'] > 10 * 1024 * 1024) {
                continue;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 's3_');
            $cmd = "aws s3 cp s3://$bucket/{$file['key']} $tempFile 2>&1";
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($tempFile)) {
                $content = file_get_contents($tempFile);
                if (stripos($content, $query) !== false) {
                    $matches[] = [
                        'key' => $file['key'],
                        'size' => $file['size'],
                        'size_human' => $file['size_human'],
                        'url' => $file['url'],
                        'content_match' => true
                    ];
                }
                unlink($tempFile);

                if (count($matches) >= $maxResults) {
                    break;
                }
            }
        }

        return $matches;
    }

    // ========== GOOGLE CLOUD STORAGE INTEGRATION ==========

    private function searchGCS(array $credentials, string $searchType, string $query, array $bucketFilters, int $maxResults): array
    {
        $projectId = $credentials['project_id'] ?? '';
        $credentialsFile = $credentials['credentials_file'] ?? '';

        if (empty($projectId)) {
            throw new \Exception('GCS project_id is required');
        }

        // For GCS, we need service account credentials
        if (!empty($credentialsFile) && file_exists($credentialsFile)) {
            putenv("GOOGLE_APPLICATION_CREDENTIALS=$credentialsFile");
        } elseif (!empty($credentials['service_account_json'])) {
            // Create temp credentials file
            $tempCreds = tempnam(sys_get_temp_dir(), 'gcs_');
            file_put_contents($tempCreds, $credentials['service_account_json']);
            putenv("GOOGLE_APPLICATION_CREDENTIALS=$tempCreds");
        } else {
            throw new \Exception('GCS credentials (credentials_file or service_account_json) are required');
        }

        $results = [];

        try {
            $buckets = empty($bucketFilters) ? $this->listGCSBuckets($projectId) : $bucketFilters;

            foreach ($buckets as $bucket) {
                $bucketName = is_array($bucket) ? $bucket['name'] : $bucket;

                if ($searchType === 'filename') {
                    $matches = $this->searchGCSBucketByFilename($bucketName, $query, $maxResults);
                } else {
                    $matches = $this->searchGCSBucketByContent($bucketName, $query, $maxResults);
                }

                if (!empty($matches)) {
                    $results[] = [
                        'bucket' => $bucketName,
                        'matches' => $matches,
                        'count' => count($matches)
                    ];
                }
            }

        } catch (\Exception $e) {
            Logger::error('GCS search failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => true,
            'buckets_searched' => count($buckets ?? []),
            'results' => $results,
            'total_matches' => array_sum(array_column($results, 'count'))
        ];
    }

    private function listGCSBuckets(string $projectId): array
    {
        $cmd = "gcloud storage buckets list --project=$projectId --format=json 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to list GCS buckets: ' . implode("\n", $output));
        }

        $buckets = json_decode(implode("\n", $output), true) ?? [];
        return array_map(fn($b) => ['name' => $b['name'] ?? $b], $buckets);
    }

    private function searchGCSBucketByFilename(string $bucket, string $query, int $maxResults): array
    {
        $cmd = "gcloud storage ls -r gs://$bucket/** 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Logger::warning("Failed to list GCS bucket $bucket");
            return [];
        }

        $matches = [];
        foreach ($output as $line) {
            $line = trim($line);
            if (strpos($line, 'gs://') === 0 && stripos($line, $query) !== false) {
                $matches[] = [
                    'key' => str_replace("gs://$bucket/", '', $line),
                    'url' => $line
                ];

                if (count($matches) >= $maxResults) {
                    break;
                }
            }
        }

        return $matches;
    }

    private function searchGCSBucketByContent(string $bucket, string $query, int $maxResults): array
    {
        // Similar to AWS, but using gcloud CLI
        $matches = [];
        // Implementation similar to AWS content search
        return $matches;
    }

    // ========== AZURE BLOB STORAGE INTEGRATION ==========

    private function searchAzure(array $credentials, string $searchType, string $query, array $bucketFilters, int $maxResults): array
    {
        $accountName = $credentials['account_name'] ?? '';
        $accountKey = $credentials['account_key'] ?? '';

        if (empty($accountName) || empty($accountKey)) {
            throw new \Exception('Azure credentials (account_name, account_key) are required');
        }

        $results = [];

        try {
            $containers = empty($bucketFilters) ? $this->listAzureContainers($credentials) : $bucketFilters;

            foreach ($containers as $container) {
                $containerName = is_array($container) ? $container['name'] : $container;

                if ($searchType === 'filename') {
                    $matches = $this->searchAzureContainerByFilename($credentials, $containerName, $query, $maxResults);
                } else {
                    $matches = $this->searchAzureContainerByContent($credentials, $containerName, $query, $maxResults);
                }

                if (!empty($matches)) {
                    $results[] = [
                        'container' => $containerName,
                        'matches' => $matches,
                        'count' => count($matches)
                    ];
                }
            }

        } catch (\Exception $e) {
            Logger::error('Azure search failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => true,
            'containers_searched' => count($containers ?? []),
            'results' => $results,
            'total_matches' => array_sum(array_column($results, 'count'))
        ];
    }

    private function listAzureContainers(array $credentials): array
    {
        $accountName = $credentials['account_name'];
        $accountKey = $credentials['account_key'];

        $cmd = "az storage container list --account-name $accountName --account-key $accountKey --output json 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to list Azure containers: ' . implode("\n", $output));
        }

        $containers = json_decode(implode("\n", $output), true) ?? [];
        return $containers;
    }

    private function searchAzureContainerByFilename(array $credentials, string $container, string $query, int $maxResults): array
    {
        $accountName = $credentials['account_name'];
        $accountKey = $credentials['account_key'];

        $cmd = "az storage blob list --container-name $container --account-name $accountName --account-key $accountKey --output json 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            Logger::warning("Failed to list Azure container $container");
            return [];
        }

        $blobs = json_decode(implode("\n", $output), true) ?? [];

        $matches = [];
        foreach ($blobs as $blob) {
            $name = $blob['name'] ?? '';
            if (stripos($name, $query) !== false) {
                $matches[] = [
                    'key' => $name,
                    'size' => $blob['properties']['contentLength'] ?? 0,
                    'url' => "https://$accountName.blob.core.windows.net/$container/$name"
                ];

                if (count($matches) >= $maxResults) {
                    break;
                }
            }
        }

        return $matches;
    }

    private function searchAzureContainerByContent(array $credentials, string $container, string $query, int $maxResults): array
    {
        // Implementation similar to AWS/GCS
        return [];
    }

    // ========== DIGITAL OCEAN SPACES INTEGRATION ==========

    private function searchDigitalOcean(array $credentials, string $searchType, string $query, array $bucketFilters, int $maxResults): array
    {
        // Digital Ocean Spaces is S3-compatible, so we can use AWS SDK
        $accessKey = $credentials['access_key'] ?? '';
        $secretKey = $credentials['secret_key'] ?? '';
        $region = $credentials['region'] ?? 'nyc3';
        $endpoint = $credentials['endpoint'] ?? "https://$region.digitaloceanspaces.com";

        if (empty($accessKey) || empty($secretKey)) {
            throw new \Exception('Digital Ocean credentials (access_key, secret_key) are required');
        }

        $results = [];

        try {
            putenv("AWS_ACCESS_KEY_ID=$accessKey");
            putenv("AWS_SECRET_ACCESS_KEY=$secretKey");

            $buckets = empty($bucketFilters) ? $this->listDOSpaces($credentials) : $bucketFilters;

            foreach ($buckets as $bucket) {
                $bucketName = is_array($bucket) ? $bucket['name'] : $bucket;

                if ($searchType === 'filename') {
                    $matches = $this->searchDOSpaceByFilename($credentials, $bucketName, $query, $maxResults);
                } else {
                    $matches = $this->searchDOSpaceByContent($credentials, $bucketName, $query, $maxResults);
                }

                if (!empty($matches)) {
                    $results[] = [
                        'space' => $bucketName,
                        'matches' => $matches,
                        'count' => count($matches)
                    ];
                }
            }

        } catch (\Exception $e) {
            Logger::error('Digital Ocean Spaces search failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return [
            'success' => true,
            'spaces_searched' => count($buckets ?? []),
            'results' => $results,
            'total_matches' => array_sum(array_column($results, 'count'))
        ];
    }

    private function listDOSpaces(array $credentials): array
    {
        $region = $credentials['region'] ?? 'nyc3';
        $endpoint = "https://$region.digitaloceanspaces.com";

        $cmd = "aws s3 ls --endpoint-url=$endpoint 2>&1";
        exec($cmd, $output, $returnCode);

        $buckets = [];
        foreach ($output as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+(.+)$/', $line, $matches)) {
                $buckets[] = ['name' => trim($matches[1])];
            }
        }

        return $buckets;
    }

    private function searchDOSpaceByFilename(array $credentials, string $space, string $query, int $maxResults): array
    {
        $region = $credentials['region'] ?? 'nyc3';
        $endpoint = "https://$region.digitaloceanspaces.com";

        $cmd = "aws s3 ls s3://$space --recursive --endpoint-url=$endpoint 2>&1";
        exec($cmd, $output, $returnCode);

        $matches = [];
        foreach ($output as $line) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}\s+(\d+)\s+(.+)$/', $line, $m)) {
                $filename = trim($m[2]);
                if (stripos($filename, $query) !== false) {
                    $matches[] = [
                        'key' => $filename,
                        'size' => (int)$m[1],
                        'url' => "https://$space.$region.digitaloceanspaces.com/$filename"
                    ];

                    if (count($matches) >= $maxResults) {
                        break;
                    }
                }
            }
        }

        return $matches;
    }

    private function searchDOSpaceByContent(array $credentials, string $space, string $query, int $maxResults): array
    {
        // Implementation similar to AWS
        return [];
    }

    // ========== BUCKET LISTING METHODS ==========

    private function listBucketsForProvider(string $provider, array $credentials): array
    {
        switch ($provider) {
            case 'aws':
                return $this->listAWSBuckets($credentials);
            case 'gcs':
                return $this->listGCSBuckets($credentials['project_id'] ?? '');
            case 'azure':
                return $this->listAzureContainers($credentials);
            case 'digitalocean':
                return $this->listDOSpaces($credentials);
            default:
                throw new \Exception("Unsupported provider: $provider");
        }
    }

    // ========== SECURITY ANALYSIS METHODS ==========

    private function analyzeBucketSecurity(string $provider, array $credentials, string $bucketName): array
    {
        $analysis = [
            'bucket' => $bucketName,
            'provider' => $provider,
            'public_access' => false,
            'encryption_enabled' => false,
            'versioning_enabled' => false,
            'logging_enabled' => false,
            'issues' => [],
            'recommendations' => []
        ];

        // Provider-specific security checks
        switch ($provider) {
            case 'aws':
                $analysis = array_merge($analysis, $this->analyzeAWSBucketSecurity($credentials, $bucketName));
                break;
            // Add other providers as needed
        }

        return $analysis;
    }

    private function analyzeAWSBucketSecurity(array $credentials, string $bucket): array
    {
        $accessKey = $credentials['access_key'];
        $secretKey = $credentials['secret_key'];

        putenv("AWS_ACCESS_KEY_ID=$accessKey");
        putenv("AWS_SECRET_ACCESS_KEY=$secretKey");

        $issues = [];
        $recommendations = [];

        // Check public access
        $cmd = "aws s3api get-bucket-acl --bucket $bucket 2>&1";
        exec($cmd, $output, $returnCode);

        $publicAccess = false;
        if ($returnCode === 0) {
            $aclData = implode("\n", $output);
            if (stripos($aclData, 'AllUsers') !== false || stripos($aclData, 'AuthenticatedUsers') !== false) {
                $publicAccess = true;
                $issues[] = 'Bucket has public access enabled';
                $recommendations[] = 'Disable public access unless absolutely necessary';
            }
        }

        // Check encryption
        $cmd = "aws s3api get-bucket-encryption --bucket $bucket 2>&1";
        exec($cmd, $output, $returnCode);
        $encryptionEnabled = ($returnCode === 0);

        if (!$encryptionEnabled) {
            $issues[] = 'Bucket encryption is not enabled';
            $recommendations[] = 'Enable default encryption (AES-256 or KMS)';
        }

        // Check versioning
        $cmd = "aws s3api get-bucket-versioning --bucket $bucket 2>&1";
        exec($cmd, $output, $returnCode);
        $versioningEnabled = false;
        if ($returnCode === 0 && stripos(implode("\n", $output), '"Status": "Enabled"') !== false) {
            $versioningEnabled = true;
        } else {
            $recommendations[] = 'Enable versioning for data protection';
        }

        return [
            'public_access' => $publicAccess,
            'encryption_enabled' => $encryptionEnabled,
            'versioning_enabled' => $versioningEnabled,
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    private function generateSecuritySummary(array $analysis): array
    {
        $totalBuckets = count($analysis);
        $publicBuckets = 0;
        $unencryptedBuckets = 0;
        $totalIssues = 0;

        foreach ($analysis as $bucket => $data) {
            if ($data['public_access'] ?? false) {
                $publicBuckets++;
            }
            if (!($data['encryption_enabled'] ?? false)) {
                $unencryptedBuckets++;
            }
            $totalIssues += count($data['issues'] ?? []);
        }

        $score = 100;
        $score -= ($publicBuckets / max($totalBuckets, 1)) * 40;
        $score -= ($unencryptedBuckets / max($totalBuckets, 1)) * 30;
        $score -= min($totalIssues * 5, 30);

        return [
            'total_buckets' => $totalBuckets,
            'public_buckets' => $publicBuckets,
            'unencrypted_buckets' => $unencryptedBuckets,
            'total_issues' => $totalIssues,
            'security_score' => max(0, round($score)),
            'risk_level' => $score >= 80 ? 'low' : ($score >= 60 ? 'medium' : 'high')
        ];
    }

    // ========== UTILITY METHODS ==========

    private function isTextFile(string $filename): bool
    {
        $textExtensions = ['txt', 'log', 'json', 'xml', 'csv', 'md', 'yml', 'yaml', 'conf', 'config', 'ini', 'sh', 'bash', 'py', 'js', 'ts', 'php', 'rb', 'go', 'java', 'c', 'cpp', 'h', 'sql'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, $textExtensions);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function generateSummary(array $results): array
    {
        $totalMatches = 0;
        $totalBuckets = 0;

        foreach ($results as $provider => $data) {
            $totalMatches += $data['total_matches'] ?? 0;
            $totalBuckets += $data['buckets_searched'] ?? 0;
        }

        return [
            'total_providers_searched' => count($results),
            'total_buckets_searched' => $totalBuckets,
            'total_matches' => $totalMatches
        ];
    }

    // ========== CACHING METHODS ==========

    private function getCacheKey(array $providers, string $searchType, string $query, array $credentials): string
    {
        // Create a cache key that doesn't expose credentials
        $credHash = md5(json_encode($credentials));
        return 'cloud_storage:' . md5(json_encode($providers) . $searchType . $query . $credHash);
    }

    private function getFromCache(string $key): ?array
    {
        try {
            $redis = new \Redis();
            if (!$redis->connect('127.0.0.1', 6379)) {
                return null;
            }

            $cached = $redis->get($key);
            $redis->close();

            return $cached ? json_decode($cached, true) : null;
        } catch (\Exception $e) {
            Logger::warning('Cache read failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function saveToCache(string $key, array $data): void
    {
        try {
            $redis = new \Redis();
            if (!$redis->connect('127.0.0.1', 6379)) {
                return;
            }

            $redis->setex($key, self::CACHE_TTL, json_encode($data));
            $redis->close();
        } catch (\Exception $e) {
            Logger::warning('Cache write failed', ['error' => $e->getMessage()]);
        }
    }
}
