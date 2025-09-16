<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;
use VeriBits\Utils\Validator;
use VeriBits\Utils\RateLimit;
use VeriBits\Utils\Logger;
use VeriBits\Services\VerificationEngine;

class VerifyController {
    private VerificationEngine $engine;

    public function __construct() {
        $this->engine = new VerificationEngine();
    }

    public function file(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('sha256')->sha256('sha256');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $sha256 = $validator->sanitize('sha256');

        try {
            $result = $this->engine->verifyFile($sha256);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('File verification completed', [
                'user_id' => $userId,
                'sha256' => $sha256,
                'score' => $result['veribit_score']
            ]);

            Response::success([
                'type' => 'file',
                'sha256' => $sha256,
                'veribit_score' => $result['veribit_score'],
                'confidence' => $result['confidence'],
                'risk_level' => $result['risk_level'],
                'factors' => $result['factors'],
                'threats' => $result['threats'],
                'metadata' => $result['metadata'],
                'badge_url' => "/api/v1/badge/$sha256",
                'verified_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('File verification failed', [
                'user_id' => $userId,
                'sha256' => $sha256,
                'error' => $e->getMessage()
            ]);
            Response::error('Verification failed', 500);
        }
    }

    public function email(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('email')->email('email')->string('email', 5, 320);

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $email = $validator->sanitize('email');

        try {
            $result = $this->engine->verifyEmail($email);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('Email verification completed', [
                'user_id' => $userId,
                'email' => $email,
                'score' => $result['veribit_score']
            ]);

            Response::success([
                'type' => 'email',
                'email' => $email,
                'veribit_score' => $result['veribit_score'],
                'confidence' => $result['confidence'],
                'risk_level' => $result['risk_level'],
                'factors' => $result['factors'],
                'threats' => $result['threats'],
                'metadata' => $result['metadata'],
                'badge_url' => '/api/v1/badge/' . md5($email),
                'verified_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('Email verification failed', [
                'user_id' => $userId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            Response::error('Verification failed', 500);
        }
    }

    public function transaction(): void {
        $claims = Auth::requireBearer();
        $userId = $claims['sub'] ?? null;

        if (!RateLimit::checkUserQuota($userId, 'monthly')) {
            Response::error('Monthly quota exceeded', 429);
            return;
        }

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($body);

        $validator->required('tx')->string('tx', 32, 128)->alphanumeric('tx')
                  ->required('network')->string('network', 3, 20)->alphanumeric('network');

        if (!$validator->isValid()) {
            Response::validationError($validator->getErrors());
            return;
        }

        $tx = $validator->sanitize('tx');
        $network = $validator->sanitize('network');

        try {
            $result = $this->engine->verifyTransaction($tx, $network);

            RateLimit::incrementUserQuota($userId, 'monthly');

            Logger::info('Transaction verification completed', [
                'user_id' => $userId,
                'tx' => $tx,
                'network' => $network,
                'score' => $result['veribit_score']
            ]);

            Response::success([
                'type' => 'transaction',
                'tx' => $tx,
                'network' => $network,
                'veribit_score' => $result['veribit_score'],
                'confidence' => $result['confidence'],
                'risk_level' => $result['risk_level'],
                'factors' => $result['factors'],
                'threats' => $result['threats'],
                'metadata' => $result['metadata'],
                'badge_url' => "/api/v1/badge/$tx",
                'verified_at' => date('c')
            ]);

        } catch (\Exception $e) {
            Logger::error('Transaction verification failed', [
                'user_id' => $userId,
                'tx' => $tx,
                'network' => $network,
                'error' => $e->getMessage()
            ]);
            Response::error('Verification failed', 500);
        }
    }
}
