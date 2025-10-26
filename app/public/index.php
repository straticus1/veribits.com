<?php
// © After Dark Systems
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Autoload all classes
spl_autoload_register(function ($class) {
    $prefix = 'VeriBits\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

use VeriBits\Utils\Response;
use VeriBits\Utils\Logger;
use VeriBits\Utils\Config;
use VeriBits\Controllers\HealthController;
use VeriBits\Controllers\VerifyController;
use VeriBits\Controllers\BadgeController;
use VeriBits\Controllers\AuthController;
use VeriBits\Controllers\WebhookController;
use VeriBits\Controllers\BillingController;
use VeriBits\Controllers\MalwareScanController;
use VeriBits\Controllers\ArchiveInspectionController;
use VeriBits\Controllers\DNSCheckController;
use VeriBits\Controllers\SSLCheckController;
use VeriBits\Controllers\IDVerificationController;
use VeriBits\Controllers\FileMagicController;
use VeriBits\Controllers\FileSignatureController;
use VeriBits\Controllers\AnonymousLimitsController;
use VeriBits\Controllers\CryptoValidationController;
use VeriBits\Controllers\SSLGeneratorController;
use VeriBits\Controllers\SSLChainResolverController;
use VeriBits\Controllers\JWTController;
use VeriBits\Controllers\DeveloperToolsController;
use VeriBits\Controllers\CodeSigningController;
use VeriBits\Controllers\ApiKeyController;
use VeriBits\Controllers\VerificationsController;
use VeriBits\Controllers\NetworkToolsController;
use VeriBits\Controllers\AdminController;
use VeriBits\Controllers\SteganographyController;
use VeriBits\Controllers\BGPController;
use VeriBits\Controllers\ToolSearchController;
use VeriBits\Controllers\CloudStorageController;
use VeriBits\Controllers\HaveIBeenPwnedController;
use VeriBits\Controllers\EmailVerificationController;

// Initialize configuration
Config::load();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([]);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Only handle API requests - let Apache serve static files
if (strpos($uri, '/api/') !== 0) {
    http_response_code(404);
    exit;
}

try {
    // Health check (no auth required)
    if ($uri === '/api/v1/health' && $method === 'GET') {
        (new HealthController())->status();
        exit;
    }

    // Anonymous limits info (no auth required)
    if ($uri === '/api/v1/limits/anonymous' && $method === 'GET') {
        (new AnonymousLimitsController())->getLimits();
        exit;
    }

    // Authentication endpoints
    if ($uri === '/api/v1/auth/register' && $method === 'POST') {
        (new AuthController())->register();
        exit;
    }
    if ($uri === '/api/v1/auth/login' && $method === 'POST') {
        (new AuthController())->login();
        exit;
    }
    if ($uri === '/api/v1/auth/logout' && $method === 'POST') {
        (new AuthController())->logout();
        exit;
    }
    if ($uri === '/api/v1/auth/token' && $method === 'POST') {
        (new AuthController())->token();
        exit;
    }
    if ($uri === '/api/v1/auth/refresh' && $method === 'POST') {
        (new AuthController())->refresh();
        exit;
    }

    // Admin endpoints
    if ($uri === '/api/v1/admin/migrate' && $method === 'POST') {
        (new AdminController())->runMigrations();
        exit;
    }
    if ($uri === '/api/v1/auth/profile' && $method === 'GET') {
        (new AuthController())->profile();
        exit;
    }

    // Verification endpoints (protected)
    if ($uri === '/api/v1/verify/file' && $method === 'POST') {
        (new VerifyController())->file();
        exit;
    }
    if ($uri === '/api/v1/verify/email' && $method === 'POST') {
        (new VerifyController())->email();
        exit;
    }
    if ($uri === '/api/v1/verify/tx' && $method === 'POST') {
        (new VerifyController())->transaction();
        exit;
    }

    // Malware scan endpoint (protected)
    if ($uri === '/api/v1/verify/malware' && $method === 'POST') {
        (new MalwareScanController())->scan();
        exit;
    }

    // Archive inspection endpoint (protected)
    if ($uri === '/api/v1/inspect/archive' && $method === 'POST') {
        (new ArchiveInspectionController())->inspect();
        exit;
    }

    // DNS check endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/dns/check' && $method === 'POST') {
        (new DNSCheckController())->check();
        exit;
    }
    if ($uri === '/api/v1/verify/dns' && $method === 'POST') {
        (new DNSCheckController())->check();
        exit;
    }

    // SSL check endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/validate' && $method === 'POST') {
        (new SSLCheckController())->validate();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/website' && $method === 'POST') {
        (new SSLCheckController())->checkWebsite();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/certificate' && $method === 'POST') {
        (new SSLCheckController())->checkCertificate();
        exit;
    }
    if ($uri === '/api/v1/verify/ssl/key-match' && $method === 'POST') {
        (new SSLCheckController())->verifyKeyMatch();
        exit;
    }

    // ID verification endpoint (protected)
    if ($uri === '/api/v1/verify/id' && $method === 'POST') {
        (new IDVerificationController())->verify();
        exit;
    }

    // File magic number analysis endpoint (protected)
    if ($uri === '/api/v1/file-magic' && $method === 'POST') {
        (new FileMagicController())->analyze();
        exit;
    }

    // File signature verification endpoint (protected)
    if ($uri === '/api/v1/verify/file-signature' && $method === 'POST') {
        (new FileSignatureController())->verify();
        exit;
    }

    // Badge endpoints
    if (preg_match('#^/api/v1/badge/(.+)$#', $uri, $m) && $method === 'GET') {
        (new BadgeController())->get($m[1]);
        exit;
    }
    if ($uri === '/api/v1/lookup' && $method === 'GET') {
        (new BadgeController())->lookup();
        exit;
    }

    // Webhook endpoints (protected)
    if ($uri === '/api/v1/webhooks' && $method === 'POST') {
        (new WebhookController())->register();
        exit;
    }
    if ($uri === '/api/v1/webhooks' && $method === 'GET') {
        (new WebhookController())->list();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)$#', $uri, $m) && $method === 'PUT') {
        $_GET['id'] = $m[1];
        (new WebhookController())->update();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)$#', $uri, $m) && $method === 'DELETE') {
        $_GET['id'] = $m[1];
        (new WebhookController())->delete();
        exit;
    }
    if (preg_match('#^/api/v1/webhooks/(.+)/test$#', $uri, $m) && $method === 'POST') {
        $_GET['id'] = $m[1];
        (new WebhookController())->test();
        exit;
    }

    // Billing endpoints (protected)
    if ($uri === '/api/v1/billing/account' && $method === 'GET') {
        (new BillingController())->getAccount();
        exit;
    }
    if ($uri === '/api/v1/billing/plans' && $method === 'GET') {
        (new BillingController())->getPlans();
        exit;
    }
    if ($uri === '/api/v1/billing/upgrade' && $method === 'POST') {
        (new BillingController())->upgradePlan();
        exit;
    }
    if ($uri === '/api/v1/billing/cancel' && $method === 'POST') {
        (new BillingController())->cancelSubscription();
        exit;
    }
    if ($uri === '/api/v1/billing/usage' && $method === 'GET') {
        (new BillingController())->getUsage();
        exit;
    }
    if ($uri === '/api/v1/billing/invoices' && $method === 'GET') {
        (new BillingController())->getInvoices();
        exit;
    }
    if ($uri === '/api/v1/billing/payment' && $method === 'POST') {
        (new BillingController())->processPayment();
        exit;
    }
    if ($uri === '/api/v1/billing/recommendation' && $method === 'GET') {
        (new BillingController())->getPlanRecommendation();
        exit;
    }
    if ($uri === '/api/v1/billing/webhook/stripe' && $method === 'POST') {
        (new BillingController())->webhookStripe();
        exit;
    }

    // Cryptocurrency validation endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/crypto/validate' && $method === 'POST') {
        (new CryptoValidationController())->validate();
        exit;
    }
    if ($uri === '/api/v1/crypto/validate/bitcoin' && $method === 'POST') {
        (new CryptoValidationController())->validateBitcoin();
        exit;
    }
    if ($uri === '/api/v1/crypto/validate/ethereum' && $method === 'POST') {
        (new CryptoValidationController())->validateEthereum();
        exit;
    }

    // SSL/TLS tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/generate-csr' && $method === 'POST') {
        (new SSLGeneratorController())->generate();
        exit;
    }
    if ($uri === '/api/v1/ssl/validate-csr' && $method === 'POST') {
        (new SSLGeneratorController())->validateCSR();
        exit;
    }

    // SSL Chain Resolver (supports anonymous with rate limiting)
    if ($uri === '/api/v1/ssl/resolve-chain' && $method === 'POST') {
        (new SSLChainResolverController())->resolveChain();
        exit;
    }
    if ($uri === '/api/v1/ssl/fetch-missing' && $method === 'POST') {
        (new SSLChainResolverController())->fetchMissing();
        exit;
    }
    if ($uri === '/api/v1/ssl/build-bundle' && $method === 'POST') {
        (new SSLChainResolverController())->buildBundle();
        exit;
    }
    if ($uri === '/api/v1/ssl/verify-key-pair' && $method === 'POST') {
        (new SSLChainResolverController())->verifyKeyPair();
        exit;
    }

    // Email Verification Tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/email/check-disposable' && $method === 'POST') {
        (new EmailVerificationController())->checkDisposable();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-spf' && $method === 'POST') {
        (new EmailVerificationController())->analyzeSPF();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-dkim' && $method === 'POST') {
        (new EmailVerificationController())->analyzeDKIM();
        exit;
    }
    if ($uri === '/api/v1/email/verify-dkim-signature' && $method === 'POST') {
        (new EmailVerificationController())->verifyDKIMSignature();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-dmarc' && $method === 'POST') {
        (new EmailVerificationController())->analyzeDMARC();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-mx' && $method === 'POST') {
        (new EmailVerificationController())->analyzeMX();
        exit;
    }
    if ($uri === '/api/v1/email/analyze-headers' && $method === 'POST') {
        (new EmailVerificationController())->analyzeHeaders();
        exit;
    }
    if ($uri === '/api/v1/email/check-blacklists' && $method === 'POST') {
        (new EmailVerificationController())->checkBlacklists();
        exit;
    }
    if ($uri === '/api/v1/email/deliverability-score' && $method === 'POST') {
        (new EmailVerificationController())->deliverabilityScore();
        exit;
    }

    // JWT tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/jwt/validate' && $method === 'POST') {
        (new JWTController())->validate();
        exit;
    }
    if ($uri === '/api/v1/jwt/decode' && $method === 'POST') {
        (new JWTController())->decode();
        exit;
    }
    if ($uri === '/api/v1/jwt/sign' && $method === 'POST') {
        (new JWTController())->sign();
        exit;
    }

    // Developer tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/regex-test' && $method === 'POST') {
        (new DeveloperToolsController())->regexTest();
        exit;
    }
    if ($uri === '/api/v1/tools/validate-data' && $method === 'POST') {
        (new DeveloperToolsController())->validateData();
        exit;
    }
    if ($uri === '/api/v1/tools/scan-secrets' && $method === 'POST') {
        (new DeveloperToolsController())->scanSecrets();
        exit;
    }
    if ($uri === '/api/v1/tools/generate-hash' && $method === 'POST') {
        (new DeveloperToolsController())->generateHash();
        exit;
    }
    if ($uri === '/api/v1/tools/url-encode' && $method === 'POST') {
        (new DeveloperToolsController())->urlEncode();
        exit;
    }

    // Code signing endpoints
    if ($uri === '/api/v1/code-signing/sign' && $method === 'POST') {
        (new CodeSigningController())->sign();
        exit;
    }
    if ($uri === '/api/v1/code-signing/quota' && $method === 'GET') {
        (new CodeSigningController())->getQuota();
        exit;
    }

    // API Keys management (protected)
    if ($uri === '/api/v1/api-keys' && $method === 'GET') {
        (new ApiKeyController())->list();
        exit;
    }
    if ($uri === '/api/v1/api-keys' && $method === 'POST') {
        (new ApiKeyController())->create();
        exit;
    }
    if (preg_match('#^/api/v1/api-keys/(.+)$#', $uri, $m) && $method === 'DELETE') {
        (new ApiKeyController())->revoke($m[1]);
        exit;
    }

    // User profile (protected)
    if ($uri === '/api/v1/user/profile' && $method === 'GET') {
        (new AuthController())->profile();
        exit;
    }

    // Verifications history (protected)
    if ($uri === '/api/v1/verifications' && $method === 'GET') {
        (new VerificationsController())->list();
        exit;
    }

    // Network tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/dns-validate' && $method === 'POST') {
        (new NetworkToolsController())->dnsValidate();
        exit;
    }
    if ($uri === '/api/v1/tools/ip-calculate' && $method === 'POST') {
        (new NetworkToolsController())->ipCalculate();
        exit;
    }
    if ($uri === '/api/v1/tools/rbl-check' && $method === 'POST') {
        (new NetworkToolsController())->rblCheck();
        exit;
    }
    if ($uri === '/api/v1/tools/smtp-relay-check' && $method === 'POST') {
        (new NetworkToolsController())->smtpRelayCheck();
        exit;
    }
    if ($uri === '/api/v1/tools/whois' && $method === 'POST') {
        (new NetworkToolsController())->whoisLookup();
        exit;
    }
    if ($uri === '/api/v1/tools/traceroute' && $method === 'POST') {
        (new NetworkToolsController())->traceroute();
        exit;
    }
    if ($uri === '/api/v1/zone-validate' && $method === 'POST') {
        (new NetworkToolsController())->zoneValidate();
        exit;
    }
    if ($uri === '/api/v1/tools/cert-convert' && $method === 'POST') {
        (new NetworkToolsController())->certConvert();
        exit;
    }

    // Steganography detection (supports anonymous with rate limiting)
    if ($uri === '/api/v1/steganography-detect' && $method === 'POST') {
        (new SteganographyController())->detect();
        exit;
    }

    // BGP Intelligence tools (supports anonymous with rate limiting)
    if ($uri === '/api/v1/bgp/prefix' && $method === 'POST') {
        (new BGPController())->prefixLookup();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn' && $method === 'POST') {
        (new BGPController())->asLookup();
        exit;
    }

    // Tool Search endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/search' && $method === 'GET') {
        (new ToolSearchController())->search();
        exit;
    }
    if ($uri === '/api/v1/tools/list' && $method === 'GET') {
        (new ToolSearchController())->list();
        exit;
    }

    // Cloud Storage Security Auditor endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/tools/cloud-storage/search' && $method === 'POST') {
        (new CloudStorageController())->search();
        exit;
    }
    if ($uri === '/api/v1/tools/cloud-storage/list-buckets' && $method === 'POST') {
        (new CloudStorageController())->listBuckets();
        exit;
    }
    if ($uri === '/api/v1/tools/cloud-storage/analyze-security' && $method === 'POST') {
        (new CloudStorageController())->analyzeSecurityPosture();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/prefixes' && $method === 'POST') {
        (new BGPController())->asPrefixes();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/peers' && $method === 'POST') {
        (new BGPController())->asPeers();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/upstreams' && $method === 'POST') {
        (new BGPController())->asUpstreams();
        exit;
    }
    if ($uri === '/api/v1/bgp/asn/downstreams' && $method === 'POST') {
        (new BGPController())->asDownstreams();
        exit;
    }
    if ($uri === '/api/v1/bgp/search' && $method === 'POST') {
        (new BGPController())->searchAS();
        exit;
    }

    // Have I Been Pwned endpoints (supports anonymous with rate limiting)
    if ($uri === '/api/v1/hibp/check-email' && $method === 'POST') {
        (new HaveIBeenPwnedController())->checkEmail();
        exit;
    }
    if ($uri === '/api/v1/hibp/check-password' && $method === 'POST') {
        (new HaveIBeenPwnedController())->checkPassword();
        exit;
    }
    if ($uri === '/api/v1/hibp/stats' && $method === 'GET') {
        (new HaveIBeenPwnedController())->getStats();
        exit;
    }

    // API documentation
    if ($uri === '/api/v1/docs' && $method === 'GET') {
        header('Content-Type: text/html');
        include __DIR__ . '/../../docs/api-docs.html';
        exit;
    }

    // 404 for unknown routes
    Response::error('Endpoint not found', 404, ['path' => $uri, 'method' => $method]);

} catch (\Throwable $e) {
    Logger::critical('Unhandled exception in API', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'uri' => $uri,
        'method' => $method
    ]);

    if (Config::isDevelopment()) {
        Response::error('Internal server error: ' . $e->getMessage(), 500, [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } else {
        Response::error('Internal server error', 500);
    }
}
