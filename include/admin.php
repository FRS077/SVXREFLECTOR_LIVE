<?php
/**
 * fichier admin.php
 * Page d'administration : modification de config.php (true/false, nombres, textes, menus)
 * Identifiants DIFFÉRENTS de ceux des visiteurs (voir section 11 de config.php).
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/../config.php';

if (!ADMIN_ENABLE) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

const CONFIG_PATH = __DIR__ . '/../config.php';
const BACKUP_DIR  = __DIR__ . '/../backup';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ------------------------------------------------------------
// Session admin, CSRF, anti force brute
// ------------------------------------------------------------
function admin_logged(): bool {
    if (empty($_SESSION['admin_ok'])) return false;
    $max = max(1, (int)ADMIN_SESSION_MIN) * 60;
    if (time() - (int)($_SESSION['admin_time'] ?? 0) > $max) {
        unset($_SESSION['admin_ok'], $_SESSION['admin_time'], $_SESSION['admin_csrf']);
        return false;
    }
    $_SESSION['admin_time'] = time();
    return true;
}
function csrf_token(): string {
    if (empty($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['admin_csrf'];
}
function csrf_ok(): bool {
    return !empty($_POST['csrf']) && !empty($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], (string)$_POST['csrf']);
}
function fail_file(): string {
    return sys_get_temp_dir() . '/svx_admin_fail_' . md5($_SERVER['REMOTE_ADDR'] ?? '?') . '.json';
}
function fail_state(): array {
    $d = @json_decode((string)@file_get_contents(fail_file()), true);
    return is_array($d) ? $d : ['n' => 0, 'until' => 0];
}
function fail_record(): void {
    $d = fail_state();
    $d['n'] = (int)$d['n'] + 1;
    if ($d['n'] >= 5) { $d['until'] = time() + 900; $d['n'] = 0; }   // 5 échecs = blocage 15 min
    @file_put_contents(fail_file(), json_encode($d), LOCK_EX);
}
function fail_reset(): void { @unlink(fail_file()); }

// ------------------------------------------------------------
// Lecture / écriture de config.php
// ------------------------------------------------------------
// Analyse une ligne define('NOM', valeur); // commentaire
function parse_define_line(string $line): ?array {
    if (!preg_match('/^(\s*define\(\s*\'([A-Z0-9_]+)\'\s*,\s*)(.*?)(\s*\)\s*;)(\s*\/\/\s*(.*?))?\s*$/', $line, $m)) return null;
    $raw = trim($m[3]);
    $type = null; $val = null;
    if ($raw === 'true' || $raw === 'false') { $type = 'bool'; $val = ($raw === 'true'); }
    elseif (preg_match('/^-?\d+$/', $raw))   { $type = 'int';  $val = (int)$raw; }
    elseif (preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'$/s', $raw, $s)) { $type = 'string'; $val = str_replace(['\\\\', "\\'"], ['\\', "'"], $s[1]); }
    elseif (preg_match('/^\[(.*)\]$/', $raw, $a)) {
        $items = [];
        if (trim($a[1]) !== '') {
            foreach (preg_split('/\s*,\s*/', trim($a[1])) as $it) {
                if (!preg_match('/^\'((?:[^\'\\\\]|\\\\.)*)\'$/', $it, $s)) return null;
                $items[] = str_replace(['\\\\', "\\'"], ['\\', "'"], $s[1]);
            }
        }
        $type = 'list'; $val = $items;
    }
    if ($type === null) return null;
    return ['raw' => $raw, 'name' => $m[2], 'type' => $type, 'value' => $val, 'prefix' => $m[1], 'suffix' => $m[4] . ($m[5] ?? ''), 'comment' => $m[6] ?? ''];
}
// Reconstruit la ligne en gardant l'alignement du commentaire quand la longueur de la valeur change
function rebuild_define(array $d, string $literal): string {
    $diff = strlen((string)$d['raw']) - strlen($literal);
    $suffix = $d['suffix'];
    if ($diff !== 0 && preg_match('/^(\s*\)\s*;)( +)(\/\/.*)$/', $suffix, $m)) {
        $suffix = $m[1] . str_repeat(' ', max(1, strlen($m[2]) + $diff)) . $m[3];
    }
    return $d['prefix'] . $literal . $suffix;
}
function php_str(string $s): string { return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $s) . "'"; }

