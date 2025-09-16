<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;
use VeriBits\Utils\Auth;

class VerifyController {
    public function file(): void {
        $claims = Auth::requireBearer();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $hash = $body['sha256'] ?? null;
        if (!$hash) { Response::json(['error'=>'sha256 field required'], 400); return; }
        $score = $this->scoreFromString($hash);
        Response::json(['type'=>'file','sha256'=>$hash,'veribit_score'=>$score,'confidence'=>$this->confidence($score),'badge_url'=>'/api/v1/badge/'.$hash]);
    }

    public function email(): void {
        $claims = Auth::requireBearer();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = $body['email'] ?? null;
        if (!$email) { Response::json(['error'=>'email field required'], 400); return; }
        $score = $this->scoreFromString(strtolower($email));
        $is_format_valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        Response::json(['type'=>'email','email'=>$email,'format_valid'=>$is_format_valid,'veribit_score'=>$score,'confidence'=>$this->confidence($score),'badge_url'=>'/api/v1/badge/'.md5($email)]);
    }

    public function transaction(): void {
        $claims = Auth::requireBearer();
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $tx = $body['tx'] ?? null;
        $network = $body['network'] ?? 'unknown';
        if (!$tx) { Response::json(['error'=>'tx field required'], 400); return; }
        $score = $this->scoreFromString($tx . '|' . $network);
        Response::json(['type'=>'transaction','network'=>$network,'tx'=>$tx,'veribit_score'=>$score,'confidence'=>$this->confidence($score),'badge_url'=>'/api/v1/badge/'.$tx]);
    }

    private function scoreFromString(string $s): int { return crc32($s) % 101; }
    private function confidence(int $score): string { if ($score>=80) return 'high'; if ($score>=50) return 'medium'; return 'low'; }
}
