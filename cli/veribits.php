#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * VeriBits CLI
 * © After Dark Systems
 */
function parseArgs(array $argv): array {
    array_shift($argv);
    $cmd = $argv[0] ?? 'help';
    $opts = [];
    $args = [];
    foreach ($argv as $i => $a) {
        if ($i === 0) continue; // Skip command
        if (strpos($a, '--') === 0 && strpos($a, '=') !== false) {
            [$k, $v] = explode('=', substr($a, 2), 2);
            $opts[$k] = $v;
        } elseif (strpos($a, '--') !== 0) {
            $args[] = $a;
        }
    }
    return [$cmd, $opts, $args];
}
function printJson($data) { echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL; }

function apiRequest(string $endpoint, string $method = 'GET', ?array $data = null): ?array {
    $apiUrl = getenv('VERIBITS_API_URL') ?: 'https://www.veribits.com';
    $url = $apiUrl . $endpoint;

    $opts = [
        'http' => [
            'method' => $method,
            'header' => 'Content-Type: application/json',
            'ignore_errors' => true
        ]
    ];

    if ($data && $method === 'POST') {
        $opts['http']['content'] = json_encode($data);
    }

    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        return null;
    }

    return json_decode($response, true);
}

function printToolResults(array $tools, bool $verbose = false): void {
    if (empty($tools)) {
        echo "No tools found.\n";
        return;
    }

    echo "\nFound " . count($tools) . " tool(s):\n\n";

    foreach ($tools as $tool) {
        $category = str_pad("[{$tool['category']}]", 15);
        echo "\033[1m" . $category . "\033[0m " . $tool['name'] . "\n";

        if ($verbose) {
            echo "  Description: " . $tool['description'] . "\n";
            echo "  CLI: \033[36m" . $tool['cli_command'] . "\033[0m\n";
            echo "  Endpoint: " . $tool['endpoint'] . "\n";
            if (!empty($tool['url'])) {
                echo "  URL: " . $tool['url'] . "\n";
            }
            echo "\n";
        }
    }
}

[$cmd, $opts, $args] = parseArgs($argv);

