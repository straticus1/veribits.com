<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Database;

class EmailVerificationController {

    // Comprehensive list of disposable email domains
    private static array $disposableDomains = [
        '10minutemail.com', 'guerrillamail.com', 'mailinator.com', 'tempmail.com',
        'throwaway.email', 'yopmail.com', 'temp-mail.org', 'fakeinbox.com',
        'maildrop.cc', 'getnada.com', 'trashmail.com', 'sharklasers.com',
        'guerrillamailblock.com', 'pokemail.net', 'spam4.me', 'grr.la',
        'jetable.org', 'mytemp.email', 'dispostable.com', 'emailondeck.com',
        '33mail.com', 'mohmal.com', 'mintemail.com', 'mailnesia.com'
    ];

    /**
     * Check if email or domain is disposable
     */
    public function checkDisposable(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_dea:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $input = $body['email'] ?? $body['domain'] ?? '';
        if (empty($input)) {
            Response::json(['error' => 'Email or domain required'], 400);
            return;
        }

        $domain = $this->extractDomain($input);
        $isDisposable = $this->isDisposableDomain($domain);

        // Check against online DEA database
        $apiCheck = $this->checkDisposableAPI($domain);

        Response::success([
            'input' => $input,
            'domain' => $domain,
            'is_disposable' => $isDisposable || $apiCheck['is_disposable'],
            'confidence' => $isDisposable ? 'high' : ($apiCheck['is_disposable'] ? 'medium' : 'low'),
            'source' => $isDisposable ? 'local_database' : ($apiCheck['is_disposable'] ? 'api_check' : 'none'),
            'risk_level' => ($isDisposable || $apiCheck['is_disposable']) ? 'high' : 'low',
            'recommendation' => ($isDisposable || $apiCheck['is_disposable'])
                ? 'Block or flag this email address'
                : 'Email appears to be legitimate'
        ]);
    }

    /**
     * Analyze SPF record for domain
     */
    public function analyzeSPF(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_spf:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? $_GET['domain'] ?? '';
        if (empty($domain)) {
            Response::json(['error' => 'Domain required'], 400);
            return;
        }

        $domain = $this->sanitizeDomain($domain);
        $spfRecord = $this->getSPFRecord($domain);

        if (!$spfRecord) {
            Response::success([
                'domain' => $domain,
                'has_spf' => false,
                'status' => 'No SPF record found',
                'risk' => 'high',
                'recommendation' => 'Configure SPF to prevent email spoofing'
            ]);
            return;
        }

        $parsed = $this->parseSPF($spfRecord, $domain);

        Response::success([
            'domain' => $domain,
            'has_spf' => true,
            'raw_record' => $spfRecord,
            'version' => $parsed['version'],
            'mechanisms' => $parsed['mechanisms'],
            'qualifiers' => $parsed['qualifiers'],
            'includes' => $parsed['includes'],
            'ip4_ranges' => $parsed['ip4'],
            'ip6_ranges' => $parsed['ip6'],
            'all_mechanism' => $parsed['all'],
            'policy_strength' => $this->assessSPFStrength($parsed),
            'dns_lookups' => $parsed['dns_lookups'],
            'warnings' => $parsed['warnings'],
            'valid' => $parsed['valid']
        ]);
    }

    /**
     * Analyze DKIM record for domain
     */
    public function analyzeDKIM(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_dkim:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? $_GET['domain'] ?? '';
        $selector = $body['selector'] ?? 'default';

        if (empty($domain)) {
            Response::json(['error' => 'Domain required'], 400);
            return;
        }

        $domain = $this->sanitizeDomain($domain);

        // Try common selectors if not specified
        $selectors = $selector !== 'default' ? [$selector] : [
            'default', 'google', 'k1', 's1', 's2', 'dkim', 'mail',
            'selector1', 'selector2', 'mta', 'smtp'
        ];

        $found = [];
        foreach ($selectors as $sel) {
            $record = $this->getDKIMRecord($domain, $sel);
            if ($record) {
                $parsed = $this->parseDKIM($record);
                $found[] = [
                    'selector' => $sel,
                    'record' => $record,
                    'parsed' => $parsed
                ];
            }
        }

        Response::success([
            'domain' => $domain,
            'has_dkim' => !empty($found),
            'records_found' => count($found),
            'selectors_checked' => $selectors,
            'records' => $found,
            'recommendation' => empty($found)
                ? 'No DKIM records found. Consider implementing DKIM for email authentication.'
                : 'DKIM configured. Verify signatures in email headers.'
        ]);
    }

