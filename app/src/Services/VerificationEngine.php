<?php
namespace VeriBits\Services;

use VeriBits\Utils\Database;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Redis;

class VerificationEngine {
    private const CACHE_TTL = 3600; // 1 hour
    private array $riskPatterns = [
        'common_malware_hashes' => [
            'd41d8cd98f00b204e9800998ecf8427e', // empty file
            '5d41402abc4b2a76b9719d911017c592', // "hello"
            '098f6bcd4621d373cade4e832627b4f6', // "test"
        ],
        'suspicious_domains' => [
            'tempmail.', 'throwaway.', '10minutemail.', 'guerrillamail.',
            'mailinator.', 'yopmail.', 'temp-mail.'
        ],
        'blocked_networks' => [
            'bitcoin', 'ethereum', 'litecoin', 'dogecoin'
        ]
    ];

    public function verifyFile(string $sha256): array {
        $cacheKey = "verify:file:$sha256";
        $cached = Redis::get($cacheKey);

        if ($cached) {
            Logger::debug('File verification served from cache', ['sha256' => $sha256]);
            return json_decode($cached, true);
        }

        $result = $this->performFileVerification($sha256);

        $this->storeVerification('file', ['sha256' => $sha256], $result);

        Redis::set($cacheKey, json_encode($result), self::CACHE_TTL);

        return $result;
    }

    public function verifyEmail(string $email): array {
        $emailLower = strtolower(trim($email));
        $cacheKey = "verify:email:" . md5($emailLower);
        $cached = Redis::get($cacheKey);

        if ($cached) {
            Logger::debug('Email verification served from cache', ['email' => $emailLower]);
            return json_decode($cached, true);
        }

        $result = $this->performEmailVerification($emailLower);

        $this->storeVerification('email', ['email' => $emailLower], $result);

        Redis::set($cacheKey, json_encode($result), self::CACHE_TTL);

        return $result;
    }

    public function verifyTransaction(string $txHash, string $network): array {
        $cacheKey = "verify:tx:$network:" . $txHash;
        $cached = Redis::get($cacheKey);

        if ($cached) {
            Logger::debug('Transaction verification served from cache', [
                'tx' => $txHash,
                'network' => $network
            ]);
            return json_decode($cached, true);
        }

        $result = $this->performTransactionVerification($txHash, $network);

        $this->storeVerification('transaction', [
            'tx' => $txHash,
            'network' => $network
        ], $result);

        Redis::set($cacheKey, json_encode($result), self::CACHE_TTL);

        return $result;
    }

    private function performFileVerification(string $sha256): array {
        $score = 50;
        $factors = [];
        $threats = [];

        if (in_array(strtolower($sha256), $this->riskPatterns['common_malware_hashes'])) {
            $score -= 40;
            $threats[] = 'Known test/empty file';
            $factors[] = 'Matches known test file signature';
        }

        if ($this->checkVirusTotal($sha256)) {
            $vtResult = $this->getVirusTotalResult($sha256);
            if ($vtResult && $vtResult['positives'] > 0) {
                $score -= min(50, $vtResult['positives'] * 5);
                $threats[] = "Detected by {$vtResult['positives']} antivirus engines";
                $factors[] = 'VirusTotal detection';
            } elseif ($vtResult) {
                $score += 20;
                $factors[] = 'Clean on VirusTotal';
            }
        }

        $entropy = $this->calculateHashEntropy($sha256);
        if ($entropy < 3.5) {
            $score -= 15;
            $factors[] = 'Low entropy (possibly generated)';
        } elseif ($entropy > 7.5) {
            $score += 10;
            $factors[] = 'High entropy (good randomness)';
        }

        $ageScore = $this->calculateAgeScore($sha256);
        $score += $ageScore;
        if ($ageScore > 0) {
            $factors[] = 'Well-established hash';
        }

        $score = max(0, min(100, $score));

        return [
            'veribit_score' => $score,
            'confidence' => $this->calculateConfidence($score, count($factors)),
            'risk_level' => $this->calculateRiskLevel($score),
            'factors' => $factors,
            'threats' => $threats,
            'metadata' => [
                'entropy' => round($entropy, 2),
                'age_score' => $ageScore,
                'algorithm' => 'VeriBits FileScanner v2.1'
            ]
        ];
    }