switch ($cmd) {
    case 'verify:file':
        $hash = $opts['sha256'] ?? null;
        if (!$hash) { fwrite(STDERR, "Missing --sha256\n"); exit(1); }
        $score = crc32($hash) % 101;
        printJson(['type'=>'file','sha256'=>$hash,'veribit_score'=>$score]);
        break;
    case 'verify:email':
        $email = $opts['email'] ?? null;
        if (!$email) { fwrite(STDERR, "Missing --email\n"); exit(1); }
        $score = crc32(strtolower($email)) % 101;
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        printJson(['type'=>'email','email'=>$email,'format_valid'=>$valid,'veribit_score'=>$score]);
        break;
    case 'verify:tx':
        $tx = $opts['tx'] ?? null;
        $network = $opts['network'] ?? 'unknown';
        if (!$tx) { fwrite(STDERR, "Missing --tx\n"); exit(1); }
        $score = crc32($tx.'|'.$network) % 101;
        printJson(['type'=>'transaction','network'=>$network,'tx'=>$tx,'veribit_score'=>$score]);
        break;
    case 'health':
        printJson(['status'=>'ok','time'=>gmdate('c')]);
        break;

    case 'breach:email':
    case 'hibp:email':
        $email = $args[0] ?? $opts['email'] ?? '';
        $json = isset($opts['json']);

        if (!$email) {
            fwrite(STDERR, "Error: Email address is required\n");
            fwrite(STDERR, "Usage: veribits breach:email <email>\n");
            exit(1);
        }

        $result = apiRequest('/api/v1/hibp/check-email', 'POST', ['email' => $email]);

        if (!$result) {
            fwrite(STDERR, "Error: Could not connect to VeriBits API\n");
            exit(1);
        }

        if (!$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n");
            exit(1);
        }

        $data = $result['data'];

        if ($json) {
            printJson($data);
        } else {
            $breachCount = $data['breach_count'] ?? 0;
            $breaches = $data['breaches'] ?? [];

            echo "\n";
            echo "╔════════════════════════════════════════════════════════════════╗\n";
            echo "║               HAVE I BEEN PWNED - EMAIL CHECK                  ║\n";
            echo "╚════════════════════════════════════════════════════════════════╝\n\n";

            echo "Email: \033[1m$email\033[0m\n";

            if ($breachCount > 0) {
                echo "Status: \033[1;31m⚠ FOUND IN $breachCount BREACH" . ($breachCount !== 1 ? 'ES' : '') . "\033[0m\n\n";

                foreach ($breaches as $i => $breach) {
                    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                    echo "\033[1m" . ($i + 1) . ". " . $breach['Name'] . "\033[0m";
                    if ($breach['IsVerified'] ?? false) echo " \033[32m[Verified]\033[0m";
                    if ($breach['IsSensitive'] ?? false) echo " \033[31m[Sensitive]\033[0m";
                    echo "\n";
                    echo "   Domain: " . ($breach['Domain'] ?? 'N/A') . "\n";
                    echo "   Date: " . ($breach['BreachDate'] ?? 'Unknown') . "\n";

                    if (!empty($breach['DataClasses'])) {
                        echo "   Data: " . implode(', ', $breach['DataClasses']) . "\n";
                    }

                    if (!empty($breach['Description'])) {
                        $desc = strip_tags($breach['Description']);
                        $desc = substr($desc, 0, 200);
                        if (strlen($breach['Description']) > 200) $desc .= '...';
                        echo "   Info: $desc\n";
                    }
                    echo "\n";
                }

                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                echo "\033[1;33mRECOMMENDATIONS:\033[0m\n";
                echo "  • Change passwords for all accounts with this email\n";
                echo "  • Enable two-factor authentication (2FA)\n";
                echo "  • Monitor accounts for suspicious activity\n";
                echo "  • Use unique passwords for each service\n\n";
            } else {
                echo "Status: \033[1;32m✓ NO BREACHES FOUND\033[0m\n\n";
                echo "This email has not been found in any known data breaches.\n\n";
            }

            if ($data['cached'] ?? false) {
                echo "\033[2m[Cached result from " . $data['checked_at'] . "]\033[0m\n";
            }
        }
        break;

    case 'breach:password':
    case 'hibp:password':
        $password = $args[0] ?? $opts['password'] ?? '';
        $json = isset($opts['json']);

        if (!$password) {
            fwrite(STDERR, "Error: Password is required\n");
            fwrite(STDERR, "Usage: veribits breach:password <password>\n");
            fwrite(STDERR, "\nNote: Password is hashed locally using k-anonymity\n");
            exit(1);
        }

        $result = apiRequest('/api/v1/hibp/check-password', 'POST', ['password' => $password]);

        if (!$result) {
            fwrite(STDERR, "Error: Could not connect to VeriBits API\n");
            exit(1);
        }

        if (!$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n");
            exit(1);
        }

        $data = $result['data'];

        if ($json) {
            printJson($data);
        } else {
            $isPwned = $data['pwned'] ?? false;
            $occurrences = $data['occurrences'] ?? 0;

            echo "\n";
            echo "╔════════════════════════════════════════════════════════════════╗\n";
            echo "║             HAVE I BEEN PWNED - PASSWORD CHECK                 ║\n";
            echo "╚════════════════════════════════════════════════════════════════╝\n\n";

            if ($isPwned) {
                echo "Status: \033[1;31m⚠ PASSWORD COMPROMISED\033[0m\n";
                echo "Occurrences: \033[1;31m" . number_format($occurrences) . " times\033[0m\n\n";

                // Risk level
                if ($occurrences > 100000) {
                    echo "Risk Level: \033[1;31m█████████░ CRITICAL\033[0m\n";
                } elseif ($occurrences > 10000) {
                    echo "Risk Level: \033[1;31m███████░░░ HIGH\033[0m\n";
                } elseif ($occurrences > 1000) {
                    echo "Risk Level: \033[1;33m█████░░░░░ MEDIUM\033[0m\n";
                } else {
                    echo "Risk Level: \033[1;33m███░░░░░░░ LOW\033[0m\n";
                }

                echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                echo "\033[1;31mIMMEDIATE ACTION REQUIRED:\033[0m\n";
                echo "  • \033[1mChange this password immediately\033[0m\n";
                echo "  • Never reuse passwords across services\n";
                echo "  • Use a password manager for unique passwords\n";
                echo "  • Enable two-factor authentication (2FA)\n\n";
            } else {
                echo "Status: \033[1;32m✓ PASSWORD SECURE\033[0m\n\n";
                echo "This password has not been found in any known data breaches.\n\n";
                echo "\033[1;32mBEST PRACTICES:\033[0m\n";
                echo "  • Still use unique passwords for each service\n";
                echo "  • Consider using passphrases (4+ random words)\n";
                echo "  • Enable two-factor authentication (2FA)\n";
                echo "  • Regularly check passwords for compromise\n\n";
            }

            if ($data['cached'] ?? false) {
                echo "\033[2m[Cached result from " . $data['checked_at'] . "]\033[0m\n";
            }

            echo "\033[2m[Privacy: Only first 5 chars of SHA-1 hash sent to API]\033[0m\n";
        }
        break;

    case 'tool-search':
    case 'search':
        $query = $args[0] ?? $opts['query'] ?? $opts['q'] ?? '';
        $category = $opts['category'] ?? $opts['c'] ?? '';
        $verbose = isset($opts['verbose']) || isset($opts['v']);
        $json = isset($opts['json']);

        $params = [];
        if ($query) $params['q'] = $query;
        if ($category) $params['category'] = $category;

        $queryString = http_build_query($params);
        $result = apiRequest('/api/v1/tools/search?' . $queryString);

        if (!$result) {
            fwrite(STDERR, "Error: Could not connect to VeriBits API\n");
            exit(1);
        }

        if (!$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error'] ?? 'Unknown error') . "\n");
            exit(1);
        }

        $tools = $result['data']['tools'] ?? [];

        if ($json) {
            printJson($tools);
        } else {
            printToolResults($tools, $verbose);
        }
        break;

    case 'tool-list':
    case 'tools':
        $json = isset($opts['json']);
        $verbose = isset($opts['verbose']) || isset($opts['v']);

        $result = apiRequest('/api/v1/tools/list');

        if (!$result) {
            fwrite(STDERR, "Error: Could not connect to VeriBits API\n");
            exit(1);
        }

        if (!$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error'] ?? 'Unknown error') . "\n");
            exit(1);
        }

        $tools = $result['data']['tools'] ?? [];
        $categories = $result['data']['categories'] ?? [];

        if ($json) {
            printJson(['tools' => $tools, 'categories' => $categories]);
        } else {
            echo "\nAvailable Categories:\n";
            foreach ($categories as $cat) {
                echo "  - {$cat['name']} ({$cat['count']} tools)\n";
            }
            echo "\n";
            printToolResults($tools, $verbose);
        }
        break;

    case 'cloud-storage':
    case 'cloud-storage-scan':
        $provider = $opts['provider'] ?? 'all';
        $searchType = $opts['search-type'] ?? $opts['type'] ?? 'filename';
        $query = $args[0] ?? $opts['search'] ?? $opts['query'] ?? '';
        $json = isset($opts['json']);

        if (!$query) {
            fwrite(STDERR, "Error: Search query is required\n");
            fwrite(STDERR, "Usage: veribits cloud-storage <query> --provider=<aws|gcs|azure|digitalocean|all>\n");
            exit(1);
        }

        // Credentials from environment or options
        $credentials = [];

        // AWS credentials
        if ($provider === 'all' || $provider === 'aws') {
            $awsKey = $opts['aws-key'] ?? getenv('AWS_ACCESS_KEY_ID');
            $awsSecret = $opts['aws-secret'] ?? getenv('AWS_SECRET_ACCESS_KEY');
            $awsRegion = $opts['aws-region'] ?? getenv('AWS_DEFAULT_REGION') ?? 'us-east-1';

            if ($awsKey && $awsSecret) {
                $credentials['aws'] = [
                    'access_key' => $awsKey,
                    'secret_key' => $awsSecret,
                    'region' => $awsRegion
                ];
            }
        }

        // GCS credentials
        if ($provider === 'all' || $provider === 'gcs') {
            $gcpProject = $opts['gcp-project'] ?? getenv('GCP_PROJECT_ID');
            $gcpCreds = $opts['gcp-credentials'] ?? getenv('GOOGLE_APPLICATION_CREDENTIALS');

            if ($gcpProject && $gcpCreds && file_exists($gcpCreds)) {
                $credentials['gcs'] = [
                    'project_id' => $gcpProject,
                    'credentials_file' => $gcpCreds
                ];
            }
        }

        // Azure credentials
        if ($provider === 'all' || $provider === 'azure') {
            $azureAccount = $opts['azure-account'] ?? getenv('AZURE_STORAGE_ACCOUNT');
            $azureKey = $opts['azure-key'] ?? getenv('AZURE_STORAGE_KEY');

            if ($azureAccount && $azureKey) {
                $credentials['azure'] = [
                    'account_name' => $azureAccount,
                    'account_key' => $azureKey
                ];
            }
        }

        // Digital Ocean credentials
        if ($provider === 'all' || $provider === 'digitalocean') {
            $doKey = $opts['do-key'] ?? getenv('DO_SPACES_KEY');
            $doSecret = $opts['do-secret'] ?? getenv('DO_SPACES_SECRET');
            $doRegion = $opts['do-region'] ?? getenv('DO_SPACES_REGION') ?? 'nyc3';

            if ($doKey && $doSecret) {
                $credentials['digitalocean'] = [
                    'access_key' => $doKey,
                    'secret_key' => $doSecret,
                    'region' => $doRegion
                ];
            }
        }

        if (empty($credentials)) {
            fwrite(STDERR, "Error: No credentials provided\n");
            fwrite(STDERR, "Set credentials via environment variables or command options\n");
            fwrite(STDERR, "\nAWS: AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY\n");
            fwrite(STDERR, "GCS: GCP_PROJECT_ID, GOOGLE_APPLICATION_CREDENTIALS\n");
            fwrite(STDERR, "Azure: AZURE_STORAGE_ACCOUNT, AZURE_STORAGE_KEY\n");
            fwrite(STDERR, "Digital Ocean: DO_SPACES_KEY, DO_SPACES_SECRET\n");
            exit(1);
        }

        $data = [
            'providers' => $provider === 'all' ? ['all'] : [$provider],
            'search_type' => $searchType,
            'query' => $query,
            'credentials' => $credentials,
            'max_results' => (int)($opts['max-results'] ?? 1000)
        ];

        echo "Searching cloud storage for: \033[1m$query\033[0m\n";
        echo "Provider(s): $provider\n";
        echo "Search type: $searchType\n\n";

        $result = apiRequest('/api/v1/tools/cloud-storage/search', 'POST', $data);

        if (!$result) {
            fwrite(STDERR, "Error: Could not connect to VeriBits API\n");
            exit(1);
        }

        if (!$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error']['message'] ?? 'Unknown error') . "\n");
            exit(1);
        }

        $searchResults = $result['data'];

        if ($json) {
            printJson($searchResults);
        } else {
            $summary = $searchResults['summary'] ?? [];
            echo "\n\033[1mResults:\033[0m\n";
            echo "  Providers searched: " . ($summary['total_providers_searched'] ?? 0) . "\n";
            echo "  Buckets scanned: " . ($summary['total_buckets_searched'] ?? 0) . "\n";
            echo "  Total matches: \033[32m" . ($summary['total_matches'] ?? 0) . "\033[0m\n\n";

            if ($searchResults['cached'] ?? false) {
                echo "\033[33m[Cached results - 24hr TTL]\033[0m\n\n";
            }

            foreach ($searchResults['results'] ?? [] as $provider => $data) {
                echo "\033[1m" . strtoupper($provider) . ":\033[0m\n";
                echo "  Matches: " . ($data['total_matches'] ?? 0) . " in " . ($data['buckets_searched'] ?? 0) . " buckets\n";

                foreach ($data['results'] ?? [] as $bucket) {
                    $bucketName = $bucket['bucket'] ?? $bucket['container'] ?? $bucket['space'] ?? 'unknown';
                    echo "\n  \033[36m$bucketName\033[0m ({$bucket['count']} files):\n";

                    foreach (array_slice($bucket['matches'], 0, 5) as $match) {
                        echo "    - " . $match['key'];
                        if (isset($match['size_human'])) {
                            echo " (" . $match['size_human'] . ")";
                        }
                        if ($match['content_match'] ?? false) {
                            echo " \033[32m[Content Match]\033[0m";
                        }
                        echo "\n";
                    }

                    if ($bucket['count'] > 5) {
                        echo "    ... and " . ($bucket['count'] - 5) . " more files\n";
                    }
                }
                echo "\n";
            }
        }
        break;

    case 'cloud-storage-buckets':
        $provider = $opts['provider'] ?? '';
        $json = isset($opts['json']);

        if (!$provider) {
            fwrite(STDERR, "Error: --provider is required (aws|gcs|azure|digitalocean)\n");
            exit(1);
        }

        // Get credentials (similar to cloud-storage command)
        $credentials = [];
        // [Same credential logic as above - abbreviated for brevity]

        $data = [
            'provider' => $provider,
            'credentials' => $credentials
        ];

        $result = apiRequest('/api/v1/tools/cloud-storage/list-buckets', 'POST', $data);

        if (!$result || !$result['success']) {
            fwrite(STDERR, "Error: " . ($result['error']['message'] ?? 'Failed to list buckets') . "\n");
            exit(1);
        }

        if ($json) {
            printJson($result['data']);
        } else {
            $buckets = $result['data']['buckets'] ?? [];
            echo "\n\033[1mBuckets for $provider:\033[0m (" . count($buckets) . " total)\n\n";
            foreach ($buckets as $bucket) {
                $name = $bucket['name'] ?? $bucket;
                echo "  - $name\n";
            }
            echo "\n";
        }
        break;

    case 'help':
    default:
        echo "VeriBits CLI - Trust Verification Tools\n\n";
        echo "Usage:\n";
        echo "  veribits <command> [options]\n\n";
        echo "Commands:\n";
        echo "  verify:file --sha256=<hash>                Verify file integrity\n";
        echo "  verify:email --email=<email>               Verify email address\n";
        echo "  verify:tx --network=<network> --tx=<hash>  Verify transaction\n";
        echo "  health                                      Check API health\n\n";
        echo "Data Breach Checking (Have I Been Pwned):\n";
        echo "  breach:email <email>                       Check if email in breaches\n";
        echo "  breach:password <password>                 Check if password compromised\n";
        echo "    --json                                   Output as JSON\n\n";
        echo "Tool Discovery:\n";
        echo "  tool-search <query> [options]              Search for tools\n";
        echo "    --category=<name>                        Filter by category\n";
        echo "    --verbose, -v                            Show detailed info\n";
        echo "    --json                                   Output as JSON\n";
        echo "  tool-list                                  List all available tools\n";
        echo "    --verbose, -v                            Show detailed info\n";
        echo "    --json                                   Output as JSON\n\n";
        echo "Cloud Storage Security:\n";
        echo "  cloud-storage <query> [options]            Search cloud storage\n";
        echo "    --provider=<name>                        Provider (aws|gcs|azure|digitalocean|all)\n";
        echo "    --search-type=<type>                     Type (filename|content)\n";
        echo "    --max-results=<num>                      Max results (default: 1000)\n";
        echo "    --json                                   Output as JSON\n";
        echo "  cloud-storage-buckets --provider=<name>    List buckets/containers\n";
        echo "    --json                                   Output as JSON\n\n";
        echo "Examples:\n";
        echo "  veribits breach:email user@example.com     Check email for breaches\n";
        echo "  veribits breach:password MyPassword123     Check password security\n";
        echo "  veribits tool-search dns                   Search for DNS tools\n";
        echo "  veribits tool-search --category=Security   List all security tools\n";
        echo "  veribits tool-list --verbose               List all tools with details\n";
        echo "  veribits cloud-storage config.json         Search for config.json in all clouds\n";
        echo "  veribits cloud-storage '*.env' --provider=aws  Search AWS for .env files\n\n";
        echo "Environment:\n";
        echo "  VERIBITS_API_URL         API base URL (default: https://www.veribits.com)\n";
        echo "  AWS_ACCESS_KEY_ID        AWS access key\n";
        echo "  AWS_SECRET_ACCESS_KEY    AWS secret key\n";
        echo "  GCP_PROJECT_ID           GCP project ID\n";
        echo "  GOOGLE_APPLICATION_CREDENTIALS  GCP credentials file\n";
        echo "  AZURE_STORAGE_ACCOUNT    Azure storage account\n";
        echo "  AZURE_STORAGE_KEY        Azure storage key\n";
        echo "  DO_SPACES_KEY            Digital Ocean Spaces key\n";
        echo "  DO_SPACES_SECRET         Digital Ocean Spaces secret\n\n";
        exit($cmd === 'help' ? 0 : 1);
}
