// © After Dark Systems
<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Utils/Response.php';
require_once __DIR__ . '/../src/Utils/Jwt.php';
require_once __DIR__ . '/../src/Utils/Auth.php';
require_once __DIR__ . '/../src/Controllers/HealthController.php';
require_once __DIR__ . '/../src/Controllers/VerifyController.php';
require_once __DIR__ . '/../src/Controllers/BadgeController.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/WebhookController.php';

use VeriBits\Utils\Response;
use VeriBits\Controllers\HealthController;
use VeriBits\Controllers\VerifyController;
use VeriBits\Controllers\BadgeController;
use VeriBits\Controllers\AuthController;
use VeriBits\Controllers\WebhookController;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/v1/health') { (new HealthController())->status(); exit; }

// Auth
if ($uri === '/api/v1/auth/token' && $method === 'POST') { (new AuthController())->token(); exit; }

// Protected example
if ($uri === '/api/v1/me' && $method === 'GET') {
    $claims = VeriBits\Utils\Auth::requireBearer();
    Response::json(['ok'=>true,'claims'=>$claims]);
    exit;
}

// Verify
if ($uri === '/api/v1/verify/file' && $method === 'POST') { (new VerifyController())->file(); exit; }
if ($uri === '/api/v1/verify/email' && $method === 'POST') { (new VerifyController())->email(); exit; }
if ($uri === '/api/v1/verify/tx' && $method === 'POST') { (new VerifyController())->transaction(); exit; }

// Badge & lookup
if (preg_match('#^/api/v1/badge/(.+)$#', $uri, $m) && $method === 'GET') { (new BadgeController())->get($m[1]); exit; }
if ($uri === '/api/v1/lookup' && $method === 'GET') { (new BadgeController())->lookup(); exit; }

// Webhooks
if ($uri === '/api/v1/webhooks' && $method === 'POST') { (new WebhookController())->register(); exit; }
if ($uri === '/api/v1/webhooks' && $method === 'GET') { (new WebhookController())->list(); exit; }

Response::json(['error'=>'Not Found','path'=>$uri], 404);
