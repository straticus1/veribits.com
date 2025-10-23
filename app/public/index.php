// © After Dark Systems
<?php
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

// Initialize configuration
Config::load();

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    Response::json([]);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Health check (no auth required)
    if ($uri === '/api/v1/health' && $method === 'GET') {
        (new HealthController())->status();
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

    // DNS check endpoint (protected)
    if ($uri === '/api/v1/verify/dns' && $method === 'POST') {
        (new DNSCheckController())->check();
        exit;
    }

    // SSL check endpoints (protected)
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