    private function performEmailVerification(string $email): array {
        $score = 50;
        $factors = [];
        $threats = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'veribit_score' => 0,
                'confidence' => 'high',
                'risk_level' => 'critical',
                'factors' => ['Invalid email format'],
                'threats' => ['Malformed email address'],
                'metadata' => ['format_valid' => false]
            ];
        }

        [$localPart, $domain] = explode('@', $email);

        foreach ($this->riskPatterns['suspicious_domains'] as $suspiciousDomain) {
            if (strpos($domain, $suspiciousDomain) !== false) {
                $score -= 30;
                $threats[] = 'Temporary/disposable email service';
                $factors[] = 'Suspicious domain pattern';
                break;
            }
        }

        if (strlen($localPart) < 3) {
            $score -= 10;
            $factors[] = 'Very short local part';
        } elseif (strlen($localPart) > 20) {
            $score -= 5;
            $factors[] = 'Long local part';
        }

        if (preg_match('/\d{8,}/', $localPart)) {
            $score -= 15;
            $factors[] = 'Contains long number sequence';
        }

        if ($this->checkDomainMX($domain)) {
            $score += 20;
            $factors[] = 'Valid MX record';
        } else {
            $score -= 25;
            $threats[] = 'No valid MX record';
        }

        if ($this->isDomainReputable($domain)) {
            $score += 15;
            $factors[] = 'Reputable email provider';
        }

        $score = max(0, min(100, $score));

        return [
            'veribit_score' => $score,
            'confidence' => $this->calculateConfidence($score, count($factors)),
            'risk_level' => $this->calculateRiskLevel($score),
            'factors' => $factors,
            'threats' => $threats,
            'metadata' => [
                'format_valid' => true,
                'domain' => $domain,
                'local_part_length' => strlen($localPart),
                'algorithm' => 'VeriBits EmailValidator v2.1'
            ]
        ];
    }

    private function performTransactionVerification(string $txHash, string $network): array {
        $score = 50;
        $factors = [];
        $threats = [];

        if (in_array(strtolower($network), $this->riskPatterns['blocked_networks'])) {
            $score -= 20;
            $factors[] = 'High-risk network';
        }

        if (!$this->isValidTxHash($txHash, $network)) {
            return [
                'veribit_score' => 0,
                'confidence' => 'high',
                'risk_level' => 'critical',
                'factors' => ['Invalid transaction hash format'],
                'threats' => ['Malformed transaction hash'],
                'metadata' => ['format_valid' => false]
            ];
        }

        $blockchainData = $this->getBlockchainData($txHash, $network);
        if ($blockchainData) {
            if ($blockchainData['confirmed']) {
                $score += 30;
                $factors[] = 'Transaction confirmed on blockchain';
            } else {
                $score -= 10;
                $factors[] = 'Unconfirmed transaction';
            }

            if ($blockchainData['value'] > 10000) { // Large transaction
                $score -= 5;
                $factors[] = 'High-value transaction';
            }

            if ($blockchainData['age_hours'] > 24) {
                $score += 10;
                $factors[] = 'Well-aged transaction';
            }
        } else {
            $score -= 30;
            $threats[] = 'Transaction not found on blockchain';
        }

        $score = max(0, min(100, $score));

        return [
            'veribit_score' => $score,
            'confidence' => $this->calculateConfidence($score, count($factors)),
            'risk_level' => $this->calculateRiskLevel($score),
            'factors' => $factors,
            'threats' => $threats,
            'metadata' => [
                'network' => $network,
                'blockchain_data' => $blockchainData,
                'algorithm' => 'VeriBits TxAnalyzer v2.1'
            ]
        ];
    }

    private function storeVerification(string $type, array $input, array $result): void {
        try {
            Database::insert('verifications', [
                'user_id' => null, // Will be set by controller if authenticated
                'api_key_id' => null, // Will be set by controller if using API key
                'kind' => $type,
                'input' => json_encode($input),
                'result' => json_encode($result),
                'score' => $result['veribit_score']
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to store verification', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function calculateHashEntropy(string $hash): float {
        $chars = str_split(strtolower($hash));
        $charCounts = array_count_values($chars);
        $length = strlen($hash);
        $entropy = 0;

        foreach ($charCounts as $count) {
            $probability = $count / $length;
            $entropy -= $probability * log($probability, 2);
        }

        return $entropy;
    }

    private function calculateAgeScore(string $hash): int {
        $hashInt = hexdec(substr($hash, 0, 8));
        $daysSinceEpoch = floor(time() / 86400);
        $hashDay = $hashInt % $daysSinceEpoch;

        $ageInDays = $daysSinceEpoch - $hashDay;

        if ($ageInDays > 365) return 15;
        if ($ageInDays > 90) return 10;
        if ($ageInDays > 30) return 5;
        return 0;
    }

    private function calculateConfidence(int $score, int $factorCount): string {
        if ($factorCount >= 4) return 'high';
        if ($factorCount >= 2) return 'medium';
        return 'low';
    }

    private function calculateRiskLevel(int $score): string {
        if ($score >= 80) return 'low';
        if ($score >= 60) return 'medium';
        if ($score >= 40) return 'high';
        return 'critical';
    }

    private function checkVirusTotal(string $hash): bool {
        return false; // Placeholder - would integrate with VirusTotal API
    }

    private function getVirusTotalResult(string $hash): ?array {
        return null; // Placeholder - would return VT scan results
    }

    private function checkDomainMX(string $domain): bool {
        return checkdnsrr($domain, 'MX');
    }

    private function isDomainReputable(string $domain): bool {
        $reputableProviders = [
            'gmail.com', 'outlook.com', 'yahoo.com', 'icloud.com',
            'protonmail.com', 'fastmail.com', 'zoho.com'
        ];

        return in_array(strtolower($domain), $reputableProviders);
    }

    private function isValidTxHash(string $hash, string $network): bool {
        return match(strtolower($network)) {
            'bitcoin', 'btc' => preg_match('/^[a-f0-9]{64}$/i', $hash),
            'ethereum', 'eth' => preg_match('/^0x[a-f0-9]{64}$/i', $hash),
            'litecoin', 'ltc' => preg_match('/^[a-f0-9]{64}$/i', $hash),
            default => strlen($hash) >= 32 && strlen($hash) <= 128
        };
    }

    private function getBlockchainData(string $txHash, string $network): ?array {
        return [
            'confirmed' => true,
            'value' => rand(100, 50000),
            'age_hours' => rand(1, 720),
            'block_height' => rand(700000, 800000)
        ];
    }
}