<?php
namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\RateLimit;

class ToolSearchController {

    private static array $tools = [
        // Network & DNS Tools
        [
            'id' => 'dns-check',
            'name' => 'DNS Lookup',
            'category' => 'Network',
            'description' => 'Query DNS records for any domain (A, AAAA, MX, TXT, CNAME, NS, SOA, etc.)',
            'endpoint' => '/api/v1/tools/dns',
            'cli_command' => 'veribits dns example.com',
            'keywords' => ['dns', 'domain', 'lookup', 'nameserver', 'records', 'mx', 'txt', 'cname', 'a record'],
            'url' => '/tools/dns'
        ],
        [
            'id' => 'whois',
            'name' => 'WHOIS Lookup',
            'category' => 'Network',
            'description' => 'Get domain and IP WHOIS information',
            'endpoint' => '/api/v1/tools/whois',
            'cli_command' => 'veribits whois example.com',
            'keywords' => ['whois', 'domain', 'registrar', 'registration', 'ip', 'owner'],
            'url' => '/tools/whois'
        ],
        [
            'id' => 'ip-calculator',
            'name' => 'IP Calculator',
            'category' => 'Network',
            'description' => 'Calculate subnet masks, CIDR ranges, and IP information',
            'endpoint' => '/api/v1/tools/ip-calculator',
            'cli_command' => 'veribits ip-calc 192.168.1.0/24',
            'keywords' => ['ip', 'subnet', 'cidr', 'netmask', 'calculator', 'network', 'ipv4', 'ipv6'],
            'url' => '/tools/ip-calculator'
        ],
        [
            'id' => 'traceroute',
            'name' => 'Visual Traceroute',
            'category' => 'Network',
            'description' => 'Trace network path to destination with visual map',
            'endpoint' => '/api/v1/tools/traceroute',
            'cli_command' => 'veribits traceroute example.com',
            'keywords' => ['traceroute', 'trace', 'route', 'hops', 'network', 'path', 'latency'],
            'url' => '/tools/traceroute'
        ],
        [
            'id' => 'bgp-intelligence',
            'name' => 'BGP Intelligence',
            'category' => 'Network',
            'description' => 'BGP routing information and AS lookups',
            'endpoint' => '/api/v1/tools/bgp',
            'cli_command' => 'veribits bgp AS15169',
            'keywords' => ['bgp', 'as', 'autonomous system', 'routing', 'prefix', 'asn'],
            'url' => '/tools/bgp'
        ],

        // Crypto & Security Tools
        [
            'id' => 'pgp',
            'name' => 'PGP/GPG Tools',
            'category' => 'Cryptography',
            'description' => 'Encrypt, decrypt, sign and verify PGP/GPG messages',
            'endpoint' => '/api/v1/tools/pgp',
            'cli_command' => 'veribits pgp encrypt --key public.key message.txt',
            'keywords' => ['pgp', 'gpg', 'encrypt', 'decrypt', 'sign', 'verify', 'openpgp', 'keys'],
            'url' => '/tools/pgp'
        ],
        [
            'id' => 'ssl-check',
            'name' => 'SSL/TLS Checker',
            'category' => 'Security',
            'description' => 'Analyze SSL/TLS certificates and security',
            'endpoint' => '/api/v1/tools/ssl-check',
            'cli_command' => 'veribits ssl-check example.com',
            'keywords' => ['ssl', 'tls', 'certificate', 'https', 'security', 'cert', 'chain'],
            'url' => '/tools/ssl-check'
        ],
        [
            'id' => 'ssl-generator',
            'name' => 'SSL Certificate Generator',
            'category' => 'Security',
            'description' => 'Generate self-signed SSL certificates',
            'endpoint' => '/api/v1/tools/ssl-generate',
            'cli_command' => 'veribits ssl-gen --domain example.com',
            'keywords' => ['ssl', 'certificate', 'generate', 'self-signed', 'cert', 'create'],
            'url' => '/tools/ssl-generator'
        ],
        [
            'id' => 'jwt',
            'name' => 'JWT Tools',
            'category' => 'Cryptography',
            'description' => 'Create, decode, and validate JSON Web Tokens',
            'endpoint' => '/api/v1/tools/jwt',
            'cli_command' => 'veribits jwt decode <token>',
            'keywords' => ['jwt', 'json', 'token', 'decode', 'verify', 'authentication', 'auth'],
            'url' => '/tools/jwt'
        ],
        [
            'id' => 'crypto-validate',
            'name' => 'Crypto Validation',
            'category' => 'Cryptography',
            'description' => 'Validate cryptocurrency addresses and transactions',
            'endpoint' => '/api/v1/tools/crypto-validate',
            'cli_command' => 'veribits crypto-validate <address>',
            'keywords' => ['crypto', 'cryptocurrency', 'bitcoin', 'ethereum', 'validate', 'address'],
            'url' => '/tools/crypto'
        ],

        // File Tools
        [
            'id' => 'file-magic',
            'name' => 'File Magic Numbers',
            'category' => 'Files',
            'description' => 'Identify file types by magic numbers/signatures',
            'endpoint' => '/api/v1/tools/file-magic',
            'cli_command' => 'veribits file-magic file.bin',
            'keywords' => ['file', 'magic', 'signature', 'type', 'identify', 'mimetype', 'header'],
            'url' => '/tools/file-magic'
        ],
        [
            'id' => 'file-signature',
            'name' => 'File Signature Verification',
            'category' => 'Security',
            'description' => 'Verify digital signatures on files',
            'endpoint' => '/api/v1/verify/file-signature',
            'cli_command' => 'veribits verify-signature file.exe',
            'keywords' => ['file', 'signature', 'verify', 'digital', 'sign', 'authenticode'],
            'url' => '/tools/file-signature'
        ],
        [
            'id' => 'malware-scan',
            'name' => 'Malware Scanner',
            'category' => 'Security',
            'description' => 'Scan files for malware and viruses',
            'endpoint' => '/api/v1/verify/malware',
            'cli_command' => 'veribits malware-scan file.exe',
            'keywords' => ['malware', 'virus', 'scan', 'antivirus', 'security', 'threat'],
            'url' => '/tools/malware-scan'
        ],
        [
            'id' => 'archive-inspection',
            'name' => 'Archive Inspector',
            'category' => 'Files',
            'description' => 'Inspect archive contents (ZIP, TAR, etc.)',
            'endpoint' => '/api/v1/inspect/archive',
            'cli_command' => 'veribits inspect-archive file.zip',
            'keywords' => ['archive', 'zip', 'tar', 'inspect', 'extract', 'contents'],
            'url' => '/tools/archive-inspection'
        ],
        [
            'id' => 'steganography',
            'name' => 'Steganography Tools',
            'category' => 'Security',
            'description' => 'Hide and extract data in images',
            'endpoint' => '/api/v1/tools/steganography',
            'cli_command' => 'veribits stego hide --image photo.png --message secret.txt',
            'keywords' => ['steganography', 'stego', 'hide', 'extract', 'image', 'secret', 'data'],
            'url' => '/tools/stego'
        ],
        [
            'id' => 'code-signing',
            'name' => 'Code Signing',
            'category' => 'Security',
            'description' => 'Sign executables and code',
            'endpoint' => '/api/v1/tools/code-sign',
            'cli_command' => 'veribits code-sign --cert cert.pfx file.exe',
            'keywords' => ['code', 'signing', 'authenticode', 'sign', 'executable', 'binary'],
            'url' => '/tools/code-signing'
        ],
        [
            'id' => 'breach-check',
            'name' => 'Data Breach Checker',
            'category' => 'Security',
            'description' => 'Check if emails or passwords have been compromised in data breaches using Have I Been Pwned database',
            'endpoint' => '/api/v1/hibp/check-email',
            'cli_command' => 'veribits breach:email user@example.com',
            'keywords' => ['breach', 'hibp', 'haveibeenpwned', 'password', 'email', 'pwned', 'compromised', 'leak', 'security', 'data breach'],
            'url' => '/tools/breach-check'
        ],

        // Developer Tools
        [
            'id' => 'base64',
            'name' => 'Base64 Encoder/Decoder',
            'category' => 'Developer',
            'description' => 'Encode and decode Base64 strings',
            'endpoint' => '/api/v1/tools/base64',
            'cli_command' => 'veribits base64 encode "hello world"',
            'keywords' => ['base64', 'encode', 'decode', 'encoding', 'conversion'],
            'url' => '/tools/base64'
        ],
        [
            'id' => 'hash',
            'name' => 'Hash Generator',
            'category' => 'Cryptography',
            'description' => 'Generate MD5, SHA1, SHA256, SHA512 hashes',
            'endpoint' => '/api/v1/tools/hash',
            'cli_command' => 'veribits hash --algo sha256 file.txt',
            'keywords' => ['hash', 'md5', 'sha1', 'sha256', 'sha512', 'checksum', 'digest'],
            'url' => '/tools/hash'
        ],

        // Cloud & Infrastructure Tools
        [
            'id' => 'cloud-storage-auditor',
            'name' => 'Cloud Storage Security Auditor',
            'category' => 'Cloud Security',
            'description' => 'Multi-cloud blob storage security scanner for AWS S3, Google Cloud Storage, Azure Blob Storage, and Digital Ocean Spaces. Search for files and content across your cloud infrastructure.',
            'endpoint' => '/api/v1/tools/cloud-storage',
            'cli_command' => 'veribits cloud-storage scan --provider aws --search "config.json"',
            'keywords' => ['cloud', 'storage', 's3', 'aws', 'azure', 'gcs', 'blob', 'bucket', 'security', 'audit', 'digital ocean', 'spaces', 'search', 'enterprise'],
            'url' => '/tools/cloud-storage-auditor'
        ],
    ];

