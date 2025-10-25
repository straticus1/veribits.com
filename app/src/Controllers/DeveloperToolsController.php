<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class DeveloperToolsController
{
    /**
     * Test regex pattern against text
     */
    public function regexTest(): void
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
        $pattern = $input['pattern'] ?? '';
        $text = $input['text'] ?? '';
        $flags = $input['flags'] ?? 'g';

        if (empty($pattern)) {
            Response::error('Regex pattern is required', 400);
            return;
        }

        try {
            // Convert flags to PHP format
            $phpFlags = '';
            if (str_contains($flags, 'i')) $phpFlags .= 'i';
            if (str_contains($flags, 'm')) $phpFlags .= 'm';
            if (str_contains($flags, 's')) $phpFlags .= 's';

            // Build full pattern
            $fullPattern = '/' . str_replace('/', '\/', $pattern) . '/' . $phpFlags;

            // Test pattern
            $matches = [];
            $matchCount = @preg_match_all($fullPattern, $text, $matches, PREG_OFFSET_CAPTURE);

            if ($matchCount === false) {
                throw new \Exception(error_get_last()['message'] ?? 'Invalid regex pattern');
            }

            $result = [
                'is_valid' => true,
                'match_count' => $matchCount,
                'matches' => [],
                'pattern' => $fullPattern
            ];

            // Format matches
            if ($matchCount > 0) {
                foreach ($matches[0] as $index => $match) {
                    $result['matches'][] = [
                        'match' => $match[0],
                        'position' => $match[1],
                        'index' => $index
                    ];
                }
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Regex test completed', $result);

        } catch (\Exception $e) {
            Response::error('Regex error: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Validate and format JSON/YAML
     */
    public function validateData(): void
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
        $data = $input['data'] ?? '';
        $type = $input['type'] ?? 'json'; // 'json' or 'yaml'
        $action = $input['action'] ?? 'validate'; // 'validate', 'format', 'convert'

        if (empty($data)) {
            Response::error('Data is required', 400);
            return;
        }

        try {
            $result = [
                'is_valid' => false,
                'type' => $type
            ];

            if ($type === 'json') {
                $decoded = json_decode($data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON: ' . json_last_error_msg());
                }

                $result['is_valid'] = true;
                $result['formatted'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $result['minified'] = json_encode($decoded);
                $result['size_original'] = strlen($data);
                $result['size_formatted'] = strlen($result['formatted']);
                $result['size_minified'] = strlen($result['minified']);

                // Convert to YAML if requested
                if ($action === 'convert') {
                    $result['converted_to_yaml'] = $this->arrayToYaml($decoded);
                }

            } elseif ($type === 'yaml') {
                // Basic YAML parsing (simple implementation)
                $lines = explode("\n", $data);
                $parsed = $this->parseYaml($lines);

                $result['is_valid'] = true;
                $result['parsed'] = $parsed;
                $result['formatted'] = $this->arrayToYaml($parsed);

                // Convert to JSON if requested
                if ($action === 'convert') {
                    $result['converted_to_json'] = json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
            }

            if (!$auth['authenticated']) {
                RateLimit::incrementAnonymousScan($auth['ip_address']);
            }

            Response::success('Data validation completed', $result);

        } catch (\Exception $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Scan text for secrets (API keys, tokens, passwords)
     */
    public function scanSecrets(): void
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
        $text = $input['text'] ?? '';

        if (empty($text)) {
            Response::error('Text to scan is required', 400);
            return;
        }

        $secrets = [];

        // Patterns for common secrets
        $patterns = [
            'AWS Access Key' => '/AKIA[0-9A-Z]{16}/',
            'AWS Secret Key' => '/aws(.{0,20})?[\'"][0-9a-zA-Z\/+]{40}[\'"]/',
            'GitHub Token' => '/ghp_[0-9a-zA-Z]{36}/',
            'GitHub OAuth' => '/gho_[0-9a-zA-Z]{36}/',
            'Slack Token' => '/xox[baprs]-[0-9a-zA-Z-]{10,48}/',
            'Stripe API Key' => '/sk_live_[0-9a-zA-Z]{24}/',
            'Stripe Publishable' => '/pk_live_[0-9a-zA-Z]{24}/',
            'Google API Key' => '/AIza[0-9A-Za-z\\-_]{35}/',
            'Google OAuth' => '/[0-9]+-[0-9A-Za-z_]{32}\.apps\.googleusercontent\.com/',
            'Heroku API Key' => '/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/',
            'MailChimp API Key' => '/[0-9a-f]{32}-us[0-9]{1,2}/',
            'Mailgun API Key' => '/key-[0-9a-zA-Z]{32}/',
            'PayPal Braintree' => '/access_token\$production\$[0-9a-z]{16}\$[0-9a-f]{32}/',
            'Picatic API Key' => '/sk_live_[0-9a-z]{32}/',
            'SendGrid API Key' => '/SG\.[0-9A-Za-z\-_]{22}\.[0-9A-Za-z\-_]{43}/',
            'Twilio API Key' => '/SK[0-9a-fA-F]{32}/',
            'Twitter Access Token' => '/[1-9][0-9]+-[0-9a-zA-Z]{40}/',
            'Private SSH Key' => '/-----BEGIN (RSA|OPENSSH|DSA|EC) PRIVATE KEY-----/',
            'Generic API Key' => '/api[_-]?key[\'"]?\s*[:=]\s*[\'"]?[0-9a-zA-Z]{16,}/',
            'Generic Secret' => '/secret[\'"]?\s*[:=]\s*[\'"]?[0-9a-zA-Z]{16,}/',
            'Password in Code' => '/password[\'"]?\s*[:=]\s*[\'"][^\'"]{8,}/',
            'JWT Token' => '/eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/',
            'Basic Auth' => '/Authorization:\s*Basic\s+[A-Za-z0-9+\/]+=*/',
            'Bearer Token' => '/Authorization:\s*Bearer\s+[A-Za-z0-9\-._~+\/]+=*/'
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $secrets[] = [
                        'type' => $name,
                        'value' => $this->maskSecret($match[0]),
                        'line' => substr_count(substr($text, 0, $match[1]), "\n") + 1,
                        'position' => $match[1],
                        'severity' => $this->getSeverity($name)
                    ];
                }
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('Secret scan completed', [
            'secrets_found' => count($secrets),
            'secrets' => $secrets,
            'risk_level' => count($secrets) === 0 ? 'low' : (count($secrets) < 5 ? 'medium' : 'high')
        ]);
    }

    /**
     * Generate hashes
     */
    public function generateHash(): void
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
        $text = $input['text'] ?? '';
        $algorithms = $input['algorithms'] ?? ['md5', 'sha1', 'sha256', 'sha512'];

        if (empty($text)) {
            Response::error('Text is required', 400);
            return;
        }

        $hashes = [];

        foreach ($algorithms as $algo) {
            $algo = strtolower($algo);
            if ($algo === 'bcrypt') {
                $hashes[$algo] = password_hash($text, PASSWORD_BCRYPT);
            } elseif (in_array($algo, hash_algos())) {
                $hashes[$algo] = hash($algo, $text);
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('Hashes generated', [
            'hashes' => $hashes,
            'input_length' => strlen($text)
        ]);
    }

    /**
     * URL encode/decode
     */
    public function urlEncode(): void
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
        $text = $input['text'] ?? '';
        $action = $input['action'] ?? 'encode';

        if (empty($text)) {
            Response::error('Text is required', 400);
            return;
        }

        $result = [];

        if ($action === 'encode') {
            $result['encoded'] = urlencode($text);
            $result['encoded_component'] = rawurlencode($text);
        } else {
            $result['decoded'] = urldecode($text);
            $result['decoded_component'] = rawurldecode($text);

            // Parse URL if it looks like a URL
            if (filter_var($text, FILTER_VALIDATE_URL)) {
                $parsed = parse_url($text);
                $result['parsed'] = $parsed;
                if (isset($parsed['query'])) {
                    parse_str($parsed['query'], $params);
                    $result['query_params'] = $params;
                }
            }
        }

        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('URL operation completed', $result);
    }

    /**
     * Simple YAML parser
     */
    private function parseYaml(array $lines): array
    {
        $result = [];
        $currentIndent = 0;

        foreach ($lines as $line) {
            $line = rtrim($line);
            if (empty($line) || $line[0] === '#') continue;

            preg_match('/^(\s*)(.+)$/', $line, $matches);
            $indent = strlen($matches[1] ?? '');
            $content = $matches[2] ?? '';

            if (str_contains($content, ':')) {
                [$key, $value] = array_map('trim', explode(':', $content, 2));
                $result[$key] = $value ?: [];
            }
        }

        return $result;
    }

    /**
     * Convert array to YAML
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $spaces = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $yaml .= $spaces . $key . ":\n";
                $yaml .= $this->arrayToYaml($value, $indent + 1);
            } else {
                $yaml .= $spaces . $key . ': ' . $value . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Mask secret for display
     */
    private function maskSecret(string $secret): string
    {
        $len = strlen($secret);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }
        return substr($secret, 0, 4) . str_repeat('*', $len - 8) . substr($secret, -4);
    }

    /**
     * Get severity for secret type
     */
    private function getSeverity(string $type): string
    {
        $critical = ['AWS Secret Key', 'Private SSH Key', 'Password in Code'];
        $high = ['AWS Access Key', 'GitHub Token', 'Stripe API Key', 'Google API Key'];

        if (in_array($type, $critical)) return 'critical';
        if (in_array($type, $high)) return 'high';
        return 'medium';
    }
}
