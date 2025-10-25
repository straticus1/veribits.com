<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class NetworkToolsController
{
    /**
     * Validate DNS records
     */
    public function dnsValidate(): void
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
        $domain = $input['domain'] ?? '';
        $recordType = $input['record_type'] ?? 'A';

        if (empty($domain)) {
            Response::error('Domain is required', 400);
            return;
        }

        try {
            $records = [];
            $dnssec = ['enabled' => false];

            // Query DNS records
            switch ($recordType) {
                case 'A':
                    $dnsRecords = @dns_get_record($domain, DNS_A);
                    break;
                case 'AAAA':
                    $dnsRecords = @dns_get_record($domain, DNS_AAAA);
                    break;
                case 'MX':
                    $dnsRecords = @dns_get_record($domain, DNS_MX);
                    break;
                case 'TXT':
                    $dnsRecords = @dns_get_record($domain, DNS_TXT);
                    break;
                case 'CNAME':
                    $dnsRecords = @dns_get_record($domain, DNS_CNAME);
                    break;
                case 'NS':
                    $dnsRecords = @dns_get_record($domain, DNS_NS);
                    break;
                case 'SOA':
                    $dnsRecords = @dns_get_record($domain, DNS_SOA);
                    break;
                case 'PTR':
                    $dnsRecords = @dns_get_record($domain, DNS_PTR);
                    break;
                case 'SRV':
                    $dnsRecords = @dns_get_record($domain, DNS_SRV);
                    break;
                case 'CAA':
                    $dnsRecords = @dns_get_record($domain, DNS_CAA);
                    break;
                default:
                    $dnsRecords = @dns_get_record($domain, DNS_ANY);
            }

            if ($dnsRecords === false) {
                Response::error('Failed to query DNS records', 400);
                return;
            }

            foreach ($dnsRecords as $record) {
                $recordData = [
                    'type' => $record['type'],
                    'ttl' => $record['ttl'] ?? null
                ];

                switch ($record['type']) {
                    case 'A':
                    case 'AAAA':
                        $recordData['value'] = $record['ip'] ?? $record['ipv6'] ?? '';
                        break;
                    case 'MX':
                        $recordData['value'] = $record['target'] ?? '';
                        $recordData['priority'] = $record['pri'] ?? null;
                        break;
                    case 'TXT':
                        $recordData['value'] = $record['txt'] ?? '';
                        break;
                    case 'CNAME':
                        $recordData['value'] = $record['target'] ?? '';
                        break;
                    case 'NS':
                        $recordData['value'] = $record['target'] ?? '';
                        break;
                    case 'SOA':
                        $recordData['value'] = sprintf(
                            'mname=%s rname=%s serial=%s',
                            $record['mname'] ?? '',
                            $record['rname'] ?? '',
                            $record['serial'] ?? ''
                        );
                        break;
                    case 'CAA':
                        $recordData['value'] = sprintf(
                            'flags=%s tag=%s value=%s',
                            $record['flags'] ?? '',
                            $record['tag'] ?? '',
                            $record['value'] ?? ''
                        );
                        break;
                    default:
                        $recordData['value'] = json_encode($record);
                }

                $records[] = $recordData;
            }

            // Check DNSSEC (simple check via dig if available)
            $dnssecCheck = @shell_exec("dig +dnssec " . escapeshellarg($domain) . " | grep -i 'ad;' 2>/dev/null");
            if (!empty($dnssecCheck)) {
                $dnssec['enabled'] = true;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('DNS validation completed', [
                'records' => $records,
                'dnssec' => $dnssec,
                'domain' => $domain,
                'record_type' => $recordType
            ]);

        } catch (\Exception $e) {
            Response::error('DNS validation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate IP subnet information
     */
    public function ipCalculate(): void
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
        $ip = $input['ip'] ?? '';
        $subnetMask = $input['subnet_mask'] ?? '';

        if (empty($ip)) {
            Response::error('IP address is required', 400);
            return;
        }

        try {
            // Parse CIDR notation
            if (str_contains($ip, '/')) {
                [$ipAddr, $cidr] = explode('/', $ip);
            } else {
                $ipAddr = $ip;
                $cidr = !empty($subnetMask) ? $this->maskToCidr($subnetMask) : 24;
            }

            if (!filter_var($ipAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                Response::error('Invalid IPv4 address', 400);
                return;
            }

            $cidr = (int)$cidr;
            if ($cidr < 0 || $cidr > 32) {
                Response::error('Invalid CIDR notation', 400);
                return;
            }

            // Calculate subnet information
            $ipLong = ip2long($ipAddr);
            $maskLong = -1 << (32 - $cidr);
            $networkLong = $ipLong & $maskLong;
            $broadcastLong = $networkLong | ~$maskLong;
            $firstUsableLong = $networkLong + 1;
            $lastUsableLong = $broadcastLong - 1;

            $totalHosts = pow(2, 32 - $cidr);
            $usableHosts = max(0, $totalHosts - 2);

            $ipClass = 'Unknown';
            $firstOctet = (int)explode('.', $ipAddr)[0];
            if ($firstOctet >= 1 && $firstOctet <= 126) $ipClass = 'A';
            elseif ($firstOctet >= 128 && $firstOctet <= 191) $ipClass = 'B';
            elseif ($firstOctet >= 192 && $firstOctet <= 223) $ipClass = 'C';
            elseif ($firstOctet >= 224 && $firstOctet <= 239) $ipClass = 'D (Multicast)';
            elseif ($firstOctet >= 240 && $firstOctet <= 255) $ipClass = 'E (Reserved)';

            $ipType = 'Public';
            if ($firstOctet == 10 ||
                ($firstOctet == 172 && (int)explode('.', $ipAddr)[1] >= 16 && (int)explode('.', $ipAddr)[1] <= 31) ||
                ($firstOctet == 192 && (int)explode('.', $ipAddr)[1] == 168)) {
                $ipType = 'Private';
            } elseif ($firstOctet == 127) {
                $ipType = 'Loopback';
            } elseif ($firstOctet == 169 && (int)explode('.', $ipAddr)[1] == 254) {
                $ipType = 'APIPA';
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('IP calculation completed', [
                'ip_address' => $ipAddr,
                'network_address' => long2ip($networkLong),
                'broadcast_address' => long2ip($broadcastLong),
                'subnet_mask' => long2ip($maskLong),
                'wildcard_mask' => long2ip(~$maskLong),
                'cidr' => $ipAddr . '/' . $cidr,
                'first_usable' => long2ip($firstUsableLong),
                'last_usable' => long2ip($lastUsableLong),
                'total_hosts' => $totalHosts,
                'usable_hosts' => $usableHosts,
                'ip_class' => $ipClass,
                'ip_type' => $ipType
            ]);

        } catch (\Exception $e) {
            Response::error('IP calculation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Convert subnet mask to CIDR notation
     */
    private function maskToCidr(string $mask): int
    {
        $long = ip2long($mask);
        $base = ip2long('255.255.255.255');
        return 32 - log(($long ^ $base) + 1, 2);
    }

    /**
     * Check RBL status for an IP address
     */
    public function rblCheck(): void
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
        $ip = $input['ip'] ?? '';

        if (empty($ip)) {
            Response::error('IP address is required', 400);
            return;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            Response::error('Invalid IPv4 address', 400);
            return;
        }

        try {
            $rbls = [
                'zen.spamhaus.org',
                'bl.spamcop.net',
                'b.barracudacentral.org',
                'dnsbl.sorbs.net',
                'cbl.abuseat.org',
                'psbl.surriel.com',
                'bl.spameatingmonkey.net',
                'dnsbl-1.uceprotect.net',
                'ix.dnsbl.manitu.net'
            ];

            $reversedIp = implode('.', array_reverse(explode('.', $ip)));
            $listings = [];
            $checkedRbls = [];

            foreach ($rbls as $rbl) {
                $checkedRbls[] = $rbl;
                $query = $reversedIp . '.' . $rbl;
                $result = @gethostbyname($query);

                // If result is not the same as query, IP is listed
                if ($result !== $query) {
                    $listings[] = [
                        'rbl' => $rbl,
                        'name' => $rbl,
                        'listed_at' => date('Y-m-d H:i:s'),
                        'reason' => 'Listed on ' . $rbl
                    ];
                }
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('RBL check completed', [
                'ip_address' => $ip,
                'listed' => count($listings) > 0,
                'blacklists_checked' => count($checkedRbls),
                'blacklists_found' => count($listings),
                'listings' => $listings,
                'checked_rbls' => $checkedRbls
            ]);

        } catch (\Exception $e) {
            Response::error('RBL check failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Check SMTP relay status
     */
    public function smtpRelayCheck(): void
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
        $target = $input['target'] ?? '';

        if (empty($target)) {
            Response::error('Email address or domain is required', 400);
            return;
        }

        try {
            // Extract domain from email if provided
            $domain = $target;
            if (str_contains($target, '@')) {
                $domain = substr($target, strrpos($target, '@') + 1);
            }

            // Get MX records
            $mxRecords = [];
            if (!getmxrr($domain, $mxRecords)) {
                Response::error('No MX records found for domain', 404);
                return;
            }

            $server = $mxRecords[0];
            $isOpenRelay = false;
            $testsPerformed = [];

            // Test 1: Try to connect to SMTP server
            $socket = @fsockopen($server, 25, $errno, $errstr, 10);
            if (!$socket) {
                Response::error('Could not connect to SMTP server: ' . $errstr, 500);
                return;
            }

            // Read banner
            $banner = fgets($socket);
            $testsPerformed[] = [
                'test' => 'SMTP Connection',
                'result' => 'Success',
                'passed' => true,
                'details' => trim($banner)
            ];

            // Send HELO
            fwrite($socket, "HELO veribits.com\r\n");
            $response = fgets($socket);

            // Test relay: Try to send from external to external
            fwrite($socket, "MAIL FROM:<test@external.com>\r\n");
            $mailFromResponse = fgets($socket);

            fwrite($socket, "RCPT TO:<test@external-recipient.com>\r\n");
            $rcptToResponse = fgets($socket);

            // Check if relay was accepted (250 response code)
            if (str_starts_with($rcptToResponse, '250')) {
                $isOpenRelay = true;
                $testsPerformed[] = [
                    'test' => 'External to External Relay',
                    'result' => 'FAILED - Relay Accepted',
                    'passed' => false,
                    'details' => 'Server accepted relay from external to external address'
                ];
            } else {
                $testsPerformed[] = [
                    'test' => 'External to External Relay',
                    'result' => 'PASSED - Relay Rejected',
                    'passed' => true,
                    'details' => 'Server properly rejected unauthorized relay'
                ];
            }

            // Clean disconnect
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('SMTP relay check completed', [
                'server' => $server,
                'mx_records' => $mxRecords,
                'is_open_relay' => $isOpenRelay,
                'open_relay' => $isOpenRelay,
                'tests_performed' => $testsPerformed
            ]);

        } catch (\Exception $e) {
            Response::error('SMTP relay check failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate DNS zone file
     */
    public function zoneValidate(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['file'])) {
            Response::error('No file uploaded', 400);
            return;
        }

        $file = $_FILES['file'];
        $type = $_POST['type'] ?? 'bind-zone';

        try {
            $tmpFile = $file['tmp_name'];
            $errors = [];
            $warnings = [];
            $valid = false;

            switch ($type) {
                case 'bind-zone':
                    // Use named-checkzone if available
                    $output = [];
                    $returnVar = 0;
                    exec("which named-checkzone 2>/dev/null", $output, $returnVar);

                    if ($returnVar === 0) {
                        $checkOutput = [];
                        exec("named-checkzone example.com " . escapeshellarg($tmpFile) . " 2>&1", $checkOutput, $returnVar);
                        $valid = $returnVar === 0;

                        foreach ($checkOutput as $line) {
                            if (str_contains(strtolower($line), 'error')) {
                                $errors[] = $line;
                            } elseif (str_contains(strtolower($line), 'warning')) {
                                $warnings[] = $line;
                            }
                        }
                    } else {
                        // Basic syntax check
                        $content = file_get_contents($tmpFile);
                        $valid = $this->basicZoneCheck($content, $errors, $warnings);
                    }
                    break;

                case 'named-conf':
                    // Use named-checkconf if available
                    $output = [];
                    $returnVar = 0;
                    exec("which named-checkconf 2>/dev/null", $output, $returnVar);

                    if ($returnVar === 0) {
                        $checkOutput = [];
                        exec("named-checkconf " . escapeshellarg($tmpFile) . " 2>&1", $checkOutput, $returnVar);
                        $valid = $returnVar === 0;

                        foreach ($checkOutput as $line) {
                            if (!empty($line)) {
                                $errors[] = $line;
                            }
                        }
                    } else {
                        $errors[] = 'named-checkconf not available - basic validation only';
                        $content = file_get_contents($tmpFile);
                        $valid = $this->basicConfigCheck($content, $errors, $warnings);
                    }
                    break;

                case 'nsd-conf':
                    // Use nsd-checkconf if available
                    $output = [];
                    $returnVar = 0;
                    exec("which nsd-checkconf 2>/dev/null", $output, $returnVar);

                    if ($returnVar === 0) {
                        $checkOutput = [];
                        exec("nsd-checkconf " . escapeshellarg($tmpFile) . " 2>&1", $checkOutput, $returnVar);
                        $valid = $returnVar === 0;

                        foreach ($checkOutput as $line) {
                            if (!empty($line)) {
                                $errors[] = $line;
                            }
                        }
                    } else {
                        $errors[] = 'nsd-checkconf not available - basic validation only';
                        $content = file_get_contents($tmpFile);
                        $valid = $this->basicConfigCheck($content, $errors, $warnings);
                    }
                    break;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Zone validation completed', [
                'valid' => $valid,
                'status' => $valid ? 'valid' : 'invalid',
                'errors' => $errors,
                'warnings' => $warnings,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            Response::error('Zone validation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Basic zone file syntax check
     */
    private function basicZoneCheck(string $content, array &$errors, array &$warnings): bool
    {
        $lines = explode("\n", $content);
        $valid = true;

        foreach ($lines as $lineNum => $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, ';')) {
                continue;
            }

            // Check for basic syntax errors
            if (str_contains($line, ' IN ')) {
                // Valid record type
                if (!preg_match('/\s(A|AAAA|MX|TXT|CNAME|NS|SOA|PTR|SRV|CAA)\s/', $line)) {
                    $warnings[] = "Line " . ($lineNum + 1) . ": Unknown record type";
                }
            }
        }

        return $valid && count($errors) === 0;
    }

    /**
     * Basic config file syntax check
     */
    private function basicConfigCheck(string $content, array &$errors, array &$warnings): bool
    {
        $valid = true;

        // Check for balanced braces
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');

        if ($openBraces !== $closeBraces) {
            $errors[] = "Unbalanced braces: {$openBraces} opening, {$closeBraces} closing";
            $valid = false;
        }

        // Check for balanced quotes
        $quotes = substr_count($content, '"') % 2;
        if ($quotes !== 0) {
            $errors[] = "Unbalanced quotes";
            $valid = false;
        }

        return $valid;
    }

    /**
     * Perform WHOIS lookup for domain or IP address
     */
    public function whoisLookup(): void
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
        $query = $input['query'] ?? '';

        if (empty($query)) {
            Response::error('Domain or IP address is required', 400);
            return;
        }

        try {
            // Validate input - must be valid domain or IP
            $isIP = filter_var($query, FILTER_VALIDATE_IP);
            $isDomain = !$isIP && preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $query);

            if (!$isIP && !$isDomain) {
                Response::error('Invalid domain or IP address', 400);
                return;
            }

            // Determine WHOIS server based on query type
            $whoisServer = null;
            $port = 43;

            if ($isIP) {
                // IP WHOIS - determine RIR
                $whoisServer = self::getIPWhoisServer($query);
            } else {
                // Domain WHOIS - extract TLD and get server
                $tld = substr($query, strrpos($query, '.') + 1);
                $whoisServer = self::getDomainWhoisServer($tld);
            }

            if (!$whoisServer) {
                Response::error('Could not determine WHOIS server for query', 400);
                return;
            }

            // Perform WHOIS lookup
            $result = self::performWhoisQuery($query, $whoisServer, $port);

            if (empty($result)) {
                Response::error('WHOIS query failed or returned no data', 500);
                return;
            }

            // Parse WHOIS response for common fields
            $parsed = self::parseWhoisResponse($result, $isIP);

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('WHOIS lookup completed', [
                'query' => $query,
                'query_type' => $isIP ? 'ip' : 'domain',
                'whois_server' => $whoisServer,
                'raw_response' => $result,
                'parsed' => $parsed
            ]);

        } catch (\Exception $e) {
            Response::error('WHOIS lookup failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get WHOIS server for IP address
     */
    private static function getIPWhoisServer(string $ip): ?string
    {
        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'whois.iana.org';
        }

        // IPv4 - determine RIR by first octet
        $firstOctet = (int)explode('.', $ip)[0];

        // Regional Internet Registries
        if ($firstOctet >= 1 && $firstOctet <= 2) return 'whois.arin.net';      // ARIN
        if ($firstOctet >= 5 && $firstOctet <= 6) return 'whois.ripe.net';      // RIPE
        if ($firstOctet >= 14 && $firstOctet <= 15) return 'whois.apnic.net';   // APNIC
        if ($firstOctet >= 24 && $firstOctet <= 27) return 'whois.arin.net';    // ARIN
        if ($firstOctet >= 41 && $firstOctet <= 41) return 'whois.afrinic.net'; // AFRINIC
        if ($firstOctet >= 58 && $firstOctet <= 61) return 'whois.apnic.net';   // APNIC
        if ($firstOctet >= 62 && $firstOctet <= 63) return 'whois.ripe.net';    // RIPE
        if ($firstOctet >= 80 && $firstOctet <= 95) return 'whois.ripe.net';    // RIPE
        if ($firstOctet >= 96 && $firstOctet <= 126) return 'whois.arin.net';   // ARIN
        if ($firstOctet >= 128 && $firstOctet <= 132) return 'whois.arin.net';  // ARIN
        if ($firstOctet >= 133 && $firstOctet <= 223) return 'whois.apnic.net'; // APNIC (most)
        if ($firstOctet >= 190 && $firstOctet <= 191) return 'whois.lacnic.net';// LACNIC
        if ($firstOctet >= 196 && $firstOctet <= 197) return 'whois.afrinic.net';// AFRINIC
        if ($firstOctet >= 200 && $firstOctet <= 201) return 'whois.lacnic.net';// LACNIC

        // Default to IANA
        return 'whois.iana.org';
    }

    /**
     * Get WHOIS server for domain TLD
     */
    private static function getDomainWhoisServer(string $tld): ?string
    {
        $servers = [
            'com' => 'whois.verisign-grs.com',
            'net' => 'whois.verisign-grs.com',
            'org' => 'whois.pir.org',
            'info' => 'whois.afilias.net',
            'biz' => 'whois.biz',
            'us' => 'whois.nic.us',
            'uk' => 'whois.nic.uk',
            'ca' => 'whois.cira.ca',
            'au' => 'whois.auda.org.au',
            'de' => 'whois.denic.de',
            'jp' => 'whois.jprs.jp',
            'fr' => 'whois.afnic.fr',
            'it' => 'whois.nic.it',
            'ru' => 'whois.tcinet.ru',
            'nl' => 'whois.domain-registry.nl',
            'br' => 'whois.registro.br',
            'eu' => 'whois.eu',
            'cn' => 'whois.cnnic.cn',
            'in' => 'whois.registry.in',
            'io' => 'whois.nic.io',
            'co' => 'whois.nic.co',
            'me' => 'whois.nic.me',
            'tv' => 'whois.nic.tv',
            'cc' => 'whois.nic.cc',
            'ws' => 'whois.website.ws',
            'mobi' => 'whois.dotmobiregistry.net',
            'name' => 'whois.nic.name',
            'asia' => 'whois.nic.asia',
            'tel' => 'whois.nic.tel',
            'xxx' => 'whois.nic.xxx'
        ];

        return $servers[strtolower($tld)] ?? 'whois.iana.org';
    }

    /**
     * Perform WHOIS query via socket
     */
    private static function performWhoisQuery(string $query, string $server, int $port = 43): string
    {
        $socket = @fsockopen($server, $port, $errno, $errstr, 10);

        if (!$socket) {
            throw new \Exception("Could not connect to WHOIS server {$server}: {$errstr}");
        }

        // Send query
        fwrite($socket, $query . "\r\n");

        // Read response
        $response = '';
        while (!feof($socket)) {
            $response .= fgets($socket, 128);
        }

        fclose($socket);

        return trim($response);
    }

    /**
     * Parse WHOIS response for common fields
     */
    private static function parseWhoisResponse(string $response, bool $isIP): array
    {
        $parsed = [];
        $lines = explode("\n", $response);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '%') || str_starts_with($line, '#')) {
                continue;
            }

            // Parse key-value pairs
            if (strpos($line, ':') !== false) {
                [$key, $value] = array_map('trim', explode(':', $line, 2));

                if ($isIP) {
                    // IP-specific fields
                    switch (strtolower($key)) {
                        case 'netname':
                        case 'netrange':
                        case 'cidr':
                        case 'orgname':
                        case 'organization':
                        case 'country':
                        case 'descr':
                        case 'remarks':
                            $parsed[$key] = $value;
                            break;
                    }
                } else {
                    // Domain-specific fields
                    switch (strtolower($key)) {
                        case 'domain name':
                        case 'registrar':
                        case 'creation date':
                        case 'updated date':
                        case 'registry expiry date':
                        case 'expiration date':
                        case 'registrant':
                        case 'registrant organization':
                        case 'admin contact':
                        case 'tech contact':
                        case 'name server':
                        case 'status':
                            $parsed[$key] = $value;
                            break;
                    }
                }
            }
        }

        return $parsed;
    }

    /**
     * Convert certificate format (PEM to PKCS12/JKS)
     */
    public function certConvert(): void
    {
        $auth = Auth::optionalAuth();

        if (!$auth['authenticated']) {
            $scanCheck = RateLimit::checkAnonymousScan($auth['ip_address'], 0);
            if (!$scanCheck['allowed']) {
                Response::error($scanCheck['message'], 429);
                return;
            }
        }

        if (!isset($_FILES['certificate']) || !isset($_FILES['private_key'])) {
            Response::error('Certificate and private key files are required', 400);
            return;
        }

        $certFile = $_FILES['certificate']['tmp_name'];
        $keyFile = $_FILES['private_key']['tmp_name'];
        $format = $_POST['format'] ?? 'pkcs12';
        $password = $_POST['password'] ?? '';
        $alias = $_POST['alias'] ?? 'mycert';

        try {
            $outputFile = tempnam(sys_get_temp_dir(), 'cert_');

            if ($format === 'pkcs12') {
                // Convert to PKCS12 using openssl
                $cmd = sprintf(
                    "openssl pkcs12 -export -in %s -inkey %s -out %s -name %s -passout pass:%s 2>&1",
                    escapeshellarg($certFile),
                    escapeshellarg($keyFile),
                    escapeshellarg($outputFile),
                    escapeshellarg($alias),
                    escapeshellarg($password)
                );

                exec($cmd, $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new \Exception('Certificate conversion failed: ' . implode("\n", $output));
                }

                $filename = 'certificate.p12';
                $contentType = 'application/x-pkcs12';

            } elseif ($format === 'jks') {
                // Convert to JKS via PKCS12 intermediate
                $p12File = tempnam(sys_get_temp_dir(), 'p12_');

                // First create PKCS12
                $cmd = sprintf(
                    "openssl pkcs12 -export -in %s -inkey %s -out %s -name %s -passout pass:%s 2>&1",
                    escapeshellarg($certFile),
                    escapeshellarg($keyFile),
                    escapeshellarg($p12File),
                    escapeshellarg($alias),
                    escapeshellarg($password ?: 'changeit')
                );

                exec($cmd, $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new \Exception('PKCS12 conversion failed: ' . implode("\n", $output));
                }

                // Then convert PKCS12 to JKS using keytool
                $cmd = sprintf(
                    "keytool -importkeystore -srckeystore %s -srcstoretype PKCS12 -srcstorepass %s -destkeystore %s -deststoretype JKS -deststorepass %s 2>&1",
                    escapeshellarg($p12File),
                    escapeshellarg($password ?: 'changeit'),
                    escapeshellarg($outputFile),
                    escapeshellarg($password ?: 'changeit')
                );

                exec($cmd, $output, $returnVar);

                @unlink($p12File);

                if ($returnVar !== 0) {
                    throw new \Exception('JKS conversion failed. keytool may not be available: ' . implode("\n", $output));
                }

                $filename = 'certificate.jks';
                $contentType = 'application/x-java-keystore';
            } else {
                throw new \Exception('Unsupported format');
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            // Send file as download
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($outputFile));

            readfile($outputFile);
            @unlink($outputFile);
            exit;

        } catch (\Exception $e) {
            Response::error('Certificate conversion failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Perform visual traceroute to a destination
     */
    public function traceroute(): void
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
        $target = $input['target'] ?? '';
        $maxHops = min((int)($input['max_hops'] ?? 30), 64); // Cap at 64 hops

        if (empty($target)) {
            Response::error('Target hostname or IP address is required', 400);
            return;
        }

        // Validate target (domain or IP)
        $isIP = filter_var($target, FILTER_VALIDATE_IP);
        $isDomain = !$isIP && preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $target);

        if (!$isIP && !$isDomain) {
            Response::error('Invalid hostname or IP address', 400);
            return;
        }

        try {
            $hops = [];
            $cmd = sprintf(
                "traceroute -m %d -q 3 -w 2 %s 2>&1",
                $maxHops,
                escapeshellarg($target)
            );

            exec($cmd, $output, $returnVar);

            // Parse traceroute output
            foreach ($output as $line) {
                // Skip header line
                if (strpos($line, 'traceroute to') !== false) {
                    continue;
                }

                // Parse hop line format: " 1  router.local (192.168.1.1)  1.234 ms  1.456 ms  1.678 ms"
                if (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $matches)) {
                    $hopNum = (int)$matches[1];
                    $hopData = trim($matches[2]);

                    $hop = [
                        'hop' => $hopNum,
                        'hostname' => null,
                        'ip' => null,
                        'latencies' => [],
                        'timeout' => false,
                        'location' => null
                    ];

                    // Check for timeout
                    if (strpos($hopData, '* * *') !== false) {
                        $hop['timeout'] = true;
                        $hops[] = $hop;
                        continue;
                    }

                    // Extract hostname and IP
                    if (preg_match('/^([^\(]+)\s*\(([^\)]+)\)/', $hopData, $addrMatch)) {
                        $hop['hostname'] = trim($addrMatch[1]);
                        $hop['ip'] = trim($addrMatch[2]);
                    } elseif (preg_match('/^(\d+\.\d+\.\d+\.\d+)/', $hopData, $ipMatch)) {
                        $hop['ip'] = $ipMatch[1];
                    }

                    // Extract latencies (ms values)
                    if (preg_match_all('/(\d+\.?\d*)\s*ms/', $hopData, $latencyMatches)) {
                        $hop['latencies'] = array_map('floatval', $latencyMatches[1]);
                    }

                    // Get geolocation for this hop's IP
                    if ($hop['ip']) {
                        $hop['location'] = $this->getIpGeolocation($hop['ip']);
                    }

                    $hops[] = $hop;
                }
            }

            if (empty($hops)) {
                Response::error('Traceroute failed or returned no data. The traceroute command may not be available.', 500);
                return;
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Traceroute completed', [
                'target' => $target,
                'hops' => $hops,
                'total_hops' => count($hops),
                'max_hops' => $maxHops
            ]);

        } catch (\Exception $e) {
            Response::error('Traceroute failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get geolocation data for an IP address
     */
    private function getIpGeolocation(string $ip): ?array
    {
        // Skip private/local IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        try {
            // Use free ip-api.com service (limit: 45 requests/minute)
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,region,regionName,city,lat,lon,isp,org,as";
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'user_agent' => 'VeriBits/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return null;
            }

            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? null,
                    'country_code' => $data['countryCode'] ?? null,
                    'region' => $data['regionName'] ?? null,
                    'city' => $data['city'] ?? null,
                    'latitude' => $data['lat'] ?? null,
                    'longitude' => $data['lon'] ?? null,
                    'isp' => $data['isp'] ?? null,
                    'org' => $data['org'] ?? null,
                    'as' => $data['as'] ?? null
                ];
            }
        } catch (\Exception $e) {
            // Fail silently - geolocation is optional
        }

        return null;
    }
}
