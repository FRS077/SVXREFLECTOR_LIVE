<?php
/**
 * fichier api_tg.php
 * TG activés + indicatifs présents / ayant parlé sur chaque TG (page talkgroupe.php)
 * Sources : /status du reflector (état actuel) + fin du log (TG activés, transmissions)
 * Auteur : FRS077 Romuald pour RNFA
 */
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise'], JSON_UNESCAPED_UNICODE);
    exit;
}
session_write_close();
date_default_timezone_set(LOG_TIMEZONE);

require_once __DIR__ . '/defaults_tg.php';
$localTg  = defined('TG_LOCAL') ? (int)TG_LOCAL : 59;
$readB    = defined('TG_READ_BYTES') ? (int)TG_READ_BYTES : (defined('LOG_READ_BYTES') ? (int)LOG_READ_BYTES : 3000000);
$names    = (isset($TG_NAMES) && is_array($TG_NAMES)) ? $TG_NAMES : [];

function tgFetchStatus(): ?array {
    $out = null;
    if (function_exists('curl_init')) {
        $ch = curl_init(STATUS_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => STATUS_TIMEOUT, CURLOPT_TIMEOUT => STATUS_TIMEOUT]);
        $r = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($r !== false && $code >= 200 && $code < 300) $out = $r;
    } else {
        $r = @file_get_contents(STATUS_URL, false, stream_context_create(['http' => ['timeout' => STATUS_TIMEOUT]]));
        if ($r !== false) $out = $r;
    }
    $d = $out ? json_decode($out, true) : null;
    return (is_array($d) && isset($d['nodes']) && is_array($d['nodes'])) ? $d['nodes'] : null;
}

// ---- 1) Log : activations de TG et transmissions
$tgs = [];            // tg => ['sel'=>[cs=>ts], 'talk'=>[cs=>[tx,sec,last]], 'last'=>ts]
$cur = [];            // cs => ['tg'=>n,'ts'=>ts]  (dernier TG connu d'après le log)
$logOk = is_readable(LOG_FILE);
$firstTs = 0;
if ($logOk) {
    $size = filesize(LOG_FILE);
    $fh = fopen(LOG_FILE, 'rb');
    $start = max(0, $size - $readB);
    fseek($fh, $start);
    $data = stream_get_contents($fh);
    fclose($fh);
    if ($start > 0) $data = substr($data, (int)strpos($data, "\n") + 1);
    $open = [];
    foreach (explode("\n", $data) as $line) {
        if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2}):(\d{2}):\s?(.*)$/', rtrim($line, "\r"), $m)) continue;
        $ts = mktime((int)$m[4], (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[1], (int)$m[3]);
        if (!$firstTs) $firstTs = $ts;
        $msg = $m[7];
        if (preg_match('/^(.+?): Select TG #(\d+)$/', $msg, $x)) {
            $cs = trim($x[1]); $tg = (int)$x[2];
            $cur[$cs] = ['tg' => $tg, 'ts' => $ts];
            if ($tg > 0) {
                $tgs[$tg]['sel'][$cs] = $ts;
                $tgs[$tg]['last'] = $ts;
            }
        } elseif (preg_match('/^(.+?): Talker start on TG #(\d+)/', $msg, $x)) {
            $cs = trim($x[1]); $tg = (int)$x[2];
            $open[$cs] = [$tg, $ts];
            $cur[$cs] = ['tg' => $tg, 'ts' => $ts];
            $t = &$tgs[$tg]['talk'][$cs];
            if (!$t) $t = ['tx' => 0, 'sec' => 0, 'last' => 0];
            $t['tx']++; $t['last'] = $ts;
            $tgs[$tg]['last'] = $ts;
            unset($t);
        } elseif (preg_match('/^(.+?): Talker stop on TG #(\d+)/', $msg, $x)) {
            $cs = trim($x[1]);
            if (isset($open[$cs])) {
                $tg = $open[$cs][0];
                if (isset($tgs[$tg]['talk'][$cs])) $tgs[$tg]['talk'][$cs]['sec'] += max(0, $ts - $open[$cs][1]);
                unset($open[$cs]);
            }
        } elseif (preg_match('/^(.+?): Login OK from /', $msg, $x)) {
            unset($cur[trim($x[1])]);
        } elseif (preg_match('/^(.+?): Client disconnected/', $msg, $x)) {
            unset($cur[trim($x[1])], $open[trim($x[1])]);
        } elseif (strpos($msg, 'SIGTERM received') !== false) {
            $cur = []; $open = [];
        }
    }
}

