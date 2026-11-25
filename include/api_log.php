<?php
/**
 * fichier api_log.php
 * SvxReflector JSON Live Reader & State Store
 * Auteur : FRS077 Romuald pour RNFA
 *
 * Format de log géré :
 *   20.11.2026 06:05:26: 173FRS049: Talker start on TG #59
 *
 * La position de lecture du log est mémorisée dans le fichier d'état :
 * plusieurs pages / visiteurs peuvent interroger ce script sans doubler les compteurs.
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

define('STATE_SCHEMA', 2);              // change si la structure de l'état évolue (relecture auto)

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (ENABLE_AUTH && (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise'], JSON_UNESCAPED_UNICODE);
    exit;
}
session_write_close(); // libère la session : les requêtes parallèles ne se bloquent pas

function getFrenchDay(string $dateStr): string {
    $days = [
        'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer',
        'Thu' => 'Jeu', 'Fri' => 'Ven', 'Sat' => 'Sam', 'Sun' => 'Dim'
    ];
    $dt = DateTime::createFromFormat('Y-m-d', $dateStr);
    if (!$dt) {
        return '';
    }
    $eng = $dt->format('D');
    return $days[$eng] ?? $eng;
}

/**
 * Analyse une ligne du log. Ne retient que :
 * connexion, déconnexion, début/fin de parole (talker start/stop), redémarrage du reflector.
 */
function parseLogLine(string $line): ?array {
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}:\d{2}:\d{2}):\s?(.*)$/u', $line, $m)) {
        return null;
    }
    $date = $m[3] . '-' . $m[2] . '-' . $m[1]; // Y-m-d
    $time = $m[4];
    $msg  = trim($m[5]);

    $callsign = '';
    $event = '';
    $talkgroup = 0;

    if (stripos($msg, 'SIGTERM received') !== false) {
        $event = 'SERVER_RESTART';
        $callsign = 'SERVEUR';
    } elseif (preg_match('/^(.+?): Login OK from /u', $msg, $x)) {
        $event = 'CONNECT';
        $callsign = $x[1];
    } elseif (preg_match('/^(.+?): Client disconnected/u', $msg, $x)) {
        // Ignore les connexions TCP brutes "IP:port" qui n'ont jamais fait de login
        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $x[1])) {
            return null;
        }
        $event = 'DISCONNECT';
        $callsign = $x[1];
    } elseif (preg_match('/^(.+?): Talker start on TG #(\d+)/u', $msg, $x)) {
        $event = 'TALK_START';
        $callsign = $x[1];
        $talkgroup = (int)$x[2];
    } elseif (preg_match('/^(.+?): Talker stop on TG #(\d+)/u', $msg, $x)) {
        $event = 'TALK_STOP';
        $callsign = $x[1];
        $talkgroup = (int)$x[2];
    } elseif (preg_match('/^(.+?): UDP frame\(s\) lost/u', $msg, $x)) {
        $event = 'UDP_LOST';
        $callsign = $x[1];
    } elseif (preg_match('/^(.+?): (?:TCP|UDP) heartbeat timeout/u', $msg, $x)) {
        $event = 'HB_TIMEOUT';
        $callsign = $x[1];
    } else {
        return null;
    }

    $callsign = trim($callsign);

    $ts = time();
    $utcIso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    try {
        $dt = new DateTime($date . ' ' . $time, new DateTimeZone(LOG_TIMEZONE));
        $ts = $dt->getTimestamp();
        $utcIso = gmdate('Y-m-d\TH:i:s\Z', $ts);
    } catch (Exception $e) {
        // on garde l'heure serveur
    }

    $day = getFrenchDay($date);

    return [
        'day'          => $day,
        'date'         => $date,
        'timestamp'    => $time,
        'datetime'     => trim($day . ' ' . $date . ' ' . $time),
        'ts'           => $ts,
        'utc_iso'      => $utcIso,
        'callsign'     => $callsign,
        'event'        => $event,
        'talkgroup'    => $talkgroup,
        'talk_time'    => 0,
        'session_time' => 0,
        'talk_status'  => $event === 'TALK_START' ? 'ON' : ($event === 'TALK_STOP' ? 'OFF' : ''),
        'author'       => AUTHOR_NAME,
    ];
}

