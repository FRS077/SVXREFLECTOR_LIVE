<?php
/**
 * fichier api_status.php
 * Relais JSON vers le /status du reflector (127.0.0.1:8090) – champs filtrés
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

function fetchStatus(): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init(STATUS_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => STATUS_TIMEOUT,
            CURLOPT_TIMEOUT        => STATUS_TIMEOUT,
        ]);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($out !== false && $code >= 200 && $code < 300) ? $out : null;
    }
    $ctx = stream_context_create(['http' => ['timeout' => STATUS_TIMEOUT]]);
    $out = @file_get_contents(STATUS_URL, false, $ctx);
    return $out === false ? null : $out;
}

function pick(array $n, array $keys): string {
    foreach ($keys as $k) {
        if (isset($n[$k]) && !is_array($n[$k]) && trim((string)$n[$k]) !== '') {
            return trim((string)$n[$k]);
        }
    }
    return '';
}

$raw = fetchStatus();
if ($raw === null) {
    echo json_encode([
        'error'  => 'Statut indisponible (' . STATUS_URL . ')',
        'now_ts' => time(),
        'nodes'  => (object)[],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$decoded = json_decode($raw, true);
$srcNodes = (is_array($decoded) && isset($decoded['nodes']) && is_array($decoded['nodes'])) ? $decoded['nodes'] : [];

$nodes = [];
foreach ($srcNodes as $callsign => $n) {
    if (!is_array($n)) {
        continue;
    }
    $tgs = [];
    if (isset($n['monitoredTGs']) && is_array($n['monitoredTGs'])) {
        foreach ($n['monitoredTGs'] as $t) {
            $tgs[] = (int)$t;
        }
    }
    $lat = (isset($n['LAT']) && is_numeric($n['LAT'])) ? (float)$n['LAT'] : null;
    $lon = (isset($n['LONG']) && is_numeric($n['LONG'])) ? (float)$n['LONG'] : null;
    if ($lat === null || $lon === null || ($lat == 0 && $lon == 0) || abs($lat) > 90 || abs($lon) > 180) {
        $lat = $lon = null;
    }

    $nodes[(string)$callsign] = [
        'name'          => pick($n, ['Name']),
        'sysop'         => pick($n, ['Sysop', 'SysOp']),
        'location'      => pick($n, ['Location']),
        'node_location' => pick($n, ['nodeLocation']),
        'locator'       => pick($n, ['Locator']),
        'mode'          => pick($n, ['Mode']),
        'rx'            => pick($n, ['RXFREQ']),
        'tx'            => pick($n, ['TXFREQ']),
        'ctcss'         => pick($n, ['CTCSS']),
        'default_tg'    => pick($n, ['DefaultTG']),
        'monitored_tgs' => $tgs,
        'tg'            => (int)($n['tg'] ?? 0),
        'is_talker'     => !empty($n['isTalker']),
        'sw'            => pick($n, ['sw']),
        'sw_ver'        => pick($n, ['swVer']),
        'linked_to'     => pick($n, ['LinkedTo']),
        'website'       => pick($n, ['Website']),
        'lat'           => $lat,
        'lon'           => $lon,
    ];
}

echo json_encode([
    'now_ts' => time(),
    'nodes'  => (object)$nodes,
], JSON_UNESCAPED_UNICODE);
