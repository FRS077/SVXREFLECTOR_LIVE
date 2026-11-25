<?php
/**
 * fichier serveur.php
 * SvxReflector Live : état du serveur (CPU, température, mémoire, disque, réseau, uptime)
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
if (!SERVER_PAGE_ENABLE) {
    http_response_code(404);
    echo 'Not found';
    exit;
}
$cfg = [
    'cpu' => (bool)SERVER_SHOW_CPU, 'temp' => (bool)SERVER_SHOW_TEMP, 'ram' => (bool)SERVER_SHOW_RAM, 'disk' => (bool)SERVER_SHOW_DISK,
    'net' => (bool)SERVER_SHOW_NET, 'uptime' => (bool)SERVER_SHOW_UPTIME, 'system' => (bool)SERVER_SHOW_SYSTEM,
    'refresh' => max(1, (int)SERVER_REFRESH_SEC),
];
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serveur • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/serveur.css?v=<?= (int)@filemtime(__DIR__ . '/../css/serveur.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-server"></i>
            <div>
                <h1>SERVEUR</h1>
                <small style="color: var(--text-muted);" id="hostLine">RNFA • état de la machine</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <a class="btn-action" href="mumble.php"><i class="fa-solid fa-headset"></i> <span>Mumble</span></a>
            <?php if (TG_PAGE_ENABLE): ?><a class="btn-action" href="talkgroupe.php"><i class="fa-solid fa-hashtag"></i> <span>Talkgroupes</span></a><?php endif; ?>
            <?= dashboard_menus_html() ?>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="../index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div class="err-box" id="errBox"></div>

    <?php if (SERVER_SHOW_UPTIME): ?>
    <div class="up-hero" id="upHero">
        <div class="up-ic"><i class="fa-solid fa-power-off"></i></div>
        <div class="up-main">
            <div class="up-label">EN SERVICE DEPUIS</div>
            <div class="up-count" id="upCount">—</div>
            <div class="up-sub" id="upSub"></div>
        </div>
        <div class="up-badges" id="upBadges"></div>
    </div>
    <?php endif; ?>

    <div class="srv-grid" id="gauges"></div>

    <div class="srv-grid srv-grid-2">
        <?php if (SERVER_SHOW_NET): ?>
        <div class="card-panel">
            <div class="panel-header"><h2><i class="fa-solid fa-network-wired"></i> Réseau</h2></div>
            <div class="net-rates">
                <div><small>Réception</small><b id="rxRate">—</b></div>
                <div><small>Émission</small><b id="txRate">—</b></div>
            </div>
            <canvas id="netChart" class="chart" height="120"></canvas>
            <div class="srv-note" id="netTotal"></div>
        </div>
        <?php endif; ?>
        <?php if (SERVER_SHOW_CPU): ?>
        <div class="card-panel">
            <div class="panel-header"><h2><i class="fa-solid fa-gauge-high"></i> Charge du processeur</h2></div>
            <div class="load-row"><span>1 min</span><div class="load-bar"><i id="ld1"></i></div><b id="ldv1"></b></div>
            <div class="load-row"><span>5 min</span><div class="load-bar"><i id="ld5"></i></div><b id="ldv5"></b></div>
            <div class="load-row"><span>15 min</span><div class="load-bar"><i id="ld15"></i></div><b id="ldv15"></b></div>
            <canvas id="cpuChart" class="chart" height="120"></canvas>
            <div class="srv-note" id="cpuNote"></div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (SERVER_SHOW_SYSTEM): ?>
    <div class="card-panel" style="margin-top: 20px;">
        <div class="panel-header"><h2><i class="fa-solid fa-circle-info"></i> Système</h2></div>
        <div class="sys-grid" id="sysGrid"></div>
    </div>
    <?php endif; ?>

    <footer>
        <?= dashboard_footer_html() ?>
    </footer>

    <script>
        const CFG = <?= json_encode($cfg) ?>;
        const THEME_KEY = 'svxreflector_theme';          // même clé que les autres pages : le thème est partagé
        const rootEl = document.documentElement;
        const themeIcon = document.getElementById('themeIcon');
        const themeLabel = document.getElementById('themeLabel');
        const tzName = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

        function applyTheme(theme) {
            const t = theme === 'light' ? 'light' : 'dark';
            rootEl.setAttribute('data-theme', t);
            try { localStorage.setItem(THEME_KEY, t); } catch (e) {}
            themeIcon.className = t === 'light' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            themeLabel.textContent = t === 'light' ? 'Mode sombre' : 'Mode clair';
            drawCharts();
        }
        let initialTheme = 'dark';
        try { const s = localStorage.getItem(THEME_KEY); if (s === 'light' || s === 'dark') initialTheme = s; } catch (e) {}
        document.getElementById('themeToggle').addEventListener('click', () => {
            applyTheme(rootEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function bytes(n, dec) {
            if (n == null) return '—';
            const u = ['o', 'Ko', 'Mo', 'Go', 'To'];
            let i = 0; n = Number(n);
            while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
            return n.toFixed(dec != null ? dec : (n >= 100 || i === 0 ? 0 : 1)).replace('.', ',') + ' ' + u[i];
        }
        function rate(n) { return n == null ? '—' : bytes(n) + '/s'; }
        function fmtDT(ts) {
            try {
                const p = {};
                new Intl.DateTimeFormat('fr-FR', { timeZone: tzName, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                    .formatToParts(new Date(ts * 1000)).forEach(x => { p[x.type] = x.value; });
                return `${p.day}/${p.month}/${p.year} à ${p.hour}:${p.minute}`;
            } catch (e) { return new Date(ts * 1000).toLocaleString(); }
        }
        function level(pct, warn, crit) { return pct >= crit ? 'crit' : (pct >= warn ? 'warn' : 'ok'); }

        let d = null, skew = 0;
        const hist = { cpu: [], rx: [], tx: [] };
        const HMAX = 90;

        /* ---------- Jauges circulaires ---------- */
        function gauge(id, icon, title, pct, big, sub, lvl, extra) {
            const R = 52, C = 2 * Math.PI * R;
            const v = pct == null ? 0 : Math.max(0, Math.min(100, pct));
            return `<div class="gauge-card lvl-${lvl}" id="g-${id}">
                <div class="gauge-title"><i class="fa-solid ${icon}"></i> ${esc(title)}</div>
                <div class="ring"><svg viewBox="0 0 120 120"><circle class="ring-bg" cx="60" cy="60" r="${R}"/>
                    <circle class="ring-fg" cx="60" cy="60" r="${R}" stroke-dasharray="${C.toFixed(1)}" stroke-dashoffset="${(C * (1 - v / 100)).toFixed(1)}"/></svg>
                    <div class="ring-val">${big}</div></div>
                <div class="gauge-sub">${sub}</div>${extra || ''}</div>`;
        }
        function renderGauges() {
            if (!d) return;
            const th = d.th, out = [];
            if (CFG.cpu) {
                const p = d.cpu.pct;
                out.push(gauge('cpu', 'fa-microchip', 'Processeur', p, p == null ? '…' : Math.round(p) + '<small>%</small>',
                    `${esc(d.cpu.cores)} cœur(s)` + (d.cpu.model ? ' • ' + esc(d.cpu.model) : ''), p == null ? 'ok' : level(p, th.pct_warn, th.pct_crit)));
            }
            if (CFG.temp) {
                const t = d.temp.max;
                const pct = t == null ? 0 : Math.min(100, t / 100 * 100);
                out.push(gauge('temp', 'fa-temperature-half', 'Température', pct, t == null ? 'N/D' : Math.round(t) + '<small>°C</small>',
                    t == null ? 'Aucune sonde disponible sur cette machine' : d.temp.zones.map(z => esc(z.name) + ' ' + z.c.toFixed(0) + '°').slice(0, 3).join(' • '),
                    t == null ? 'ok' : (t >= th.temp_crit ? 'crit' : (t >= th.temp_warn ? 'warn' : 'ok'))));
            }
            if (CFG.ram) {
                const m = d.mem;
                out.push(gauge('ram', 'fa-memory', 'Mémoire', m.total ? m.pct : null, m.total ? Math.round(m.pct) + '<small>%</small>' : 'N/D',
                    m.total ? `${bytes(m.used)} / ${bytes(m.total)}` + (m.swap_total ? ` • swap ${bytes(m.swap_used)} / ${bytes(m.swap_total)}` : '') : '', m.total ? level(m.pct, th.pct_warn, th.pct_crit) : 'ok'));
            }
            if (CFG.disk) {
                (d.disks || []).forEach((k, i) => out.push(gauge('disk' + i, 'fa-hard-drive', 'Disque ' + k.path, k.pct, Math.round(k.pct) + '<small>%</small>',
                    `${bytes(k.used)} / ${bytes(k.total)} • libre ${bytes(k.free)}`, level(k.pct, th.pct_warn, th.pct_crit))));
            }
            document.getElementById('gauges').innerHTML = out.join('');
        }

        /* ---------- Uptime : compteur vivant ---------- */
        function fmtUp(sec) {
            sec = Math.max(0, Math.floor(sec));
            const j = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60;
            return { j, h, m, s };
        }
        function tickUp() {
            const el = document.getElementById('upCount');
            if (!el || !d || !d.uptime.boot) return;
            const u = fmtUp((Date.now() + skew) / 1000 - d.uptime.boot);
            el.innerHTML = `<span>${u.j}</span><small>j</small> <span>${String(u.h).padStart(2, '0')}</span><small>h</small> <span>${String(u.m).padStart(2, '0')}</span><small>min</small> <span>${String(u.s).padStart(2, '0')}</span><small>s</small>`;
        }
        function renderUp() {
            const sub = document.getElementById('upSub');
            if (!sub || !d) return;
            sub.textContent = d.uptime.boot ? 'Dernier démarrage le ' + fmtDT(d.uptime.boot) : '';
            const b = [];
            if (d.log && d.log.age != null) {
                const ok = d.log.age < 300;
                b.push(`<span class="badge ${ok ? 'ok' : 'warn'}"><i class="fa-solid fa-circle"></i> Log SvxReflector ${ok ? 'actif' : 'silencieux'} <small>(écrit il y a ${d.log.age < 90 ? d.log.age + ' s' : Math.round(d.log.age / 60) + ' min'})</small></span>`);
            } else b.push('<span class="badge warn"><i class="fa-solid fa-circle"></i> Log SvxReflector illisible</span>');
            document.getElementById('upBadges').innerHTML = b.join('');
        }

        /* ---------- Graphiques (historique de la session) ---------- */
        function css(n) { return getComputedStyle(document.documentElement).getPropertyValue(n).trim() || '#00d8e6'; }
        function line(ctx, w, h, series, colors, max) {
            ctx.clearRect(0, 0, w, h);
            ctx.strokeStyle = css('--border-color'); ctx.lineWidth = 1;
            for (let i = 1; i < 4; i++) { const y = h * i / 4; ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(w, y); ctx.stroke(); }
            series.forEach((arr, si) => {
                if (arr.length < 2) return;
                const step = w / (HMAX - 1), off = (HMAX - arr.length) * step;
                const pts = arr.map((v, i) => [off + i * step, h - 4 - (Math.min(v, max) / max) * (h - 10)]);
                const g = ctx.createLinearGradient(0, 0, 0, h);
                g.addColorStop(0, colors[si] + '55'); g.addColorStop(1, colors[si] + '00');
                ctx.beginPath(); ctx.moveTo(pts[0][0], h); pts.forEach(p => ctx.lineTo(p[0], p[1])); ctx.lineTo(pts[pts.length - 1][0], h); ctx.closePath(); ctx.fillStyle = g; ctx.fill();
                ctx.beginPath(); pts.forEach((p, i) => i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1]));
                ctx.strokeStyle = colors[si]; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
            });
        }
        function sizeCanvas(c) {
            const r = c.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
            if (c.width !== Math.round(r.width * dpr)) { c.width = Math.round(r.width * dpr); c.height = Math.round(120 * dpr); }
            const ctx = c.getContext('2d'); ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            return [ctx, r.width, 120];
        }
        function drawCharts() {
            const n = document.getElementById('netChart');
            if (n) { const [ctx, w, h] = sizeCanvas(n); const mx = Math.max(1024, ...hist.rx, ...hist.tx) * 1.15; line(ctx, w, h, [hist.rx, hist.tx], ['#10b981', '#a855f7'], mx); }
            const c = document.getElementById('cpuChart');
            if (c) { const [ctx, w, h] = sizeCanvas(c); line(ctx, w, h, [hist.cpu], ['#00d8e6'], 100); }
        }
        function push(a, v) { if (v == null) return; a.push(v); if (a.length > HMAX) a.shift(); }

        function render() {
            if (!d) return;
            push(hist.cpu, d.cpu.pct); push(hist.rx, d.net.rx); push(hist.tx, d.net.tx);
            document.getElementById('hostLine').textContent = 'RNFA • ' + (d.sys.host || 'état de la machine') + (d.sys.os ? ' • ' + d.sys.os : '');
            renderGauges(); renderUp(); tickUp(); drawCharts();
            if (CFG.net) {
                document.getElementById('rxRate').textContent = '↓ ' + rate(d.net.rx);
                document.getElementById('txRate').textContent = '↑ ' + rate(d.net.tx);
                document.getElementById('netTotal').textContent = 'Depuis le démarrage : ' + bytes(d.net.rx_total) + ' reçus • ' + bytes(d.net.tx_total) + ' émis';
            }
            if (CFG.cpu) {
                const cores = d.cpu.cores || 1;
                [['1', 0], ['5', 1], ['15', 2]].forEach(([k, i]) => {
                    const v = d.cpu.load[i] || 0, pct = Math.min(100, v / cores * 100);
                    document.getElementById('ld' + k).style.width = pct + '%';
                    document.getElementById('ld' + k).className = 'lv-' + level(pct, d.th.pct_warn, d.th.pct_crit);
                    document.getElementById('ldv' + k).textContent = v.toFixed(2).replace('.', ',');
                });
                document.getElementById('cpuNote').textContent = 'Charge moyenne rapportée au nombre de cœurs (' + cores + ').';
            }
            const sg = document.getElementById('sysGrid');
            if (sg) {
                const rows = [['Machine', d.sys.host], ['Système', d.sys.os], ['Noyau', d.sys.kernel + ' (' + d.sys.arch + ')'], ['Processeur', d.cpu.model], ['PHP', d.sys.php],
                              ['Serveur web', d.sys.web], ['Fuseau du serveur', d.sys.tz], ['Taille du log SvxReflector', d.log && d.log.size != null ? bytes(d.log.size) : '']];
                sg.innerHTML = rows.filter(r => r[1]).map(r => `<div><small>${esc(r[0])}</small><span>${esc(r[1])}</span></div>`).join('');
            }
        }

        let busy = false;
        async function load() {
            if (busy) return;
            busy = true;
            const box = document.getElementById('errBox');
            try {
                const res = await fetch('api_server.php?_=' + Date.now(), { cache: 'no-store' });
                if (res.status === 401) { location.href = '../index.php'; return; }
                d = await res.json();
                skew = d.now * 1000 - Date.now();
                box.style.display = 'none';
                render();
            } catch (e) {
                box.textContent = 'Connexion au serveur perdue, nouvelle tentative…';
                box.style.display = 'block';
            } finally { busy = false; }
        }
        applyTheme(initialTheme);
        load();
        setInterval(load, CFG.refresh * 1000);
        setInterval(tickUp, 1000);
        window.addEventListener('resize', drawCharts);

        (function () {
            function tick() {
                var el = document.getElementById('liveDatetime');
                if (!el) return;
                var dt = new Date(), t;
                try {
                    t = new Intl.DateTimeFormat(navigator.language || 'fr-FR', {
                        weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit',
                        hour: '2-digit', minute: '2-digit', second: '2-digit'
                    }).format(dt);
                } catch (e) { t = dt.toLocaleString(); }
                el.textContent = t;
            }
            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