function defaultState(): array {
    return [
        'offset'             => -1,  // position de lecture dans le log (-1 = pas encore initialisé)
        'last_datetime'      => 'En attente...',
        'last_utc_iso'       => '',
        'active_callsign'    => 'REPOS',
        'active_tg'          => 0,
        'active_state'       => 'ATTENTE',
        'talk_status'        => 'OFF',
        'last_talk_time'     => 0,
        'tx_count'           => 0,
        'total_talk_time'    => 0,
        'talk_starts'        => [], // callsign => timestamp du début de parole
        'connected_stations' => [], // callsign => ['since','since_ts','utc_iso']
        'tg_counts'          => [], // 'TG' => int
        'recent_events'      => [], // dernières transmissions (talk start / stop)
        'conn_events'        => [], // dernières connexions / déconnexions
        'schema'             => STATE_SCHEMA,
        'stats_date'         => '', // jour (Y-m-d) auquel se rapportent les statistiques
        'stats'              => [], // callsign => tx, talk_time, max_talk, udp_lost, timeouts, connects, disconnects
        'hourly_tx'          => array_fill(0, 24, 0), // transmissions par heure du jour
        'hourly_talk'        => array_fill(0, 24, 0), // secondes de parole par heure du jour
        'longest'            => ['callsign' => '', 'talk_time' => 0, 'datetime' => ''],
    ];
}

function loadState(): array {
    if (!file_exists(STATE_FILE)) {
        return defaultState();
    }
    $raw = @file_get_contents(STATE_FILE);
    if ($raw === false) {
        return defaultState();
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return defaultState();
    }
    if ((int)($decoded['schema'] ?? 0) !== STATE_SCHEMA) {
        return defaultState(); // ancien format : on relit le log proprement
    }
    return array_merge(defaultState(), $decoded);
}

