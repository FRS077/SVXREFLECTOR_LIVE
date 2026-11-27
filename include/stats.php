<?php
/**
 * fichier stats.php
 * Page Statistiques du jour – SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

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
            <div><h3 id="kpiTxTitle">Transmissions du jour</h3><div class="number" id="kpiTx">0</div><div class="sub" id="kpiTxSub"></div></div>
        </div>
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-stopwatch"></i></div>
            <div><h3 id="kpiTalkTitle">Temps de parole du jour</h3><div class="number" id="kpiTalk">0s</div><div class="sub" id="kpiTalkSub"></div></div>
        </div>
        <div class="kpi">
            <div class="kpi-icon"><i class="fa-solid fa-microphone-lines"></i></div>
            <div><h3>Stations ayant parlé</h3><div class="number" id="kpiSpeakers">0</div><div class="sub" id="kpiSpkSub"></div></div>
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





    <?php if (STATS_SHOW_ANALYSIS): ?>
    <section class="an" id="analysis">
        <div class="card-panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-chart-column"></i> Analyse des temps de parole</h2>
                <small id="anDateLabel">—</small>
            </div>
            <div class="an-bar">
                <label for="anDate">Analyse jusqu'au</label>
                <input type="date" id="anDate">
                <button type="button" class="btn-action" id="anGo">Afficher</button>
            </div>
            <div class="kpi-grid" id="anKpis"><div class="empty">Chargement…</div></div>
        </div>

        <div class="card-panel">
            <div class="panel-header"><h2><i class="fa-solid fa-trophy"></i> Records – 30 derniers jours</h2></div>
            <div class="kpi-grid" id="anRecords"></div>
        </div>

        <div class="grid-2">
            <div class="card-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-line"></i> Évolution des temps de parole – 30 derniers jours</h2><small>8 stations les plus actives</small></div>
                <div class="an-legend" id="anLegend"></div>
                <div id="anEvo" class="svgbox"></div>
                <div class="hours-note">Clique sur un indicatif pour le masquer / le ré-afficher. Étiquette = pic de la station. PTT de moins de <?= (int)STATS_MIN_PTT ?> s non comptés.</div>
            </div>
            <div class="card-panel">
                <div class="panel-header"><h2><i class="fa-solid fa-chart-simple"></i> Temps de parole total du réseau par jour – 30 derniers jours</h2></div>
                <div id="anDaily" class="svgbox"></div>
                <div class="hours-note">Survole une barre pour le détail ; le jour record est en ambre. Indicatifs masqués exclus.</div>
            </div>
        </div>
    </section>

    <script>
    (function () {
        const $a = id => document.getElementById(id);
        const E = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const hms = sec => { sec = Math.max(0, Math.round(sec)); const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60; return String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's'; };
        const short = sec => { sec = Math.round(sec); if (sec < 60) return sec + 's'; const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60); return h ? h + 'h' + String(m).padStart(2, '0') : m + ' min'; };
        const axisT = min => { min = Math.round(min); return min >= 60 ? Math.floor(min / 60) + 'h' + String(min % 60).padStart(2, '0') : min + ' min'; };
        const fr = k => k.slice(8) + '/' + k.slice(5, 7) + '/' + k.slice(0, 4);
        const COL = ['var(--accent-cyan)', 'var(--accent-amber)', 'var(--accent-green)', 'var(--accent-red)', '#a78bfa', '#f472b6', '#5eead4', '#94a3b8'];
        const OFF = new Set();   // indicatifs masqués dans le graphique
        let AN = null;

        function nice(maxMin) {   // pas "rond" pour l'axe (minutes)
            const steps = [1, 2, 5, 10, 15, 20, 30, 40, 60, 120, 180, 240, 360, 600];
            let st = steps.find(x => maxMin / x <= 5) || 600;
            return { st, top: Math.max(st, Math.ceil(maxMin / st) * st) };
        }
        function frame(topMin, st, W, H, pl, pr, pt, pb, ytitle) {
            let g = '';
            for (let v = 0; v <= topMin + 0.001; v += st) {
                const y = pt + (H - pt - pb) * (1 - v / topMin);
                g += '<line class="gl" x1="' + pl + '" x2="' + (W - pr) + '" y1="' + y + '" y2="' + y + '"/><text class="ax" x="' + (pl - 6) + '" y="' + (y + 3) + '" text-anchor="end">' + axisT(v) + '</text>';
            }
            return g + '<text class="ax" transform="rotate(-90 11 ' + (H / 2) + ')" x="11" y="' + (H / 2) + '" text-anchor="middle">' + ytitle + '</text>';
        }
        function xlabels(labels, X, H, step) {
            return labels.map((l, i) => (i % step === 0) ? '<text class="ax" x="' + X(i) + '" y="' + (H - 8) + '" text-anchor="middle">' + E(l) + '</text>' : '').join('');
        }

        function bars(el, vals, labels, titles, color, ytitle, W, H, step) {
            const pl = 50, pr = 12, pt = 12, pb = 28;
            const maxMin = Math.max(...vals, 1) / 60, n = nice(maxMin);
            const bw = (W - pl - pr) / vals.length, mx = Math.max(...vals);
            let g = frame(n.top, n.st, W, H, pl, pr, pt, pb, ytitle);
            vals.forEach((v, i) => {
                const h = (H - pt - pb) * (v / 60 / n.top), x = pl + bw * i + bw * 0.16;
                g += '<rect class="bx' + (v === mx && v > 0 ? ' pk' : '') + '" x="' + x + '" y="' + (H - pb - h) + '" width="' + bw * 0.68 + '" height="' + Math.max(h, v > 0 ? 1.5 : 0) + '" rx="3" style="fill:' + color + '"><title>' + E(titles[i]) + '</title></rect>';
            });
            g += xlabels(labels, i => pl + bw * (i + 0.5), H, step);
            el.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="svgc">' + g + '</svg>';
        }

        function lines(el) {
            const W = 560, H = 330, pl = 50, pr = 14, pt = 22, pb = 28, S = AN.series;
            if (!S.length) { el.innerHTML = '<div class="empty">Pas encore de données.</div>'; $a('anLegend').innerHTML = ''; return; }
            const vis = S.map((s, k) => ({ s, k })).filter(o => !OFF.has(o.s.cs));
            const all = vis.flatMap(o => o.s.values), n = nice(Math.max(...all, 1) / 60), len = S[0].values.length;
            const X = i => pl + (W - pl - pr) * i / (len - 1), Y = v => pt + (H - pt - pb) * (1 - v / 60 / n.top);
            const keys = AN.days.slice(60).map(d => d.key);
            let g = frame(n.top, n.st, W, H, pl, pr, pt, pb, 'Minutes de parole'), tags = '', used = [];
            vis.forEach(o => {
                const c = COL[o.k % COL.length], s = o.s, pts = s.values.map((v, i) => X(i).toFixed(1) + ',' + Y(v).toFixed(1));
                g += '<polyline class="evl" style="stroke:' + c + '" points="' + pts.join(' ') + '"/>';
                s.values.forEach((v, i) => { g += '<circle class="evd" style="stroke:' + c + '" cx="' + X(i) + '" cy="' + Y(v) + '" r="3"><title>' + E(s.cs + ' – ' + keys[i].slice(8) + '/' + keys[i].slice(5, 7) + ' : ' + short(v)) + '</title></circle>'; });
                const mx = Math.max(...s.values);
                if (mx > 0) {   // étiquette sur le pic
                    const i = s.values.indexOf(mx), tw = s.cs.length * 6.4 + 12;
                    let x = Math.min(Math.max(X(i), pl + tw / 2), W - pr - tw / 2), y = Y(mx) - 16;
                    for (let t = 0; t < 8 && used.some(u => Math.abs(u[0] - x) < (u[2] + tw) / 2 && Math.abs(u[1] - y) < 16); t++) y -= 17;
                    y = Math.max(y, 2); used.push([x, y, tw]);
                    tags += '<g class="evt"><rect x="' + (x - tw / 2) + '" y="' + y + '" width="' + tw + '" height="15" rx="7" style="fill:' + c + '"/><text x="' + x + '" y="' + (y + 11) + '" text-anchor="middle">' + E(s.cs) + '</text></g>';
                }
            });
            g += tags + xlabels(keys.map(k => k.slice(8) + '/' + k.slice(5, 7)), X, H, 3);
            el.innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="svgc">' + g + '</svg>';
            $a('anLegend').innerHTML = S.map((s, k) => '<button type="button" class="lg' + (OFF.has(s.cs) ? ' off' : '') + '" data-cs="' + E(s.cs) + '"><i style="border-color:' + COL[k % COL.length] + '"></i>' + E(s.cs) + '</button>').join('');
            $a('anLegend').querySelectorAll('.lg').forEach(b => b.addEventListener('click', () => { const c = b.dataset.cs; OFF.has(c) ? OFF.delete(c) : OFF.add(c); lines($a('anEvo')); }));
        }

        function kpi(icon, t, v, s1, s2) { return '<div class="kpi"><div class="kpi-icon"><i class="fa-solid ' + icon + '"></i></div><div><h3>' + t + '</h3><div class="number">' + v + '</div>' + (s1 ? '<div class="sub">' + s1 + '</div>' : '') + (s2 ? '<div class="sub">' + s2 + '</div>' : '') + '</div></div>'; }
        function vs(cur, ref, lab, kind) {
            if (!(ref > 0)) return '— vs ' + lab + ' : pas de donnée';
            const p = Math.round((cur - ref) / ref * 100);
            return '<span class="' + (p > 0 ? 'up' : p < 0 ? 'down' : '') + '">' + (p > 0 ? '▲ +' : p < 0 ? '▼ ' : '= ') + p + ' %</span> vs ' + lab;
        }

        function render() {
            const D = AN.days, n = D.length, cur = D[n - 1], prev = D[n - 2] || { talk: 0, tx: 0 };
            const w = D.slice(n - 7), wp = D.slice(n - 14, n - 7);
            const sum = (a, k) => a.reduce((x, d) => x + d[k], 0);
            const q = sum(D, 'talk');
            $a('anDateLabel').textContent = fr(AN.date);
            $a('anKpis').innerHTML =
                kpi('fa-calendar-week', 'Temps semaine', hms(sum(w, 'talk')), 'Top : ' + E(AN.top_week || '—'), vs(sum(w, 'talk'), sum(wp, 'talk'), 'sem. préc.')) +
                kpi('fa-comments', 'Transmissions semaine', sum(w, 'tx'), '7 derniers jours', vs(sum(w, 'tx'), sum(wp, 'tx'), 'sem. préc.')) +
                kpi('fa-hourglass-half', 'Temps trimestre', hms(q), 'Top : ' + E(AN.top_quarter || '—'), '90 derniers jours');
            const d30 = D.slice(n - 30), act = d30.filter(d => d.talk > 0), tot = sum(d30, 'talk');
            const best = act.reduce((a, d) => d.talk > a.talk ? d : a, { talk: 0, key: '' });
            $a('anRecords').innerHTML =
                kpi('fa-network-wired', 'Total réseau', hms(tot), act.length + ' jour(s) avec activité', '') +
                kpi('fa-gauge-high', 'Moyenne par jour', hms(act.length ? tot / act.length : 0), 'Jours actifs uniquement', '') +
                kpi('fa-fire', 'Jour le plus actif', best.key ? fr(best.key) : '—', best.key ? hms(best.talk).replace(/^00h /, '') : '', '') +
                kpi('fa-trophy', 'Plus longue prise de parole', hms(AN.record.talk), E(AN.record.cs || '—') + (AN.record.date ? ' – ' + fr(AN.record.date) : ''), '');
            lines($a('anEvo'));
            bars($a('anDaily'), d30.map(d => d.talk), d30.map(d => d.key.slice(8) + '/' + d.key.slice(5, 7)), d30.map(d => fr(d.key) + ' : ' + short(d.talk) + ' • ' + d.tx + ' TX'), 'var(--accent-cyan)', 'Minutes', 560, 330, 3);
        }


        // Cartes du haut : « Top » et variation vs hier (onglet Jour, jour courant)
        window.anKpiExtra = function () {
            const t = $a('kpiTalkSub'), x = $a('kpiTxSub'), sp = $a('kpiSpkSub'); if (!t || !x) return;
            const top = Object.entries(stats).filter(([, v]) => v.talk_time > 0).sort((a, b) => b[1].talk_time - a[1].talk_time)[0];
            let dTalk = '', dTx = '', dSpk = '';
            if (period === 'day' && AN && AN.days && AN.days.length > 1 && AN.days[AN.days.length - 1].key === statsDate) {
                const y = AN.days[AN.days.length - 2];
                dTalk = vs(totalTalk, y.talk, 'hier'); dTx = vs(txCount, y.tx, 'hier');
                dSpk = vs(Object.values(stats).filter(v => v.tx > 0).length, y.spk, 'hier');
            }
            t.innerHTML = (top ? 'Top : ' + E(top[0]) : '') + (dTalk ? '<br>' + dTalk : '');
            x.innerHTML = dTx;
            if (sp) sp.innerHTML = dSpk;
        };

        async function load(date) {
            try {
                const r = await fetch('api_history.php?mode=analysis&date=' + encodeURIComponent(date || '') + '&x=' + Date.now(), { cache: 'no-store' });
                const j = await r.json();
                if (j.error) throw new Error(j.error);
                AN = j; $a('anDate').value = j.date;
                render(); window.anKpiExtra();
            } catch (e) { $a('anKpis').innerHTML = '<div class="empty">Analyse indisponible.</div>'; }
        }

        $a('anGo').addEventListener('click', () => load($a('anDate').value));
        $a('anDate').addEventListener('change', () => load($a('anDate').value));
        load('');
        setInterval(() => { if (!$a('anDate').value || $a('anDate').value === (AN && AN.date)) load($a('anDate').value); }, 60000);
    })();
    </script>
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

            updateDayLabel();
            if (window.anKpiExtra) window.anKpiExtra();
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

        // Indicatifs masqués (include/callsigns_masques.json) ; jokers « * » acceptés
        const HIDDEN_CALLS = <?= json_encode(hidden_callsigns(), JSON_UNESCAPED_UNICODE) ?>.map(p => new RegExp('^' + p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$'));
        function isHiddenCall(cs) { cs = String(cs || '').trim().toUpperCase(); return HIDDEN_CALLS.some(r => r.test(cs)); }

        /** Onglet « Jour » : retire les indicatifs masqués du classement et des totaux (les autres périodes sont filtrées côté serveur). */
        function dropHiddenDay() {
            Object.keys(stats).forEach(cs => {
                if (!isHiddenCall(cs)) return;
                txCount = Math.max(0, txCount - (stats[cs].tx || 0));
                totalTalk = Math.max(0, totalTalk - (stats[cs].talk_time || 0));
                delete stats[cs];
            });
            if (longest && isHiddenCall(longest.callsign)) {
                longest = { callsign: '', talk_time: 0, datetime: '' };
                Object.entries(stats).forEach(([cs, s]) => { if ((s.max_talk || 0) > longest.talk_time) longest = { callsign: cs, talk_time: s.max_talk, datetime: '' }; });
            }
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
                    txCount = Math.max(0, (st.tx_count || 0) - (st.short_tx || 0));   // PTT courts exclus des statistiques
                    totalTalk = st.total_talk_time || 0;
                    statsDate = st.stats_date || '';
                    dropHiddenDay();
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
            renderDaily();
            renderHours();
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
