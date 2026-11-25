<?php
/**
 * fichier log.php
 * Page Log (événements utiles du réflecteur, IP masquées) – SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

$isLogged = !ENABLE_AUTH || (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true);
if (!$isLogged || !LOG_PAGE_ENABLE) {
    header('Location: ../index.php');
    exit;
}

// Filtres proposés = uniquement les types activés dans config.php
$filters = [];
if (LOG_SHOW_LOGIN)      { $filters['login']   = ['Connexions',  'fa-right-to-bracket']; }
if (LOG_SHOW_DISCONNECT) { $filters['logout']  = ['Déconnexions', 'fa-right-from-bracket']; }
if (LOG_SHOW_ERRORS)     { $filters['error']   = ['Incidents',   'fa-triangle-exclamation']; }
if (LOG_SHOW_RESTART)    { $filters['restart'] = ['Service',     'fa-power-off']; }
if (LOG_SHOW_UDP_LOSS)   { $filters['udp']     = ['Pertes UDP',  'fa-wave-square']; }
if (LOG_SHOW_TG_SELECT)  { $filters['tg']      = ['TG',          'fa-hashtag']; }
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/log.css?v=<?= (int)@filemtime(__DIR__ . '/../css/log.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-list-check"></i>
            <div>
                <h1>LOG</h1>
                <small style="color: var(--text-muted);" id="subLabel">Événements du réflecteur</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <a class="btn-action" href="mumble.php"><i class="fa-solid fa-headset"></i> <span>Mumble</span></a>
            <?= dashboard_menus_html() ?>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="../index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div class="error-box" id="errorBox"></div>

    <div class="card-panel">
        <div class="panel-header">
            <div class="seg" id="filters">
                <button type="button" class="active" data-f="all"><i class="fa-solid fa-list"></i>Tout</button>
                <?php foreach ($filters as $key => $f): ?>
                <button type="button" data-f="<?= $key ?>"><i class="fa-solid <?= $f[1] ?>"></i><?= $f[0] ?></button>
                <?php endforeach; ?>
            </div>
            <small id="countInfo">—</small>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Heure</th>
                        <th>Événement</th>
                        <th>Station</th>
                        <?php if (LOG_SHOW_IP): ?><th>IP</th><?php endif; ?>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody id="logBody"><tr><td colspan="6" class="empty">Chargement…</td></tr></tbody>
            </table>
        </div>
        <?php if (LOG_SHOW_IP && LOG_MASK_IP): ?>
        <div class="legend"><i class="fa-solid fa-user-shield"></i> Adresses IP partiellement masquées.</div>
        <?php endif; ?>
    </div>

    <footer>
        <?= dashboard_footer_html() ?>
    </footer>

    <script>
        const THEME_KEY = 'svxreflector_theme';
        const rootEl = document.documentElement;
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        const SHOW_IP = <?= json_encode((bool)LOG_SHOW_IP) ?>;
        const COLS = SHOW_IP ? 6 : 5;

        function applyTheme(theme) {
            const t = theme === 'light' ? 'light' : 'dark';
            rootEl.setAttribute('data-theme', t);
            try { localStorage.setItem(THEME_KEY, t); } catch (e) {}
            themeIcon.className = t === 'light' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            themeLabel.textContent = t === 'light' ? 'Mode sombre' : 'Mode clair';
        }
        applyTheme(rootEl.getAttribute('data-theme'));
        document.getElementById('themeToggle').addEventListener('click', () => {
            applyTheme(rootEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        const LABELS = {
            login: 'CONNEXION', logout: 'DÉCONNEXION', error: 'INCIDENT',
            restart: 'SERVICE', udp: 'PERTE UDP', tg: 'TG'
        };
        let events = [];
        let filter = 'all';
        let timer = null;

        function fmtDate(d) {                      // 2026-11-20 -> 20/11/2026
            const p = String(d).split('-');
            return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
        }

        function render() {
            const list = events.filter(e => filter === 'all' || e.type === filter);
            document.getElementById('countInfo').textContent = list.length + ' événement' + (list.length > 1 ? 's' : '');
            const body = document.getElementById('logBody');
            if (!list.length) {
                body.innerHTML = '<tr><td colspan="' + COLS + '" class="empty">Aucun événement</td></tr>';
                return;
            }
            body.innerHTML = list.map(e =>
                '<tr>' +
                '<td class="mono">' + esc(fmtDate(e.date)) + '</td>' +
                '<td class="mono">' + esc(e.time) + '</td>' +
                '<td><span class="tag tag-' + esc(e.type) + '">' + esc(LABELS[e.type] || e.type) + '</span></td>' +
                '<td class="cs">' + esc(e.station) + '</td>' +
                (SHOW_IP ? '<td class="mono">' + esc(e.ip || '') + '</td>' : '') +
                '<td>' + esc(e.detail) + '</td>' +
                '</tr>'
            ).join('');
        }

        async function load() {
            const box = document.getElementById('errorBox');
            try {
                const r = await fetch('api_events.php', { cache: 'no-store' });
                if (r.status === 401) { location.href = '../index.php'; return; }
                const d = await r.json();
                if (d.error) { box.textContent = d.error; box.style.display = 'block'; }
                else { box.style.display = 'none'; }
                events = d.events || [];
                render();
                if (!timer && d.refresh > 0) timer = setInterval(load, d.refresh * 1000);
            } catch (e) {
                box.textContent = 'Impossible de lire les événements.';
                box.style.display = 'block';
            }
        }

        document.getElementById('filters').addEventListener('click', ev => {
            const b = ev.target.closest('button[data-f]');
            if (!b) return;
            filter = b.dataset.f;
            document.querySelectorAll('#filters button').forEach(x => x.classList.toggle('active', x === b));
            render();
        });

        load();
    </script>
</body>
</html>
