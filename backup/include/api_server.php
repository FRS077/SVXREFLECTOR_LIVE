<?php
/**
 * fichier api_server.php
 * SvxReflector Live - informations du serveur (Linux : /proc et /sys), réponse JSON
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}
session_write_close();

function rd(string $f): string { return is_readable($f) ? (string)@file_get_contents($f) : ''; }

// ---- Mémoire ----
$mem = ['total' => 0, 'avail' => 0, 'used' => 0, 'pct' => 0, 'swap_total' => 0, 'swap_used' => 0];
$mi = rd('/proc/meminfo');
if ($mi !== '') {
    $v = [];
    foreach (explode("\n", $mi) as $l) { if (preg_match('/^(\w+):\s+(\d+) kB/', $l, $m)) $v[$m[1]] = (int)$m[2] * 1024; }
    $mem['total'] = $v['MemTotal'] ?? 0;
    $mem['avail'] = $v['MemAvailable'] ?? (($v['MemFree'] ?? 0) + ($v['Buffers'] ?? 0) + ($v['Cached'] ?? 0));
    $mem['used'] = max(0, $mem['total'] - $mem['avail']);
    $mem['pct'] = $mem['total'] ? round($mem['used'] / $mem['total'] * 100, 1) : 0;
    $mem['swap_total'] = $v['SwapTotal'] ?? 0;
    $mem['swap_used'] = max(0, ($v['SwapTotal'] ?? 0) - ($v['SwapFree'] ?? 0));
}

// ---- Disques ----
$disks = [];
foreach (SERVER_DISK_PATHS as $path) {
    $t = @disk_total_space($path); $f = @disk_free_space($path);
    if ($t && $f !== false) $disks[] = ['path' => $path, 'total' => (float)$t, 'used' => (float)($t - $f), 'free' => (float)$f, 'pct' => round(($t - $f) / $t * 100, 1)];
}

// ---- Température (zones thermiques, puis hwmon) ----
$temps = [];
foreach (glob('/sys/class/thermal/thermal_zone*') ?: [] as $z) {
    $raw = trim(rd($z . '/temp'));
    if ($raw === '' || !is_numeric($raw)) continue;
    $c = (float)$raw / (abs((float)$raw) > 1000 ? 1000 : 1);
    if ($c > -40 && $c < 150) $temps[] = ['name' => trim(rd($z . '/type')) ?: basename($z), 'c' => round($c, 1)];
}
if (!$temps) {
    foreach (glob('/sys/class/hwmon/hwmon*/temp*_input') ?: [] as $f) {
        $raw = trim(rd($f));
        if ($raw === '' || !is_numeric($raw)) continue;
        $c = (float)$raw / 1000;
        if ($c > -40 && $c < 150) $temps[] = ['name' => trim(rd(dirname($f) . '/name')) ?: basename(dirname($f)), 'c' => round($c, 1)];
    }
}
$tempMax = $temps ? max(array_column($temps, 'c')) : null;

// ---- Uptime ----
$up = (float)explode(' ', rd('/proc/uptime') ?: '0')[0];