// Constantes jamais modifiables depuis le formulaire général
function is_protected(string $name): bool {
    if (strpos($name, 'ADMIN_') === 0) return true;
    static $paths = ['LOG_FILE','STATUS_URL','STATE_FILE','HISTORY_FILE','VISITORS_FILE','MUMBLE_LOG','MUMBLE_STATE'];
    return in_array($name, $paths, true) && !ADMIN_EDIT_PATHS;
}

// Liste des réglages (avec sections) lue dans config.php
function read_settings(array $lines): array {
    $items = []; $section = 'Général';
    foreach ($lines as $i => $line) {
        if (preg_match('/^\/\/\s*(\d+)\)\s*(.+?)\s*$/', $line, $m)) { $section = $m[1] . ') ' . $m[2]; continue; }
        $d = parse_define_line($line);
        if (!$d || is_protected($d['name'])) continue;
        $d['section'] = $section; $d['line'] = $i;
        $items[] = $d;
    }
    return $items;
}

// Blocs de menus : $MENU_LINKS / $MENU_SOCIAL
function read_menu(array $lines, string $var): ?array {
    $start = null; $end = null;
    foreach ($lines as $i => $line) {
        if ($start === null && preg_match('/^\$' . $var . '\s*=\s*\[\s*$/', $line)) { $start = $i; continue; }
        if ($start !== null && preg_match('/^\];\s*$/', $line)) { $end = $i; break; }
    }
    if ($start === null || $end === null) return null;
    $rows = [];
    for ($i = $start + 1; $i < $end; $i++) {
        if (preg_match('/^\s*\[\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*(FACEBOOK_URL|\'((?:[^\'\\\\]|\\\\.)*)\')\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\]\s*,?\s*$/', $lines[$i], $m)) {
            $un = fn($x) => str_replace(['\\\\', "\\'"], ['\\', "'"], $x);
            $rows[] = ['label' => $un($m[1]), 'url' => $m[2] === 'FACEBOOK_URL' ? '' : $un($m[3]), 'icon' => $un($m[4]), 'fixed' => $m[2] === 'FACEBOOK_URL', 'orig' => $lines[$i]];
        } else {
            return null;   // format inattendu : on ne touche pas à ce bloc
        }
    }
    return ['start' => $start, 'end' => $end, 'rows' => $rows];
}

function valid_url(string $u): bool { return $u === '' || (bool)preg_match('#^(https?://|mailto:|tel:)[^\s\'"<>\\\\]+$#i', $u); }

// Vérifie et formate la valeur envoyée pour un réglage ; retourne [littéral PHP, valeur] ou une erreur
function build_value(array $d, $posted, array &$errors) {
    $n = $d['name'];
    switch ($d['type']) {
        case 'bool':
            $v = ($posted === '1');
            return [$v ? 'true' : 'false', $v];
        case 'int':
            $p = trim((string)$posted);
            if (!preg_match('/^\d{1,9}$/', $p)) { $errors[] = "$n : nombre entier positif attendu."; return null; }
            return [(string)(int)$p, (int)$p];
        case 'list':
            $out = [];
            foreach (explode(',', (string)$posted) as $it) {
                $it = trim($it);
                if ($it === '') continue;
                if (!preg_match('/^[A-Za-z0-9_\-\/\. ]{1,40}$/', $it)) { $errors[] = "$n : « $it » n'est pas un indicatif valide."; return null; }
                $out[] = $it;
            }
            return ['[' . implode(', ', array_map('php_str', $out)) . ']', $out];
        default:
            $p = trim((string)$posted);
            if (preg_match('/[\x00-\x1F\x7F]/', $p) || mb_strlen($p) > 300) { $errors[] = "$n : texte invalide (caractères de contrôle ou trop long)."; return null; }
            if (substr($n, -4) === '_URL' && $n !== 'STATUS_URL' && !valid_url($p)) { $errors[] = "$n : adresse http(s)://, mailto: ou tel: attendue."; return null; }
            if ($n === 'STATUS_URL' && !preg_match('#^https?://[^\s\'"]+$#i', $p)) { $errors[] = "$n : adresse http(s):// attendue."; return null; }
            if ($n === 'LOG_TIMEZONE' && !in_array($p, timezone_identifiers_list(), true)) { $errors[] = "$n : fuseau horaire inconnu."; return null; }
            if ($n === 'STATS_DEFAULT_PERIOD' && !in_array($p, ['day','week','month','year'], true)) { $errors[] = "$n : day, week, month ou year."; return null; }
            if (strpos($n, '_COLOR_') !== false && !preg_match('/^#[0-9a-fA-F]{6}$/', $p)) { $errors[] = "$n : couleur au format #rrggbb."; return null; }
            if ($n === 'BACKGROUND_FILE' && $p !== '' && (!preg_match('#^[A-Za-z0-9_\-/\.]+$#', $p) || strpos($p, '..') !== false)) { $errors[] = "$n : chemin relatif simple attendu (ex. image/fond.svg)."; return null; }
            if ($n === 'AUTH_USER' && !preg_match('/^[A-Za-z0-9_.\-@]{1,40}$/', $p)) { $errors[] = "$n : 1 à 40 caractères (lettres, chiffres, _ . - @)."; return null; }
            if ($n === 'AUTH_PASS' && mb_strlen($p) < 6) { $errors[] = "$n : 6 caractères minimum."; return null; }
            return [php_str($p), $p];
    }
}