    /**
     * Verify DKIM signature from email headers
     */
    public function verifyDKIMSignature(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_dkim_verify:$clientIp", 50, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $headers = $body['headers'] ?? '';
        if (empty($headers)) {
            Response::json(['error' => 'Email headers required'], 400);
            return;
        }

        $signature = $this->extractDKIMSignature($headers);
        if (!$signature) {
            Response::success([
                'verified' => false,
                'error' => 'No DKIM-Signature header found in provided headers'
            ]);
            return;
        }

        $verification = $this->verifyDKIM($headers, $signature);

        Response::success($verification);
    }

    /**
     * Analyze DMARC record for domain
     */
    public function analyzeDMARC(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_dmarc:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? $_GET['domain'] ?? '';
        if (empty($domain)) {
            Response::json(['error' => 'Domain required'], 400);
            return;
        }

        $domain = $this->sanitizeDomain($domain);
        $dmarcRecord = $this->getDMARCRecord($domain);

        if (!$dmarcRecord) {
            Response::success([
                'domain' => $domain,
                'has_dmarc' => false,
                'status' => 'No DMARC record found',
                'risk' => 'high',
                'recommendation' => 'Configure DMARC to protect against email spoofing and phishing'
            ]);
            return;
        }

        $parsed = $this->parseDMARC($dmarcRecord);

        Response::success([
            'domain' => $domain,
            'has_dmarc' => true,
            'raw_record' => $dmarcRecord,
            'version' => $parsed['version'],
            'policy' => $parsed['policy'],
            'subdomain_policy' => $parsed['subdomain_policy'],
            'percentage' => $parsed['percentage'],
            'alignment' => [
                'dkim' => $parsed['adkim'],
                'spf' => $parsed['aspf']
            ],
            'reporting' => [
                'aggregate' => $parsed['rua'],
                'forensic' => $parsed['ruf']
            ],
            'options' => [
                'forensic_options' => $parsed['fo'],
                'report_format' => $parsed['rf']
            ],
            'policy_strength' => $this->assessDMARCStrength($parsed),
            'warnings' => $parsed['warnings'],
            'recommendations' => $this->getDMARCRecommendations($parsed)
        ]);
    }

    /**
     * Get MX records and analyze mail server configuration
     */
    public function analyzeMX(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_mx:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? $_GET['domain'] ?? '';
        if (empty($domain)) {
            Response::json(['error' => 'Domain required'], 400);
            return;
        }

        $domain = $this->sanitizeDomain($domain);
        $mxRecords = $this->getMXRecords($domain);

        if (empty($mxRecords)) {
            Response::success([
                'domain' => $domain,
                'has_mx' => false,
                'status' => 'No MX records found',
                'can_receive_email' => false
            ]);
            return;
        }

        // Analyze each MX record
        $analyzed = [];
        foreach ($mxRecords as $mx) {
            $analyzed[] = [
                'hostname' => $mx['target'],
                'priority' => $mx['pri'],
                'ip_addresses' => $this->resolveHostname($mx['target']),
                'reverse_dns' => $this->getReverseDNS($mx['target']),
                'supports_tls' => $this->checkTLSSupport($mx['target'])
            ];
        }

        Response::success([
            'domain' => $domain,
            'has_mx' => true,
            'mx_count' => count($mxRecords),
            'records' => $analyzed,
            'primary_mx' => $analyzed[0] ?? null,
            'redundancy' => count($mxRecords) > 1,
            'status' => 'healthy'
        ]);
    }

