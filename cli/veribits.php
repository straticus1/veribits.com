#!/usr/bin/env php
<?php
declare(strict_types=1);
/**
 * VeriBits CLI
 * © After Dark Systems
 */
function parseArgs(array $argv): array {
    array_shift($argv);
    $cmd = $argv[0] ?? 'help';
    $opts = [];
    foreach ($argv as $a) {
        if (strpos($a, '--') === 0 && strpos($a, '=') !== false) {
            [$k, $v] = explode('=', substr($a, 2), 2);
            $opts[$k] = $v;
        }
    }
    return [$cmd, $opts];
}
function printJson($data) { echo json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL; }

[$cmd, $opts] = parseArgs($argv);

switch ($cmd) {
    case 'verify:file':
        $hash = $opts['sha256'] ?? null;
        if (!$hash) { fwrite(STDERR, "Missing --sha256\n"); exit(1); }
        $score = crc32($hash) % 101;
        printJson(['type'=>'file','sha256'=>$hash,'veribit_score'=>$score]);
        break;
    case 'verify:email':
        $email = $opts['email'] ?? null;
        if (!$email) { fwrite(STDERR, "Missing --email\n"); exit(1); }
        $score = crc32(strtolower($email)) % 101;
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        printJson(['type'=>'email','email'=>$email,'format_valid'=>$valid,'veribit_score'=>$score]);
        break;
    case 'verify:tx':
        $tx = $opts['tx'] ?? null;
        $network = $opts['network'] ?? 'unknown';
        if (!$tx) { fwrite(STDERR, "Missing --tx\n"); exit(1); }
        $score = crc32($tx.'|'.$network) % 101;
        printJson(['type'=>'transaction','network'=>$network,'tx'=>$tx,'veribit_score'=>$score]);
        break;
    case 'health':
        printJson(['status'=>'ok','time'=>gmdate('c')]);
        break;
    default:
        fwrite(STDERR, "VeriBits CLI\n".
            "  verify:file --sha256=<hash>\n".
            "  verify:email --email=<email>\n".
            "  verify:tx --network=<btc|eth|usdc|...> --tx=<hash>\n".
            "  health\n");
        exit(1);
}
