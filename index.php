<?php
/**
 * fichier index.php
 * SvxReflector Live Dashboard (JSON Polling + Visitor Timezone + Talk On/Off + Dark/Light)
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/config.php';


if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

$auth_error = '';
if (ENABLE_AUTH && isset($_POST['login'])) {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';
    if ($u === AUTH_USER && $p === AUTH_PASS) {
        $_SESSION['authenticated'] = true;
        header('Location: index.php');
        exit;
    }
    $auth_error = 'Identifiant ou mot de passe incorrect.';
}

$isLogged = !ENABLE_AUTH || (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true);

if (!$isLogged) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion • SvxReflector Live</title>
        <link rel="stylesheet" href="css/login.css?v=<?= (int)@filemtime(__DIR__ . '/css/login.css') ?>">
    </head>
    <body>
        <div class="box">
            <h2>SVXREFLECTOR</h2>
            <?php if ($auth_error): ?>
                <div class="err"><?= htmlspecialchars($auth_error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST">
                <label for="username">Identifiant</label>
                <input id="username" type="text" name="username" required autocomplete="username" autofocus>
                <label for="password">Mot de passe</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
                <button type="submit" name="login">Se connecter</button>
            </form>
        </div>
        <footer>Dashboard SvxReflector Live • Conçu par <strong><?= htmlspecialchars(AUTHOR_NAME, ENT_QUOTES, 'UTF-8') ?></strong><?= dashboard_version_html() ?></footer>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SvxReflector Live Dashboard • FRS077 Romuald pour RNFA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/index.css?v=<?= (int)@filemtime(__DIR__ . '/css/index.css') ?>">
    <link rel="stylesheet" href="css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-broadcast-tower"></i>
            <div>
                <h1>SVXREFLECTOR LIVE</h1>
                <small style="color: var(--text-muted);" id="tzSubtitle">RNFA Network Monitor</small>
            </div>
        </div>
        <div class="header-right">
            <div class="datetime-pill" id="liveDatetime">En attente...</div>
            <?php if (SHOW_VISITORS): ?>
            <span class="btn-action" id="visitorsPill" title="Visiteurs connectés en ce moment" style="cursor: default;"><i class="fa-solid fa-eye"></i> <span id="visitorsCount">—</span></span>
            <?php endif; ?>
            <a class="btn-action" href="include/info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="include/stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="include/carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <a class="btn-action" href="include/mumble.php"><i class="fa-solid fa-headset"></i> <span>Mumble</span></a>
            <?php if (LOG_PAGE_ENABLE): ?>
            <a class="btn-action" href="include/log.php"><i class="fa-solid fa-list-check"></i> <span>Log</span></a>
            <?php endif; ?>
            <?= dashboard_menus_html() ?>
            <?php if (INDEX_SHOW_ADMIN_LINK && ADMIN_ENABLE): ?>
            <a class="btn-action" href="include/admin.php"><i class="fa-solid fa-user-shield"></i> <span>Admin</span></a>
            <?php endif; ?>
            <?php if (CONTACT_URL !== ''): ?>
            <a class="btn-action" href="<?= htmlspecialchars(CONTACT_URL, ENT_QUOTES, 'UTF-8') ?>"<?= strpos(CONTACT_URL, 'mailto:') === 0 ? '' : ' target="_blank" rel="noopener"' ?>><i class="fa-solid fa-envelope"></i> <span>Contact</span></a>
            <?php endif; ?>
            <button type="button" class="btn-action" id="soundToggle" title="Bip sonore quand une station passe en PTT">
                <i class="fa-solid fa-bell-slash"></i> <span>Alerte PTT : OFF</span>
            </button>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div class="live-banner" id="liveBanner">
        <div class="talker-info">
            <i class="fa-solid fa-microphone-lines" style="font-size: 2rem; color: var(--accent-text);"></i>
            <div>
                <div style="font-size: 0.78rem; color: var(--text-muted);" id="talkerLabel">EN ÉMISSION</div>
                <div class="callsign-badge" id="activeCallsign">—</div>
                <div id="activeName" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 2px;"></div>
            </div>
            <div class="tg-badge" id="activeTG">TG —</div>
            <div class="talk-badge talk-off" id="talkStatusBadge">
                <i class="fa-solid fa-circle" style="font-size: 0.65rem;"></i>
                <span id="talkStatusText">PTT OFF</span>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div style="text-align: right;" id="timerBox">
                <div style="font-size: 0.78rem; color: var(--text-muted);">TEMPS DE PAROLE</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-main); font-family: monospace;" id="talkTimer">0s</div>
            </div>
            <div class="vumeter" id="vumeter">
                <div class="vu-bar"></div><div class="vu-bar"></div>
                <div class="vu-bar"></div><div class="vu-bar"></div>
                <div class="vu-bar"></div><div class="vu-bar"></div>
            </div>
        </div>
        <div class="ptt-limit" id="pttLimit" style="display: none;">
            <div class="ptt-limit-head">
                <span>LIMITE DE PAROLE (<?= rtrim(rtrim(number_format(TALK_MAX_SEC / 60, 1, ',', ''), '0'), ',') ?> min)</span>
                <span id="pttRemaining">3:00</span>
            </div>
            <div class="ptt-track"><div class="ptt-fill" id="pttFill"></div></div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
            <div class="stat-data">
                <h3>Stations Connectées</h3>
                <div class="number" id="connectedCount">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-comments"></i></div>
            <div class="stat-data">
                <h3>Transmissions du jour</h3>
                <div class="number" id="txCount">0</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-stopwatch"></i></div>
            <div class="stat-data">
                <h3>Temps de parole du jour</h3>
                <div class="number" id="totalTalkTime">0s</div>
            </div>
        </div>
    </div>

    <?php if (ALERT_RELAY_DOWN): ?>
    <div id="relayAlert" style="display:none; margin-bottom:16px; display:none; flex-direction:column; gap:8px;"></div>
    <?php endif; ?>

    <div class="stations-bar" id="stationsBar">
        <span class="stations-title"><i class="fa-solid fa-satellite-dish"></i> Stations actives :</span>
        <span style="color: var(--text-muted); font-size: 0.85rem;">Aucune station détectée pour l'instant</span>
    </div>

    <?php if (INDEX_SHOW_DISCONNECTED): ?>
    <div class="stations-bar" id="disconnectedBar" style="display: none;">
        <span class="stations-title" style="color: var(--text-muted);"><i class="fa-solid fa-plug-circle-xmark"></i> Dernières déconnexions :</span>
    </div>
    <?php endif; ?>

    <div class="main-grid">
        <div class="card-panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-list-ul"></i> Transmissions en Direct</h2>
                <small id="lastEventInfo" style="color: var(--text-muted);">—</small>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date / Heure</th>
                            <th>Indicatif</th>
                            <?php if (INDEX_SHOW_NAME_COL): ?><th>Prénom</th><?php endif; ?>
                            <?php if (INDEX_SHOW_PLACE_COL): ?><th>Ville</th><?php endif; ?>
                            <th>PTT ON / OFF</th>
                            <th>TG</th>
                            <th>Durée PTT</th>
                        </tr>
                    </thead>
                    <tbody id="logTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="card-panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-microphone"></i> Dernière personne à avoir parlé</h2>
            </div>
            <div class="lt-body" id="lastTalkerBody">
                <div class="lt-empty">Aucune transmission pour l'instant</div>
            </div>
        </div>
    </div>

    <footer>
        Dashboard SvxReflector Live • Conçu par <strong><?= htmlspecialchars(AUTHOR_NAME, ENT_QUOTES, 'UTF-8') ?></strong><?= dashboard_version_html() ?>
    </footer>

    <script>
        const THEME_STORAGE_KEY = 'svxreflector_theme';
        const rootEl = document.documentElement;
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');

        const visitorLocale = navigator.language || 'fr-FR';
        const visitorTimeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        document.getElementById('tzSubtitle').textContent = 'RNFA Network Monitor';

        let fileOffset = -1;
        let currentState = null;
        let localTxTimer = null;
        let localTxSec = 0;
        let vuInterval = null;

        /**
         * Formate une date UTC ISO selon le pays/fuseau horaire du visiteur.
         */
        function formatVisitorDateTime(utcIso, fallbackText) {
            if (!utcIso) return fallbackText || '—';
            const d = new Date(utcIso);
            if (isNaN(d.getTime())) return fallbackText || '—';
            try {
                return new Intl.DateTimeFormat(visitorLocale, {
                    timeZone: visitorTimeZone,
                    weekday: 'short',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }).format(d);
            } catch (err) {
                return fallbackText || d.toLocaleString();
            }
        }

        function formatVisitorTimeOnly(utcIso, fallbackText) {
            if (!utcIso) return fallbackText || '';
            const d = new Date(utcIso);
            if (isNaN(d.getTime())) return fallbackText || '';
            try {
                return new Intl.DateTimeFormat(visitorLocale, {
                    timeZone: visitorTimeZone,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }).format(d);
            } catch (err) {
                return fallbackText || '';
            }
        }

        function applyTheme(theme) {
            const safeTheme = theme === 'light' ? 'light' : 'dark';
            rootEl.setAttribute('data-theme', safeTheme);
            try { localStorage.setItem(THEME_STORAGE_KEY, safeTheme); } catch (e) {}

            if (safeTheme === 'light') {
                themeIcon.className = 'fa-solid fa-moon';
                themeLabel.textContent = 'Mode sombre';
            } else {
                themeIcon.className = 'fa-solid fa-sun';
                themeLabel.textContent = 'Mode clair';
            }
        }

        let initialTheme = 'dark';
        try {
            const saved = localStorage.getItem(THEME_STORAGE_KEY);
            if (saved === 'light' || saved === 'dark') initialTheme = saved;
        } catch (e) {}
        applyTheme(initialTheme);

        themeToggleBtn.addEventListener('click', () => {
            applyTheme(rootEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        function setVuMeter(active) {
            document.querySelectorAll('.vu-bar').forEach(bar => {
                if (active) {
                    bar.style.height = (Math.floor(Math.random() * 26) + 7) + 'px';
                    bar.classList.add('active');
                } else {
                    bar.style.height = '6px';
                    bar.classList.remove('active');
                }
            });
        }

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }

        function formatDuration(sec) {
            const n = Math.max(0, parseInt(sec, 10) || 0);
            if (n < 60) return n + 's';
            const h = Math.floor(n / 3600);
            const m = Math.floor((n % 3600) / 60);
            const s = n % 60;
            if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m ${String(s).padStart(2, '0')}s`;
            return `${m}m ${String(s).padStart(2, '0')}s`;
        }

        /** Colonne "PTT ON / OFF" : uniquement pour les événements de parole. */
        function renderTalkBadge(ev, talkStatus) {
            if (ev === 'TALK_START' || talkStatus === 'ON') {
                return '<span class="tag-event tag-talk-on"><i class="fa-solid fa-microphone"></i> PTT ON</span>';
            }
            if (ev === 'TALK_STOP') {
                return '<span class="tag-event tag-talk-off"><i class="fa-solid fa-microphone-slash"></i> PTT OFF</span>';
            }
            return '<span style="color: var(--text-muted);">—</span>';
        }

        let mumbleUsers = [];   // personnes connectées sur Mumble (api_mumble.php)

        const INDEX_SHOW_DISCONNECTED = <?= json_encode(INDEX_SHOW_DISCONNECTED) ?>;
        const INDEX_DISCONNECTED_MAX = <?= (int)INDEX_DISCONNECTED_MAX ?>;
        const INDEX_DISCONNECTED_HOURS = <?= (int)INDEX_DISCONNECTED_HOURS ?>;

        /** Stations déconnectées récemment (et pas revenues depuis) : indicatif + heure du départ. */
        function renderDisconnected(activeNames) {
            const bar = document.getElementById('disconnectedBar');
            if (!bar || !currentState) return;
            const title = bar.querySelector('.stations-title');
            const seen = new Set();
            const items = [];
            const limitMs = INDEX_DISCONNECTED_HOURS > 0 ? Date.now() - INDEX_DISCONNECTED_HOURS * 3600 * 1000 : 0;
            for (const e of (currentState.conn_events || [])) {            // du plus récent au plus ancien
                if (e.event !== 'DISCONNECT' || !e.callsign) continue;
                if (seen.has(e.callsign)) continue;                       // dernière déconnexion de la station seulement
                seen.add(e.callsign);
                if (activeNames.has(e.callsign)) continue;                // déjà reconnectée
                const d = e.utc_iso ? new Date(e.utc_iso) : null;
                if (d && !isNaN(d.getTime()) && limitMs && d.getTime() < limitMs) continue;
                items.push({ cs: e.callsign, d: (d && !isNaN(d.getTime())) ? d : null, e });
                if (items.length >= INDEX_DISCONNECTED_MAX) break;
            }
            bar.innerHTML = '';
            bar.appendChild(title);
            if (items.length === 0) { bar.style.display = 'none'; return; }
            bar.style.display = '';
            const today = new Date().toDateString();
            for (const it of items) {
                let when = '';
                if (it.d) {
                    try {
                        const opt = it.d.toDateString() === today
                            ? { timeZone: visitorTimeZone, hour: '2-digit', minute: '2-digit' }
                            : { timeZone: visitorTimeZone, day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' };
                        when = new Intl.DateTimeFormat(visitorLocale, opt).format(it.d);
                    } catch (err) { when = ''; }
                }
                const chip = document.createElement('span');
                chip.className = 'station-chip is-off';
                chip.title = 'Déconnectée' + (when ? ' à ' + when : '') + (it.e.session_time ? ' • session de ' + formatDuration(it.e.session_time) : '');
                chip.innerHTML = esc(it.cs) + (when ? '<small>' + esc(when) + '</small>' : '');
                bar.appendChild(chip);
            }
        }

        function renderStations(stationsObj, serverNowTs) {
            const bar = document.getElementById('stationsBar');
            const title = bar.querySelector('.stations-title');
            bar.innerHTML = '';
            bar.appendChild(title);

            // Stations connectées = log + stations présentes dans /status (au cas où le log ne contient plus leur connexion)
            const merged = Object.assign({}, (typeof nodesInfo !== 'undefined' && nodesInfo) ? nodesInfo : {}, stationsObj || {});
            const entries = Object.entries(merged);

            // Personnes connectées sur Mumble (hors passerelle), ajoutées à la suite
            const mumble = (mumbleUsers || []).filter(u => !u.gw && !Object.prototype.hasOwnProperty.call(merged, u.name));

            if (INDEX_SHOW_DISCONNECTED) {
                renderDisconnected(new Set([...Object.keys(merged), ...(mumbleUsers || []).map(u => u.name)]));
            }

            document.getElementById('connectedCount').textContent = String(entries.length + mumble.length);

            if (entries.length === 0 && mumble.length === 0) {
                const empty = document.createElement('span');
                empty.style.color = 'var(--text-muted)';
                empty.style.fontSize = '0.85rem';
                empty.textContent = 'Aucune station détectée pour l\'instant';
                bar.appendChild(empty);
                return;
            }

            const nowSec = Math.floor(Date.now() / 1000);
            const skew = serverNowTs ? (nowSec - serverNowTs) : 0;

            const talkers = (currentState && currentState.talk_starts) ? currentState.talk_starts : {};
            for (const [cs] of entries) {
                const isTalking = Object.prototype.hasOwnProperty.call(talkers, cs);
                const chip = document.createElement('span');
                chip.className = 'station-chip' + (isTalking ? ' is-talking' : '');
                chip.textContent = cs;
                bar.appendChild(chip);
            }
            for (const u of mumble) {
                const chip = document.createElement('span');
                chip.className = 'station-chip';
                chip.title = 'Connecté sur Mumble';
                chip.innerHTML = '<i class="fa-solid fa-headset" style="font-size:0.72rem; opacity:0.75; margin-right:5px;"></i>' + esc(u.name);
                bar.appendChild(chip);
            }
        }

        const INDEX_SHOW_NAME_COL = <?= json_encode(INDEX_SHOW_NAME_COL) ?>;
        const INDEX_SHOW_PLACE_COL = <?= json_encode(INDEX_SHOW_PLACE_COL) ?>;

        function renderRecentTable(eventsList) {
            const tbody = document.getElementById('logTableBody');
            tbody.innerHTML = '';
            const talkers = (currentState && currentState.talk_starts) ? currentState.talk_starts : {};

            // Une seule ligne par transmission : le début (PTT ON) et la fin (PTT OFF) sont fusionnés
            const rows = [];
            const open = {};
            const chrono = (eventsList || []).slice(0, 60).slice().reverse(); // du plus ancien au plus récent
            chrono.forEach(d => {
                if (d.event === 'TALK_START') {
                    const row = { start: d, on: Object.prototype.hasOwnProperty.call(talkers, d.callsign), dur: null, tg: d.talkgroup };
                    open[d.callsign] = row;
                    rows.push(row);
                } else if (d.event === 'TALK_STOP') {
                    const row = open[d.callsign];
                    if (row) {
                        row.on = false;
                        row.dur = d.talk_time;
                        delete open[d.callsign];
                    } else {
                        rows.push({ start: d, on: false, dur: d.talk_time, tg: d.talkgroup });
                    }
                }
            });

            rows.reverse().forEach(r => {
                const d = r.start;
                const dtLocal = formatVisitorDateTime(d.utc_iso, d.datetime);
                const badge = r.on
                    ? '<span class="tag-event tag-talk-on"><i class="fa-solid fa-microphone"></i> PTT ON</span>'
                    : '<span class="tag-event tag-talk-off"><i class="fa-solid fa-microphone-slash"></i> PTT OFF</span>';
                const ni = (typeof nodesInfo !== 'undefined' && nodesInfo[d.callsign]) || {};
                const prenom = ni.name || ni.sysop || '';
                const place = ni.node_location || ni.location || '';
                const dur = r.on ? 'en cours' : (r.dur === null ? '—' : formatDuration(r.dur));
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-family: monospace; font-size: 0.8rem; color: var(--text-muted);">${dtLocal}</td>
                    <td class="nw"><strong style="color: var(--accent-text); font-family: monospace;">${esc(d.callsign)}</strong></td>
                    ${INDEX_SHOW_NAME_COL ? `<td style="color: var(--text-main);">${esc(prenom)}</td>` : ''}
                    ${INDEX_SHOW_PLACE_COL ? `<td style="color: var(--text-muted); font-size: 0.85rem;">${esc(place)}</td>` : ''}
                    <td class="nw">${badge}</td>
                    <td class="nw" style="font-family: monospace;">${r.tg ? 'TG ' + r.tg : '—'}</td>
                    <td class="nw" style="font-family: monospace;">${dur}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        let nodesInfo = {};   // infos stations venant de api_status.php
        let lastEvents = [];  // derniers événements du log

        /** Derniers indicatifs distincts ayant émis (le plus récent d'abord). */
        function pickLastTalkers(eventsList, max) {
            const seen = new Set();
            const out = [];
            for (const e of (eventsList || [])) {
                if (e.event !== 'TALK_START' && e.event !== 'TALK_STOP') continue;
                if (seen.has(e.callsign)) continue;
                seen.add(e.callsign);
                out.push(e);
                if (out.length >= max) break;
            }
            return out;
        }

        /** Découpe une date en jour (en toutes lettres), date et heure, dans le fuseau du visiteur. */
        function formatDateParts(utcIso, fallbackText) {
            const d = utcIso ? new Date(utcIso) : null;
            if (!d || isNaN(d.getTime())) return { day: '—', date: fallbackText || '—', time: '' };
            try {
                const day = new Intl.DateTimeFormat(visitorLocale, { timeZone: visitorTimeZone, weekday: 'long' }).format(d);
                const date = new Intl.DateTimeFormat(visitorLocale, { timeZone: visitorTimeZone, day: '2-digit', month: '2-digit', year: 'numeric' }).format(d);
                const time = new Intl.DateTimeFormat(visitorLocale, { timeZone: visitorTimeZone, hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(d);
                return { day: day.charAt(0).toUpperCase() + day.slice(1), date, time };
            } catch (e) {
                return { day: '—', date: fallbackText || '—', time: '' };
            }
        }

        function renderLastTalker(eventsList) {
            const body = document.getElementById('lastTalkerBody');
            const list = pickLastTalkers(eventsList, 6);
            if (list.length === 0) {
                body.innerHTML = '<div class="lt-empty">Aucune transmission pour l\'instant</div>';
                return;
            }

            const main = list[0];
            const info = nodesInfo[main.callsign] || {};
            const isOn = main.event === 'TALK_START';
            const who = [info.name, info.node_location || info.location].filter(Boolean).join(' • ');
            const dp = formatDateParts(main.utc_iso, main.datetime);
            const badge = isOn
                ? '<span class="tag-event tag-talk-on"><i class="fa-solid fa-microphone"></i> PTT ON</span>'
                : '<span class="tag-event tag-talk-off"><i class="fa-solid fa-microphone-slash"></i> PTT OFF</span>';

            let html = `
                <div class="lt-main${isOn ? ' is-on' : ''}">
                    <div class="lt-top">
                        <div class="lt-callsign">${esc(main.callsign)}</div>
                        ${badge}
                    </div>
                    ${who ? `<div class="lt-who">${esc(who)}</div>` : ''}
                    <div class="lt-grid">
                        <div class="lt-item"><small>Jour</small><span>${esc(dp.day)}</span></div>
                        <div class="lt-item"><small>Date</small><span>${esc(dp.date)}</span></div>
                        <div class="lt-item"><small>Heure</small><span class="lt-time">${esc(dp.time)}</span></div>
                    </div>
                    <div class="lt-grid lt-grid-2">
                        <div class="lt-item"><small>Talkgroup</small><span>${main.talkgroup ? 'TG ' + esc(main.talkgroup) : '—'}</span></div>
                        <div class="lt-item"><small>Durée PTT</small><span>${isOn ? 'En cours…' : esc(formatDuration(main.talk_time))}</span></div>
                    </div>
                </div>`;

            if (list.length > 1) {
                html += '<div class="lt-history-title">Précédemment</div><div class="lt-history">';
                for (const e of list.slice(1)) {
                    const i = nodesInfo[e.callsign] || {};
                    html += `
                        <div class="lt-hrow">
                            <div><strong style="font-family: monospace; color: var(--accent-text);">${esc(e.callsign)}</strong>
                                <div style="color: var(--text-muted); font-size: 0.75rem;">${esc(i.name || '')}</div></div>
                            <div style="font-family: monospace;">${e.talkgroup ? 'TG ' + esc(e.talkgroup) : '—'}</div>
                            <div style="font-family: monospace; color: var(--text-muted);">${esc(formatVisitorTimeOnly(e.utc_iso, e.timestamp))}</div>
                            <div style="font-family: monospace;">${e.event === 'TALK_START' ? '…' : esc(formatDuration(e.talk_time))}</div>
                        </div>`;
                }
                html += '</div>';
            }
            body.innerHTML = html;
        }

        async function pollStatus() {
            try {
                const res = await fetch('include/api_status.php?_=' + Date.now(), { cache: 'no-store' });
                if (res.status === 401) { window.location.href = 'index.php'; return; }
                const data = await res.json();
                if (data && data.nodes) {
                    nodesInfo = data.nodes;
                    renderLastTalker(lastEvents);
                    if (!data.error) checkRelays(Object.keys(data.nodes));
                }
            } catch (e) {}
        }

        // ---------- Alerte sonore quand une station passe en PTT ----------
        const SOUND_KEY = 'svxreflector_sound';
        let soundOn = false;
        let audioCtx = null;
        let wasTalking = null; // null = premier affichage (pas de bip au chargement)
        try { soundOn = localStorage.getItem(SOUND_KEY) === '1'; } catch (e) {}
        const soundBtn = document.getElementById('soundToggle');

        function refreshSoundBtn() {
            soundBtn.querySelector('i').className = soundOn ? 'fa-solid fa-bell' : 'fa-solid fa-bell-slash';
            soundBtn.querySelector('span').textContent = soundOn ? 'Alerte PTT : ON' : 'Alerte PTT : OFF';
        }
        function getAudio() {
            if (!audioCtx) {
                const C = window.AudioContext || window.webkitAudioContext;
                if (C) audioCtx = new C();
            }
            return audioCtx;
        }
        // Le navigateur bloque le son tant qu'il n'y a pas eu de clic : au premier clic / touche, on « débloque » l'audio
        function unlockAudio() {
            try {
                const ctx = getAudio();
                if (ctx && ctx.state === 'suspended') ctx.resume();
            } catch (e) {}
        }
        ['click', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, unlockAudio, { passive: true }));
        function beep() {
            if (!soundOn) return;
            try {
                const ctx = getAudio();
                if (!ctx) return;
                if (ctx.state === 'suspended') ctx.resume();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.value = 880;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.25);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.27);
            } catch (e) {}
        }
        soundBtn.addEventListener('click', () => {
            soundOn = !soundOn;
            try { localStorage.setItem(SOUND_KEY, soundOn ? '1' : '0'); } catch (e) {}
            refreshSoundBtn();
            if (soundOn) { getAudio(); beep(); }
        });
        refreshSoundBtn();

        // ---------- Alerte « relais tombé » ----------
        const ALERT_RELAY_DOWN = <?= json_encode(ALERT_RELAY_DOWN) ?>;
        const WATCH_STATIONS = <?= json_encode(array_values(WATCH_STATIONS)) ?>;
        const RELAY_DOWN_AFTER = <?= (int)RELAY_DOWN_AFTER_POLLS ?>;
        const relayState = {};   // indicatif -> { seen, miss, downSince, backAt }

        function alarmTones(list) {
            if (!soundOn) return;
            try {
                const ctx = getAudio();
                if (!ctx) return;
                if (ctx.state === 'suspended') ctx.resume();
                let t = ctx.currentTime;
                list.forEach(f => {
                    const o = ctx.createOscillator(), g = ctx.createGain();
                    o.type = 'sine'; o.frequency.value = f;
                    g.gain.setValueAtTime(0.0001, t);
                    g.gain.exponentialRampToValueAtTime(0.2, t + 0.02);
                    g.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
                    o.connect(g); g.connect(ctx.destination);
                    o.start(t); o.stop(t + 0.24);
                    t += 0.25;
                });
            } catch (e) {}
        }

        function checkRelays(present) {
            if (!ALERT_RELAY_DOWN) return;
            const set = new Set(present);
            const names = WATCH_STATIONS.length ? WATCH_STATIONS : Array.from(new Set([...Object.keys(relayState), ...present]));
            const nowSec = Math.floor(Date.now() / 1000);
            names.forEach(cs => {
                const st = relayState[cs] || (relayState[cs] = { seen: false, miss: 0, downSince: null, backAt: null });
                if (set.has(cs)) {
                    if (st.downSince) {            // revenu
                        st.backAt = nowSec;
                        st.backAfter = nowSec - st.downSince;
                        st.downSince = null;
                        alarmTones([523, 784]);
                    }
                    st.seen = true;
                    st.miss = 0;
                } else {
                    st.miss++;
                    const explicit = WATCH_STATIONS.length > 0;      // station nommée : alerte même si jamais vue sur cette page
                    if (!st.downSince && st.miss >= RELAY_DOWN_AFTER && (st.seen || explicit)) {
                        st.downSince = nowSec - (RELAY_DOWN_AFTER - 1) * 5;
                        st.backAt = null;
                        alarmTones([440, 330, 440, 330]);
                    }
                }
            });
            renderRelayAlert();
        }

        function renderRelayAlert() {
            const box = document.getElementById('relayAlert');
            if (!box) return;
            const nowSec = Math.floor(Date.now() / 1000);
            const rows = [];
            Object.entries(relayState).forEach(([cs, st]) => {
                if (st.downSince) {
                    const hh = new Date(st.downSince * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    rows.push('<div style="background:var(--bad-bg, rgba(239,68,68,.16)); border:1px solid var(--accent-red); color:var(--bad-text, #f87171); border-radius:10px; padding:10px 16px; font-size:0.92rem;"><i class="fa-solid fa-triangle-exclamation"></i> <strong style="font-family:monospace;">' + esc(cs) + '</strong> hors ligne depuis ' + hh + ' (' + formatDuration(nowSec - st.downSince) + ')</div>');
                } else if (st.backAt && nowSec - st.backAt < 60) {
                    rows.push('<div style="background:var(--talk-on-bg, rgba(16,185,129,.18)); border:1px solid var(--accent-green); color:var(--talk-on-text, #34d399); border-radius:10px; padding:10px 16px; font-size:0.92rem;"><i class="fa-solid fa-circle-check"></i> <strong style="font-family:monospace;">' + esc(cs) + '</strong> de retour (coupure de ' + formatDuration(st.backAfter || 0) + ')</div>');
                }
            });
            box.innerHTML = rows.join('');
            box.style.display = rows.length ? 'flex' : 'none';
        }
        setInterval(renderRelayAlert, 1000);

        let talkStartLocal = 0; // début de la parole en cours (horloge du visiteur)

        const TALK_MAX_SEC = <?= (int)TALK_MAX_SEC ?>; // limite de parole (config.php)

        function formatClock(sec) {
            const n = Math.max(0, Math.floor(sec));
            return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0');
        }

        /** Chrono de parole + barre de progression (compte à rebours 3 min). */
        function tickTalkTimer() {
            const nowLocal = Math.floor(Date.now() / 1000);
            const elapsed = Math.max(0, nowLocal - talkStartLocal);
            document.getElementById('talkTimer').textContent = formatDuration(elapsed);

            const box = document.getElementById('pttLimit');
            const fill = document.getElementById('pttFill');
            const remainingEl = document.getElementById('pttRemaining');
            const remaining = TALK_MAX_SEC - elapsed;

            box.classList.remove('warn', 'danger', 'over');
            if (remaining > 0) {
                fill.style.width = (remaining / TALK_MAX_SEC * 100) + '%';
                remainingEl.textContent = formatClock(remaining);
                if (remaining <= 10) box.classList.add('danger');
                else if (remaining <= 30) box.classList.add('warn');
            } else {
                fill.style.width = '100%';
                remainingEl.textContent = 'Temps dépassé (+' + formatClock(-remaining) + ')';
                box.classList.add('over');
            }
        }

        /** Bandeau du haut : uniquement la station qui parle en ce moment. */
        function updateTopTalkBanner(state, serverNowTs) {
            const banner = document.getElementById('liveBanner');
            const callsignEl = document.getElementById('activeCallsign');
            const nameEl = document.getElementById('activeName');
            const labelEl = document.getElementById('talkerLabel');
            const tgEl = document.getElementById('activeTG');
            const badge = document.getElementById('talkStatusBadge');
            const timerBox = document.getElementById('timerBox');

            const nowLocal = Math.floor(Date.now() / 1000);
            const skew = serverNowTs ? (nowLocal - serverNowTs) : 0;
            const starts = (state.talk_starts && !Array.isArray(state.talk_starts)) ? state.talk_starts : {};
            // ignore un début de parole sans fin jamais reçue (plus de 15 min)
            const talkers = Object.keys(starts).filter(k => !serverNowTs || (serverNowTs - starts[k]) < 900);

            if (talkers.length > 0) {
                const cs = talkers.includes(state.active_callsign) ? state.active_callsign : talkers[0];
                let tg = (cs === state.active_callsign) ? state.active_tg : 0;
                if (!tg) {
                    const ev = (state.recent_events || []).find(e => e.callsign === cs && e.event === 'TALK_START');
                    tg = ev ? ev.talkgroup : 0;
                }
                const info = nodesInfo[cs] || {};

                banner.classList.add('is-talking');
                if (wasTalking === false) beep();
                wasTalking = true;
                labelEl.textContent = 'EN ÉMISSION';
                callsignEl.textContent = cs;
                callsignEl.style.color = '';
                nameEl.textContent = [info.name, info.node_location || info.location].filter(Boolean).join(' • ');
                nameEl.style.display = nameEl.textContent ? '' : 'none';
                tgEl.textContent = tg ? 'TG ' + tg : 'TG —';
                tgEl.style.display = '';
                badge.className = 'talk-badge talk-on';
                document.getElementById('talkStatusText').textContent = 'PTT ON';
                badge.style.display = '';
                timerBox.style.display = '';
                document.getElementById('pttLimit').style.display = '';

                talkStartLocal = starts[cs] + skew;
                tickTalkTimer();
                if (!localTxTimer) {
                    localTxTimer = setInterval(tickTalkTimer, 1000);
                }
                if (!vuInterval) {
                    vuInterval = setInterval(() => setVuMeter(true), 150);
                }
            } else {
                banner.classList.remove('is-talking');
                wasTalking = false;
                labelEl.textContent = 'ÉMISSION';
                callsignEl.textContent = 'Aucune émission';
                callsignEl.style.color = 'var(--text-muted)';
                nameEl.style.display = 'none';
                tgEl.style.display = 'none';
                badge.style.display = 'none';
                timerBox.style.display = 'none';
                document.getElementById('pttLimit').style.display = 'none';

                clearInterval(localTxTimer);
                localTxTimer = null;
                clearInterval(vuInterval);
                vuInterval = null;
                setVuMeter(false);
            }
        }

        function syncDashboardFromState(state, serverNowTs) {
            if (!state) return;
            currentState = state;

            const formattedDt = formatVisitorDateTime(state.last_utc_iso, state.last_datetime);
            document.getElementById('lastEventInfo').textContent = 'Dernier événement : ' + formattedDt;
            document.getElementById('txCount').textContent = String(state.tx_count || 0);
            document.getElementById('totalTalkTime').textContent = formatDuration(state.total_talk_time || 0);

            updateTopTalkBanner(state, serverNowTs);
            renderStations(state.connected_stations, serverNowTs);
            renderRecentTable(state.recent_events);
            lastEvents = state.recent_events || [];
            renderLastTalker(lastEvents);
        }

        async function pollLogJson() {
            try {
                const url = `include/api_log.php?offset=${encodeURIComponent(fileOffset)}&_=${Date.now()}`;
                const res = await fetch(url, { cache: 'no-store' });
                if (res.status === 401) {
                    window.location.href = 'index.php';
                    return;
                }
                const payload = await res.json();
                if (payload.error) {
                    document.getElementById('lastEventInfo').textContent = payload.error;
                    return;
                }
                if (typeof payload.offset === 'number') {
                    fileOffset = payload.offset;
                }
                syncDashboardFromState(payload.state, payload.now_ts);
            } catch (err) {
                // erreur réseau ponctuelle : on réessaie au prochain cycle
            }
        }

        pollLogJson();

        async function pollMumbleUsers() {
            try {
                const r = await fetch('include/api_mumble.php', { cache: 'no-store' });
                if (!r.ok) return;
                const d = await r.json();
                mumbleUsers = Array.isArray(d.online) ? d.online : [];
                if (currentState) renderStations(currentState.connected_stations, Math.floor(Date.now() / 1000));
            } catch (e) { /* Mumble indisponible : on garde l'affichage actuel */ }
        }
        pollMumbleUsers();
        setInterval(pollMumbleUsers, 5000);

        // ---------- Visiteurs connectés en temps réel ----------
        (function () {
            const pill = document.getElementById('visitorsCount');
            if (!pill) return;
            let vid = '';
            try { vid = localStorage.getItem('svxreflector_vid') || ''; } catch (e) {}
            if (!vid) {
                vid = Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
                try { localStorage.setItem('svxreflector_vid', vid); } catch (e) {}
            }
            async function beat() {
                try {
                    const r = await fetch('include/api_visitors.php?id=' + encodeURIComponent(vid) + '&_=' + Date.now(), { cache: 'no-store' });
                    if (!r.ok) return;
                    const d = await r.json();
                    if (typeof d.visitors === 'number') {
                        pill.textContent = d.visitors + (d.visitors > 1 ? ' visiteurs' : ' visiteur');
                    }
                } catch (e) {}
            }
            beat();
            setInterval(beat, 10000);
            window.addEventListener('pagehide', () => {
                try { navigator.sendBeacon('include/api_visitors.php?leave=1&id=' + encodeURIComponent(vid)); } catch (e) {}
            });
        })();

        setInterval(pollLogJson, 1000);
        pollStatus();
        setInterval(pollStatus, 5000);
        setInterval(() => {
            if (currentState && currentState.connected_stations) {
                renderStations(currentState.connected_stations, Math.floor(Date.now() / 1000));
            }
        }, 1000);
    </script>

    <!-- Horloge en temps réel : script indépendant (fonctionne même si un autre script a une erreur) -->
    <script>
        (function () {
            function tick() {
                var el = document.getElementById('liveDatetime');
                if (!el) return;
                var d = new Date(), t;
                try {
                    t = new Intl.DateTimeFormat(navigator.language || 'fr-FR', {
                        weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit',
                        hour: '2-digit', minute: '2-digit', second: '2-digit'
                    }).format(d);
                } catch (e) { t = d.toLocaleString(); }
                el.textContent = t;
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