    /**
     * Analyze email headers
     */
    public function analyzeHeaders(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_headers:$clientIp", 50, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $headers = $body['headers'] ?? '';
        if (empty($headers)) {
            Response::json(['error' => 'Email headers required'], 400);
            return;
        }

        $parsed = $this->parseEmailHeaders($headers);

        Response::success([
            'authentication' => [
                'spf' => $parsed['spf'],
                'dkim' => $parsed['dkim'],
                'dmarc' => $parsed['dmarc']
            ],
            'routing' => $parsed['received'],
            'metadata' => [
                'from' => $parsed['from'],
                'to' => $parsed['to'],
                'subject' => $parsed['subject'],
                'date' => $parsed['date'],
                'message_id' => $parsed['message_id']
            ],
            'spam_score' => $parsed['spam_score'],
            'delivery_time' => $parsed['delivery_time'],
            'hops' => count($parsed['received']),
            'warnings' => $parsed['warnings']
        ]);
    }

    /**
     * Check domain/IP against major blacklists
     */
    public function checkBlacklists(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_blacklist:$clientIp", 50, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $input = $body['query'] ?? $_GET['query'] ?? '';
        if (empty($input)) {
            Response::json(['error' => 'Domain or IP address required'], 400);
            return;
        }

        $isIP = filter_var($input, FILTER_VALIDATE_IP);
        $results = $this->checkRBLs($input, $isIP);

        $listed = array_filter($results, fn($r) => $r['listed']);

        Response::success([
            'query' => $input,
            'type' => $isIP ? 'ip' : 'domain',
            'is_blacklisted' => !empty($listed),
            'blacklists_checked' => count($results),
            'blacklists_listed' => count($listed),
            'results' => $results,
            'severity' => $this->calculateBlacklistSeverity($listed),
            'recommendation' => empty($listed)
                ? 'Not found on major blacklists'
                : 'Listed on ' . count($listed) . ' blacklist(s). Contact RBL operators for delisting.'
        ]);
    }