function saveState(array $state): void {
    @file_put_contents(STATE_FILE, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Incrémente un compteur de la station (statistiques du jour). */
function bump(array &$state, string $cs, string $key, int $by = 1): void {
    if (!isset($state['stats'][$cs])) {
        $state['stats'][$cs] = [
            'tx' => 0, 'talk_time' => 0, 'max_talk' => 0,
            'udp_lost' => 0, 'timeouts' => 0, 'connects' => 0, 'disconnects' => 0,
        ];
    }
    $state['stats'][$cs][$key] = (int)($state['stats'][$cs][$key] ?? 0) + $by;
}

/** Remet à zéro les compteurs du jour quand la date du log change. */
function rollDayIfNeeded(array &$state, string $date): void {
    if (($state['stats_date'] ?? '') === $date) {
        return;
    }
    $state['stats_date']      = $date;
    $state['stats']           = [];
    $state['hourly_tx']       = array_fill(0, 24, 0);
    $state['hourly_talk']     = array_fill(0, 24, 0);
    $state['longest']         = ['callsign' => '', 'talk_time' => 0, 'datetime' => ''];
    $state['tx_count']        = 0;
    $state['total_talk_time'] = 0;
    $state['tg_counts']       = [];
}

/**
 * Applique un événement à l'état. Retourne l'événement enrichi (durées calculées),
 * ou null s'il ne doit pas apparaître dans les listes.
 */
function applyEventToState(array &$state, array $ev): ?array {
    $type = $ev['event'];
    $cs   = $ev['callsign'];
    $tg   = (int)$ev['talkgroup'];
    $ts   = (int)$ev['ts'];

    rollDayIfNeeded($state, $ev['date']);

    if ($type === 'UDP_LOST') {
        bump($state, $cs, 'udp_lost');
        return null;
    }
    if ($type === 'HB_TIMEOUT') {
        bump($state, $cs, 'timeouts');
        return null;
    }

    if ($type === 'SERVER_RESTART') {
        $state['connected_stations'] = [];
        $state['talk_starts'] = [];
        $state['talk_status'] = 'OFF';
        $state['active_callsign'] = 'REPOS';
        $state['active_tg'] = 0;
        return null;
    }

    $state['last_datetime'] = $ev['datetime'];
    $state['last_utc_iso']  = $ev['utc_iso'];

    if ($type === 'CONNECT') {
        $state['connected_stations'][$cs] = [
            'since'    => $ev['timestamp'],
            'since_ts' => $ts,
            'utc_iso'  => $ev['utc_iso'],
        ];
        $state['active_state'] = 'CONNEXION';
        bump($state, $cs, 'connects');
        array_unshift($state['conn_events'], $ev);
    } elseif ($type === 'DISCONNECT') {
        if (isset($state['connected_stations'][$cs]['since_ts'])) {
            $ev['session_time'] = max(0, $ts - (int)$state['connected_stations'][$cs]['since_ts']);
        }
        unset($state['connected_stations'][$cs], $state['talk_starts'][$cs]);
        $state['active_state'] = 'DÉCONNEXION';
        bump($state, $cs, 'disconnects');
        array_unshift($state['conn_events'], $ev);
    } elseif ($type === 'TALK_START') {
        $state['talk_starts'][$cs] = $ts;
        $state['talk_status'] = 'ON';
        $state['active_callsign'] = $cs;
        $state['active_tg'] = $tg;
        $state['active_state'] = 'EN ÉMISSION';
        if ($tg > 0) {
            $key = (string)$tg;
            $state['tg_counts'][$key] = (int)($state['tg_counts'][$key] ?? 0) + 1;
        }
        array_unshift($state['recent_events'], $ev);
    } elseif ($type === 'TALK_STOP') {
        $dur = 0;
        if (isset($state['talk_starts'][$cs])) {
            $dur = max(0, $ts - (int)$state['talk_starts'][$cs]);
            unset($state['talk_starts'][$cs]);
        }
        $ev['talk_time'] = $dur;

        // statistiques du jour
        bump($state, $cs, 'tx');
        bump($state, $cs, 'talk_time', $dur);
        if ($dur > (int)$state['stats'][$cs]['max_talk']) {
            $state['stats'][$cs]['max_talk'] = $dur;
        }
        $hour = max(0, min(23, (int)substr($ev['timestamp'], 0, 2)));
        $state['hourly_tx'][$hour]   = (int)($state['hourly_tx'][$hour] ?? 0) + 1;
        $state['hourly_talk'][$hour] = (int)($state['hourly_talk'][$hour] ?? 0) + $dur;
        if ($dur > (int)($state['longest']['talk_time'] ?? 0)) {
            $state['longest'] = ['callsign' => $cs, 'talk_time' => $dur, 'datetime' => $ev['datetime']];
        }

        $state['tx_count'] = (int)$state['tx_count'] + 1;
        $state['total_talk_time'] = (int)$state['total_talk_time'] + $dur;
        $state['last_talk_time'] = $dur;
        $state['talk_status'] = 'OFF';
        $state['active_callsign'] = $cs;
        $state['active_tg'] = $tg;
        $state['active_state'] = 'FIN TX';
        array_unshift($state['recent_events'], $ev);
    }

    if (count($state['recent_events']) > MAX_TALK_EVENTS) {
        $state['recent_events'] = array_slice($state['recent_events'], 0, MAX_TALK_EVENTS);
    }
    if (count($state['conn_events']) > MAX_CONN_EVENTS) {
        $state['conn_events'] = array_slice($state['conn_events'], 0, MAX_CONN_EVENTS);
    }
    return $ev;
}

if (!file_exists(LOG_FILE) || !is_readable(LOG_FILE)) {
    $st = loadState();
    echo json_encode([
        'error'  => 'Fichier ' . LOG_FILE . ' introuvable ou non lisible par Apache.',
        'offset' => 0,
        'now_ts' => time(),
        'state'  => $st,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Verrou : une seule requête à la fois lit le log et met l'état à jour
$lockFp = @fopen(STATE_FILE . '.lock', 'c');
if ($lockFp) {
    flock($lockFp, LOCK_EX);
}

clearstatcache(true, LOG_FILE);
$fileSize = filesize(LOG_FILE);
if ($fileSize === false) {
    $fileSize = 0;
}

$state = loadState();
$offset = (int)$state['offset'];
$newEvents = [];

$fp = @fopen(LOG_FILE, 'r');
if ($fp) {
    if ($offset < 0) {
        // Premier appel : rejoue les dernières lignes pour initialiser l'état
        $state = defaultState();
        $tail = [];
        while (($l = fgets($fp)) !== false) {
            $tail[] = $l;
            if (count($tail) > INITIAL_TAIL_LINES) {
                array_shift($tail);
            }
        }
        foreach ($tail as $line) {
            $parsed = parseLogLine($line);
            if ($parsed !== null) {
                $applied = applyEventToState($state, $parsed);
                if ($applied !== null) {
                    $newEvents[] = $applied;
                }
            }
        }
        $offset = ftell($fp);
        if ($offset === false) {
            $offset = $fileSize;
        }
    } else {
        // Log tronqué / tourné : on repart du début
        if ($fileSize < $offset) {
            $offset = 0;
        }

        fseek($fp, $offset, SEEK_SET);
        while (($l = fgets($fp)) !== false) {
            if (substr($l, -1) !== "\n") {
                break; // ligne incomplète en cours d'écriture
            }
            $parsed = parseLogLine($l);
            if ($parsed !== null) {
                $applied = applyEventToState($state, $parsed);
                if ($applied !== null) {
                    $newEvents[] = $applied;
                }
            }
            $posAfterLine = ftell($fp);
            if ($posAfterLine !== false) {
                $offset = $posAfterLine;
            }
        }
    }
    fclose($fp);
    $state['offset'] = $offset;
    saveState($state);
}

if ($lockFp) {
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
}

echo json_encode([
    'offset'    => $offset,
    'now_ts'    => time(),
    'new_count' => count($newEvents),
    'events'    => $newEvents,
    'state'     => $state,
    'author'    => AUTHOR_NAME,
], JSON_UNESCAPED_UNICODE);
