<?php
/**
 * fichier info.php
 * Page Infos Utilisateurs – SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

$isLogged = !ENABLE_AUTH || (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true);
if (!$isLogged) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Infos Utilisateurs • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/info.css?v=<?= (int)@filemtime(__DIR__ . '/../css/info.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-address-card"></i>
            <div>
                <h1>INFOS UTILISATEURS</h1>
                <small style="color: var(--text-muted);">RNFA Network Monitor</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <a class="btn-action" href="mumble.php"><i class="fa-solid fa-headset"></i> <span>Mumble</span></a>
            <?php if (LOG_PAGE_ENABLE): ?>
            <a class="btn-action" href="log.php"><i class="fa-solid fa-list-check"></i> <span>Log</span></a>
            <?php endif; ?>
            <?= dashboard_menus_html() ?>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="../index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div class="tabs">
        <button type="button" class="tab active" id="tabStations"><i class="fa-solid fa-satellite-dish"></i> Stations</button>
        <button type="button" class="tab" id="tabJournal"><i class="fa-solid fa-plug"></i> Connexions / Déconnexions</button>
    </div>

    <div class="toolbar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="searchInput" placeholder="Rechercher un indicatif, un nom, une ville, un locator…" autocomplete="off">
        </div>
        <button type="button" class="btn-action" id="pttFilter"><i class="fa-solid fa-microphone"></i> <span>En PTT uniquement</span></button>
        <div class="count-info" id="countInfo">—</div>
    </div>

    <div class="error-box" id="errorBox"></div>

    <div class="cards-grid" id="cardsGrid">
        <div class="empty">Chargement…</div>
    </div>

    <div class="card-panel" id="journalPanel" style="display: none;">
        <div class="panel-header">
            <h2><i class="fa-solid fa-plug"></i> Journal des connexions / déconnexions</h2>
            <div style="display: flex; align-items: center; gap: 12px;">
                <small id="journalInfo" style="color: var(--text-muted);">—</small>
                <button type="button" class="btn-action" id="csvBtn"><i class="fa-solid fa-file-csv"></i> <span>Exporter CSV</span></button>
            </div>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Jour / Date / Heure (Pays)</th>
                        <th>Indicatif</th>
                        <th>Événement</th>
                        <th>Durée de session</th>
                    </tr>
                </thead>
                <tbody id="journalBody"></tbody>
            </table>
        </div>
    </div>

    <footer>
        <?= dashboard_footer_html() ?>
    </footer>

    <script>
        const THEME_KEY = 'svxreflector_theme';
        const rootEl = document.documentElement;
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');

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
        function clean(v) {
            v = (v == null ? '' : String(v)).trim();
            return (v === '' || v === 'None' || v === '0') ? '' : v;
        }
        function withUnit(v, unit) {
            v = clean(v);
            return /^\d+(\.\d+)?$/.test(v) ? v + ' ' + unit : v;
        }
        function websiteHtml(v) {
            v = clean(v);
            if (!v) return '';
            let href = '';
            if (/^https?:\/\//i.test(v)) href = v;
            else if (/^[a-z0-9.-]+\.[a-z]{2,}(\/\S*)?$/i.test(v)) href = 'https://' + v;
            return href
                ? `<a href="${esc(href)}" target="_blank" rel="noopener noreferrer">${esc(v)}</a>`
                : esc(v);
        }
        function row(label, valueHtml) {
            return valueHtml ? `<div class="row"><span class="lbl">${label}</span><span class="val">${valueHtml}</span></div>` : '';
        }

        let allNodes = [];
        let onlyTalking = false;
        let connEvents = [];   // journal connexions / déconnexions (api_log.php)
        let sinceMap = {};     // callsign => {since_ts,...} (api_log.php)
        let logNow = 0;        // heure serveur au moment de la lecture
        let currentTab = 'stations';

        const visitorLocale = navigator.language || 'fr-FR';
        const visitorTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

        function formatVisitorDateTime(utcIso, fallbackText) {
            if (!utcIso) return fallbackText || '—';
            const d = new Date(utcIso);
            if (isNaN(d.getTime())) return fallbackText || '—';
            try {
                return new Intl.DateTimeFormat(visitorLocale, {
                    timeZone: visitorTimeZone, weekday: 'short', year: 'numeric', month: '2-digit',
                    day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit'
                }).format(d);
            } catch (err) {
                return fallbackText || d.toLocaleString();
            }
        }

        function formatLong(sec) {
            const n = Math.max(0, parseInt(sec, 10) || 0);
            if (n < 60) return n + 's';
            if (n < 3600) return Math.floor(n / 60) + 'm ' + (n % 60) + 's';
            return Math.floor(n / 3600) + 'h ' + Math.floor((n % 3600) / 60) + 'm';
        }

        function sinceText(callsign) {
            const s = sinceMap[callsign];
            if (!s || !s.since_ts || !logNow) return '';
            return formatLong(logNow - parseInt(s.since_ts, 10));
        }

        function renderJournal() {
            const q = document.getElementById('searchInput').value.trim().toLowerCase();
            const body = document.getElementById('journalBody');
            const list = connEvents.filter(e => !q || String(e.callsign || '').toLowerCase().includes(q));
            document.getElementById('journalInfo').textContent = list.length + ' événement(s)';

            if (list.length === 0) {
                body.innerHTML = '<tr><td colspan="4" style="text-align:center; color: var(--text-muted); padding: 30px;">Aucun événement</td></tr>';
                return;
            }
            body.innerHTML = list.map(e => {
                const isConn = e.event === 'CONNECT';
                const badge = isConn
                    ? '<span class="tag-event tag-connect"><i class="fa-solid fa-plug-circle-check"></i> CONNEXION</span>'
                    : '<span class="tag-event tag-disconnect"><i class="fa-solid fa-plug-circle-xmark"></i> DÉCONNEXION</span>';
                const session = (!isConn && e.session_time > 0) ? formatLong(e.session_time) : '—';
                return `
                    <tr>
                        <td style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">${esc(formatVisitorDateTime(e.utc_iso, e.datetime))}</td>
                        <td><strong style="color: var(--accent-text); font-family: monospace;">${esc(e.callsign)}</strong></td>
                        <td>${badge}</td>
                        <td style="font-family: monospace;">${esc(session)}</td>
                    </tr>`;
            }).join('');
        }

        function setTab(tab) {
            currentTab = tab;
            const isStations = tab === 'stations';
            document.getElementById('tabStations').classList.toggle('active', isStations);
            document.getElementById('tabJournal').classList.toggle('active', !isStations);
            document.getElementById('cardsGrid').style.display = isStations ? '' : 'none';
            document.getElementById('journalPanel').style.display = isStations ? 'none' : 'flex';
            document.getElementById('pttFilter').style.display = isStations ? '' : 'none';
        }

        function renderCards() {
            const q = document.getElementById('searchInput').value.trim().toLowerCase();
            const grid = document.getElementById('cardsGrid');

            let list = allNodes.filter(n => {
                if (onlyTalking && !n.is_talker) return false;
                if (!q) return true;
                return [n.callsign, n.name, n.sysop, n.location, n.node_location, n.locator]
                    .some(v => String(v || '').toLowerCase().includes(q));
            });

            list.sort((a, b) => (Number(b.is_talker) - Number(a.is_talker)) || a.callsign.localeCompare(b.callsign));

            const talking = allNodes.filter(n => n.is_talker).length;
            document.getElementById('countInfo').innerHTML =
                `<strong>${allNodes.length}</strong> station(s) connectée(s) • <strong>${talking}</strong> en PTT`;

            if (list.length === 0) {
                grid.innerHTML = '<div class="empty">Aucune station à afficher</div>';
                return;
            }

            grid.innerHTML = list.map(n => {
                const tgs = (n.monitored_tgs || []).map(t => `<span class="tg-pill">TG ${esc(t)}</span>`).join('');
                const place = clean(n.node_location) || clean(n.location);
                const sw = [clean(n.sw), clean(n.sw_ver)].filter(Boolean).join(' ');
                const badge = n.is_talker
                    ? '<span class="badge badge-ptt"><i class="fa-solid fa-microphone"></i> PTT</span>'
                    : '<span class="badge badge-online"><i class="fa-solid fa-circle" style="font-size:0.5rem;"></i> EN LIGNE</span>';
                return `
                    <div class="node-card${n.is_talker ? ' is-talking' : ''}">
                        <div class="node-top">
                            <div>
                                <div class="node-callsign">${esc(n.callsign)}</div>
                                <div class="node-name">${esc(clean(n.name))}</div>
                            </div>
                            ${badge}
                        </div>
                        <div class="node-rows">
                            ${row('Localisation', esc(place))}
                            ${row('Région', clean(n.location) !== place ? esc(clean(n.location)) : '')}
                            ${row('Locator', esc(clean(n.locator)))}
                            ${row('Mode', esc(clean(n.mode)))}
                            ${row('RX', esc(withUnit(n.rx, 'MHz')))}
                            ${row('TX', esc(withUnit(n.tx, 'MHz')))}
                            ${row('CTCSS', esc(withUnit(n.ctcss, 'Hz')))}
                            ${row('TG par défaut', esc(clean(n.default_tg)))}
                            ${row('TG actuel', n.tg > 0 ? esc('TG ' + n.tg) : '')}
                            ${row('TG écoutés', tgs)}
                            ${row('Sysop', esc(clean(n.sysop)))}
                            ${row('En ligne depuis', esc(sinceText(n.callsign)))}
                            ${row('Logiciel', esc(sw))}
                            ${row('Site web', websiteHtml(n.website))}
                        </div>
                    </div>`;
            }).join('');
        }

        async function fetchJson(url) {
            const res = await fetch(url + '?_=' + Date.now(), { cache: 'no-store' });
            if (res.status === 401) { window.location.href = '../index.php'; return null; }
            return res.json();
        }

        async function loadAll() {
            const errBox = document.getElementById('errorBox');
            const errors = [];

            try {
                const data = await fetchJson('api_status.php');
                if (data) {
                    if (data.error) errors.push(data.error);
                    allNodes = Object.entries(data.nodes || {}).map(([callsign, n]) => Object.assign({ callsign }, n));
                }
            } catch (e) {
                errors.push('Impossible de contacter le serveur.');
            }

            try {
                const log = await fetchJson('api_log.php');
                if (log) {
                    if (log.error) errors.push(log.error);
                    if (log.state) {
                        connEvents = log.state.conn_events || [];
                        sinceMap = log.state.connected_stations || {};
                        logNow = log.now_ts || 0;
                    }
                }
            } catch (e) {
                errors.push('Journal indisponible.');
            }

            if (errors.length) {
                errBox.textContent = errors.join(' — ');
                errBox.style.display = 'block';
            } else {
                errBox.style.display = 'none';
            }
            renderCards();
            renderJournal();
        }

        document.getElementById('searchInput').addEventListener('input', () => { renderCards(); renderJournal(); });
        document.getElementById('tabStations').addEventListener('click', () => setTab('stations'));
        document.getElementById('tabJournal').addEventListener('click', () => setTab('journal'));

        document.getElementById('csvBtn').addEventListener('click', () => {
            const rows = [['Date', 'Heure', 'Indicatif', 'Evenement', 'Duree session (s)']];
            connEvents.forEach(e => rows.push([
                e.date, e.timestamp, e.callsign,
                e.event === 'CONNECT' ? 'Connexion' : 'Deconnexion',
                e.session_time || 0
            ]));
            const csv = '\ufeff' + rows.map(r => r.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(';')).join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'connexions_svxreflector.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 1000);
        });
        document.getElementById('pttFilter').addEventListener('click', function () {
            onlyTalking = !onlyTalking;
            this.classList.toggle('active', onlyTalking);
            renderCards();
        });

        loadAll();
        setInterval(loadAll, 5000);
    </script>
</body>
</html>
