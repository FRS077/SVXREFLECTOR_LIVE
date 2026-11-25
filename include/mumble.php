<?php
/**
 * fichier mumble.php
 * Page Mumble – SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 */

ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';

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
    <title>Mumble • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/mumble.css?v=<?= (int)@filemtime(__DIR__ . '/../css/mumble.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-headset"></i>
            <div>
                <h1>MUMBLE</h1>
                <small style="color: var(--text-muted);">RNFA Network Monitor</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
            <a class="btn-action" href="carte.php"><i class="fa-solid fa-map-location-dot"></i> <span>Carte</span></a>
            <?php if (LOG_PAGE_ENABLE): ?>
            <a class="btn-action" href="log.php"><i class="fa-solid fa-list-check"></i> <span>Log</span></a>
            <?php endif; ?>
            <?php if (MUMBLE_ENABLE_CONNECT_ALERT || MUMBLE_ENABLE_DISCONNECT_ALERT || MUMBLE_ALERT_GATEWAY_DOWN): ?>
            <button type="button" class="btn-action" id="soundToggle" title="Bip quand une personne se connecte ou quitte Mumble">
                <i class="fa-solid fa-bell-slash"></i> <span>Alerte : OFF</span>
            </button>
            <?php endif; ?>
            <?= dashboard_menus_html() ?>
            <button type="button" class="btn-action" id="themeToggle" aria-label="Changer de thème">
                <i class="fa-solid fa-sun" id="themeIcon"></i>
                <span id="themeLabel">Mode clair</span>
            </button>
            <a class="btn-logout" href="../index.php?action=logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
        </div>
    </header>

    <div id="toastBox" style="position:fixed; top:14px; right:14px; z-index:9999; display:flex; flex-direction:column; gap:8px; max-width:340px;"></div>

    <div class="error-box" id="errBox"></div>

    <?php if (MUMBLE_SHOW_PTT_BANNER): ?>
    <div class="ptt-banner" id="pttBanner">
        <i class="fa-solid fa-microphone-lines" style="font-size:1.8rem;color:var(--accent-cyan)"></i>
        <div>
            <small style="color:var(--text-muted)">Émission via Mumble</small>
            <div class="who" id="pttWho">Aucune émission</div>
        </div>
        <span class="badge-on" id="pttBadge" style="display:none">PTT ON</span>
        <span class="who" id="pttTimer" style="display:none">0:00</span>
        <div class="bar" id="pttBarWrap" style="display:none"><i id="pttBar"></i></div>
    </div>

    <?php endif; ?>

    <?php if (MUMBLE_SHOW_KPIS): ?>
    <div class="kpis">
        <?php if (MUMBLE_SHOW_KPI_ONLINE): ?><div class="kpi"><small>Connectés sur Mumble</small><div class="v" id="kOnline">0</div></div><?php endif; ?>
        <?php if (MUMBLE_SHOW_KPI_GATEWAY): ?><div class="kpi"><small>Passerelle RNFA-GW</small><div class="v" id="kGw">—</div></div><?php endif; ?>
        <?php if (MUMBLE_SHOW_KPI_TX): ?><div class="kpi"><small>Transmissions Mumble (récentes)</small><div class="v" id="kTx">0</div></div><?php endif; ?>
        <?php if (MUMBLE_SHOW_KPI_REJECTED): ?><div class="kpi"><small>Connexions refusées</small><div class="v" id="kRej">0</div></div><?php endif; ?>
    </div>

    <?php endif; ?>

    <?php if (MUMBLE_SHOW_USERS): ?>
    <div class="panel">
        <h2><i class="fa-solid fa-users"></i> Personnes connectées</h2>
        <div class="users" id="users"><span class="empty">Chargement…</span></div>
    </div>

    <?php endif; ?>

    <?php if (MUMBLE_SHOW_TRANSMISSIONS): ?>
    <div class="panel">
        <h2><i class="fa-solid fa-tower-broadcast"></i> Dernières transmissions Mumble (PTT)</h2>
        <div class="tbl"><table>
            <thead><tr><th>Heure</th><th>Station</th><th>PTT</th><th>TG</th><th>Durée PTT</th></tr></thead>
            <tbody id="pttBody"><tr><td colspan="5" class="empty">Aucune transmission Mumble détectée.</td></tr></tbody>
        </table></div>
    </div>

    <?php endif; ?>

    <?php if (MUMBLE_SHOW_TODAY_STATS): ?>
    <div class="panel">
        <h2><i class="fa-solid fa-calendar-day"></i> Aujourd'hui</h2>
        <div class="kpis" style="margin-bottom:0;">
            <div class="kpi"><small>Connexions</small><div class="v" id="tdConn" style="font-size:1.25rem;">0</div></div>
            <div class="kpi"><small>Personnes différentes</small><div class="v" id="tdPeople" style="font-size:1.25rem;">0</div></div>
            <div class="kpi"><small>Temps de présence cumulé</small><div class="v" id="tdTime" style="font-size:1.25rem;">0s</div></div>
            <div class="kpi"><small>Plus longue présence</small><div class="v" id="tdLong" style="font-size:1.0rem;">—</div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (MUMBLE_SHOW_GATEWAY_QUALITY): ?>
    <div class="panel">
        <h2><i class="fa-solid fa-heart-pulse"></i> Qualité de la passerelle <span id="gwChip" class="tag" style="margin-left:6px;">—</span></h2>
        <div class="kpis" style="margin-bottom:0;">
            <div class="kpi"><small>Connectée depuis</small><div class="v" id="gqUp" style="font-size:1.25rem;">—</div></div>
            <div class="kpi"><small>Disponibilité 24 h</small><div class="v" id="gqAvail" style="font-size:1.25rem;">—</div></div>
            <div class="kpi"><small>Timeouts (24 h / 7 j)</small><div class="v" id="gqTo" style="font-size:1.25rem;">—</div></div>
            <div class="kpi"><small>Reconnexions (24 h / 7 j)</small><div class="v" id="gqRe" style="font-size:1.25rem;">—</div></div>
            <div class="kpi"><small>Dernier timeout</small><div class="v" id="gqLast" style="font-size:1.0rem;">—</div></div>
            <div class="kpi"><small>Plus longue session (7 j)</small><div class="v" id="gqLong" style="font-size:1.25rem;">—</div></div>
        </div>
        <div class="note">Indice = 5 × timeouts + reconnexions sur 24 h. Stable &lt; 10 • Moyenne 10 à 39 • Instable ≥ 40.</div>
    </div>
    <?php endif; ?>

    <?php if (MUMBLE_SHOW_HISTORY): ?>
    <div class="panel">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Historique connexions / départs (une ligne par personne)
            <?php if (MUMBLE_SHOW_EXPORT_CSV): ?><button type="button" class="btn-action" id="csvBtn" style="margin-left:auto;"><i class="fa-solid fa-file-csv"></i> <span>Export CSV</span></button><?php endif; ?></h2>
        <div class="tbl"><table>
            <thead><tr><th>Utilisateur</th><th>Dernière connexion</th><th>Dernier départ</th><th>Dernière durée</th><th>Sessions</th><th>Temps total</th></tr></thead>
            <tbody id="histBody"><tr><td colspan="6" class="empty">—</td></tr></tbody>
        </table></div>
    </div>

    <?php endif; ?>

    <footer>
        <p>Développé par <strong>FRS077 Romuald</strong> pour RNFA<?= dashboard_version_html() ?></p>
    </footer>

    <script>
        const GW_REFLECTOR = <?= json_encode(MUMBLE_REFLECTOR_NODE) ?>;   // nom du nœud Mumble dans le reflector
        const TALK_MAX_SEC = <?= (int)TALK_MAX_SEC ?>;
        let mum = { online: [], history: [], gateway: 'RNFA-GW', rejected: 0 };
        let ref = { talk_starts: {}, recent_events: [] };
        let skew = 0;

        function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function fmtDT(ts) { return new Date(ts * 1000).toLocaleString(undefined, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }); }
        function fmtDur(s) {
            s = Math.max(0, Math.floor(s));
            const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
            if (d) return d + 'j ' + h + 'h ' + String(m).padStart(2, '0') + 'm';
            if (h) return h + 'h ' + String(m).padStart(2, '0') + 'm ' + String(x).padStart(2, '0') + 's';
            if (m) return m + 'm ' + String(x).padStart(2, '0') + 's';
            return x + 's';
        }
        const nowSrv = () => Math.floor(Date.now() / 1000) - skew;

        const $ = id => document.getElementById(id);
        const setText = (id, t) => { const e = $(id); if (e) e.textContent = t; };
        const MUMBLE_SHOW_GATEWAY_CARD = <?= json_encode(MUMBLE_SHOW_GATEWAY_CARD) ?>;
        const MUMBLE_SHOW_USER_SINCE = <?= json_encode(MUMBLE_SHOW_USER_SINCE) ?>;
        const MUMBLE_SHOW_USER_DURATION = <?= json_encode(MUMBLE_SHOW_USER_DURATION) ?>;

        const MUMBLE_SHOW_TAB_COUNT = <?= json_encode(MUMBLE_SHOW_TAB_COUNT) ?>;
        let historyRows = [];   // lignes de l'historique (pour l'export CSV)

        function renderToday() {
            if (!$('tdConn')) return;
            const now = nowSrv();
            const mid = new Date(); mid.setHours(0, 0, 0, 0);
            const t0 = Math.floor(mid.getTime() / 1000);
            const sess = mum.history.filter(h => !h.gw).map(h => ({ n: h.name, s: h.since_ts, e: h.end_ts }))
                .concat(mum.online.filter(u => !u.gw).map(u => ({ n: u.name, s: u.since_ts, e: now })))
                .filter(x => x.e >= t0);
            const conn = sess.filter(x => x.s >= t0).length;
            const people = new Set(sess.map(x => x.n)).size;
            let total = 0, best = null;
            sess.forEach(x => {
                const d = Math.max(0, x.e - Math.max(x.s, t0));
                total += d;
                if (!best || d > best.d) best = { d, n: x.n };
            });
            setText('tdConn', conn);
            setText('tdPeople', people);
            setText('tdTime', fmtDur(total));
            setText('tdLong', best ? fmtDur(best.d) + ' (' + best.n + ')' : '—');
        }

        function exportCsv() {
            const q = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
            const lines = [['Utilisateur', 'Derniere connexion', 'Dernier depart', 'Derniere duree (s)', 'Sessions', 'Temps total (s)'].map(q).join(';')];
            historyRows.forEach(p => lines.push([
                p.name, new Date(p.since * 1000).toLocaleString(), p.online ? 'En ligne' : new Date(p.end * 1000).toLocaleString(), p.dur, p.n, p.total
            ].map(q).join(';')));
            const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'mumble_historique_' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        }
        if ($('csvBtn')) $('csvBtn').addEventListener('click', exportCsv);

        const MUMBLE_ENABLE_CONNECT_ALERT = <?= json_encode(MUMBLE_ENABLE_CONNECT_ALERT) ?>;
        const MUMBLE_ENABLE_DISCONNECT_ALERT = <?= json_encode(MUMBLE_ENABLE_DISCONNECT_ALERT) ?>;
        const MUMBLE_ALERT_GATEWAY_DOWN = <?= json_encode(MUMBLE_ALERT_GATEWAY_DOWN) ?>;

        // ---------- Alertes sonores / messages ----------
        const SOUND_KEY = 'svxreflector_sound_mumble';
        let soundOn = false, audioCtx = null;
        try { soundOn = localStorage.getItem(SOUND_KEY) === '1'; } catch (e) {}
        const soundBtn = document.getElementById('soundToggle');
        function refreshSoundBtn() {
            if (!soundBtn) return;
            soundBtn.querySelector('i').className = soundOn ? 'fa-solid fa-bell' : 'fa-solid fa-bell-slash';
            soundBtn.querySelector('span').textContent = soundOn ? 'Alerte : ON' : 'Alerte : OFF';
        }
        function tones(list) {
            if (!soundOn) return;
            try {
                const C = window.AudioContext || window.webkitAudioContext;
                if (!audioCtx && C) audioCtx = new C();
                const ctx = audioCtx; if (!ctx) return;
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
        // Le navigateur bloque le son tant qu'il n'y a pas eu de clic : au premier clic / touche, on « débloque » l'audio
        function unlockAudio() {
            try {
                const C = window.AudioContext || window.webkitAudioContext;
                if (!audioCtx && C) audioCtx = new C();
                if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
            } catch (e) {}
        }
        ['click', 'mousedown', 'pointerdown', 'keydown', 'touchstart'].forEach(ev => document.addEventListener(ev, unlockAudio, { passive: true }));
        // retour sur la page / l'onglet : on relance l'audio
        document.addEventListener('visibilitychange', () => { if (!document.hidden) unlockAudio(); });
        window.addEventListener('focus', unlockAudio);
        window.addEventListener('pageshow', unlockAudio);

        function toast(html, bad) {
            const box = document.getElementById('toastBox');
            if (!box) return;
            const d = document.createElement('div');
            d.style.cssText = 'background:var(--card-bg); border:1px solid ' + (bad ? 'var(--accent-red)' : 'var(--accent-green)') + '; color:var(--text-main); padding:10px 14px; border-radius:10px; font-size:0.88rem; box-shadow:0 8px 24px rgba(0,0,0,.35);';
            d.innerHTML = html;
            box.appendChild(d);
            setTimeout(() => d.remove(), 9000);
        }
        if (soundBtn) {
            soundBtn.addEventListener('click', () => {
                soundOn = !soundOn;
                try { localStorage.setItem(SOUND_KEY, soundOn ? '1' : '0'); } catch (e) {}
                refreshSoundBtn();
                if (soundOn) tones([880]);
            });
            refreshSoundBtn();
        }

        let knownKeys = null;   // personnes déjà vues (null = premier chargement, pas d'alerte)
        let knownNames = {};    // clé -> nom (pour annoncer un départ)
        let gwWasUp = null;
        function checkAlerts() {
            const keys = new Set(mum.online.filter(u => !u.gw).map(u => u.id + ':' + u.since_ts));
            if (knownKeys !== null && MUMBLE_ENABLE_CONNECT_ALERT) {
                mum.online.filter(u => !u.gw && !knownKeys.has(u.id + ':' + u.since_ts)).forEach(u => {
                    toast('<i class="fa-solid fa-user-plus" style="color:var(--accent-green)"></i> <strong>' + esc(u.name) + '</strong> vient de se connecter sur Mumble');
                    tones([660, 880]);
                });
            }
            if (knownKeys !== null && MUMBLE_ENABLE_DISCONNECT_ALERT) {
                knownKeys.forEach(k => {
                    if (!keys.has(k)) {
                        toast('<i class="fa-solid fa-user-minus" style="color:var(--accent-red)"></i> <strong>' + esc(knownNames[k] || '?') + '</strong> a quitté Mumble', true);
                        tones([880, 660, 440]);
                    }
                });
            }
            knownNames = {};
            mum.online.filter(u => !u.gw).forEach(u => { knownNames[u.id + ':' + u.since_ts] = u.name; });
            knownKeys = keys;
            const up = mum.online.some(u => u.gw);
            if (gwWasUp !== null && MUMBLE_ALERT_GATEWAY_DOWN) {
                if (gwWasUp && !up) { toast('<i class="fa-solid fa-triangle-exclamation" style="color:var(--accent-red)"></i> Passerelle <strong>' + esc(mum.gateway || 'RNFA-GW') + '</strong> hors ligne', true); tones([440, 330]); }
                if (!gwWasUp && up) { toast('<i class="fa-solid fa-circle-check" style="color:var(--accent-green)"></i> Passerelle <strong>' + esc(mum.gateway || 'RNFA-GW') + '</strong> de retour'); tones([523, 784]); }
            }
            gwWasUp = up;
        }

        function renderGwQuality() {
            if (!$('gwChip')) return;
            const now = nowSrv();
            const sess = mum.history.filter(h => h.gw).map(h => ({ s: h.since_ts, e: h.end_ts, reason: h.reason }));
            const cur = mum.online.find(u => u.gw);
            if (cur) sess.push({ s: cur.since_ts, e: now, reason: '' });
            const w24 = 86400, w7 = 7 * 86400;
            const inWin = sec => sess.filter(x => x.e >= now - sec);
            const toN = sec => sess.filter(x => x.reason === 'Timeout' && x.e >= now - sec).length;
            const reN = sec => sess.filter(x => x.s >= now - sec).length;
            let cov = 0;
            inWin(w24).forEach(x => { cov += Math.max(0, Math.min(x.e, now) - Math.max(x.s, now - w24)); });
            const avail = Math.min(100, cov / w24 * 100);
            const score = 5 * toN(w24) + reN(w24);
            const chip = $('gwChip');
            chip.style.color = '';
            if (!cur) { chip.textContent = 'HORS LIGNE'; chip.className = 'tag red'; }
            else if (score >= 40) { chip.textContent = 'INSTABLE'; chip.className = 'tag red'; }
            else if (score >= 10) { chip.textContent = 'MOYENNE'; chip.className = 'tag'; chip.style.color = '#f59e0b'; }
            else { chip.textContent = 'STABLE'; chip.className = 'tag'; chip.style.color = 'var(--accent-green)'; }
            setText('gqUp', cur ? fmtDur(now - cur.since_ts) : 'Hors ligne');
            setText('gqAvail', avail.toFixed(1) + ' %');
            setText('gqTo', toN(w24) + ' / ' + toN(w7));
            setText('gqRe', reN(w24) + ' / ' + reN(w7));
            const last = sess.filter(x => x.reason === 'Timeout').sort((a, b) => b.e - a.e)[0];
            setText('gqLast', last ? fmtDT(last.e) : 'Aucun');
            const longest = Math.max(0, ...inWin(w7).map(x => Math.min(x.e, now) - x.s));
            setText('gqLong', longest ? fmtDur(longest) : '—');
        }

        function render() {
            const talkStart = ref.talk_starts ? ref.talk_starts[GW_REFLECTOR] : undefined;
            const talking = talkStart !== undefined;
            const gwUp = mum.online.some(u => u.gw);
            renderGwQuality();
            renderToday();
            if (MUMBLE_SHOW_TAB_COUNT) document.title = '(' + mum.online.filter(u => !u.gw).length + ') Mumble • SvxReflector Live';

            setText('kOnline', mum.online.filter(u => !u.gw).length);
            const kg = $('kGw');
            if (kg) {
                kg.textContent = gwUp ? 'EN LIGNE' : 'HORS LIGNE';
                kg.style.color = gwUp ? 'var(--accent-green)' : 'var(--accent-red)';
            }
            setText('kRej', mum.rejected || 0);

            // Bandeau PTT
            const banner = $('pttBanner');
            if (banner) {
            banner.classList.toggle('on', talking);
            document.getElementById('pttWho').textContent = talking ? 'Mumble (' + GW_REFLECTOR + ')' : 'Aucune émission';
            ['pttBadge', 'pttTimer', 'pttBarWrap'].forEach(id => document.getElementById(id).style.display = talking ? '' : 'none');
            if (talking) {
                const el = Math.max(0, nowSrv() - talkStart);
                document.getElementById('pttTimer').textContent = fmtDur(el);
                const pct = Math.min(100, el / TALK_MAX_SEC * 100);
                const b = document.getElementById('pttBar');
                b.style.width = pct + '%';
                b.style.background = pct > 85 ? 'var(--accent-red)' : 'var(--accent-green)';
            }
            }

            // Personnes connectées
            const box = $('users');
            const shownUsers = mum.online.filter(u => MUMBLE_SHOW_GATEWAY_CARD || !u.gw);
            if (box) {
            if (!shownUsers.length) {
                box.innerHTML = '<span class="empty">Personne n\'est connecté sur Mumble.</span>';
            } else {
                box.innerHTML = shownUsers.map(u => {
                    const ptt = u.gw && talking;
                    return '<div class="ucard' + (u.gw ? ' gw' : '') + (ptt ? ' ptt' : '') + '">' +
                        '<div class="n"><span>' + esc(u.name) + '</span>' +
                        (ptt ? '<span class="tag red">PTT</span>' : (u.gw ? '<span class="tag">passerelle</span>' : '')) + '</div>' +
                        '<div class="m">' +
                        (MUMBLE_SHOW_USER_SINCE ? '<i class="fa-solid fa-right-to-bracket"></i> Connecté à ' + fmtDT(u.since_ts) : '') +
                        (MUMBLE_SHOW_USER_SINCE && MUMBLE_SHOW_USER_DURATION ? '<br>' : '') +
                        (MUMBLE_SHOW_USER_DURATION ? '<i class="fa-solid fa-hourglass-half"></i> Depuis ' + fmtDur(nowSrv() - u.since_ts) : '') +
                        '</div></div>';
                }).join('');
            }
            }

            // Transmissions Mumble : une seule ligne par transmission (ON en rouge, puis OFF)
            const ev = (ref.recent_events || []).filter(e => e.callsign === GW_REFLECTOR);
            const txRows = [];
            if (talking) {
                const el = Math.max(0, nowSrv() - talkStart);
                const tgOn = ((ev.find(e => e.event === 'TALK_START') || {}).talkgroup) || (ref.active_tg || 0);
                txRows.push({ ts: talkStart, tg: tgOn, on: true, dur: el });
            }
            ev.filter(e => e.event === 'TALK_STOP').forEach(e => {
                const d = e.talk_time || 0;
                txRows.push({ ts: e.ts - d, tg: e.talkgroup, on: false, dur: d });
            });
            setText('kTx', txRows.filter(r => !r.on).length);
            const pb = $('pttBody');
            if (pb) pb.innerHTML = txRows.length ? txRows.slice(0, 30).map(r =>
                '<tr' + (r.on ? ' style="background:var(--bad-bg)"' : '') + '><td class="mono">' + fmtDT(r.ts) + '</td><td class="mono"><strong' + (r.on ? ' style="color:var(--bad-text)"' : '') + '>' + esc(GW_REFLECTOR) + '</strong></td>' +
                '<td>' + (r.on ? '<span class="tag red">PTT ON</span>' : '<span class="tag" style="color:var(--accent-green);border-color:var(--accent-green)">PTT OFF</span>') + '</td>' +
                '<td class="mono">' + (r.tg ? 'TG ' + r.tg : '—') + '</td>' +
                '<td class="mono">' + fmtDur(r.dur) + '</td></tr>'
            ).join('') : '<tr><td colspan="5" class="empty">Aucune transmission Mumble détectée.</td></tr>';

            // Historique : une seule ligne par personne
            const people = {};
            mum.history.forEach(h => {          // history est classé du plus récent au plus ancien
                const p = people[h.name] || (people[h.name] = { name: h.name, since: h.since_ts, end: h.end_ts, dur: h.duration, reason: h.reason, n: 0, total: 0, online: false });
                p.n++; p.total += h.duration;
            });
            mum.online.forEach(u => {
                const p = people[u.name] || (people[u.name] = { name: u.name, n: 0, total: 0 });
                Object.assign(p, { since: u.since_ts, end: null, dur: nowSrv() - u.since_ts, reason: '', online: true });
                p.n++; p.total += nowSrv() - u.since_ts;
            });
            const list = Object.values(people).sort((x, y) => (y.online - x.online) || ((y.end || y.since) - (x.end || x.since)));
            historyRows = list;
            const hb = $('histBody');
            if (hb) hb.innerHTML = list.length ? list.map(p =>
                '<tr><td class="mono"><strong>' + esc(p.name) + '</strong></td><td class="mono">' + fmtDT(p.since) + '</td>' +
                '<td class="mono">' + (p.online ? '<span class="tag" style="color:var(--accent-green);border-color:var(--accent-green)">En ligne</span>' : fmtDT(p.end)) + '</td>' +
                '<td class="mono">' + fmtDur(p.dur) + '</td><td class="mono">' + p.n + '</td><td class="mono">' + fmtDur(p.total) + '</td></tr>'
            ).join('') : '<tr><td colspan="6" class="empty">Aucun historique.</td></tr>';
        }

        async function getJson(url) {
            const r = await fetch(url, { cache: 'no-store' });
            if (r.status === 401) { location.href = '../index.php'; return null; }
            return r.json();
        }
        async function pollMumble() {
            try {
                const d = await getJson('api_mumble.php');
                if (!d) return;
                const eb = document.getElementById('errBox');
                if (d.error) { eb.style.display = 'block'; eb.textContent = d.error; } else { eb.style.display = 'none'; }
                mum = Object.assign(mum, d);
                if (!d.error) checkAlerts();
                skew = Math.floor(Date.now() / 1000) - (d.now_ts || Math.floor(Date.now() / 1000));
                render();
            } catch (e) {}
        }
        async function pollRef() {
            try {
                const d = await getJson('api_log.php');
                if (d && d.state) { ref = d.state; render(); }
            } catch (e) {}
        }

        // Thème
        (function () {
            const root = document.documentElement, icon = document.getElementById('themeIcon'), label = document.getElementById('themeLabel');
            function apply(t) { root.setAttribute('data-theme', t); icon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; label.textContent = t === 'dark' ? 'Mode clair' : 'Mode sombre'; }
            apply(root.getAttribute('data-theme') || 'dark');
            document.getElementById('themeToggle').addEventListener('click', () => {
                const t = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                apply(t); try { localStorage.setItem('svxreflector_theme', t); } catch (e) {}
            });
        })();

        pollMumble(); pollRef();
        setInterval(pollMumble, 3000);
        setInterval(pollRef, 1000);
        setInterval(render, 1000);
    </script>
</body>
</html>
