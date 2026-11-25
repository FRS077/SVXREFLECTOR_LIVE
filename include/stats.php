<?php
/**
 * fichier stats.php
 * Page Statistiques du jour – SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

$periodsAvailable = [];
if (STATS_SHOW_PERIOD_DAY)   { $periodsAvailable['day']   = 'Jour'; }
if (STATS_SHOW_PERIOD_WEEK)  { $periodsAvailable['week']  = 'Semaine'; }
if (STATS_SHOW_PERIOD_MONTH) { $periodsAvailable['month'] = 'Mois'; }
if (STATS_SHOW_PERIOD_YEAR)  { $periodsAvailable['year']  = 'Année'; }
if (!$periodsAvailable) { $periodsAvailable['day'] = 'Jour'; }
$defaultPeriod = isset($periodsAvailable[STATS_DEFAULT_PERIOD]) ? STATS_DEFAULT_PERIOD : array_key_first($periodsAvailable);

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
    <title>Statistiques • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/stats.css?v=<?= (int)@filemtime(__DIR__ . '/../css/stats.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-chart-column"></i>
            <div>
                <h1>STATISTIQUES</h1>
                <small style="color: var(--text-muted);" id="dayLabel">RNFA Network Monitor</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
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

    <div class="error-box" id="errorBox"></div>

    <?php if (count($periodsAvailable) > 1): ?>
    <div class="period-bar">
        <div class="seg" id="periodTabs">
            <?php foreach ($periodsAvailable as $key => $lab): ?>
            <button type="button" data-period="<?= $key ?>" class="<?= $key === $defaultPeriod ? 'active' : '' ?>"><?= $lab ?></button>
            <?php endforeach; ?>
        </div>
        <div class="nav" id="periodNav" style="display:none;">
            <button type="button" class="btn-action" id="pPrev" aria-label="Période précédente"><i class="fa-solid fa-chevron-left"></i></button>
            <span class="plabel" id="pLabel">—</span>
            <button type="button" class="btn-action" id="pNext" aria-label="Période suivante"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (STATS_SHOW_KPIS): ?>
    <div class="kpi-grid">
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-comments"></i></div>
            <div><h3 id="kpiTxTitle">Transmissions du jour</h3><div class="number" id="kpiTx">0</div></div>
        </div>
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-stopwatch"></i></div>
            <div><h3 id="kpiTalkTitle">Temps de parole du jour</h3><div class="number" id="kpiTalk">0s</div></div>
        </div>
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-microphone-lines"></i></div>
            <div><h3>Stations ayant parlé</h3><div class="number" id="kpiSpeakers">0</div></div>
        </div>
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-trophy"></i></div>
            <div>
                <h3>Plus longue transmission</h3>
                <div class="number" id="kpiLongest">—</div>
                <div class="sub" id="kpiLongestSub"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (STATS_SHOW_RANKING || STATS_SHOW_HOURLY): ?>
    <div class="grid-2"<?= (STATS_SHOW_RANKING && STATS_SHOW_HOURLY) ? '' : ' style="grid-template-columns: 1fr;"' ?>>
        <?php if (STATS_SHOW_RANKING): ?>
        <div class="card-panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-ranking-star"></i> Classement des stations</h2>
                <div class="seg">
                    <button type="button" class="active" id="rankTalk">Temps de parole</button>
                    <button type="button" id="rankTx">Transmissions</button>
                </div>
            </div>
            <div id="rankBody"><div class="empty">Chargement…</div></div>
        </div>
        <?php endif; ?>

        <?php if (STATS_SHOW_HOURLY): ?>
        <div class="card-panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-clock"></i> Activité par heure</h2>
                <small id="peakInfo">—</small>
            </div>
            <div class="hours" id="hoursChart"></div>
            <div class="hours-note">Nombre de transmissions par heure (heure du serveur, fuseau Europe/Paris).</div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (STATS_SHOW_DAILY_CHART): ?>
    <div class="card-panel" id="dailyPanel" style="display:none;">
        <div class="panel-header">
            <h2><i class="fa-solid fa-calendar-days"></i> <span id="dailyTitle">Activité par jour</span></h2>
            <small id="dailyInfo">—</small>
        </div>
        <div class="daily-bars" id="dailyChart"></div>
        <div class="hours-note">Nombre de transmissions (survolez une barre pour le détail).</div>
    </div>
    <?php endif; ?>

    <?php if (STATS_SHOW_QUALITY): ?>
    <div class="card-panel">
        <div class="panel-header">
            <h2><i class="fa-solid fa-heart-pulse"></i> Qualité de liaison</h2>
            <small id="qualityInfo">—</small>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Station</th>
                        <th class="num">Trames UDP perdues</th>
                        <th class="num">Timeouts</th>
                        <th class="num">Déconnexions</th>
                        <th class="num">Connexions</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody id="qualityBody"></tbody>
            </table>
        </div>
        <div class="legend">
            Indice = trames perdues + 5 × timeouts + 3 × déconnexions. Stable &lt; 10 • Moyenne 10 à 39 • Instable ≥ 40. <span id="qualityScope">Compteurs depuis minuit (heure du serveur).</span>
        </div>
    </div>
    <?php endif; ?>

    <footer>
        Dashboard SvxReflector Live • Conçu par <strong><?= htmlspecialchars(AUTHOR_NAME, ENT_QUOTES, 'UTF-8') ?></strong><?= dashboard_version_html() ?>
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
        function fmt(sec) {
            const n = Math.max(0, parseInt(sec, 10) || 0);
            if (n < 60) return n + 's';
            if (n < 3600) return Math.floor(n / 60) + 'm ' + (n % 60) + 's';
            return Math.floor(n / 3600) + 'h ' + Math.floor((n % 3600) / 60) + 'm';
        }

        let nodes = {};
        let stats = {};
        let hourlyTx = [];
        let hourlyTalk = [];
        let longest = {};
        let txCount = 0;
        let totalTalk = 0;
        let statsDate = '';
        let rankMode = 'talk';
        let period = <?= json_encode($defaultPeriod) ?>;
        let pOffset = 0;
        let periodLabel = '';
        let dailyData = [];
        let hasPrev = false;

        function renderDaily() {
            const panel = $('dailyPanel');
            if (!panel) return;
            if (period === 'day') { panel.style.display = 'none'; return; }
            panel.style.display = '';
            $('dailyTitle').textContent = period === 'year' ? 'Activité par mois' : 'Activité par jour';
            const max = Math.max(...dailyData.map(d => d.tx), 0);
            const moisC = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
            $('dailyChart').innerHTML = dailyData.map(d => {
                const pct = max > 0 ? Math.round(d.tx / max * 100) : 0;
                let lbl, full;
                if (period === 'year') {
                    const mi = parseInt(d.key.slice(5, 7), 10) - 1;
                    lbl = moisC[mi]; full = moisC[mi] + ' ' + d.key.slice(0, 4);
                } else {
                    const dt = new Date(d.key + 'T12:00:00');
                    lbl = period === 'week' ? ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'][dt.getDay()] + ' ' + d.key.slice(8) : d.key.slice(8);
                    full = d.key.slice(8) + '/' + d.key.slice(5, 7) + '/' + d.key.slice(0, 4);
                }
                const title = full + ' : ' + d.tx + ' transmission(s), ' + fmt(d.talk) + ' de parole';
                const cls = d.tx === 0 ? 'zero' : (d.tx === max ? 'peak' : '');
                return `<div class="hcol" title="${esc(title)}"><div class="hbarwrap"><div class="hval">${d.tx > 0 ? d.tx : ''}</div><div class="hbar ${cls}" style="height:${d.tx === 0 ? 2 : Math.max(pct * 0.85, 4)}%"></div></div><div class="hlbl">${esc(lbl)}</div></div>`;
            }).join('');
            $('dailyInfo').textContent = txCount + ' TX • ' + fmt(totalTalk);
        }

        function applyPeriodTexts() {
            const day = period === 'day';
            const t1 = $('kpiTxTitle'), t2 = $('kpiTalkTitle');
            const w = { day: 'du jour', week: 'de la semaine', month: 'du mois', year: "de l'année" }[period];
            if (t1) t1.textContent = 'Transmissions ' + w;
            if (t2) t2.textContent = 'Temps de parole ' + w;
            const q = $('qualityScope');
            if (q) q.textContent = day ? 'Compteurs depuis minuit (heure du serveur).' : 'Compteurs cumulés sur la période affichée.';
            const nav = $('periodNav');
            if (nav) nav.style.display = day ? 'none' : '';
            const pl = $('pLabel'); if (pl) pl.textContent = periodLabel || '—';
            const pn = $('pNext'); if (pn) pn.disabled = pOffset >= 0;
            const pp = $('pPrev'); if (pp) pp.disabled = !hasPrev;
        }

        const $ = id => document.getElementById(id);

        function renderKpis() {
            if (!$('kpiTx')) { updateDayLabel(); return; }
            document.getElementById('kpiTx').textContent = String(txCount);
            document.getElementById('kpiTalk').textContent = fmt(totalTalk);
            const speakers = Object.values(stats).filter(s => s.tx > 0).length;
            document.getElementById('kpiSpeakers').textContent = String(speakers);

            if (longest && longest.talk_time > 0) {
                document.getElementById('kpiLongest').textContent = fmt(longest.talk_time);
                document.getElementById('kpiLongestSub').textContent = longest.callsign;
            } else {
                document.getElementById('kpiLongest').textContent = '—';
                document.getElementById('kpiLongestSub').textContent = '';
            }

            updateDayLabel();
        }

        function updateDayLabel() {
            if (period !== 'day') {
                document.getElementById('dayLabel').textContent = periodLabel || 'RNFA Network Monitor';
                return;
            }
            if (/^\d{4}-\d{2}-\d{2}$/.test(statsDate)) {
                const [y, m, d] = statsDate.split('-');
                document.getElementById('dayLabel').textContent = 'Journée du ' + d + '/' + m + '/' + y;
            }
        }

        function renderRank() {
            if (!$('rankBody')) return;
            const body = document.getElementById('rankBody');
            const rows = Object.entries(stats)
                .filter(([, s]) => s.tx > 0)
                .map(([cs, s]) => ({ cs, tx: s.tx, talk: s.talk_time, max: s.max_talk }))
                .sort((a, b) => rankMode === 'talk' ? (b.talk - a.talk) || (b.tx - a.tx) : (b.tx - a.tx) || (b.talk - a.talk))
                .slice(0, 10);

            if (rows.length === 0) {
                body.innerHTML = '<div class="empty">' + (period === 'day' ? 'Aucune transmission aujourd\'hui' : 'Aucune transmission sur cette période') + '</div>';
                return;
            }
            const top = rankMode === 'talk' ? rows[0].talk : rows[0].tx;
            body.innerHTML = rows.map((r, i) => {
                const value = rankMode === 'talk' ? r.talk : r.tx;
                const pct = top > 0 ? Math.max(2, Math.round(value / top * 100)) : 2;
                const name = (nodes[r.cs] && nodes[r.cs].name) ? nodes[r.cs].name : '';
                return `
                    <div class="rank-row">
                        <div class="rank-pos">${i + 1}</div>
                        <div class="rank-main">
                            <div class="rank-top">
                                <div><strong class="cs">${esc(r.cs)}</strong><span class="nm">${esc(name)}</span></div>
                                <div class="rank-val">${esc(fmt(r.talk))} • ${r.tx} TX</div>
                            </div>
                            <div class="bar"><div class="bar-fill" style="width:${pct}%"></div></div>
                        </div>
                    </div>`;
            }).join('');
        }

        function renderHours() {
            if (!$('hoursChart')) return;
            const chart = document.getElementById('hoursChart');
            const tx = (hourlyTx && hourlyTx.length === 24) ? hourlyTx : new Array(24).fill(0);
            const talk = (hourlyTalk && hourlyTalk.length === 24) ? hourlyTalk : new Array(24).fill(0);
            const max = Math.max(...tx, 0);
            const peakHour = max > 0 ? tx.indexOf(max) : -1;

            chart.innerHTML = tx.map((v, h) => {
                const pct = max > 0 ? Math.round(v / max * 100) : 0;
                const hh = String(h).padStart(2, '0');
                const cls = v === 0 ? 'zero' : (h === peakHour ? 'peak' : '');
                const title = `${hh}h : ${v} transmission(s), ${fmt(talk[h])} de parole`;
                return `
                    <div class="hcol" title="${esc(title)}">
                        <div class="hbarwrap">
                            <div class="hval">${v > 0 ? v : ''}</div>
                            <div class="hbar ${cls}" style="height:${v === 0 ? 2 : Math.max(pct * 0.85, 4)}%"></div>
                        </div>
                        <div class="hlbl">${hh}</div>
                    </div>`;
            }).join('');

            document.getElementById('peakInfo').textContent = peakHour >= 0
                ? `Pic : ${String(peakHour).padStart(2, '0')}h (${max} TX)`
                : 'Aucune activité';
        }

        function qualityScore(s) {
            return (s.udp_lost || 0) + 5 * (s.timeouts || 0) + 3 * (s.disconnects || 0);
        }

        function renderQuality() {
            if (!$('qualityBody')) return;
            const body = document.getElementById('qualityBody');
            const names = new Set([...Object.keys(stats), ...Object.keys(nodes)]);
            const empty = { udp_lost: 0, timeouts: 0, disconnects: 0, connects: 0 };

            const rows = [...names].map(cs => Object.assign({ cs }, empty, stats[cs] || {}, { online: !!nodes[cs] }))
                .map(r => Object.assign(r, { score: qualityScore(r) }))
                .sort((a, b) => b.score - a.score || a.cs.localeCompare(b.cs));

            const unstable = rows.filter(r => r.score >= 40).length;
            document.getElementById('qualityInfo').textContent = rows.length + ' station(s) • ' + unstable + ' instable(s)';

            if (rows.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="empty">Aucune donnée</td></tr>';
                return;
            }
            body.innerHTML = rows.map(r => {
                let chip = '<span class="chip chip-good">STABLE</span>';
                if (r.score >= 40) chip = '<span class="chip chip-bad">INSTABLE</span>';
                else if (r.score >= 10) chip = '<span class="chip chip-warn">MOYENNE</span>';
                const name = (nodes[r.cs] && nodes[r.cs].name) ? ` <span style="color: var(--text-muted); font-size: 0.8rem;">${esc(nodes[r.cs].name)}</span>` : '';
                return `
                    <tr>
                        <td><span class="dot ${r.online ? 'on' : ''}"></span><strong style="font-family: monospace; color: var(--accent-text);">${esc(r.cs)}</strong>${name}</td>
                        <td class="num">${r.udp_lost}</td>
                        <td class="num">${r.timeouts}</td>
                        <td class="num">${r.disconnects}</td>
                        <td class="num">${r.connects}</td>
                        <td>${chip}</td>
                    </tr>`;
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
                if (data) nodes = data.nodes || {};
            } catch (e) { /* noms indisponibles : on continue */ }

            if (period !== 'day') {
                try {
                    const hist = await fetchJson('api_history.php?period=' + period + '&offset=' + pOffset + '&x');
                    if (hist) {
                        if (hist.error) errors.push(hist.error);
                        stats = (hist.stats && !Array.isArray(hist.stats)) ? hist.stats : {};
                        hourlyTx = hist.hourly_tx || [];
                        hourlyTalk = hist.hourly_talk || [];
                        longest = hist.longest || {};
                        txCount = hist.tx_count || 0;
                        totalTalk = hist.total_talk_time || 0;
                        dailyData = hist.daily || [];
                        periodLabel = hist.label || '';
                        hasPrev = !!hist.has_prev;
                    }
                } catch (e) {
                    errors.push('Impossible de contacter le serveur.');
                }
            } else try {
                const log = await fetchJson('api_log.php');
                if (log) {
                    if (log.error) errors.push(log.error);
                    const st = log.state || {};
                    stats = (st.stats && !Array.isArray(st.stats)) ? st.stats : {};
                    hourlyTx = st.hourly_tx || [];
                    hourlyTalk = st.hourly_talk || [];
                    longest = st.longest || {};
                    txCount = st.tx_count || 0;
                    totalTalk = st.total_talk_time || 0;
                    statsDate = st.stats_date || '';
                }
            } catch (e) {
                errors.push('Impossible de contacter le serveur.');
            }

            if (errors.length) {
                errBox.textContent = errors.join(' — ');
                errBox.style.display = 'block';
            } else {
                errBox.style.display = 'none';
            }
            applyPeriodTexts();
            renderKpis();
            renderRank();
            renderHours();
            renderDaily();
            renderQuality();
        }

        document.getElementById('rankTalk').addEventListener('click', function () {
            rankMode = 'talk';
            this.classList.add('active');
            document.getElementById('rankTx').classList.remove('active');
            renderRank();
        });
        document.getElementById('rankTx').addEventListener('click', function () {
            rankMode = 'tx';
            this.classList.add('active');
            document.getElementById('rankTalk').classList.remove('active');
            renderRank();
        });

        document.querySelectorAll('#periodTabs button').forEach(b => {
            b.addEventListener('click', function () {
                document.querySelectorAll('#periodTabs button').forEach(x => x.classList.remove('active'));
                this.classList.add('active');
                period = this.dataset.period;
                pOffset = 0;
                periodLabel = '';
                loadAll();
            });
        });
        if ($('pPrev')) $('pPrev').addEventListener('click', () => { if (hasPrev) { pOffset--; loadAll(); } });
        if ($('pNext')) $('pNext').addEventListener('click', () => { if (pOffset < 0) { pOffset++; loadAll(); } });

        loadAll();
        setInterval(() => { if (period === 'day' || pOffset === 0) loadAll(); }, 5000);
    </script>
</body>
</html>
