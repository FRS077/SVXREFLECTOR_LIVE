<?php
/**
 * fichier api_events.php
 * Événements utiles du réflecteur pour la page Log (IP masquées côté serveur)
 * Auteur : FRS077 Romuald pour RNFA
 *
 * L'IP complète ne quitte jamais le serveur si LOG_MASK_IP = true.
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!LOG_PAGE_ENABLE) {
    http_response_code(404);
    echo json_encode(['error' => 'Page Log désactivée']);
    exit;
}
if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit;
}
session_write_close();

// Masque les derniers octets d'une IPv4 : 90.45.7.14 -> 90.45.x.x
function maskIp(string $ip): string {
    if (!LOG_MASK_IP) return $ip;
    $p = explode('.', $ip);
    if (count($p) !== 4) return 'x.x.x.x';
    $n = max(1, min(4, (int)LOG_MASK_OCTETS));
    for ($i = 4 - $n; $i < 4; $i++) $p[$i] = 'x';
    return implode('.', $p);
}
// Masque toute IPv4 présente dans un texte (port retiré)
function maskText(string $s): string {
    return preg_replace_callback('/\b(\d{1,3}(?:\.\d{1,3}){3})(?::\d+)?\b/', fn($m) => maskIp($m[1]), $s);
}

// Raisons courantes traduites (les autres restent telles quelles)
function frReason(string $r): string {
    static $map = [
        'Locally ordered disconnect'       => 'Déconnexion demandée par le réflecteur',
        'Connection closed by remote peer' => 'Connexion fermée par la station',
        'Protocol error'                   => 'Erreur de protocole (connexion invalide)',
    ];
    return $map[$r] ?? $r;
}

if (!is_readable(LOG_FILE)) {
    echo json_encode(['error' => 'Fichier log introuvable ou non lisible', 'events' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$size = filesize(LOG_FILE);
$fh = fopen(LOG_FILE, 'rb');
$start = max(0, $size - (int)LOG_READ_BYTES);
fseek($fh, $start);
$data = stream_get_contents($fh);
fclose($fh);
if ($start > 0) { $data = substr($data, (int)strpos($data, "\n") + 1); }   // ligne coupée ignorée

$events = [];
foreach (explode("\n", $data) as $line) {
    $line = rtrim($line, "\r");
    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4}) (\d{2}:\d{2}:\d{2}): (.*)$/', $line, $m)) continue;
    $date = $m[3] . '-' . $m[2] . '-' . $m[1];
    $time = $m[4];
    $msg  = $m[5];
    $ev = null;

    // Connexion réussie : "CALL: Login OK from 1.2.3.4:5678 with protocol version 2.0"
    if (preg_match('/^(.+?): Login OK from (\d+\.\d+\.\d+\.\d+):\d+ with protocol version (\S+)$/', $msg, $x)) {
        if (LOG_SHOW_LOGIN) $ev = ['type' => 'login', 'station' => $x[1], 'ip' => maskIp($x[2]), 'detail' => 'Protocole ' . $x[3]];
    }
    // Déconnexion : "CALL ou IP:port: Client disconnected: raison"
    elseif (preg_match('/^(.+?): Client disconnected: (.*)$/', $msg, $x)) {
        if (LOG_SHOW_DISCONNECT) {
            $who = $x[1]; $ip = '';
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $who, $y)) { $ip = maskIp($y[1]); $who = '—'; }
            $reason = $x[2];
            $isErr = stripos($reason, 'error') !== false || stripos($reason, 'timeout') !== false;
            $ev = ['type' => $isErr ? 'error' : 'logout', 'station' => $who, 'ip' => $ip, 'detail' => frReason(maskText($reason))];
        }
    }
    // Incidents
    elseif (preg_match('/^(.+?): (TCP heartbeat timeout)$/', $msg, $x)) {
        if (LOG_SHOW_ERRORS) $ev = ['type' => 'error', 'station' => $x[1], 'ip' => '', 'detail' => 'Timeout de liaison (heartbeat)'];
    }
    elseif (preg_match('/^(.+?): (.*(?:auth|Authentication|denied|refused|Access denied|Unknown|Invalid|failed).*)$/i', $msg, $x)
            && stripos($msg, 'Received valid auth key') === false) {
        if (LOG_SHOW_ERRORS) {
            $who = $x[1]; $ip = '';
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+):\d+$/', $who, $y)) { $ip = maskIp($y[1]); $who = '—'; }
            $ev = ['type' => 'error', 'station' => $who, 'ip' => $ip, 'detail' => maskText($x[2])];
        }
    }
    elseif (preg_match('/^(?:ERROR|WARNING|\*\*\* ERROR)\b:?\s*(.*)$/i', $msg, $x)) {
        if (LOG_SHOW_ERRORS) $ev = ['type' => 'error', 'station' => '—', 'ip' => '', 'detail' => maskText($x[1])];
    }
    // Arrêt / démarrage du réflecteur
    elseif (strpos($msg, 'SIGTERM received') !== false || strpos($msg, 'Shutting down') !== false) {
        if (LOG_SHOW_RESTART) $ev = ['type' => 'restart', 'station' => 'Réflecteur', 'ip' => '', 'detail' => 'Arrêt du service'];
    }
    elseif (preg_match('/^SvxReflector v\S+/', $msg, $x)) {
        if (LOG_SHOW_RESTART) $ev = ['type' => 'restart', 'station' => 'Réflecteur', 'ip' => '', 'detail' => 'Démarrage du service'];
    }
    // Pertes de trames
    elseif (preg_match('/^(.+?): (UDP frame\(s\) lost|Dropping out of sequence frame)/', $msg, $x)) {
        if (LOG_SHOW_UDP_LOSS) $ev = ['type' => 'udp', 'station' => $x[1], 'ip' => '', 'detail' => $x[2] === 'UDP frame(s) lost' ? 'Trames UDP perdues' : 'Trame hors séquence'];
    }
    // Changement de TG
    elseif (preg_match('/^(.+?): Select TG #(\d+)$/', $msg, $x)) {
        if (LOG_SHOW_TG_SELECT) $ev = ['type' => 'tg', 'station' => $x[1], 'ip' => '', 'detail' => 'Sélection du TG ' . $x[2]];
    }

    if ($ev) {
        $ev['date'] = $date;
        $ev['time'] = $time;
        $events[] = $ev;
    }
}

// Lignes strictement identiques (même seconde, même événement) : une seule est conservée
$clean = [];
$seen = [];
foreach ($events as $e) {
    $k = implode('|', [$e['date'], $e['time'], $e['type'], $e['station'], $e['ip'], $e['detail']]);
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $clean[] = $e;
}

$clean = array_slice(array_reverse($clean), 0, max(1, (int)LOG_MAX_LINES));   // plus récent en premier
echo json_encode([
    'events'  => $clean,
    'showIp'  => LOG_SHOW_IP,
    'masked'  => LOG_MASK_IP,
    'refresh' => max(0, (int)LOG_REFRESH_SEC),
], JSON_UNESCAPED_UNICODE);
