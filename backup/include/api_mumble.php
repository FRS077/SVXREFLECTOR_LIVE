<?php
/**
 * fichier api_mumble.php
 * Analyse du log Mumble (connexions / déconnexions) – état persistant
 * Auteur : FRS077 Romuald pour RNFA
 */
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
session_write_close();

define('STATE_SCHEMA_M', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}

function defState(): array {
    return ['schema' => STATE_SCHEMA_M, 'offset' => -1, 'inode' => 0, 'online' => [], 'history' => [], 'rejected' => 0];
}
function loadSt(): array {
    $j = @file_get_contents(MUMBLE_STATE);
    $s = $j ? json_decode($j, true) : null;
    return (is_array($s) && ($s['schema'] ?? 0) === STATE_SCHEMA_M) ? $s : defState();
}

function applyLine(array &$st, string $line): void {
    // 2026-11-21T02:00:39.310775+01:00 host mumble-server[769]: <W>2026-11-21 02:00:39.310 1 => <8:RNFA-GW(3)> Authenticated
    if (!preg_match('/^(\d{4}-\d\d-\d\dT[\d:.]+[+-]\d\d:\d\d)\s+\S+\s+mumble-server\[\d+\]:\s+<\w>\S+\s+\S+\s+(?:\d+\s+=>\s+)?(.*)$/u', $line, $m)) {
        // lignes sans préfixe « N => » (arrêt / démarrage du serveur)
        if (preg_match('/^(\d{4}-\d\d-\d\dT[\d:.]+[+-]\d\d:\d\d)\s+.*(Killing running servers|Shutting down|Server listening)/u', $line, $r)) {
            $ts = strtotime($r[1]);
            foreach ($st['online'] as $sid => $u) {
                endSession($st, (string)$sid, $ts, 'Arrêt du serveur');
            }
        }
        return;
    }
    $ts  = strtotime($m[1]);
    $msg = trim($m[2]);

    if (preg_match('/^Server listening|^Ending voice thread$/u', $msg)) {
        return;
    }
    if (preg_match('/^<(\d+):(.+?)\((-?\d+)\)>\s+(.*)$/u', $msg, $x)) {
        $sid = $x[1]; $name = $x[2]; $uid = (int)$x[3]; $what = $x[4];
        if ($what === 'Authenticated') {
            $st['online'][$sid] = ['name' => $name, 'since_ts' => $ts, 'registered' => $uid >= 0, 'channel' => 'Root', 'gw' => ($name === GATEWAY_NAME)];
        } elseif (preg_match('/^Connection closed|^Timeout|^Disconnected/u', $what)) {
            if (isset($st['online'][$sid])) {
                $reason = ($what === 'Timeout') ? 'Timeout' : 'Déconnecté';
                endSession($st, $sid, $ts, $reason);
            }
        } elseif (preg_match('/^Rejected connection/u', $what)) {
            $st['rejected'] = (int)$st['rejected'] + 1;
        } elseif (preg_match('/^Moved (.+?):(\d+)\(-?\d+\) to (.+?)\[\d+:-?\d+\]/u', $what, $mv)) {
            $tsid = $mv[2];
            if (isset($st['online'][$tsid])) {
                $st['online'][$tsid]['channel'] = $mv[3];
            }
        }
    }
}

function endSession(array &$st, string $sid, int $ts, string $reason): void {
    $u = $st['online'][$sid] ?? null;
    if (!$u) { return; }
    unset($st['online'][$sid]);
    array_unshift($st['history'], [
        'name' => $u['name'], 'gw' => $u['gw'], 'since_ts' => $u['since_ts'], 'end_ts' => $ts,
        'duration' => max(0, $ts - $u['since_ts']), 'reason' => $reason,
    ]);
    if (count($st['history']) > MAX_HISTORY) {
        $st['history'] = array_slice($st['history'], 0, MAX_HISTORY);
    }
}

if (!is_readable(MUMBLE_LOG)) {
    echo json_encode(['error' => 'Fichier ' . MUMBLE_LOG . ' introuvable ou non lisible par Apache (ajouter www-data au groupe adm).', 'now_ts' => time(), 'online' => [], 'history' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$lock = @fopen(MUMBLE_STATE . '.lock', 'c');
if ($lock) { flock($lock, LOCK_EX); }

clearstatcache(true, MUMBLE_LOG);
$size  = (int)filesize(MUMBLE_LOG);
$inode = (int)@fileinode(MUMBLE_LOG);
$st = loadSt();
$off = (int)$st['offset'];

if ($off < 0) {
    $st = defState();
    $off = max(0, $size - INITIAL_MAX_BYTES);
} elseif ($size < $off || ($st['inode'] && $inode && $inode !== (int)$st['inode'])) {
    $st['online'] = [];   // log tourné : on repart du début
    $off = 0;
}

$fp = @fopen(MUMBLE_LOG, 'r');
if ($fp) {
    fseek($fp, $off);
    if ($off > 0 && $off === max(0, $size - INITIAL_MAX_BYTES) && $st['offset'] < 0) {
        fgets($fp); // ligne probablement tronquée
    }
    while (($l = fgets($fp)) !== false) {
        if (substr($l, -1) !== "\n") { break; }
        applyLine($st, $l);
        $p = ftell($fp);
        if ($p !== false) { $off = $p; }
    }
    fclose($fp);
}
$st['offset'] = $off;
$st['inode']  = $inode;
@file_put_contents(MUMBLE_STATE, json_encode($st), LOCK_EX);
if ($lock) { flock($lock, LOCK_UN); fclose($lock); }

$online = [];
foreach ($st['online'] as $sid => $u) {
    $online[] = ['id' => (int)$sid] + $u;
}
usort($online, fn($a, $b) => [$b['gw'], $a['name']] <=> [$a['gw'], $b['name']]);

echo json_encode([
    'now_ts'   => time(),
    'online'   => $online,
    'history'  => $st['history'],
    'rejected' => $st['rejected'],
    'gateway'  => GATEWAY_NAME,
], JSON_UNESCAPED_UNICODE);