// ---- 2) /status : état actuel des stations
$status = tgFetchStatus();
$nodes = [];
if ($status !== null) {
    foreach ($status as $cs => $n) {
        if (!is_array($n)) continue;
        $mon = [];
        if (isset($n['monitoredTGs']) && is_array($n['monitoredTGs'])) foreach ($n['monitoredTGs'] as $t) $mon[] = (int)$t;
        $nodes[(string)$cs] = [
            'tg'      => (int)($n['tg'] ?? 0),
            'talker'  => !empty($n['isTalker']),
            'monitor' => $mon,
            'name'    => trim((string)($n['Sysop'] ?? $n['SysOp'] ?? '')),
            'mode'    => trim((string)($n['Mode'] ?? '')),
        ];
    }
} else {
    foreach ($cur as $cs => $c) $nodes[(string)$cs] = ['tg' => (int)$c['tg'], 'talker' => false, 'monitor' => [], 'name' => '', 'mode' => ''];
}

// ---- 3) Assemblage par TG
$out = [];
$ensure = function (int $tg) use (&$out, $names, $localTg) {
    if (!isset($out[$tg])) {
        $out[$tg] = ['tg' => $tg, 'name' => (string)($names[$tg] ?? ($tg === $localTg ? 'TG principal' : TG_NAME_DEFAULT)), 'local' => $tg === $localTg,
                     'now' => [], 'monitor' => [], 'talked' => [], 'selected' => [], 'tx' => 0, 'last' => 0, 'active' => false];
    }
};
foreach ($tgs as $tg => $info) {
    if ($tg <= 0) continue;
    $ensure($tg);
    $out[$tg]['last'] = (int)($info['last'] ?? 0);
    foreach (($info['talk'] ?? []) as $cs => $t) {
        $out[$tg]['talked'][] = ['cs' => $cs, 'tx' => $t['tx'], 'sec' => $t['sec'], 'last' => $t['last'], 'online' => isset($nodes[$cs])];
        $out[$tg]['tx'] += $t['tx'];
    }
    foreach (($info['sel'] ?? []) as $cs => $ts) {
        $out[$tg]['selected'][] = ['cs' => $cs, 'ts' => $ts, 'online' => isset($nodes[$cs])];
    }
}
foreach ($nodes as $cs => $n) {
    if ($n['tg'] > 0) {
        $ensure($n['tg']);
        $out[$n['tg']]['now'][] = ['cs' => $cs, 'talker' => $n['talker'], 'name' => $n['name'], 'mode' => $n['mode']];
        if ($n['talker']) $out[$n['tg']]['active'] = true;
    }
    foreach ($n['monitor'] as $t) {
        if ($t > 0 && $t !== $n['tg']) { $ensure($t); $out[$t]['monitor'][] = $cs; }
    }
}
$ensure($localTg);
foreach (array_keys($names) as $__t) { if ((int)$__t > 0) $ensure((int)$__t); }   // les TG nommés sont toujours listés
foreach ($out as &$o) {
    usort($o['talked'], fn($a, $b) => $b['last'] <=> $a['last']);
    usort($o['selected'], fn($a, $b) => $b['ts'] <=> $a['ts']);
    sort($o['monitor']);
}
unset($o);
$list = array_values($out);
usort($list, function ($a, $b) use ($localTg) {
    if ($a['local'] !== $b['local']) return $a['local'] ? -1 : 1;
    if (count($a['now']) !== count($b['now'])) return count($b['now']) <=> count($a['now']);
    return $b['last'] <=> $a['last'];
});

// Stations hors TG local (ou sans TG)
$away = []; $none = [];
foreach ($nodes as $cs => $n) {
    if ($n['tg'] > 0 && $n['tg'] !== $localTg) $away[] = ['cs' => $cs, 'tg' => $n['tg'], 'talker' => $n['talker']];
    elseif ($n['tg'] <= 0) $none[] = $cs;
}

echo json_encode([
    'now_ts'   => time(),
    'local_tg' => $localTg,
    'since_ts' => $firstTs,
    'status_ok'=> $status !== null,
    'log_ok'   => $logOk,
    'tgs'      => $list,
    'away'     => $away,
    'no_tg'    => $none,
    'nodes'    => count($nodes),
], JSON_UNESCAPED_UNICODE);
