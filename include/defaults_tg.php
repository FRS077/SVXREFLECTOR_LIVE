<?php
/**
 * fichier defaults_tg.php
 * Valeurs par défaut des options ajoutées en 1.0.17 (TG, serveur, dernières connexions, lien FreeDMR).
 * Chargé après config.php : si ton config.php ne contient pas encore ces lignes, rien ne casse.
 */
$__d = [
    'INDEX_SHOW_CONNECTED'  => true,
    'FREEDMR_URL'           => 'http://freedmr.fr',
    'FREEDMR_LABEL'         => 'FreeDMR',
    'TG_PAGE_ENABLE'        => true,
    'TG_LOCAL'              => 59,
    'TG_REFRESH_SEC'        => 10,
    'TG_READ_BYTES'         => 3000000,
    'TG_SHOW_MONITORED'     => true,
    'TG_SHOW_SELECTED'      => true,
    'TG_NAME_DEFAULT'       => 'TG provisoire',
    'HIDE_CALLSIGNS_ENABLE' => true,
    'NAV_REORDER_ENABLE'    => true,
    'STATS_SHOW_ANALYSIS'   => true,
    'STATS_MIN_PTT'         => 2,
    'NEWS_PAGE_ENABLE'      => true,
    'UPDATE_CHECK_ENABLE'   => true,
    'UPDATE_REPO'           => 'FRS077/SVXREFLECTOR_LIVE',
    'UPDATE_BRANCH'         => 'main',
    'UPDATE_CHECK_HOURS'    => 24,
    'UPDATE_API_BASE'       => 'https://api.github.com',
    'UPDATE_RAW_BASE'       => 'https://raw.githubusercontent.com',
    'WEATHER_ENABLE'        => true,
    'WEATHER_ASK_LOCATION'  => true,
    'WEATHER_SHOW_ICON'     => true,
    'WEATHER_SHOW_TEMP'     => true,
    'WEATHER_SHOW_SUN'      => true,
    'WEATHER_DEFAULT_NAME'  => 'Lorgies',
    'WEATHER_DEFAULT_LAT'   => 50.5469,
    'WEATHER_DEFAULT_LON'   => 2.8133,
    'WEATHER_REFRESH_MIN'   => 15,
    'INDEX_SHOW_SERVER'     => true,
    'SERVER_PAGE_ENABLE'    => true,
    'SERVER_SHOW_HOST'      => true,
    'SERVER_SHOW_CPU'       => true,
    'SERVER_SHOW_TEMP'      => true,
    'SERVER_SHOW_RAM'       => true,
    'SERVER_SHOW_DISK'      => true,
    'SERVER_SHOW_NET'       => true,
    'SERVER_SHOW_UPTIME'    => true,
    'SERVER_SHOW_SYSTEM'    => true,
    'SERVER_DISK_PATHS'     => ['/'],
    'SERVER_TEMP_WARN'      => 60,
    'SERVER_TEMP_CRIT'      => 75,
    'SERVER_PCT_WARN'       => 75,
    'SERVER_PCT_CRIT'       => 90,
    'SERVER_REFRESH_SEC'    => 3,
];
foreach ($__d as $__k => $__v) { if (!defined($__k)) define($__k, $__v); }
unset($__d, $__k, $__v);
if (!isset($TG_NAMES) || !is_array($TG_NAMES)) { $TG_NAMES = [59 => 'TG principal', 62 => 'TG de dégagement', 100 => 'SSTV', 101 => 'Packet APRS', 102 => 'Test']; }

/* Signature (copie de secours + empreinte) : ne pas modifier */
if (!defined('SVX_SIG_B')) {
    define('SVX_SIG_B', 'fSJyZi5hZm5yLy86c3B0dGgiIDoidSIgLCJBRk5SIHJ1b3AgZGxhdW1vUiA3' .
        'NzBTUkYiIDoibiIgLCJyYXAgdafDbm9DIKKA4iBldmlMIHJvdGNlbGZlUnh2' .
        'UyBkcmFvYmhzYUQiIDoiYSJ7');
    define('SVX_SIG_H', '3bf54a8bd23056c8045ec90c2c6fcc9b1376e45a5c3b0715335a8801d5cf3be0');
}
require_once __DIR__ . '/footer.php';

/* ---------- Indicatifs masqués (balises, passerelles…) : include/callsigns_masques.json ----------
 * Masqués uniquement dans « Transmissions en Direct » (index.php) et dans les Statistiques.
 * Jokers acceptés : « RNFA-* ». Sans fichier, seule la balise RNFA-APRS est masquée. */
if (!defined('HIDE_CALLSIGNS_FILE')) define('HIDE_CALLSIGNS_FILE', __DIR__ . '/callsigns_masques.json');
if (!function_exists('hidden_callsigns')) {
    function hidden_callsigns(): array {
        static $list = null;
        if ($list !== null) return $list;
        $list = [];
        if (!HIDE_CALLSIGNS_ENABLE) return $list;
        $raw = is_readable(HIDE_CALLSIGNS_FILE) ? json_decode((string)@file_get_contents(HIDE_CALLSIGNS_FILE), true) : null;
        $src = is_array($raw) ? (isset($raw['callsigns']) && is_array($raw['callsigns']) ? $raw['callsigns'] : $raw) : ['RNFA-APRS'];
        foreach ($src as $c) {
            $c = strtoupper(trim((string)$c));
            if ($c !== '' && preg_match('/^[A-Z0-9*\/_\-\. ()]{1,40}$/', $c)) $list[$c] = $c;
        }
        $list = array_values($list);
        return $list;
    }
    function callsign_is_hidden(string $cs): bool {
        $cs = strtoupper(trim($cs));
        foreach (hidden_callsigns() as $p) { if ($p === $cs || (strpos($p, '*') !== false && fnmatch($p, $cs))) return true; }
        return false;
    }
}
