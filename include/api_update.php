<?php
/**
 * fichier api_update.php
 * Vérifie une fois par jour (cache serveur) s'il existe une version plus récente sur GitHub.
 * Source : dernière « Release » du dépôt (notes comprises), sinon version.txt de la branche principale.
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

/** Numéro de version « 1.0.26 » extrait de « v1.0.26 », « 1.0.26 @ 2026-2027 », etc. */
function upd_parse_version(string $s): string {
    return preg_match('/(\d+(?:\.\d+){1,3})/', $s, $m) ? $m[1] : '';
}
function upd_http(string $url): ?array {
    $hdr = ['User-Agent: SvxReflectorLive-UpdateCheck', 'Accept: application/vnd.github+json'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_HTTPHEADER => $hdr, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $body === false ? null : ['code' => $code, 'body' => (string)$body];
    }
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'header' => implode("\r\n", $hdr)]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : ['code' => 200, 'body' => $body];
}

$current = '';
$vf = __DIR__ . '/../version.txt';
if (is_readable($vf)) $current = upd_parse_version((string)strtok((string)file_get_contents($vf), "\r\n"));

$repo = UPDATE_REPO;
$ttl = max(1, (int)UPDATE_CHECK_HOURS) * 3600;
$dir = (defined('HISTORY_FILE') && is_dir(dirname(HISTORY_FILE)) && is_writable(dirname(HISTORY_FILE))) ? dirname(HISTORY_FILE) : sys_get_temp_dir();
$cacheFile = $dir . '/svxreflector_update.json';
$cache = null;
if (is_readable($cacheFile)) { $c = json_decode((string)@file_get_contents($cacheFile), true); if (is_array($c)) $cache = $c; }

$fresh = $cache && (time() - (int)($cache['checked'] ?? 0)) < (empty($cache['ok']) ? 3600 : $ttl);
if (UPDATE_CHECK_ENABLE && !$fresh) {
    $res = ['ok' => false, 'checked' => time(), 'latest' => $cache['latest'] ?? '', 'notes' => $cache['notes'] ?? '', 'name' => $cache['name'] ?? '',
            'date' => $cache['date'] ?? '', 'url' => $cache['url'] ?? ''];
    $r = upd_http(rtrim(UPDATE_API_BASE, '/') . '/repos/' . $repo . '/releases/latest');
    if ($r && $r['code'] === 200 && ($d = json_decode($r['body'], true)) && !empty($d['tag_name'])) {
        $res = ['ok' => true, 'checked' => time(), 'latest' => upd_parse_version((string)$d['tag_name']),
                'name' => mb_substr((string)($d['name'] ?? $d['tag_name']), 0, 120),
                'notes' => mb_substr((string)($d['body'] ?? ''), 0, 4000),
                'date' => substr((string)($d['published_at'] ?? ''), 0, 10),
                'url' => (string)($d['html_url'] ?? '')];
    } elseif ($r && $r['code'] === 404) {      // pas de Release publiée : on lit version.txt du dépôt
        $r2 = upd_http(rtrim(UPDATE_RAW_BASE, '/') . '/' . $repo . '/' . UPDATE_BRANCH . '/version.txt');
        if ($r2 && $r2['code'] === 200 && ($v = upd_parse_version((string)strtok($r2['body'], "\r\n"))) !== '') {
            $res = ['ok' => true, 'checked' => time(), 'latest' => $v, 'name' => 'Version ' . $v, 'notes' => '', 'date' => '', 'url' => ''];
        }
    }
    @file_put_contents($cacheFile, json_encode($res), LOCK_EX);
    $cache = $res;
}

$latest = (string)($cache['latest'] ?? '');
$url = (string)($cache['url'] ?? '');
if ($url === '' || strpos($url, 'https://github.com/' . $repo) !== 0) $url = 'https://github.com/' . $repo . '/releases';
echo json_encode([
    'enabled' => (bool)UPDATE_CHECK_ENABLE,
    'current' => $current,
    'latest'  => $latest,
    'newer'   => ($current !== '' && $latest !== '' && version_compare($latest, $current, '>')),
    'name'    => (string)($cache['name'] ?? ''),
    'notes'   => (string)($cache['notes'] ?? ''),
    'date'    => (string)($cache['date'] ?? ''),
    'url'     => $url,
    'repo'    => 'https://github.com/' . $repo,
    'checked' => (int)($cache['checked'] ?? 0),
    'ok'      => !empty($cache['ok']),
], JSON_UNESCAPED_UNICODE);
