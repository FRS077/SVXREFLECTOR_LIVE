<?php
/**
 * fichier api_visitors.php
 * Nombre de visiteurs connectés en temps réel (battement de cœur des pages ouvertes)
 * Auteur : FRS077 Romuald pour RNFA
 */
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}
session_write_close();

$id = isset($_GET['id']) ? preg_replace('/[^a-zA-Z0-9]/', '', (string)$_GET['id']) : '';
$id = substr($id, 0, 32);
$leave = !empty($_GET['leave']);
$now = time();

$fp = @fopen(VISITORS_FILE, 'c+');
$visitors = [];
if ($fp) {
    flock($fp, LOCK_EX);
    $raw = stream_get_contents($fp);
    $visitors = $raw ? (json_decode($raw, true) ?: []) : [];
    foreach ($visitors as $k => $ts) {
        if (!is_int($ts) || $ts < $now - VISITOR_TTL * 3) { unset($visitors[$k]); }
    }
    if ($id !== '') {
        if ($leave) { unset($visitors[$id]); } else { $visitors[$id] = $now; }
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($visitors));
    flock($fp, LOCK_UN);
    fclose($fp);
}

$count = 0;
foreach ($visitors as $ts) {
    if ($ts >= $now - VISITOR_TTL) { $count++; }
}
echo json_encode(['visitors' => $count, 'now_ts' => $now]);
