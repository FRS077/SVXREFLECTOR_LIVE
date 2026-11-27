<?php
/**
 * fichier api_history.php
 * Statistiques par semaine / mois / année (historique construit à partir du log reflector)
 * Auteur : FRS077 Romuald pour RNFA
 */
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

define('HISTORY_SCHEMA', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise'], JSON_UNESCAPED_UNICODE);
    exit;
}
session_write_close();
date_default_timezone_set(LOG_TIMEZONE);

function newDay(): array {
    return ['tx' => 0, 'talk' => 0, 'max' => 0, 'maxcs' => '', 'htx' => array_fill(0, 24, 0), 'htalk' => array_fill(0, 24, 0), 'cs' => []];
}
function newCs(): array {
    return ['tx' => 0, 'talk' => 0, 'max' => 0, 'udp' => 0, 'to' => 0, 'conn' => 0, 'disc' => 0];
}
function defH(): array {
    return ['schema' => HISTORY_SCHEMA, 'offset' => -1, 'inode' => 0, 'open' => [], 'days' => []];
}
function loadH(): array {
    $j = @file_get_contents(HISTORY_FILE);
    $h = $j ? json_decode($j, true) : null;
    return (is_array($h) && ($h['schema'] ?? 0) === HISTORY_SCHEMA) ? $h : defH();
}

function applyLine(array &$h, string $line): void {
    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2}):\s?(.*)$/u', trim($line), $m)) {
        return;
    }
    $date = $m[3] . '-' . $m[2] . '-' . $m[1];
    $hour = (int)$m[4];
    $msg  = trim($m[7]);
    $ts   = mktime($hour, (int)$m[5], (int)$m[6], (int)$m[2], (int)$m[1], (int)$m[3]);

    if (stripos($msg, 'SIGTERM received') !== false) {
        $h['open'] = [];
        return;
    }
    if (!preg_match('/^(.+?): (Login OK from |Client disconnected|Talker start on TG #|Talker stop on TG #|UDP frame\(s\) lost|(?:TCP|UDP) heartbeat timeout)/u', $msg, $x)) {
        return;
    }
    $cs = trim($x[1]);
    $kind = $x[2];
    if ($kind === 'Client disconnected' && preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $cs)) {
        return;
    }
    if (!isset($h['days'][$date])) { $h['days'][$date] = newDay(); }
    $d = &$h['days'][$date];
    if (!isset($d['cs'][$cs])) { $d['cs'][$cs] = newCs(); }

    if ($kind === 'Login OK from ') {
        $d['cs'][$cs]['conn']++;
    } elseif ($kind === 'Client disconnected') {
        $d['cs'][$cs]['disc']++;
        unset($h['open'][$cs]);
    } elseif (strpos($kind, 'UDP frame') === 0) {
        $d['cs'][$cs]['udp']++;
    } elseif (strpos($kind, 'heartbeat') !== false) {
        $d['cs'][$cs]['to']++;
    } elseif (strpos($kind, 'Talker start') === 0) {
        $h['open'][$cs] = $ts;
    } elseif (strpos($kind, 'Talker stop') === 0) {
        $dur = isset($h['open'][$cs]) ? max(0, $ts - (int)$h['open'][$cs]) : 0;
        unset($h['open'][$cs]);
        if ($dur < (int)STATS_MIN_PTT) { $d['short'] = (int)($d['short'] ?? 0) + 1; unset($d); return; }   // PTT trop court : compté à part, pas dans les statistiques
        $d['tx']++;
        $d['talk'] += $dur;
        $d['htx'][$hour]++;
        $d['htalk'][$hour] += $dur;
        $d['cs'][$cs]['tx']++;
        $d['cs'][$cs]['talk'] += $dur;
        if (!isset($d['cs'][$cs]['hx'][$hour])) { $d['cs'][$cs]['hx'][$hour] = [0, 0]; }   // détail horaire par station (pour pouvoir masquer un indicatif)
        $d['cs'][$cs]['hx'][$hour][0]++;
        $d['cs'][$cs]['hx'][$hour][1] += $dur;
        if ($dur > $d['cs'][$cs]['max']) { $d['cs'][$cs]['max'] = $dur; }
        if ($dur > $d['max']) { $d['max'] = $dur; $d['maxcs'] = $cs; }
    }
    unset($d);
}

