<?php
// © After Dark Systems
declare(strict_types=1);

namespace VeriBits\Controllers;

use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\RateLimit;

class CryptoValidationController
{
    /**
     * Generic crypto validation - auto-detects currency type
     */
    public function validate(): void
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
        $address = $input['address'] ?? $input['value'] ?? '';
        $currency = $input['currency'] ?? '';

        if (empty($address)) {
            Response::error('Address or value is required', 400);
            return;
        }

        // Auto-detect or use specified currency
        if (empty($currency)) {
            $currency = $this->detectCurrency($address);
        }

        $currency = strtoupper($currency);

        // Validate based on currency
        switch ($currency) {
            case 'BTC':
            case 'BITCOIN':
                $result = $this->validateBitcoinAddress($address);
                break;
            case 'ETH':
            case 'ETHEREUM':
                $result = $this->validateEthereumAddress($address);
                break;
            default:
                Response::error('Unsupported currency: ' . $currency, 400, [
                    'supported' => ['BTC', 'BITCOIN', 'ETH', 'ETHEREUM']
                ]);
                return;
        }

        // Increment scan count for anonymous users
        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success([
            'currency' => $currency,
            'validation' => $result
        ]);
    }

    /**
     * Auto-detect cryptocurrency type from address format
     */
    private function detectCurrency(string $address): string
    {
        // Ethereum addresses start with 0x
        if (str_starts_with($address, '0x') && strlen($address) === 42) {
            return 'ETH';
        }

        // Bech32 Bitcoin addresses
        if (preg_match('/^(bc1|tb1)[a-z0-9]{39,87}$/i', $address)) {
            return 'BTC';
        }

        // Legacy Bitcoin addresses
        if (preg_match('/^[13mn2][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address)) {
            return 'BTC';
        }

        return 'UNKNOWN';
    }

    /**
     * Validate Bitcoin address or transaction ID
     */
    public function validateBitcoin(): void
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
        $value = $input['value'] ?? '';
        $type = $input['type'] ?? 'address'; // 'address' or 'transaction'

        if (empty($value)) {
            Response::error('Value is required', 400);
            return;
        }

        if ($type === 'address') {
            $result = $this->validateBitcoinAddress($value);
        } else {
            $result = $this->validateBitcoinTransaction($value);
        }

        // Increment scan count for anonymous users
        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('Bitcoin validation completed', $result);
    }

    /**
     * Validate Ethereum address or transaction hash
     */
    public function validateEthereum(): void
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
        $value = $input['value'] ?? '';
        $type = $input['type'] ?? 'address'; // 'address' or 'transaction'

        if (empty($value)) {
            Response::error('Value is required', 400);
            return;
        }

        if ($type === 'address') {
            $result = $this->validateEthereumAddress($value);
        } else {
            $result = $this->validateEthereumTransaction($value);
        }

        // Increment scan count for anonymous users
        if (!$auth['authenticated']) {
            RateLimit::incrementAnonymousScan($auth['ip_address']);
        }

        Response::success('Ethereum validation completed', $result);
    }

    /**
     * Validate Bitcoin address (P2PKH, P2SH, Bech32)
     */
    private function validateBitcoinAddress(string $address): array
    {
        $result = [
            'value' => $address,
            'type' => 'bitcoin_address',
            'is_valid' => false,
            'format' => null,
            'network' => null,
            'details' => []
        ];

        // Bech32 addresses (bc1... for mainnet, tb1... for testnet)
        if (preg_match('/^(bc1|tb1)[a-z0-9]{39,87}$/i', $address)) {
            $result['format'] = 'Bech32 (SegWit)';
            $result['network'] = str_starts_with(strtolower($address), 'bc1') ? 'mainnet' : 'testnet';
            $result['is_valid'] = $this->validateBech32($address);
            $result['details'] = [
                'version' => 'Native SegWit',
                'case_sensitive' => 'Must be lowercase',
                'length' => strlen($address) . ' characters'
            ];
            return $result;
        }

        // Legacy and P2SH addresses (Base58)
        if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address)) {
            if ($address[0] === '1') {
                $result['format'] = 'P2PKH (Legacy)';
                $result['network'] = 'mainnet';
            } elseif ($address[0] === '3') {
                $result['format'] = 'P2SH (Script Hash)';
                $result['network'] = 'mainnet';
            }

            $result['is_valid'] = $this->validateBase58Check($address);
            $result['details'] = [
                'encoding' => 'Base58Check',
                'length' => strlen($address) . ' characters',
                'checksum' => $result['is_valid'] ? 'Valid' : 'Invalid'
            ];
            return $result;
        }

        // Testnet addresses
        if (preg_match('/^[mn2][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address)) {
            if ($address[0] === 'm' || $address[0] === 'n') {
                $result['format'] = 'P2PKH (Legacy)';
            } elseif ($address[0] === '2') {
                $result['format'] = 'P2SH (Script Hash)';
            }
            $result['network'] = 'testnet';
            $result['is_valid'] = $this->validateBase58Check($address);
            $result['details'] = [
                'encoding' => 'Base58Check',
                'length' => strlen($address) . ' characters'
            ];
            return $result;
        }

        $result['details']['error'] = 'Invalid Bitcoin address format';
        return $result;
    }

    /**
     * Validate Bitcoin transaction ID
     */
    private function validateBitcoinTransaction(string $txid): array
    {
        $result = [
            'value' => $txid,
            'type' => 'bitcoin_transaction',
            'is_valid' => false,
            'details' => []
        ];

        // Bitcoin txid is 64 character hex string
        if (preg_match('/^[a-fA-F0-9]{64}$/', $txid)) {
            $result['is_valid'] = true;
            $result['details'] = [
                'format' => 'Hexadecimal',
                'length' => '64 characters',
                'byte_order' => 'Little-endian (display format)'
            ];
        } else {
            $result['details']['error'] = 'Invalid transaction ID format (must be 64 hex characters)';
        }

        return $result;
    }

    /**
     * Validate Ethereum address with checksum (EIP-55)
     */
    private function validateEthereumAddress(string $address): array
    {
        $result = [
            'value' => $address,
            'type' => 'ethereum_address',
            'is_valid' => false,
            'checksum_valid' => false,
            'details' => []
        ];

        // Must start with 0x and be 42 characters
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $address)) {
            $result['details']['error'] = 'Invalid Ethereum address format (must be 0x followed by 40 hex characters)';
            return $result;
        }

        $result['is_valid'] = true;
        $result['details']['format'] = 'Hexadecimal';
        $result['details']['length'] = '42 characters (including 0x prefix)';

        // Validate checksum (EIP-55)
        $checksumAddress = $this->toChecksumAddress($address);
        $result['checksum_valid'] = ($address === $checksumAddress);
        $result['details']['checksum_address'] = $checksumAddress;

        if (!$result['checksum_valid']) {
            if (strtolower($address) === strtolower($checksumAddress)) {
                $result['details']['warning'] = 'Address is valid but checksum is incorrect or missing';
            } else {
                $result['details']['error'] = 'Invalid checksum';
                $result['is_valid'] = false;
            }
        }

        return $result;
    }

    /**
     * Validate Ethereum transaction hash
     */
    private function validateEthereumTransaction(string $txhash): array
    {
        $result = [
            'value' => $txhash,
            'type' => 'ethereum_transaction',
            'is_valid' => false,
            'details' => []
        ];

        // Ethereum tx hash is 0x followed by 64 hex characters
        if (preg_match('/^0x[a-fA-F0-9]{64}$/', $txhash)) {
            $result['is_valid'] = true;
            $result['details'] = [
                'format' => 'Hexadecimal',
                'length' => '66 characters (including 0x prefix)',
                'bytes' => '32 bytes'
            ];
        } else {
            $result['details']['error'] = 'Invalid transaction hash format (must be 0x followed by 64 hex characters)';
        }

        return $result;
    }

    /**
     * Validate Base58Check encoding (simplified)
     */
    private function validateBase58Check(string $address): bool
    {
        // Base58 character set
        $base58chars = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

        // Check all characters are valid Base58
        for ($i = 0; $i < strlen($address); $i++) {
            if (strpos($base58chars, $address[$i]) === false) {
                return false;
            }
        }

        // Length check
        $len = strlen($address);
        if ($len < 26 || $len > 35) {
            return false;
        }

        return true;
    }

    /**
     * Validate Bech32 encoding (simplified)
     */
    private function validateBech32(string $address): bool
    {
        // Bech32 must be lowercase
        if ($address !== strtolower($address)) {
            return false;
        }

        // Valid Bech32 character set
        $bech32chars = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

        // Split at '1' separator
        $parts = explode('1', $address);
        if (count($parts) !== 2) {
            return false;
        }

        [$hrp, $data] = $parts;

        // Validate data part characters
        for ($i = 0; $i < strlen($data); $i++) {
            if (strpos($bech32chars, $data[$i]) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert Ethereum address to EIP-55 checksum format
     */
    private function toChecksumAddress(string $address): string
    {
        $address = strtolower(str_replace('0x', '', $address));
        $hash = hash('sha3-256', $address);

        $checksum = '0x';
        for ($i = 0; $i < 40; $i++) {
            $hashChar = $hash[$i];
            $addressChar = $address[$i];

            if (ctype_digit($addressChar)) {
                $checksum .= $addressChar;
            } else {
                $checksum .= (intval($hashChar, 16) >= 8) ? strtoupper($addressChar) : $addressChar;
            }
        }

        return $checksum;
    }
}