// ---- CPU et réseau : différence entre deux relevés (mémorisés dans un petit fichier) ----
$cpuNow = null;
$st = rd('/proc/stat');
if (preg_match('/^cpu\s+(.+)$/m', $st, $m)) {
    $p = array_map('intval', preg_split('/\s+/', trim($m[1])));
    $cpuNow = [array_sum(array_slice($p, 0, 8)), ($p[3] ?? 0) + ($p[4] ?? 0)];
}
$rx = 0; $tx = 0; $nd = rd('/proc/net/dev');
foreach (explode("\n", $nd) as $l) {
    if (!preg_match('/^\s*([\w.\-@]+):\s*(\d+)(?:\s+\d+){7}\s+(\d+)/', $l, $m)) continue;
    if ($m[1] === 'lo' || strpos($m[1], 'docker') === 0 || strpos($m[1], 'veth') === 0 || strpos($m[1], 'br-') === 0) continue;
    $rx += (float)$m[2]; $tx += (float)$m[3];
}
$now = microtime(true);
$cpuPct = null; $rxRate = null; $txRate = null;
$sf = sys_get_temp_dir() . '/svxreflector_server_sample.json';
$fh = @fopen($sf, 'c+');
if ($fh) {
    flock($fh, LOCK_EX);
    $prev = json_decode((string)stream_get_contents($fh), true);
    if (is_array($prev) && $now - $prev['t'] < 1.2 && isset($prev['out'])) {          // plusieurs visiteurs : on réutilise le dernier calcul
        [$cpuPct, $rxRate, $txRate] = $prev['out'];
    } else {
        if (is_array($prev) && $now - $prev['t'] < 120 && $now > $prev['t']) {
            $dt = $now - $prev['t'];
            if ($cpuNow && !empty($prev['cpu'])) {
                $dTot = $cpuNow[0] - $prev['cpu'][0]; $dIdle = $cpuNow[1] - $prev['cpu'][1];
                if ($dTot > 0) $cpuPct = round(max(0, min(100, (1 - $dIdle / $dTot) * 100)), 1);
            }
            if (isset($prev['rx']) && $rx >= $prev['rx']) $rxRate = round(($rx - $prev['rx']) / $dt);
            if (isset($prev['tx']) && $tx >= $prev['tx']) $txRate = round(($tx - $prev['tx']) / $dt);
        }
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, json_encode(['t' => $now, 'cpu' => $cpuNow, 'rx' => $rx, 'tx' => $tx, 'out' => [$cpuPct, $rxRate, $txRate]]));
        if ($cpuPct === null && is_array($prev) && isset($prev['out'][0])) { [$cpuPct, $rxRate, $txRate] = $prev['out']; }
    }
    fflush($fh); flock($fh, LOCK_UN); fclose($fh);
}

$cores = max(1, preg_match_all('/^processor\s*:/m', rd('/proc/cpuinfo')));
$load = function_exists('sys_getloadavg') ? (sys_getloadavg() ?: [0, 0, 0]) : [0, 0, 0];
$os = '';
if (preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', rd('/etc/os-release'), $m)) $os = $m[1];
$model = '';
$ci = rd('/proc/cpuinfo');
if (preg_match('/^Model\s*:\s*(.+)$/m', $ci, $m) || preg_match('/^model name\s*:\s*(.+)$/m', $ci, $m)) $model = trim(preg_replace('/\s+/', ' ', $m[1]));

$logAge = null; $logSize = null;
if (is_readable(LOG_FILE)) { clearstatcache(true, LOG_FILE); $logAge = max(0, time() - (int)filemtime(LOG_FILE)); $logSize = (int)filesize(LOG_FILE); }

echo json_encode([
    'now' => time(),
    'cpu' => ['pct' => $cpuPct, 'cores' => $cores, 'load' => array_map(fn($x) => round($x, 2), $load), 'model' => $model],
    'temp' => ['max' => $tempMax, 'zones' => $temps],
    'mem' => $mem, 'disks' => $disks,
    'net' => ['rx' => $rxRate, 'tx' => $txRate, 'rx_total' => $rx, 'tx_total' => $tx],
    'uptime' => ['sec' => (int)$up, 'boot' => $up > 0 ? time() - (int)$up : 0],
    'sys' => ['host' => SERVER_SHOW_HOST ? php_uname('n') : '', 'os' => $os, 'kernel' => php_uname('r'), 'arch' => php_uname('m'),
              'php' => PHP_VERSION, 'web' => $_SERVER['SERVER_SOFTWARE'] ?? '', 'tz' => date_default_timezone_get()],
    'log' => ['age' => $logAge, 'size' => $logSize],
    'th' => ['temp_warn' => (int)SERVER_TEMP_WARN, 'temp_crit' => (int)SERVER_TEMP_CRIT, 'pct_warn' => (int)SERVER_PCT_WARN, 'pct_crit' => (int)SERVER_PCT_CRIT],
], JSON_UNESCAPED_UNICODE);
