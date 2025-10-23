<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Database;

class DNSCheckController {
    private const PUBLIC_DNS_SERVERS = [
        'Google Primary' => '8.8.8.8',
        'Google Secondary' => '8.8.4.4',
        'Cloudflare Primary' => '1.1.1.1',
        'Cloudflare Secondary' => '1.0.0.1',
        'Quad9' => '9.9.9.9',
        'OpenDNS' => '208.67.222.222'
    ];

    private const RBL_SERVERS = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'dnsbl.sorbs.net',
        'cbl.abuseat.org',
        'b.barracudacentral.org'
    ];

    public function check(): void {
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

        if (isset($body['check_type'])) {
            $validator->in('check_type', ['full', 'records', 'ns', 'security', 'email', 'propagation', 'blacklist']);
        }

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $domain = strtolower(trim($validator->sanitize('domain')));
        $checkType = $body['check_type'] ?? 'full';

        // Remove protocol if present
        $domain = preg_replace('#^https?://#i', '', $domain);
        // Remove path if present
        $domain = explode('/', $domain)[0];
        // Remove port if present
        $domain = explode(':', $domain)[0];

        try {
            $startTime = microtime(true);
            $results = [];

            // Perform requested checks
            if ($checkType === 'full' || $checkType === 'records') {
                $results['dns_records'] = $this->getAllRecords($domain);
            }

            if ($checkType === 'full' || $checkType === 'ns') {
                $results['ns_verification'] = $this->verifyNSRecords($domain);
            }

            if ($checkType === 'full' || $checkType === 'security') {
                $results['dnssec_status'] = $this->checkDNSSEC($domain);
            }

            if ($checkType === 'full' || $checkType === 'email') {
                $results['email_config'] = $this->checkEmailConfiguration($domain);
            }

            if ($checkType === 'full' || $checkType === 'propagation') {
                $results['propagation'] = $this->checkPropagation($domain);
            }

            if ($checkType === 'full' || $checkType === 'blacklist') {
                $results['blacklist_status'] = $this->checkBlacklists($domain);
            }

            $checkTimeMs = (int)((microtime(true) - $startTime) * 1000);

            // Calculate health score and identify issues
            $healthAnalysis = $this->analyzeHealth($results, $checkType);

            // Generate badge ID
            $badgeId = 'dns_' . substr(md5($domain . time()), 0, 16);

            // Store in database
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO dns_checks
                (user_id, api_key_id, domain, check_type, dns_records, dnssec_status,
                 blacklist_status, email_config, propagation_results, health_score,
                 issues_found, badge_id)
                VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
                RETURNING id
            ');

            $stmt->execute([
                $userId,
                $apiKeyId,
                $domain,
                $checkType,
                json_encode($results['dns_records'] ?? null),
                $results['dnssec_status'] ?? null,
                json_encode($results['blacklist_status'] ?? null),
                json_encode($results['email_config'] ?? null),
                json_encode($results['propagation'] ?? null),
                $healthAnalysis['score'],
                json_encode($healthAnalysis['issues']),
                $badgeId
            ]);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('DNS check completed', [
                'user_id' => $userId,
                'domain' => $domain,
                'check_type' => $checkType,
                'health_score' => $healthAnalysis['score']
            ]);

            Response::success([
                'type' => 'dns_check',
                'domain' => $domain,
                'check_type' => $checkType,
                'results' => $results,
                'health_score' => $healthAnalysis['score'],
                'health_grade' => $this->getHealthGrade($healthAnalysis['score']),
                'issues_found' => $healthAnalysis['issues'],
                'check_time_ms' => $checkTimeMs,
                'badge_id' => $badgeId,
                'badge_url' => "/api/v1/badge/$badgeId",
                'checked_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('DNS check failed', [
                'user_id' => $userId,
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            Response::error('DNS check failed: ' . $e->getMessage(), 500);
        }
    }

    private function getAllRecords(string $domain): array {
        $records = [];
        $types = ['A', 'AAAA', 'MX', 'NS', 'TXT', 'CNAME', 'SOA', 'PTR'];

        foreach ($types as $type) {
            $dnsType = constant('DNS_' . $type);
            $result = @dns_get_record($domain, $dnsType);

            if ($result !== false && !empty($result)) {
                $records[$type] = $result;
            }
        }

        return $records;
    }

    private function verifyNSRecords(string $domain): array {
        $nsRecords = @dns_get_record($domain, DNS_NS);

        if ($nsRecords === false || empty($nsRecords)) {
            return [
                'status' => 'error',
                'message' => 'No NS records found',
                'nameservers' => []
            ];
        }

        $nameservers = [];
        $issues = [];

        foreach ($nsRecords as $ns) {
            $nsHost = $ns['target'];
            $nsInfo = [
                'hostname' => $nsHost,
                'responsive' => false,
                'ip_addresses' => [],
                'response_time_ms' => null
            ];

            // Resolve NS to IP
            $nsIPs = @dns_get_record($nsHost, DNS_A);
            if ($nsIPs !== false && !empty($nsIPs)) {
                $nsInfo['ip_addresses'] = array_column($nsIPs, 'ip');

                // Check if NS is responsive
                $startTime = microtime(true);
                $testQuery = @dns_get_record($domain, DNS_A, $nameservers);
                $responseTime = (microtime(true) - $startTime) * 1000;

                $nsInfo['responsive'] = true;
                $nsInfo['response_time_ms'] = (int)$responseTime;
            } else {
                $issues[] = "Nameserver $nsHost could not be resolved";
            }

            $nameservers[] = $nsInfo;
        }

        // Check for minimum NS count
        if (count($nsRecords) < 2) {
            $issues[] = 'Less than 2 nameservers configured (recommended: 2+)';
        }

        return [
            'status' => empty($issues) ? 'healthy' : 'warning',
            'nameserver_count' => count($nsRecords),
            'nameservers' => $nameservers,
            'issues' => $issues
        ];
    }

    private function checkDNSSEC(string $domain): string {
        // Check for DNSKEY records
        $dnskey = @dns_get_record($domain, DNS_ANY);

        if ($dnskey === false) {
            return 'unknown';
        }

        // Look for DNSSEC-related records
        $hasDNSSEC = false;
        foreach ($dnskey as $record) {
            if (isset($record['type']) && in_array($record['type'], ['DNSKEY', 'DS', 'RRSIG'])) {
                $hasDNSSEC = true;
                break;
            }
        }

        return $hasDNSSEC ? 'enabled' : 'disabled';
    }

    private function checkEmailConfiguration(string $domain): array {
        $config = [
            'mx_records' => [],
            'spf_record' => null,
            'dmarc_record' => null,
            'dkim_records' => [],
            'status' => 'not_configured'
        ];

        // Check MX records
        $mxRecords = @dns_get_record($domain, DNS_MX);
        if ($mxRecords !== false && !empty($mxRecords)) {
            $config['mx_records'] = array_map(function($mx) {
                return [
                    'host' => $mx['target'],
                    'priority' => $mx['pri']
                ];
            }, $mxRecords);
            $config['status'] = 'partial';
        }

        // Check SPF record (in TXT records)
        $txtRecords = @dns_get_record($domain, DNS_TXT);
        if ($txtRecords !== false) {
            foreach ($txtRecords as $txt) {
                $value = $txt['txt'] ?? '';

                if (stripos($value, 'v=spf1') === 0) {
                    $config['spf_record'] = $value;
                    $config['spf_valid'] = $this->validateSPF($value);
                }
            }
        }

        // Check DMARC record
        $dmarcRecords = @dns_get_record('_dmarc.' . $domain, DNS_TXT);
        if ($dmarcRecords !== false && !empty($dmarcRecords)) {
            foreach ($dmarcRecords as $dmarc) {
                $value = $dmarc['txt'] ?? '';
                if (stripos($value, 'v=DMARC1') === 0) {
                    $config['dmarc_record'] = $value;
                    $config['dmarc_policy'] = $this->parseDMARCPolicy($value);
                }
            }
        }

        // Update status
        if (!empty($config['mx_records']) && $config['spf_record'] && $config['dmarc_record']) {
            $config['status'] = 'fully_configured';
        } elseif (!empty($config['mx_records']) || $config['spf_record']) {
            $config['status'] = 'partial';
        }

        return $config;
    }

    private function validateSPF(string $spf): bool {
        // Basic SPF validation
        return preg_match('/^v=spf1\s+.*(-all|~all|\?all)$/i', $spf) === 1;
    }

    private function parseDMARCPolicy(string $dmarc): ?string {
        if (preg_match('/p=(none|quarantine|reject)/i', $dmarc, $matches)) {
            return strtolower($matches[1]);
        }
        return null;
    }

    private function checkPropagation(string $domain): array {
        $results = [];

        foreach (self::PUBLIC_DNS_SERVERS as $name => $server) {
            $startTime = microtime(true);

            // Use dig command for specific DNS server
            $output = [];
            $returnCode = 0;
            $escapedDomain = escapeshellarg($domain);
            @exec("dig @$server $escapedDomain A +short 2>&1", $output, $returnCode);

            $responseTime = (microtime(true) - $startTime) * 1000;

            $results[$name] = [
                'server' => $server,
                'responsive' => $returnCode === 0,
                'records' => $returnCode === 0 ? array_filter($output) : [],
                'response_time_ms' => (int)$responseTime
            ];
        }

        // Check consistency
        $allRecords = array_map(function($r) {
            return implode(',', $r['records']);
        }, $results);

        $uniqueRecords = array_unique(array_filter($allRecords));
        $consistent = count($uniqueRecords) <= 1;

        return [
            'servers' => $results,
            'consistent' => $consistent,
            'propagation_status' => $consistent ? 'complete' : 'incomplete'
        ];
    }

    private function checkBlacklists(string $domain): array {
        $results = [];
        $blacklisted = false;

        // Get domain's A records to check IPs
        $aRecords = @dns_get_record($domain, DNS_A);

        if ($aRecords === false || empty($aRecords)) {
            return [
                'status' => 'no_ip',
                'message' => 'No A records found to check',
                'listings' => []
            ];
        }

        $listings = [];

        foreach ($aRecords as $record) {
            $ip = $record['ip'];
            $reversedIP = implode('.', array_reverse(explode('.', $ip)));

            foreach (self::RBL_SERVERS as $rbl) {
                $checkHost = "$reversedIP.$rbl";
                $result = @dns_get_record($checkHost, DNS_A);

                if ($result !== false && !empty($result)) {
                    $blacklisted = true;
                    $listings[] = [
                        'ip' => $ip,
                        'rbl' => $rbl,
                        'listed' => true
                    ];
                }
            }
        }

        return [
            'status' => $blacklisted ? 'blacklisted' : 'clean',
            'ips_checked' => array_column($aRecords, 'ip'),
            'rbls_checked' => count(self::RBL_SERVERS),
            'listings' => $listings
        ];
    }

    private function analyzeHealth(array $results, string $checkType): array {
        $score = 100;
        $issues = [];

        // NS Record issues
        if (isset($results['ns_verification'])) {
            $ns = $results['ns_verification'];
            if ($ns['status'] === 'error') {
                $score -= 30;
                $issues[] = 'No nameservers found';
            } elseif ($ns['nameserver_count'] < 2) {
                $score -= 10;
                $issues[] = 'Insufficient nameservers (recommended: 2+)';
            }
            $issues = array_merge($issues, $ns['issues'] ?? []);
        }

        // DNSSEC
        if (isset($results['dnssec_status'])) {
            if ($results['dnssec_status'] === 'disabled') {
                $score -= 5;
                $issues[] = 'DNSSEC not enabled';
            }
        }

        // Email configuration
        if (isset($results['email_config'])) {
            $email = $results['email_config'];
            if (empty($email['mx_records'])) {
                $score -= 10;
            }
            if (!isset($email['spf_record'])) {
                $score -= 10;
                $issues[] = 'No SPF record found';
            }
            if (!isset($email['dmarc_record'])) {
                $score -= 10;
                $issues[] = 'No DMARC record found';
            }
        }

        // Propagation
        if (isset($results['propagation'])) {
            if (!$results['propagation']['consistent']) {
                $score -= 15;
                $issues[] = 'DNS propagation incomplete across servers';
            }
        }

        // Blacklists
        if (isset($results['blacklist_status'])) {
            if ($results['blacklist_status']['status'] === 'blacklisted') {
                $score -= 40;
                $issues[] = 'Domain/IP found on blacklists';
            }
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues
        ];
    }

    private function getHealthGrade(int $score): string {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}