function eachLine(string $path, int $from, callable $fn, ?int &$endPos = null): void {
    $gz = (substr($path, -3) === '.gz');
    $fp = $gz ? @gzopen($path, 'r') : @fopen($path, 'r');
    if (!$fp) { return; }
    if (!$gz && $from > 0) { fseek($fp, $from); }
    while (($l = ($gz ? gzgets($fp) : fgets($fp))) !== false) {
        if (!$gz && substr($l, -1) !== "\n") { break; }
        $fn($l);
        if (!$gz) { $endPos = ftell($fp); }
    }
    $gz ? gzclose($fp) : fclose($fp);
}

function rotatedFiles(): array {
    $files = [];
    foreach ((array)glob(LOG_FILE . '*') as $f) {
        if ($f !== LOG_FILE && is_file($f) && is_readable($f)) { $files[] = $f; }
    }
    usort($files, fn($a, $b) => filemtime($a) <=> filemtime($b));
    return $files;
}

if (!is_readable(LOG_FILE)) {
    echo json_encode(['error' => 'Fichier ' . LOG_FILE . ' introuvable ou non lisible par Apache.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lock = @fopen(HISTORY_FILE . '.lock', 'c');
if ($lock) { flock($lock, LOCK_EX); }

clearstatcache(true);
$h = loadH();
$size  = (int)filesize(LOG_FILE);
$inode = (int)@fileinode(LOG_FILE);
$apply = function ($l) use (&$h) { applyLine($h, $l); };

if ($h['offset'] < 0) {
    // Première construction : logs archivés (du plus ancien au plus récent) puis log courant
    $h = defH();
    foreach (rotatedFiles() as $f) { eachLine($f, 0, $apply); }
    $off = 0;
    eachLine(LOG_FILE, 0, $apply, $off);
    $h['offset'] = $off;
} else {
    $off = (int)$h['offset'];
    if ($inode && $h['inode'] && $inode !== (int)$h['inode']) {
        // Le log a tourné : on termine l'ancien fichier (devenu archive) puis on repart de zéro
        foreach (rotatedFiles() as $f) {
            if (substr($f, -3) !== '.gz' && (int)@fileinode($f) === (int)$h['inode']) {
                eachLine($f, $off, $apply);
            }
        }
        $off = 0;
    } elseif ($size < $off) {
        $off = 0;
    }
    eachLine(LOG_FILE, $off, $apply, $off);
    $h['offset'] = $off;
}
$h['inode'] = $inode;

// purge
if (count($h['days']) > KEEP_DAYS) {
    ksort($h['days']);
    $h['days'] = array_slice($h['days'], -KEEP_DAYS, null, true);
}
@file_put_contents(HISTORY_FILE, json_encode($h, JSON_UNESCAPED_UNICODE), LOCK_EX);
if ($lock) { flock($lock, LOCK_UN); fclose($lock); }


// ---------------- Analyse des temps de parole (mode=analysis) ----------------
if (($_GET['mode'] ?? '') === 'analysis') {
    $dt = DateTime::createFromFormat('Y-m-d', (string)($_GET['date'] ?? ''), new DateTimeZone(LOG_TIMEZONE));
    $dt = $dt ?: new DateTime('now', new DateTimeZone(LOG_TIMEZONE));
    $dt->setTime(0, 0);
    $today = (new DateTime('now', new DateTimeZone(LOG_TIMEZONE)))->format('Y-m-d');
    if ($dt->format('Y-m-d') > $today) { $dt = new DateTime($today, new DateTimeZone(LOG_TIMEZONE)); }
    $dKey = $dt->format('Y-m-d');
    $keys = [];   // 90 jours se terminant à la date choisie
    for ($i = 89; $i >= 0; $i--) { $keys[] = (clone $dt)->modify("-$i days")->format('Y-m-d'); }

    $adj = function (array $d): array {   // jour sans les indicatifs masqués
        $csOut = [];
        foreach ($d['cs'] as $cs => $v) {
            if (callsign_is_hidden((string)$cs)) {
                $d['tx'] -= $v['tx']; $d['talk'] -= $v['talk'];
                foreach (($v['hx'] ?? []) as $hr => $hv) { $d['htalk'][(int)$hr] -= $hv[1]; }
                continue;
            }
            $csOut[$cs] = $v;
        }
        $d['cs'] = $csOut;
        $d['tx'] = max(0, $d['tx']); $d['talk'] = max(0, $d['talk']);
        for ($i = 0; $i < 24; $i++) { $d['htalk'][$i] = max(0, $d['htalk'][$i]); }
        $d['max'] = 0; $d['maxcs'] = '';
        foreach ($d['cs'] as $cs => $v) { if ($v['max'] > $d['max']) { $d['max'] = $v['max']; $d['maxcs'] = $cs; } }
        return $d;
    };

    $days = []; $cs30 = []; $h30 = array_fill(0, 24, 0); $topW = []; $topQ = []; $topD = [];
    $rec = ['cs' => '', 'talk' => 0, 'date' => ''];
    foreach ($keys as $idx => $k) {
        if (!isset($h['days'][$k])) { $days[] = ['key' => $k, 'talk' => 0, 'tx' => 0, 'short' => 0, 'spk' => 0]; continue; }
        $d = $adj($h['days'][$k]);
        $spk = 0; foreach ($d['cs'] as $v0) { if (($v0['tx'] ?? 0) > 0) { $spk++; } }
        $days[] = ['key' => $k, 'talk' => $d['talk'], 'tx' => $d['tx'], 'short' => (int)($h['days'][$k]['short'] ?? 0), 'spk' => $spk];
        foreach ($d['cs'] as $cs => $v) {
            $topQ[$cs] = ($topQ[$cs] ?? 0) + $v['talk'];
            if ($idx >= 83) { $topW[$cs] = ($topW[$cs] ?? 0) + $v['talk']; }
            if ($k === $dKey) { $topD[$cs] = ['tx' => $v['tx'], 'talk' => $v['talk'], 'max' => $v['max']]; }
            if ($idx >= 60) { $cs30[$cs][$k] = $v['talk']; }
        }
        if ($idx >= 60) {
            for ($i = 0; $i < 24; $i++) { $h30[$i] += $d['htalk'][$i]; }
            if ($d['max'] > $rec['talk']) { $rec = ['cs' => $d['maxcs'], 'talk' => $d['max'], 'date' => $k]; }
        }
    }
    arsort($topQ); arsort($topW);
    $sum30 = array_map('array_sum', $cs30); arsort($sum30);
    $top8 = array_slice(array_keys($sum30), 0, 8);
    $series = [];
    foreach ($top8 as $cs) {
        $vals = [];
        foreach (array_slice($keys, 60) as $k) { $vals[] = (int)($cs30[$cs][$k] ?? 0); }
        $series[] = ['cs' => $cs, 'values' => $vals];
    }
    echo json_encode([
        'date'    => $dKey,
        'days'    => $days,                                   // 90 jours
        'series'  => $series,                                 // 30 derniers jours, 8 stations les plus actives
        'hourly'  => $h30,                                    // minutes cumulées par heure (30 jours)
        'record'  => $rec,
        'top_day' => array_key_first($topD) === null ? '' : (function ($a) { uasort($a, fn($x, $y) => $y['talk'] <=> $x['talk']); return array_key_first($a); })($topD),
        'top_week' => array_key_first($topW) ?? '',
        'top_quarter' => array_key_first($topQ) ?? '',
        'day_cs'  => (object)$topD,
        'week_cs' => (object)$topW,
        'quarter_cs' => (object)$topQ,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------- Agrégation de la période demandée ----------------
$period = $_GET['period'] ?? 'week';
if (!in_array($period, ['week', 'month', 'year'], true)) { $period = 'week'; }
$offsetP = max(-60, min(0, (int)($_GET['offset'] ?? 0)));

$now = new DateTime('now', new DateTimeZone(LOG_TIMEZONE));
$mois = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
if ($period === 'week') {
    $start = (clone $now)->modify('monday this week')->setTime(0, 0)->modify(($offsetP * 7) . ' days');
    $end   = (clone $start)->modify('+6 days');
    $label = 'Semaine du ' . $start->format('d/m/Y') . ' au ' . $end->format('d/m/Y');
} elseif ($period === 'month') {
    $start = (clone $now)->modify('first day of this month')->setTime(0, 0)->modify($offsetP . ' months');
    $end   = (clone $start)->modify('last day of this month');
    $label = ucfirst($mois[(int)$start->format('n') - 1]) . ' ' . $start->format('Y');
} else {
    $start = (clone $now)->setDate((int)$now->format('Y') + $offsetP, 1, 1)->setTime(0, 0);
    $end   = (clone $start)->setDate((int)$start->format('Y'), 12, 31);
    $label = 'Année ' . $start->format('Y');
}
$sKey = $start->format('Y-m-d');
$eKey = $end->format('Y-m-d');

$stats = []; $htx = array_fill(0, 24, 0); $htalk = array_fill(0, 24, 0);
$longest = ['callsign' => '', 'talk_time' => 0, 'datetime' => ''];
$tx = 0; $talk = 0; $daily = []; $daysWithData = 0;

// une entrée par jour de la période (jours vides inclus pour le graphique)
if ($period === 'year') {
    for ($mth = 1; $mth <= 12; $mth++) { $daily[sprintf('%s-%02d', $start->format('Y'), $mth)] = ['tx' => 0, 'talk' => 0]; }
} else {
    for ($c = clone $start; $c <= $end; $c->modify('+1 day')) { $daily[$c->format('Y-m-d')] = ['tx' => 0, 'talk' => 0]; }
}

foreach ($h['days'] as $date => $d) {
    if ($date < $sKey || $date > $eKey) { continue; }
    $daysWithData++;
    // Indicatifs masqués (balises…) : retirés des totaux, des graphiques et du classement
    if (hidden_callsigns()) {
        foreach ($d['cs'] as $cs => $v) {
            if (!callsign_is_hidden((string)$cs)) { continue; }
            $d['tx'] -= $v['tx']; $d['talk'] -= $v['talk'];
            foreach (($v['hx'] ?? []) as $hr => $hv) { $d['htx'][(int)$hr] -= $hv[0]; $d['htalk'][(int)$hr] -= $hv[1]; }
        }
        $d['tx'] = max(0, $d['tx']); $d['talk'] = max(0, $d['talk']);
        for ($i = 0; $i < 24; $i++) { $d['htx'][$i] = max(0, $d['htx'][$i]); $d['htalk'][$i] = max(0, $d['htalk'][$i]); }
        $d['max'] = 0; $d['maxcs'] = '';
        foreach ($d['cs'] as $cs => $v) { if (!callsign_is_hidden((string)$cs) && $v['max'] > $d['max']) { $d['max'] = $v['max']; $d['maxcs'] = $cs; } }
    }
    $tx += $d['tx']; $talk += $d['talk'];
    for ($i = 0; $i < 24; $i++) { $htx[$i] += $d['htx'][$i]; $htalk[$i] += $d['htalk'][$i]; }
    $dk = ($period === 'year') ? substr($date, 0, 7) : $date;
    if (isset($daily[$dk])) { $daily[$dk]['tx'] += $d['tx']; $daily[$dk]['talk'] += $d['talk']; }
    if ($d['max'] > $longest['talk_time']) {
        $longest = ['callsign' => $d['maxcs'], 'talk_time' => $d['max'], 'datetime' => $date];
    }
    foreach ($d['cs'] as $cs => $v) {
        if (callsign_is_hidden((string)$cs)) { continue; }
        if (!isset($stats[$cs])) {
            $stats[$cs] = ['tx' => 0, 'talk_time' => 0, 'max_talk' => 0, 'udp_lost' => 0, 'timeouts' => 0, 'connects' => 0, 'disconnects' => 0];
        }
        $stats[$cs]['tx'] += $v['tx'];
        $stats[$cs]['talk_time'] += $v['talk'];
        $stats[$cs]['max_talk'] = max($stats[$cs]['max_talk'], $v['max']);
        $stats[$cs]['udp_lost'] += $v['udp'];
        $stats[$cs]['timeouts'] += $v['to'];
        $stats[$cs]['connects'] += $v['conn'];
        $stats[$cs]['disconnects'] += $v['disc'];
    }
}

$firstDay = $h['days'] ? min(array_keys($h['days'])) : $sKey;
$dailyOut = [];
foreach ($daily as $k => $v) { $dailyOut[] = ['key' => $k] + $v; }

echo json_encode([
    'period'          => $period,
    'offset'          => $offsetP,
    'label'           => $label,
    'start'           => $sKey,
    'end'             => $eKey,
    'has_prev'        => $firstDay < $sKey,
    'days_with_data'  => $daysWithData,
    'first_day'       => $firstDay,
    'stats'           => (object)$stats,
    'hourly_tx'       => $htx,
    'hourly_talk'     => $htalk,
    'longest'         => $longest,
    'tx_count'        => $tx,
    'total_talk_time' => $talk,
    'daily'           => $dailyOut,
], JSON_UNESCAPED_UNICODE);
