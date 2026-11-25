<?php
/**
 * fichier talkgroupe.php
 * Page Talkgroupes : tous les TG activés, avec les indicatifs présents / ayant parlé sur chacun
 * Les stations restées sur un TG autre que le TG local sont mises en évidence.
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

$isLogged = !ENABLE_AUTH || (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true);
if (!$isLogged || !TG_PAGE_ENABLE) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talkgroupes • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/talkgroupe.css?v=<?= (int)@filemtime(__DIR__ . '/../css/talkgroupe.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-hashtag"></i>
            <div>
                <h1>TALKGROUPES</h1>
                <small style="color: var(--text-muted);" id="subLabel">TG activés et stations présentes</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <a class="btn-action" href="mumble.php"><i class="fa-solid fa-headset"></i> <span>Mumble</span></a>
            <?php if (FREEDMR_URL !== ''): ?>
            <a class="btn-action" href="<?= htmlspecialchars(FREEDMR_URL, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><i class="fa-solid fa-tower-broadcast"></i> <span><?= htmlspecialchars(FREEDMR_LABEL, ENT_QUOTES, 'UTF-8') ?></span></a>
            <?php endif; ?>
            <?= dashboard_menus_html() ?>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="../index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div class="error-box" id="errorBox"></div>
    <div class="tg-away-box" id="awayBox"></div>

    <div class="tg-summary">
        <div class="seg" id="filters">
            <button type="button" class="active" data-f="all"><i class="fa-solid fa-list"></i>Tous les TG</button>
            <button type="button" data-f="now"><i class="fa-solid fa-satellite-dish"></i>Avec stations</button>
            <button type="button" data-f="away"><i class="fa-solid fa-arrow-right-arrow-left"></i>Hors TG local</button>
        </div>
        <small id="countInfo" style="color: var(--text-muted);">—</small>
    </div>

    <div class="tg-grid" id="tgGrid"><div class="empty">Chargement…</div></div>

    <footer>
        <?= dashboard_footer_html() ?>
    </footer>

    <script>
        const THEME_KEY = 'svxreflector_theme';
        const rootEl = document.documentElement;
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        const SHOW_MONITORED = <?= json_encode((bool)TG_SHOW_MONITORED) ?>;
        const SHOW_SELECTED = <?= json_encode((bool)TG_SHOW_SELECTED) ?>;
        const REFRESH = <?= max(0, (int)TG_REFRESH_SEC) ?>;

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
        const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        function fmtWhen(ts) {
            if (!ts) return '';
            const d = new Date(ts * 1000);
            const same = d.toDateString() === new Date().toDateString();
            const o = same ? { hour: '2-digit', minute: '2-digit' } : { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' };
            try { return new Intl.DateTimeFormat('fr-FR', Object.assign({ timeZone: tz }, o)).format(d); } catch (e) { return ''; }
        }
        function fmtDur(n) {
            n = Math.round(n || 0);
            const m = Math.floor(n / 60), s = n % 60;
            return m > 0 ? m + 'm ' + String(s).padStart(2, '0') + 's' : s + 's';
        }

        let data = null, filter = 'all', timer = null;

        function chips(list, cls) { return '<div class="tg-chips">' + list.join('') + '</div>'; }

        function render() {
            if (!data) return;
            const local = data.local_tg;
            let tgs = data.tgs || [];
            if (filter === 'now')  tgs = tgs.filter(t => t.now.length > 0);
            if (filter === 'away') tgs = tgs.filter(t => !t.local && t.now.length > 0);
            document.getElementById('countInfo').textContent =
                tgs.length + ' TG • ' + data.nodes + ' station' + (data.nodes > 1 ? 's' : '') + ' en ligne' +
                (data.since_ts ? ' • historique depuis ' + fmtWhen(data.since_ts) : '');

            const away = document.getElementById('awayBox');
            if (data.away && data.away.length) {
                away.innerHTML = '<strong><i class="fa-solid fa-triangle-exclamation"></i> Hors TG ' + esc(local) + ' :</strong>' +
                    data.away.map(a => esc(a.cs) + ' (TG ' + esc(a.tg) + ')').join(' • ');
                away.style.display = 'block';
            } else { away.style.display = 'none'; }

            const grid = document.getElementById('tgGrid');
            if (!tgs.length) { grid.innerHTML = '<div class="empty">Aucun TG à afficher</div>'; return; }
            grid.innerHTML = tgs.map(t => {
                const cls = 'tg-card' + (t.local ? ' is-local' : '') + (t.active ? ' is-live' : '');
                let badges = '';
                if (t.active) badges += '<span class="tg-badge live"><i class="fa-solid fa-microphone"></i> PTT en cours</span> ';
                if (t.local) badges += '<span class="tg-badge local">TG local</span>';
                else if (t.now.length) badges += '<span class="tg-badge other">Hors TG local</span>';

                const now = t.now.length
                    ? chips(t.now.map(n => '<span class="tg-chip ' + (n.talker ? 'talking' : 'now') + '">' + esc(n.cs) + '</span>'))
                    : '<div class="tg-none">Personne actuellement</div>';
                const talked = t.talked.length
                    ? chips(t.talked.map(n => '<span class="tg-chip' + (n.online ? '' : ' gone') + '" title="' + esc(n.tx) + ' transmission(s), ' + esc(fmtDur(n.sec)) + ' de parole">' +
                        esc(n.cs) + '<small>' + esc(n.tx) + '× ' + esc(fmtWhen(n.last)) + '</small></span>'))
                    : '<div class="tg-none">Aucune transmission dans l\'historique</div>';

                let extra = '';
                if (SHOW_SELECTED && t.selected.length) {
                    extra += '<div><div class="tg-sec">Ont activé ce TG</div>' +
                        chips(t.selected.map(n => '<span class="tg-chip' + (n.online ? '' : ' gone') + '">' + esc(n.cs) + '<small>' + esc(fmtWhen(n.ts)) + '</small></span>')) + '</div>';
                }
                if (SHOW_MONITORED && t.monitor.length) {
                    extra += '<div><div class="tg-sec">Écoutent ce TG</div>' +
                        chips(t.monitor.map(c => '<span class="tg-chip">' + esc(c) + '</span>')) + '</div>';
                }
                return '<div class="' + cls + '">' +
                    '<div class="tg-head"><div><span class="tg-num">TG ' + esc(t.tg) + '</span>' +
                    (t.name ? '<span class="tg-name">' + esc(t.name) + '</span>' : '') + '</div><div>' + badges + '</div></div>' +
                    '<div><div class="tg-sec">Stations sur ce TG maintenant (' + t.now.length + ')</div>' + now + '</div>' +
                    '<div><div class="tg-sec">Ont parlé (' + t.tx + ' transmission' + (t.tx > 1 ? 's' : '') + ')</div>' + talked + '</div>' +
                    extra +
                    '<div class="tg-foot">' + (t.last ? 'Dernière activité : ' + esc(fmtWhen(t.last)) : 'Aucune activité dans l\'historique') + '</div>' +
                    '</div>';
            }).join('');
        }

        async function load() {
            const box = document.getElementById('errorBox');
            try {
                const r = await fetch('api_tg.php?_=' + Date.now(), { cache: 'no-store' });
                if (r.status === 401) { location.href = '../index.php'; return; }
                data = await r.json();
                const msgs = [];
                if (data.error) msgs.push(data.error);
                if (data.status_ok === false) msgs.push('Statut du réflecteur indisponible : TG déduits du log seulement.');
                if (data.log_ok === false) msgs.push('Fichier log non lisible : historique indisponible.');
                if (msgs.length) { box.textContent = msgs.join(' '); box.style.display = 'block'; } else { box.style.display = 'none'; }
                render();
            } catch (e) {
                box.textContent = 'Impossible de lire les talkgroupes.';
                box.style.display = 'block';
            }
            if (!timer && REFRESH > 0) timer = setInterval(load, REFRESH * 1000);
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