    public function search(): void {
        $clientIp = RateLimit::getClientIp();

        // Check rate limit
        if (!RateLimit::check("tool_search:$clientIp", 100, 60)) {
            Response::json(['error' => 'Rate limit exceeded'], 429);
            return;
        }

        $query = $_GET['q'] ?? $_GET['query'] ?? '';
        $category = $_GET['category'] ?? '';

        if (empty($query) && empty($category)) {
            Response::success([
                'tools' => self::$tools,
                'total' => count(self::$tools),
                'categories' => $this->getCategories()
            ]);
            return;
        }

        $results = $this->performSearch($query, $category);

        Response::success([
            'query' => $query,
            'category' => $category,
            'tools' => $results,
            'total' => count($results),
            'categories' => $this->getCategories()
        ]);
    }

    private function performSearch(string $query, string $category): array {
        $query = strtolower(trim($query));
        $results = [];

        foreach (self::$tools as $tool) {
            $score = 0;

            // Category filter
            if (!empty($category) && strcasecmp($tool['category'], $category) !== 0) {
                continue;
            }

            // If no query, return all tools in category
            if (empty($query)) {
                $results[] = array_merge($tool, ['score' => 0]);
                continue;
            }

            // Exact name match - highest score
            if (stripos($tool['name'], $query) !== false) {
                $score += 100;
            }

            // ID match
            if (stripos($tool['id'], $query) !== false) {
                $score += 90;
            }

            // Description match
            if (stripos($tool['description'], $query) !== false) {
                $score += 50;
            }

            // Keyword matches
            foreach ($tool['keywords'] as $keyword) {
                if (stripos($keyword, $query) !== false) {
                    $score += 30;
                }
                if (strcasecmp($keyword, $query) === 0) {
                    $score += 50; // Exact keyword match
                }
            }

            // CLI command match
            if (stripos($tool['cli_command'], $query) !== false) {
                $score += 20;
            }

            // Category match
            if (stripos($tool['category'], $query) !== false) {
                $score += 40;
            }

            if ($score > 0) {
                $results[] = array_merge($tool, ['score' => $score]);
            }
        }

        // Sort by score (highest first)
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    private function getCategories(): array {
        $categories = [];
        foreach (self::$tools as $tool) {
            $category = $tool['category'];
            if (!isset($categories[$category])) {
                $categories[$category] = [
                    'name' => $category,
                    'count' => 0
                ];
            }
            $categories[$category]['count']++;
        }
        return array_values($categories);
    }

    public function list(): void {
        Response::success([
            'tools' => self::$tools,
            'total' => count(self::$tools),
            'categories' => $this->getCategories()
        ]);
    }

    public static function getAllTools(): array {
        return self::$tools;
    }
}