    /**
     * Generate comprehensive deliverability score
     */
    public function deliverabilityScore(): void {
        $clientIp = RateLimit::getClientIp();
        if (!RateLimit::check("email_score:$clientIp", 30, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $domain = $body['domain'] ?? $_GET['domain'] ?? '';
        if (empty($domain)) {
            Response::json(['error' => 'Domain required'], 400);
            return;
        }

        $domain = $this->sanitizeDomain($domain);

        // Run all checks
        $spf = $this->getSPFRecord($domain);
        $dmarc = $this->getDMARCRecord($domain);
        $mx = $this->getMXRecords($domain);
        $blacklists = $this->checkRBLs($domain, false);

        $score = 0;
        $maxScore = 100;
        $factors = [];

        // SPF check (25 points)
        if ($spf) {
            $parsed = $this->parseSPF($spf, $domain);
            $spfScore = $parsed['valid'] ? 25 : 15;
            $score += $spfScore;
            $factors[] = ['name' => 'SPF Record', 'score' => $spfScore, 'max' => 25, 'status' => 'pass'];
        } else {
            $factors[] = ['name' => 'SPF Record', 'score' => 0, 'max' => 25, 'status' => 'fail'];
        }

        // DMARC check (25 points)
        if ($dmarc) {
            $parsed = $this->parseDMARC($dmarc);
            $dmarcScore = $parsed['policy'] === 'reject' ? 25 : ($parsed['policy'] === 'quarantine' ? 20 : 15);
            $score += $dmarcScore;
            $factors[] = ['name' => 'DMARC Policy', 'score' => $dmarcScore, 'max' => 25, 'status' => 'pass'];
        } else {
            $factors[] = ['name' => 'DMARC Policy', 'score' => 0, 'max' => 25, 'status' => 'fail'];
        }

        // MX records (20 points)
        if (!empty($mx)) {
            $mxScore = count($mx) > 1 ? 20 : 15;
            $score += $mxScore;
            $factors[] = ['name' => 'MX Records', 'score' => $mxScore, 'max' => 20, 'status' => 'pass'];
        } else {
            $factors[] = ['name' => 'MX Records', 'score' => 0, 'max' => 20, 'status' => 'fail'];
        }

        // Blacklist check (30 points)
        $listed = array_filter($blacklists, fn($r) => $r['listed']);
        $blacklistScore = empty($listed) ? 30 : max(0, 30 - (count($listed) * 5));
        $score += $blacklistScore;
        $factors[] = ['name' => 'Blacklist Status', 'score' => $blacklistScore, 'max' => 30, 'status' => empty($listed) ? 'pass' : 'fail'];

        $grade = $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : ($score >= 60 ? 'D' : 'F')));

        Response::success([
            'domain' => $domain,
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100),
            'grade' => $grade,
            'factors' => $factors,
            'summary' => $this->getDeliverabilityRecommendations($factors, $score)
        ]);
    }

    // ============== Helper Methods ==============

    private function extractDomain(string $email): string {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return strtolower(substr(strrchr($email, "@"), 1));
        }
        return strtolower(trim($email));
    }

    private function sanitizeDomain(string $domain): string {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/^www\./', '', $domain);
        return $domain;
    }

    private function isDisposableDomain(string $domain): bool {
        return in_array(strtolower($domain), self::$disposableDomains);
    }

    private function checkDisposableAPI(string $domain): array {
        // Simple heuristic check for disposable patterns
        $patterns = ['temp', 'disposable', 'trash', 'fake', 'throwaway', 'guerrilla'];
        foreach ($patterns as $pattern) {
            if (stripos($domain, $pattern) !== false) {
                return ['is_disposable' => true];
            }
        }
        return ['is_disposable' => false];
    }

    private function getSPFRecord(string $domain): ?string {
        $records = @dns_get_record($domain, DNS_TXT);
        if (!$records) return null;

        foreach ($records as $record) {
            if (isset($record['txt']) && stripos($record['txt'], 'v=spf1') === 0) {
                return $record['txt'];
            }
        }
        return null;
    }

    private function parseSPF(string $spf, string $domain): array {
        $result = [
            'version' => 'spf1',
            'mechanisms' => [],
            'qualifiers' => [],
            'includes' => [],
            'ip4' => [],
            'ip6' => [],
            'all' => null,
            'dns_lookups' => 0,
            'warnings' => [],
            'valid' => true
        ];

        $parts = explode(' ', $spf);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Extract qualifier
            $qualifier = '+';
            if (in_array($part[0], ['+', '-', '~', '?'])) {
                $qualifier = $part[0];
                $part = substr($part, 1);
            }

            if (preg_match('/^v=spf1$/i', $part)) {
                continue;
            }

            if (strpos($part, 'include:') === 0) {
                $include = substr($part, 8);
                $result['includes'][] = $include;
                $result['dns_lookups']++;
                $result['mechanisms'][] = ['type' => 'include', 'value' => $include, 'qualifier' => $qualifier];
            } elseif (strpos($part, 'ip4:') === 0) {
                $ip = substr($part, 4);
                $result['ip4'][] = $ip;
                $result['mechanisms'][] = ['type' => 'ip4', 'value' => $ip, 'qualifier' => $qualifier];
            } elseif (strpos($part, 'ip6:') === 0) {
                $ip = substr($part, 4);
                $result['ip6'][] = $ip;
                $result['mechanisms'][] = ['type' => 'ip6', 'value' => $ip, 'qualifier' => $qualifier];
            } elseif (strpos($part, 'a:') === 0 || strpos($part, 'a/') === 0 || $part === 'a') {
                $result['dns_lookups']++;
                $result['mechanisms'][] = ['type' => 'a', 'value' => $part, 'qualifier' => $qualifier];
            } elseif (strpos($part, 'mx:') === 0 || strpos($part, 'mx/') === 0 || $part === 'mx') {
                $result['dns_lookups']++;
                $result['mechanisms'][] = ['type' => 'mx', 'value' => $part, 'qualifier' => $qualifier];
            } elseif (in_array($part, ['all', '+all', '-all', '~all', '?all'])) {
                $result['all'] = $qualifier . 'all';
                $result['mechanisms'][] = ['type' => 'all', 'value' => $part, 'qualifier' => $qualifier];
            }

            $result['qualifiers'][] = $qualifier;
        }

        // Warnings
        if ($result['dns_lookups'] > 10) {
            $result['warnings'][] = 'SPF record exceeds 10 DNS lookups (RFC limit)';
            $result['valid'] = false;
        }

        if (!$result['all']) {
            $result['warnings'][] = 'No "all" mechanism found';
        }

        return $result;
    }

    private function getDKIMRecord(string $domain, string $selector): ?string {
        $lookup = "$selector._domainkey.$domain";
        $records = @dns_get_record($lookup, DNS_TXT);

        if (!$records) return null;

        foreach ($records as $record) {
            if (isset($record['txt']) && stripos($record['txt'], 'v=DKIM1') !== false) {
                return $record['txt'];
            }
        }
        return null;
    }

    private function parseDKIM(string $record): array {
        $result = [];
        $pairs = explode(';', $record);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;

            list($key, $value) = explode('=', $pair, 2);
            $result[trim($key)] = trim($value);
        }

        return [
            'version' => $result['v'] ?? null,
            'key_type' => $result['k'] ?? 'rsa',
            'public_key' => $result['p'] ?? null,
            'hash_algorithms' => $result['h'] ?? 'sha256',
            'service_type' => $result['s'] ?? '*',
            'flags' => $result['t'] ?? null,
            'notes' => $result['n'] ?? null
        ];
    }

    private function extractDKIMSignature(string $headers): ?array {
        if (preg_match('/DKIM-Signature:\s*(.+?)(?=\r?\n\S|\r?\n\r?\n|$)/is', $headers, $matches)) {
            $signature = $matches[1];
            $parsed = [];

            preg_match_all('/(\w+)=([^;]+);?/', $signature, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $pair) {
                $parsed[trim($pair[1])] = trim($pair[2]);
            }

            return $parsed;
        }
        return null;
    }

    private function verifyDKIM(string $headers, array $signature): array {
        // This is a simplified verification - full DKIM verification requires
        // reconstructing the signed headers and verifying the RSA signature
        $domain = $signature['d'] ?? null;
        $selector = $signature['s'] ?? null;

        if (!$domain || !$selector) {
            return [
                'verified' => false,
                'error' => 'Missing domain or selector in DKIM signature'
            ];
        }

        $publicKey = $this->getDKIMRecord($domain, $selector);

        return [
            'verified' => !empty($publicKey),
            'domain' => $domain,
            'selector' => $selector,
            'algorithm' => $signature['a'] ?? 'unknown',
            'headers_signed' => $signature['h'] ?? null,
            'body_hash' => $signature['bh'] ?? null,
            'signature' => substr($signature['b'] ?? '', 0, 50) . '...',
            'public_key_found' => !empty($publicKey),
            'timestamp' => $signature['t'] ?? null
        ];
    }

    private function getDMARCRecord(string $domain): ?string {
        $lookup = "_dmarc.$domain";
        $records = @dns_get_record($lookup, DNS_TXT);

        if (!$records) return null;

        foreach ($records as $record) {
            if (isset($record['txt']) && stripos($record['txt'], 'v=DMARC1') === 0) {
                return $record['txt'];
            }
        }
        return null;
    }

    private function parseDMARC(string $record): array {
        $result = [
            'version' => null,
            'policy' => null,
            'subdomain_policy' => null,
            'percentage' => 100,
            'adkim' => 'r',
            'aspf' => 'r',
            'rua' => [],
            'ruf' => [],
            'fo' => '0',
            'rf' => 'afrf',
            'warnings' => []
        ];

        $pairs = explode(';', $record);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) continue;

            if (!str_contains($pair, '=')) continue;

            list($key, $value) = explode('=', $pair, 2);
            $key = trim($key);
            $value = trim($value);

            switch ($key) {
                case 'v':
                    $result['version'] = $value;
                    break;
                case 'p':
                    $result['policy'] = $value;
                    break;
                case 'sp':
                    $result['subdomain_policy'] = $value;
                    break;
                case 'pct':
                    $result['percentage'] = (int)$value;
                    break;
                case 'adkim':
                    $result['adkim'] = $value;
                    break;
                case 'aspf':
                    $result['aspf'] = $value;
                    break;
                case 'rua':
                    $result['rua'] = array_map('trim', explode(',', $value));
                    break;
                case 'ruf':
                    $result['ruf'] = array_map('trim', explode(',', $value));
                    break;
                case 'fo':
                    $result['fo'] = $value;
                    break;
                case 'rf':
                    $result['rf'] = $value;
                    break;
            }
        }

        if (!in_array($result['policy'], ['none', 'quarantine', 'reject'])) {
            $result['warnings'][] = 'Invalid DMARC policy';
        }

        return $result;
    }

    private function getMXRecords(string $domain): array {
        $records = @dns_get_record($domain, DNS_MX);
        if (!$records) return [];

        usort($records, fn($a, $b) => $a['pri'] <=> $b['pri']);
        return $records;
    }

    private function resolveHostname(string $hostname): array {
        $ips = [];

        // Get IPv4
        $a = @dns_get_record($hostname, DNS_A);
        if ($a) {
            foreach ($a as $record) {
                $ips[] = $record['ip'];
            }
        }

        // Get IPv6
        $aaaa = @dns_get_record($hostname, DNS_AAAA);
        if ($aaaa) {
            foreach ($aaaa as $record) {
                $ips[] = $record['ipv6'];
            }
        }

        return $ips;
    }

    private function getReverseDNS(string $hostname): array {
        $ips = $this->resolveHostname($hostname);
        $ptr = [];

        foreach ($ips as $ip) {
            $reverse = @gethostbyaddr($ip);
            if ($reverse && $reverse !== $ip) {
                $ptr[$ip] = $reverse;
            }
        }

        return $ptr;
    }

    private function checkTLSSupport(string $hostname, int $port = 25): bool {
        // Simple socket check for STARTTLS support
        $socket = @fsockopen($hostname, $port, $errno, $errstr, 5);
        if (!$socket) return false;

        stream_set_timeout($socket, 5);
        fgets($socket); // Read banner

        fwrite($socket, "EHLO veribits.com\r\n");
        $response = '';
        while ($line = fgets($socket)) {
            $response .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }

        fclose($socket);

        return stripos($response, 'STARTTLS') !== false;
    }

    private function parseEmailHeaders(string $headers): array {
        $result = [
            'from' => null,
            'to' => null,
            'subject' => null,
            'date' => null,
            'message_id' => null,
            'spf' => null,
            'dkim' => null,
            'dmarc' => null,
            'received' => [],
            'spam_score' => null,
            'delivery_time' => null,
            'warnings' => []
        ];

        $lines = explode("\n", $headers);

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^From:\s*(.+)$/i', $line, $m)) {
                $result['from'] = trim($m[1]);
            } elseif (preg_match('/^To:\s*(.+)$/i', $line, $m)) {
                $result['to'] = trim($m[1]);
            } elseif (preg_match('/^Subject:\s*(.+)$/i', $line, $m)) {
                $result['subject'] = trim($m[1]);
            } elseif (preg_match('/^Date:\s*(.+)$/i', $line, $m)) {
                $result['date'] = trim($m[1]);
            } elseif (preg_match('/^Message-ID:\s*(.+)$/i', $line, $m)) {
                $result['message_id'] = trim($m[1]);
            } elseif (preg_match('/^Received:\s*(.+)$/i', $line, $m)) {
                $result['received'][] = trim($m[1]);
            } elseif (preg_match('/^Authentication-Results:.*spf=(\w+)/i', $line, $m)) {
                $result['spf'] = strtolower($m[1]);
            } elseif (preg_match('/^Authentication-Results:.*dkim=(\w+)/i', $line, $m)) {
                $result['dkim'] = strtolower($m[1]);
            } elseif (preg_match('/^Authentication-Results:.*dmarc=(\w+)/i', $line, $m)) {
                $result['dmarc'] = strtolower($m[1]);
            } elseif (preg_match('/^X-Spam-Score:\s*(.+)$/i', $line, $m)) {
                $result['spam_score'] = trim($m[1]);
            }
        }

        return $result;
    }

    private function checkRBLs(string $query, bool $isIP): array {
        $rbls = [
            'zen.spamhaus.org' => 'Spamhaus ZEN',
            'bl.spamcop.net' => 'SpamCop',
            'dnsbl.sorbs.net' => 'SORBS',
            'b.barracudacentral.org' => 'Barracuda',
            'dnsbl-1.uceprotect.net' => 'UCEPROTECT Level 1',
            'cbl.abuseat.org' => 'Composite Blocking List',
            'psbl.surriel.com' => 'Passive Spam Block List',
            'dnsbl.invaluement.com' => 'Invaluement',
            'ix.dnsbl.manitu.net' => 'NiX Spam',
            'all.s5h.net' => 'S5H'
        ];

        $results = [];

        if ($isIP) {
            // Reverse IP for DNS lookup
            $reversed = implode('.', array_reverse(explode('.', $query)));

            foreach ($rbls as $rbl => $name) {
                $lookup = "$reversed.$rbl";
                $listed = @dns_get_record($lookup, DNS_A);

                $results[] = [
                    'rbl' => $name,
                    'hostname' => $rbl,
                    'listed' => !empty($listed),
                    'response' => $listed[0]['ip'] ?? null
                ];
            }
        } else {
            // Domain-based RBL check
            $domainRBLs = [
                'dbl.spamhaus.org' => 'Spamhaus DBL',
                'multi.uribl.com' => 'URIBL Multi',
                'multi.surbl.org' => 'SURBL Multi'
            ];

            foreach ($domainRBLs as $rbl => $name) {
                $lookup = "$query.$rbl";
                $listed = @dns_get_record($lookup, DNS_A);

                $results[] = [
                    'rbl' => $name,
                    'hostname' => $rbl,
                    'listed' => !empty($listed),
                    'response' => $listed[0]['ip'] ?? null
                ];
            }
        }

        return $results;
    }

    private function assessSPFStrength(array $parsed): string {
        $score = 0;

        if ($parsed['all'] === '-all') $score += 3;
        elseif ($parsed['all'] === '~all') $score += 2;
        elseif ($parsed['all'] === '?all') $score += 1;

        if ($parsed['dns_lookups'] <= 10) $score += 2;
        if (!empty($parsed['ip4']) || !empty($parsed['ip6'])) $score += 1;

        if ($score >= 5) return 'strong';
        if ($score >= 3) return 'moderate';
        return 'weak';
    }

    private function assessDMARCStrength(array $parsed): string {
        if ($parsed['policy'] === 'reject' && $parsed['percentage'] === 100) {
            return 'strong';
        }
        if ($parsed['policy'] === 'quarantine') {
            return 'moderate';
        }
        return 'weak';
    }

    private function getDMARCRecommendations(array $parsed): array {
        $recommendations = [];

        if ($parsed['policy'] === 'none') {
            $recommendations[] = 'Consider upgrading to "quarantine" or "reject" policy';
        }

        if (empty($parsed['rua'])) {
            $recommendations[] = 'Add aggregate report email (rua) to receive delivery reports';
        }

        if ($parsed['percentage'] < 100) {
            $recommendations[] = 'Increase percentage to 100% once testing is complete';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'DMARC configuration looks good';
        }

        return $recommendations;
    }

    private function calculateBlacklistSeverity(array $listed): string {
        $count = count($listed);
        if ($count === 0) return 'none';
        if ($count >= 5) return 'critical';
        if ($count >= 3) return 'high';
        if ($count >= 2) return 'medium';
        return 'low';
    }

    private function getDeliverabilityRecommendations(array $factors, int $score): array {
        $recommendations = [];

        foreach ($factors as $factor) {
            if ($factor['status'] === 'fail') {
                switch ($factor['name']) {
                    case 'SPF Record':
                        $recommendations[] = 'Configure SPF record to authorize mail servers';
                        break;
                    case 'DMARC Policy':
                        $recommendations[] = 'Implement DMARC policy to prevent email spoofing';
                        break;
                    case 'MX Records':
                        $recommendations[] = 'Configure MX records to receive email';
                        break;
                    case 'Blacklist Status':
                        $recommendations[] = 'Remove from blacklists to improve deliverability';
                        break;
                }
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Email configuration is excellent!';
        }

        return $recommendations;
    }
}