// Sauvegarde + écriture sécurisée de config.php
function make_backup(string $content): ?string {
    if (!is_dir(BACKUP_DIR) && !@mkdir(BACKUP_DIR, 0750, true)) return null;
    @file_put_contents(BACKUP_DIR . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    @file_put_contents(BACKUP_DIR . '/index.html', '');
    $base = 'config_' . date('Ymd_His');
    $name = $base . '.php';
    for ($k = 2; file_exists(BACKUP_DIR . '/' . $name) && $k < 100; $k++) { $name = $base . '_' . $k . '.php'; }   // jamais d'écrasement
    if (@file_put_contents(BACKUP_DIR . '/' . $name, $content, LOCK_EX) === false) return null;
    $all = glob(BACKUP_DIR . '/config_*.php') ?: [];
    rsort($all);
    foreach (array_slice($all, max(1, (int)ADMIN_BACKUP_KEEP)) as $old) @unlink($old);
    return $name;
}
function php_syntax_ok(string $code): bool {
    try { token_get_all($code, TOKEN_PARSE); return true; } catch (\Throwable $e) { return false; }
}
function write_config(string $newContent, string $oldContent, string &$msg): bool {
    if (!php_syntax_ok($newContent)) { $msg = 'Le fichier obtenu n\'est pas valide : aucune modification écrite.'; return false; }
    if (!is_writable(CONFIG_PATH)) { $msg = 'config.php n\'est pas modifiable par le serveur web (droits d\'écriture). Voir l\'aide en bas de page.'; return false; }
    if (make_backup($oldContent) === null) { $msg = 'Impossible de créer la sauvegarde (dossier backup/ non inscriptible) : rien n\'a été modifié.'; return false; }
    if (@file_put_contents(CONFIG_PATH, $newContent, LOCK_EX) === false) { $msg = 'Écriture de config.php impossible.'; return false; }
    if (function_exists('opcache_invalidate')) @opcache_invalidate(CONFIG_PATH, true);
    return true;
}

// ------------------------------------------------------------
// Actions
// ------------------------------------------------------------
$flash = ''; $flashType = 'ok'; $errors = []; $overlay = []; $menuOverlay = [];
$locked = fail_state()['until'] > time();

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_ok'], $_SESSION['admin_time'], $_SESSION['admin_csrf']);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['do'] ?? '';
    if ($act === 'login') {
        if ($locked) {
            $flash = 'Trop de tentatives. Réessayez dans quelques minutes.'; $flashType = 'err';
        } else {
            $u = (string)($_POST['user'] ?? ''); $p = (string)($_POST['pass'] ?? '');
            $okUser = hash_equals(ADMIN_USER, $u);
            $okPass = password_verify($p, ADMIN_PASS_HASH);
            if ($okUser && $okPass) {
                session_regenerate_id(true);
                $_SESSION['admin_ok'] = true; $_SESSION['admin_time'] = time();
                fail_reset();
                header('Location: admin.php');
                exit;
            }
            fail_record();
            usleep(400000);
            $flash = 'Identifiant ou mot de passe incorrect.'; $flashType = 'err';
            $locked = fail_state()['until'] > time();
        }
    } elseif (admin_logged()) {
        if (!csrf_ok()) {
            $flash = 'Session expirée ou requête invalide, rechargez la page.'; $flashType = 'err';
        } elseif ($act === 'save') {
            $old = (string)file_get_contents(CONFIG_PATH);
            $lines = preg_split('/\r\n|\n/', $old);
            $changed = [];
            foreach (read_settings($lines) as $d) {
                $n = $d['name'];
                $posted = $_POST['c'][$n] ?? null;      // champ absent = réglage inchangé
                if ($posted === null) continue;
                if (is_array($posted)) { $errors[] = "$n : valeur invalide."; continue; }
                $overlay[$n] = $posted;
                $r = build_value($d, $posted, $errors);
                if ($r === null) continue;
                if ($r[1] !== $d['value']) {
                    $lines[$d['line']] = rebuild_define($d, $r[0]);
                    $changed[] = $n;
                }
            }
            // Menus (le bloc est récrit de bas en haut pour garder les numéros de ligne valides)
            foreach (['MENU_SOCIAL' => 'social', 'MENU_LINKS' => 'links'] as $var => $key) {
                $blk = read_menu($lines, $var);
                if (!$blk || !isset($_POST['m'][$key]) || !is_array($_POST['m'][$key])) continue;
                $new = []; $tuples = [];
                $postedRows = $_POST['m'][$key];
                $maxI = max(array_merge([count($blk['rows']) - 1], array_map('intval', array_keys($postedRows))));
                for ($i = 0; $i <= $maxI; $i++) {
                    if (($blk['rows'][$i]['fixed'] ?? false)) { $new[] = $blk['rows'][$i]['orig']; continue; }   // ligne Facebook : inchangée
                    $r = $postedRows[$i] ?? null;
                    if (!is_array($r)) continue;
                    $label = trim((string)($r['label'] ?? '')); $url = trim((string)($r['url'] ?? '')); $icon = trim((string)($r['icon'] ?? ''));
                    $menuOverlay[$key][] = ['label' => $label, 'url' => $url, 'icon' => $icon];
                    if ($label === '' && $url === '') continue;               // ligne vide = supprimée
                    if ($label === '' || mb_strlen($label) > 40 || preg_match('/[\x00-\x1F\x7F]/', $label)) { $errors[] = "$var : un texte est vide ou invalide."; continue; }
                    if (!valid_url($url)) { $errors[] = "$var « $label » : adresse http(s)://, mailto: ou tel: attendue."; continue; }
                    if ($icon === '') $icon = 'fa-solid fa-link';
                    if (!preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9\-]+$/', $icon)) { $errors[] = "$var « $label » : icône au format « fa-solid fa-globe »."; continue; }
                    $tuples[] = [$label, $url, $icon];
                    $new[] = '    [' . php_str($label) . ', ' . php_str($url) . ', ' . php_str($icon) . '],';
                }
                $oldTuples = [];
                foreach ($blk['rows'] as $br) { if (!$br['fixed']) $oldTuples[] = [$br['label'], $br['url'], $br['icon']]; }
                if ($tuples !== $oldTuples && !$errors) {
                    array_splice($lines, $blk['start'] + 1, $blk['end'] - $blk['start'] - 1, $new);
                    $changed[] = '$' . $var;
                }
            }
            if ($errors) {
                $flash = 'Rien n\'a été enregistré : corrigez les points ci-dessous.'; $flashType = 'err';
            } elseif (!$changed) {
                $flash = 'Aucune modification à enregistrer.'; $flashType = 'ok';
            } else {
                $msg = '';
                if (write_config(implode("\n", $lines), $old, $msg)) {
                    $flash = count($changed) . ' modification(s) enregistrée(s) : ' . implode(', ', $changed) . '. Sauvegarde créée.';
                    $overlay = []; $menuOverlay = [];
                } else { $flash = $msg; $flashType = 'err'; }
            }
        } elseif ($act === 'password') {
            $cur = (string)($_POST['cur'] ?? ''); $new = (string)($_POST['new'] ?? ''); $conf = (string)($_POST['conf'] ?? '');
            if (!password_verify($cur, ADMIN_PASS_HASH)) { $flash = 'Mot de passe actuel incorrect.'; $flashType = 'err'; }
            elseif (strlen($new) < 10) { $flash = 'Le nouveau mot de passe doit faire 10 caractères minimum.'; $flashType = 'err'; }
            elseif ($new !== $conf) { $flash = 'La confirmation ne correspond pas.'; $flashType = 'err'; }
            elseif ($new === AUTH_PASS) { $flash = 'Le mot de passe admin doit être différent de celui des visiteurs.'; $flashType = 'err'; }
            else {
                $old = (string)file_get_contents(CONFIG_PATH);
                $lines = preg_split('/\r\n|\n/', $old); $done = false;
                foreach ($lines as $i => $line) {
                    $d = parse_define_line($line);
                    if ($d && $d['name'] === 'ADMIN_PASS_HASH') { $lines[$i] = $d['prefix'] . php_str(password_hash($new, PASSWORD_DEFAULT)) . $d['suffix']; $done = true; break; }
                }
                $msg = '';
                if ($done && write_config(implode("\n", $lines), $old, $msg)) { $flash = 'Mot de passe administrateur modifié.'; }
                else { $flash = $done ? $msg : 'Ligne ADMIN_PASS_HASH introuvable dans config.php.'; $flashType = 'err'; }
            }
        } elseif ($act === 'restore') {
            $f = (string)($_POST['file'] ?? '');
            if (!preg_match('/^config_\d{8}_\d{6}(?:_\d+)?\.php$/', $f) || !is_file(BACKUP_DIR . '/' . $f)) { $flash = 'Sauvegarde introuvable.'; $flashType = 'err'; }
            else {
                $bk = (string)file_get_contents(BACKUP_DIR . '/' . $f);
                $msg = '';
                if (write_config($bk, (string)file_get_contents(CONFIG_PATH), $msg)) { $flash = 'Sauvegarde ' . $f . ' restaurée (la version précédente a été sauvegardée aussi).'; }
                else { $flash = $msg; $flashType = 'err'; }
            }
        }
    }
}

