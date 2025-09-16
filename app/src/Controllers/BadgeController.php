<?php
namespace VeriBits\Controllers;
use VeriBits\Utils\Response;

class BadgeController {
    public function get(string $id): void {
        $score = $this->scoreFromString($id);
        Response::json(['id'=>$id,'veribit_score'=>$score,'confidence'=>$this->confidence($score),'verified_at'=>gmdate('c')]);
    }
    public function lookup(): void {
        $q = $_GET['q'] ?? '';
        $score = $this->scoreFromString($q);
        Response::json(['query'=>$q,'veribit_score'=>$score,'confidence'=>$this->confidence($score)]);
    }
    private function scoreFromString(string $s): int { return crc32($s) % 101; }
    private function confidence(int $score): string { if ($score>=80) return 'high'; if ($score>=50) return 'medium'; return 'low'; }
}
