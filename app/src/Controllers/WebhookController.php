<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;

class WebhookController {
    public function register(): void {
        $claims = Auth::requireBearer();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $url = $body['url'] ?? null;
        if (!$url) { Response::json(['error'=>'missing url'], 400); return; }
        Response::json(['ok'=>true,'url'=>$url,'message'=>'webhook registered (stub)']);
    }
    public function list(): void {
        $claims = Auth::requireBearer();
        Response::json(['data'=>[],'message'=>'list webhooks (stub)']);
    }
}