$logged = admin_logged();
$settings = []; $menus = []; $backups = [];
if ($logged) {
    // Relecture après une éventuelle écriture
    $content = (string)file_get_contents(CONFIG_PATH);
    $lines = preg_split('/\r\n|\n/', $content);
    $settings = read_settings($lines);
    $menus = ['links' => read_menu($lines, 'MENU_LINKS'), 'social' => read_menu($lines, 'MENU_SOCIAL')];
    $backups = glob(BACKUP_DIR . '/config_*.php') ?: [];
    rsort($backups);
    $backups = array_map('basename', array_slice($backups, 0, 15));
}
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Administration • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/admin.css?v=<?= (int)@filemtime(__DIR__ . '/../css/admin.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-user-shield"></i>
            <div>
                <h1>ADMINISTRATION</h1>
                <small style="color: var(--text-muted);">Modification de config.php</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <?php if ($logged): ?>
            <a class="btn-logout" href="admin.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion admin</a>
            <?php endif; ?>
        </div>
    </header>

    <?php if (!$isHttps): ?>
    <div class="flash err"><i class="fa-solid fa-triangle-exclamation"></i> Connexion non chiffrée (http) : le mot de passe admin circule en clair. Utilisez https si la page est accessible depuis Internet.</div>
    <?php endif; ?>
    <?php if ($flash !== '' || $errors): ?>
    <div class="flash <?= $flashType ?>"><?= h($flash) ?>
        <?php if ($errors): ?><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <?php endif; ?>

<?php if (!$logged): ?>
    <div class="card-panel login-box">
        <div class="panel-header"><h2><i class="fa-solid fa-lock"></i> Accès administrateur</h2></div>
        <?php if ($locked): ?>
            <p class="help">Trop de tentatives échouées. Accès bloqué quelques minutes.</p>
        <?php else: ?>
        <form method="post" autocomplete="off">
            <input type="hidden" name="do" value="login">
            <label for="user">Identifiant administrateur</label>
            <input class="inp" type="text" id="user" name="user" required autofocus>
            <label for="pass">Mot de passe administrateur</label>
            <input class="inp" type="password" id="pass" name="pass" required>
            <p style="margin-top:18px;"><button class="btn-primary" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Se connecter</button></p>
        </form>
        <?php endif; ?>
        <p class="help" style="margin-top:14px;">Ces identifiants sont différents de ceux des visiteurs du dashboard.</p>
    </div>
<?php else: ?>

    <form method="post" id="cfgForm" autocomplete="off">
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <?php
        $bySection = [];
        foreach ($settings as $s) { $bySection[$s['section']][] = $s; }
        foreach ($bySection as $title => $items): ?>
        <div class="card-panel" style="margin-bottom:18px;">
            <div class="panel-header"><h2><?= h($title) ?></h2></div>
            <div class="set-grid">
            <?php foreach ($items as $s):
                $n = $s['name'];
                $val = array_key_exists($n, $overlay) ? $overlay[$n] : $s['value'];
                $wide = in_array($s['type'], ['list'], true) || ($s['type'] === 'string' && !preg_match('/^#[0-9a-fA-F]{6}$/', (string)$s['value']));
                ?>
                <div class="set-row<?= $wide ? ' wide' : '' ?>">
                    <div class="set-txt">
                        <div class="nm"><?= h($n) ?></div>
                        <?php if ($s['comment'] !== ''): ?><div class="ds"><?= h($s['comment']) ?></div><?php endif; ?>
                    </div>
                    <div class="set-ctl">
                    <?php if ($s['type'] === 'bool'):
                        $on = ($val === '1' || $val === true); ?>
                        <label class="sw"><input type="hidden" name="c[<?= h($n) ?>]" value="0"><input type="checkbox" name="c[<?= h($n) ?>]" value="1"<?= $on ? ' checked' : '' ?>><span></span></label>
                    <?php elseif ($s['type'] === 'int'): ?>
                        <input class="inp num" type="number" min="0" name="c[<?= h($n) ?>]" value="<?= h($val) ?>">
                    <?php elseif ($s['type'] === 'list'):
                        $txt = is_array($val) ? implode(', ', $val) : $val; ?>
                        <input class="inp" type="text" name="c[<?= h($n) ?>]" value="<?= h($txt) ?>" placeholder="INDICATIF1, INDICATIF2">
                    <?php elseif (preg_match('/^#[0-9a-fA-F]{6}$/', (string)$val)): ?>
                        <div class="col-pair">
                            <input type="color" value="<?= h($val) ?>" oninput="this.nextElementSibling.value=this.value">
                            <input class="inp" style="width:110px" type="text" name="c[<?= h($n) ?>]" value="<?= h($val) ?>" maxlength="7" oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value))this.previousElementSibling.value=this.value">
                        </div>
                    <?php elseif ($n === 'AUTH_PASS'): ?>
                        <input class="inp" type="password" name="c[<?= h($n) ?>]" value="<?= h($val) ?>" autocomplete="new-password">
                    <?php else: ?>
                        <input class="inp" type="text" name="c[<?= h($n) ?>]" value="<?= h($val) ?>">
                    <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php foreach (['links' => 'Menu « Liens »', 'social' => 'Menu « Réseaux »'] as $key => $title):
            $blk = $menus[$key] ?? null; if (!$blk) continue;
            $rows = $blk['rows'];
            if (!empty($menuOverlay[$key])) {                    // valeurs en cours de correction
                $ov = $menuOverlay[$key]; $j = 0;
                foreach ($rows as $k => $r) { if (!$r['fixed'] && isset($ov[$j])) { $rows[$k] = $ov[$j] + ['fixed' => false]; } if (!$r['fixed']) $j++; }
                for (; $j < count($ov); $j++) $rows[] = $ov[$j] + ['fixed' => false];
            }
            $rows[] = ['label' => '', 'url' => '', 'icon' => '', 'fixed' => false];
            $rows[] = ['label' => '', 'url' => '', 'icon' => '', 'fixed' => false]; ?>
        <div class="card-panel" style="margin-bottom:18px;">
            <div class="panel-header"><h2><?= h($title) ?></h2></div>
            <p class="help" style="margin-top:0;">Texte • adresse (https://…) • icône Font Awesome. Une entrée sans adresse n'est pas affichée ; vider texte et adresse supprime la ligne ; les lignes vides du bas servent à en ajouter.</p>
            <?php foreach ($rows as $i => $r): ?>
            <div class="menu-row<?= $r['fixed'] ? ' fixed' : '' ?>">
                <input class="inp" type="text" name="m[<?= $key ?>][<?= $i ?>][label]" value="<?= h($r['label']) ?>" placeholder="Texte"<?= $r['fixed'] ? ' disabled' : '' ?>>
                <input class="inp" type="text" name="m[<?= $key ?>][<?= $i ?>][url]" value="<?= h($r['fixed'] ? (defined('FACEBOOK_URL') ? FACEBOOK_URL : '') : $r['url']) ?>" placeholder="https://…"<?= $r['fixed'] ? ' disabled title="Modifiable dans FACEBOOK_URL (Page principale)"' : '' ?>>
                <input class="inp" type="text" name="m[<?= $key ?>][<?= $i ?>][icon]" value="<?= h($r['icon']) ?>" placeholder="fa-solid fa-link"<?= $r['fixed'] ? ' disabled' : '' ?>>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div class="savebar">
            <span class="help">Une sauvegarde de config.php est créée automatiquement à chaque enregistrement.</span>
            <button class="btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
        </div>
    </form>

    <div class="card-panel" style="margin-bottom:18px;">
        <div class="panel-header"><h2><i class="fa-solid fa-key"></i> Mot de passe administrateur</h2></div>
        <form method="post" autocomplete="off" style="max-width:420px;">
            <input type="hidden" name="do" value="password">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <label class="f-label">Mot de passe actuel</label>
            <input class="inp" type="password" name="cur" required autocomplete="current-password">
            <label class="f-label">Nouveau mot de passe (10 caractères minimum)</label>
            <input class="inp" type="password" name="new" required minlength="10" autocomplete="new-password">
            <label class="f-label">Confirmation</label>
            <input class="inp" type="password" name="conf" required minlength="10" autocomplete="new-password">
            <p style="margin-top:14px;"><button class="btn-small" type="submit">Changer le mot de passe</button></p>
        </form>
    </div>

    <div class="card-panel" style="margin-bottom:18px;">
        <div class="panel-header"><h2><i class="fa-solid fa-clock-rotate-left"></i> Sauvegardes de config.php</h2></div>
        <?php if (!$backups): ?>
            <p class="help">Aucune sauvegarde pour l'instant.</p>
        <?php else: ?>
        <form method="post" onsubmit="return confirm('Restaurer cette sauvegarde ? La configuration actuelle sera elle aussi sauvegardée.');">
            <input type="hidden" name="do" value="restore">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <div class="col-pair">
                <select class="inp" name="file" style="max-width:340px;">
                    <?php foreach ($backups as $b):
                        preg_match('/^config_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})(?:_\d+)?\.php$/', $b, $m); ?>
                        <option value="<?= h($b) ?>"><?= h("$m[3]/$m[2]/$m[1] à $m[4]:$m[5]:$m[6]") ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-small" type="submit">Restaurer</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <div class="card-panel" style="margin-bottom:18px;">
        <div class="panel-header"><h2><i class="fa-solid fa-circle-info"></i> Aide</h2></div>
        <div class="help">
            • Le serveur web doit pouvoir écrire dans <code>config.php</code> et dans le dossier <code>backup/</code> à la racine du site.
              Exemple (Debian/Ubuntu) : <code>chown www-data:www-data config.php</code> puis <code>mkdir backup &amp;&amp; chown www-data:www-data backup</code>.<br>
            • Les chemins de fichiers/services (log, état…) et les identifiants admin ne sont pas modifiables ici
              (pour les chemins : <code>ADMIN_EDIT_PATHS</code> à <code>true</code> dans config.php).<br>
            • Déconnexion automatique après <?= (int)ADMIN_SESSION_MIN ?> minutes d'inactivité.
        </div>
    </div>

<?php endif; ?>

    <footer>
        Dashboard SvxReflector Live • Conçu par <strong><?= htmlspecialchars(AUTHOR_NAME, ENT_QUOTES, 'UTF-8') ?></strong><?= dashboard_version_html() ?>
    </footer>
</body>
</html>
