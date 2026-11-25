<?php
/**
 * fichier carte.php
 * Carte des stations – SvxReflector Live
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
    <title>Carte • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/carte.css?v=<?= (int)@filemtime(__DIR__ . '/../css/carte.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-map-location-dot"></i>
            <div>
                <h1>CARTE DES STATIONS</h1>
                <small style="color: var(--text-muted);">RNFA Network Monitor</small>
            </div>
        </div>
        <div class="header-right">
            <a class="btn-action" href="../index.php"><i class="fa-solid fa-gauge-high"></i> <span>Dashboard</span></a>
            <a class="btn-action" href="info.php"><i class="fa-solid fa-address-card"></i> <span>Infos utilisateurs</span></a>
            <a class="btn-action" href="stats.php"><i class="fa-solid fa-chart-column"></i> <span>Statistiques</span></a>
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

    <div class="toolbar">
        <span class="lg"><i class="fa-solid fa-circle g"></i> En ligne</span>
        <span class="lg"><i class="fa-solid fa-circle r"></i> En PTT</span>
        <span id="mapInfo">—</span>
        <?php if (MAP_SHOW_BUTTONS): ?>
        <button type="button" class="btn-action" id="btnFrance"><i class="fa-solid fa-crosshairs"></i> <span>France</span></button>
        <?php endif; ?>
        <span class="right" id="noCoords"></span>
    </div>

    <div class="error-box" id="errorBox"></div>

    <?php if (MAP_SHOW_STATION_LIST): ?>
    <div class="toolbar" id="stationList" style="margin-top:-8px;">
        <input type="search" id="stSearch" placeholder="Rechercher une station…" style="background:var(--surface-subtle); color:var(--text-main); border:1px solid var(--border-color); border-radius:20px; padding:6px 12px; font-size:0.82rem; min-width:190px;">
        <span id="stChips" style="display:flex; flex-wrap:wrap; gap:8px;"></span>
    </div>
    <?php endif; ?>

    <div id="map"></div>

    <footer>
        <?= dashboard_footer_html() ?>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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

        const FOCUS_FRANCE = <?= json_encode(MAP_FOCUS_FRANCE) ?>;
        // France métropolitaine (Corse comprise)
        const FRANCE_BOUNDS = [[41.0, -5.6], [51.4, 9.8]];
        const inFrance = n => n.lat >= 41.0 && n.lat <= 51.4 && n.lon >= -5.6 && n.lon <= 9.8;

        const map = L.map('map', { worldCopyJump: true, minZoom: <?= (int)MAP_MIN_ZOOM ?> }).setView([46.6, 2.5], 6);
        if (FOCUS_FRANCE) map.fitBounds(FRANCE_BOUNDS, { padding: [10, 10] });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; contributeurs <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        window.addEventListener('load', () => map.invalidateSize());

        const markers = {};
        let firstFit = true;
        let lastPlaced = [];
        let lastNodes = {};
        let wasTalking = new Set();
        const AUTO_ZOOM_PTT = <?= json_encode(MAP_AUTO_ZOOM_PTT) ?>;
        const ANTENNA_ICON = <?= json_encode(MAP_ANTENNA_ICON) ?>;

        const TYPE_ICONS = <?= json_encode(MAP_TYPE_ICONS) ?>;
        const SHOW_LEGEND = <?= json_encode(MAP_SHOW_LEGEND) ?>;

        // Type de station d'après l'indicatif : -RE relais, -GW passerelle, -M / /M mobile, -P / /P portable
        function stationType(cs) {
            if (!TYPE_ICONS) return 'relay';
            const c = String(cs || '').toUpperCase().trim();
            if (/-RE\d*$/.test(c)) return 'relay';
            if (/-GW\d*$/.test(c)) return 'gateway';
            if (/[-\/]M$/.test(c)) return 'mobile';
            if (/[-\/]P$/.test(c)) return 'portable';
            return 'station';
        }
        const TYPE_LABELS = { relay: 'Relais', gateway: 'Passerelle (gateway)', mobile: 'Mobile', portable: 'Portable', station: 'Station' };

        // Dessins 40x40 : ondes = ant-w1 / ant-w2 (elles clignotent en PTT)
        const TYPE_SVG = {
            relay:
                '<path class="ant-body" d="M14 36 L20 17 L26 36 M16.6 28 H23.4 M18.3 22.5 H21.7 M20 17 V36"/>' +
                '<circle class="ant-dot" cx="20" cy="12" r="2.4"/>' +
                '<path class="ant-wave ant-w1" d="M24.2 7.8 A6 6 0 0 1 24.2 16.2 M15.8 7.8 A6 6 0 0 0 15.8 16.2"/>' +
                '<path class="ant-wave ant-w2" d="M27.1 4.9 A10 10 0 0 1 27.1 19.1 M12.9 4.9 A10 10 0 0 0 12.9 19.1"/>',
            gateway:
                '<rect class="ant-body" x="10" y="14" width="20" height="15" rx="2.5"/>' +
                '<path class="ant-body" d="M14 19.5 H26 M23 16.8 L26 19.5 L23 22.2 M26 24.5 H14 M17 21.8 L14 24.5 L17 27.2 M14 29 V33 M26 29 V33"/>' +
                '<path class="ant-body" d="M20 14 V9"/><circle class="ant-dot" cx="20" cy="7" r="2.2"/>' +
                '<path class="ant-wave ant-w1" d="M23.6 3.6 A5.5 5.5 0 0 1 23.6 10.4 M16.4 3.6 A5.5 5.5 0 0 0 16.4 10.4"/>' +
                '<path class="ant-wave ant-w2" d="M26.3 1.2 A9.5 9.5 0 0 1 26.3 12.8 M13.7 1.2 A9.5 9.5 0 0 0 13.7 12.8"/>',
            mobile:
                '<path class="ant-body" d="M5 29 V23 L10.5 22.5 L15 16 H25 L29.5 22.5 L35 23 V29 Z M15.5 22.5 H25"/>' +
                '<circle class="ant-dot" cx="12" cy="29.5" r="3.4"/><circle class="ant-dot" cx="28" cy="29.5" r="3.4"/>' +
                '<path class="ant-wave ant-w1" d="M16 11.5 A5 5 0 0 1 24 11.5"/>' +
                '<path class="ant-wave ant-w2" d="M12.5 8 A9.5 9.5 0 0 1 27.5 8"/>',
            portable:
                '<circle class="ant-dot" cx="20" cy="10" r="3.1"/>' +
                '<path class="ant-body" d="M20 14.5 V25 M20 17.5 L14.5 22.5 M20 17.5 L26 21 M20 25 L14.5 34 M20 25 L25.5 29 L24.5 35"/>' +
                '<path class="ant-wave ant-w1" d="M24.4 5.8 A6 6 0 0 1 24.4 14.2 M15.6 5.8 A6 6 0 0 0 15.6 14.2"/>' +
                '<path class="ant-wave ant-w2" d="M27.3 2.9 A10 10 0 0 1 27.3 17.1 M12.7 2.9 A10 10 0 0 0 12.7 17.1"/>',
            station:
                '<circle class="ant-dot" cx="20" cy="32" r="3.6"/>' +
                '<path class="ant-wave ant-w1" d="M24.2 27.8 A6 6 0 0 1 24.2 36.2 M15.8 27.8 A6 6 0 0 0 15.8 36.2"/>' +
                '<path class="ant-wave ant-w2" d="M27.1 24.9 A10 10 0 0 1 27.1 39.1 M12.9 24.9 A10 10 0 0 0 12.9 39.1"/>'
        };
        const TYPE_ANCHOR = { relay: [20, 36], gateway: [20, 34], mobile: [20, 34], portable: [20, 35], station: [20, 32] };

        // Icône : verte = en ligne, rouge animée = en PTT
        function antennaIcon(talker, type) {
            type = TYPE_SVG[type] ? type : 'relay';
            return L.divIcon({
                className: 'ant-icon t-' + type + ' ' + (talker ? 'ptt' : 'on'),
                html: '<svg viewBox="0 0 40 40" aria-hidden="true">' + TYPE_SVG[type] + '</svg>',
                iconSize: [40, 40], iconAnchor: TYPE_ANCHOR[type], popupAnchor: [0, -30], tooltipAnchor: [14, -22]
            });
        }

        // Légende des icônes (n'affiche que les types réellement présents sur la carte)
        let legendBox = null;
        function updateLegend(types) {
            if (!SHOW_LEGEND || !TYPE_ICONS) return;
            if (!legendBox) {
                const Lg = L.Control.extend({
                    options: { position: 'bottomleft' },
                    onAdd: function () {
                        const d = L.DomUtil.create('div', 'map-legend');
                        legendBox = d;
                        return d;
                    }
                });
                map.addControl(new Lg());
            }
            const order = ['relay', 'gateway', 'mobile', 'portable', 'station'];
            legendBox.innerHTML = order.filter(t => types.has(t)).map(t =>
                '<div class="lg-row"><span class="ant-icon on t-' + t + ' lg-ic"><svg viewBox="0 0 40 40">' + TYPE_SVG[t] + '</svg></span>' + TYPE_LABELS[t] + '</div>'
            ).join('');
            legendBox.style.display = legendBox.innerHTML ? 'block' : 'none';
        }

        <?php if (MAP_SHOW_SCALE): ?>L.control.scale({ metric: true, imperial: false }).addTo(map);<?php endif; ?>
        <?php if (MAP_SHOW_FULLSCREEN): ?>
        (function () {
            const Fs = L.Control.extend({
                options: { position: 'topleft' },
                onAdd: function () {
                    const div = L.DomUtil.create('div', 'leaflet-bar');
                    const a = L.DomUtil.create('div', 'fs-btn', div);
                    a.innerHTML = '<i class="fa-solid fa-expand"></i>';
                    a.title = 'Plein écran';
                    L.DomEvent.disableClickPropagation(div);
                    L.DomEvent.on(a, 'click', function () {
                        const el = document.getElementById('map');
                        if (document.fullscreenElement) document.exitFullscreen();
                        else if (el.requestFullscreen) el.requestFullscreen();
                    });
                    return div;
                }
            });
            map.addControl(new Fs());
            document.addEventListener('fullscreenchange', () => setTimeout(() => map.invalidateSize(), 200));
        })();
        <?php endif; ?>

        function focusStation(cs) {
            const m = markers[cs];
            if (!m) return;
            map.flyTo(m.getLatLng(), Math.max(map.getZoom(), 11), { duration: 0.8 });
            m.openPopup();
        }

        function renderStationList() {
            const box = document.getElementById('stChips');
            if (!box) return;
            const q = (document.getElementById('stSearch').value || '').trim().toLowerCase();
            const items = Object.entries(lastNodes)
                .filter(([cs, n]) => !q || cs.toLowerCase().includes(q) || clean(n.name).toLowerCase().includes(q) || (clean(n.node_location) + ' ' + clean(n.location)).toLowerCase().includes(q))
                .sort((a, b) => (b[1].is_talker - a[1].is_talker) || a[0].localeCompare(b[0]));
            box.innerHTML = items.map(([cs, n]) => {
                const placed = n.lat !== null && n.lat !== undefined && n.lon !== null && n.lon !== undefined;
                return `<span class="st-chip${n.is_talker ? ' ptt' : ''}${placed ? '' : ' nopos'}" data-cs="${esc(cs)}" title="${placed ? 'Zoomer sur la station' : 'Pas de coordonnées'}">${esc(cs)}</span>`;
            }).join('') || '<span style="color:var(--text-muted); font-size:0.82rem;">Aucune station</span>';
        }
        const stBox = document.getElementById('stChips');
        if (stBox) {
            stBox.addEventListener('click', e => {
                const c = e.target.closest('.st-chip');
                if (c && !c.classList.contains('nopos')) focusStation(c.dataset.cs);
            });
            document.getElementById('stSearch').addEventListener('input', renderStationList);
        }

        const btnF = document.getElementById('btnFrance');
        const btnA = document.getElementById('btnAll');
        if (btnF) btnF.addEventListener('click', () => {
            const fr = lastPlaced.filter(inFrance);
            if (fr.length > 1) map.fitBounds(fr.map(n => [n.lat, n.lon]), { padding: [50, 50], maxZoom: 9 });
            else map.fitBounds(FRANCE_BOUNDS, { padding: [10, 10] });
        });
        if (btnA) btnA.addEventListener('click', () => {
            if (lastPlaced.length) map.fitBounds(lastPlaced.map(n => [n.lat, n.lon]), { padding: [40, 40], maxZoom: 9 });
        });

        function popupHtml(cs, n) {
            const place = clean(n.node_location) || clean(n.location);
            const lines = [
                `<strong class="cs">${esc(cs)}</strong>`,
                n.is_talker ? '<span class="pp-ptt">● En PTT</span>' : 'En ligne',
                TYPE_ICONS ? 'Type : ' + TYPE_LABELS[stationType(cs)] : '',
                clean(n.name) ? 'Nom : ' + esc(clean(n.name)) : '',
                place ? esc(place) : '',
                clean(n.locator) ? 'Locator : ' + esc(clean(n.locator)) : '',
                (n.monitored_tgs && n.monitored_tgs.length) ? 'TG : ' + esc(n.monitored_tgs.join(', ')) : ''
            ].filter(Boolean);
            return lines.join('<br>');
        }

        function updateMap(nodesObj) {
            const entries = Object.entries(nodesObj || {});
            const placed = entries.filter(([, n]) => n.lat !== null && n.lon !== null && n.lat !== undefined && n.lon !== undefined);
            const seen = new Set();

            placed.forEach(([cs, n]) => {
                seen.add(cs);
                const color = n.is_talker ? '#ef4444' : '#10b981';
                const radius = n.is_talker ? 13 : 9;
                const latlng = [n.lat, n.lon];
                if (markers[cs]) {
                    markers[cs].setLatLng(latlng);
                    if (ANTENNA_ICON) {
                        if (markers[cs]._talker !== !!n.is_talker) {
                            markers[cs].setIcon(antennaIcon(!!n.is_talker, stationType(cs)));
                            markers[cs]._talker = !!n.is_talker;
                        }
                        markers[cs].setZIndexOffset(n.is_talker ? 1000 : 0);
                    } else {
                        markers[cs].setStyle({ color: color, fillColor: color, radius: radius });
                    }
                    markers[cs].setPopupContent(popupHtml(cs, n));
                } else {
                    markers[cs] = ANTENNA_ICON
                        ? L.marker(latlng, { icon: antennaIcon(!!n.is_talker, stationType(cs)), zIndexOffset: n.is_talker ? 1000 : 0 })
                        : L.circleMarker(latlng, { radius: radius, color: color, fillColor: color, fillOpacity: 0.85, weight: 2 });
                    markers[cs]._talker = !!n.is_talker;
                    markers[cs].bindTooltip(cs).bindPopup(popupHtml(cs, n)).addTo(map);
                }
                if (n.is_talker && !ANTENNA_ICON) markers[cs].bringToFront();
            });

            Object.keys(markers).forEach(cs => {
                if (!seen.has(cs)) {
                    map.removeLayer(markers[cs]);
                    delete markers[cs];
                }
            });

            updateLegend(new Set(placed.map(([cs]) => stationType(cs))));
            lastPlaced = placed.map(([, n]) => n);
            lastNodes = nodesObj || {};
            renderStationList();
            if (AUTO_ZOOM_PTT) {
                const nowTalking = new Set(entries.filter(([, n]) => n.is_talker).map(([cs]) => cs));
                for (const cs of nowTalking) { if (!wasTalking.has(cs) && markers[cs]) { focusStation(cs); break; } }
                wasTalking = nowTalking;
            }
            if (firstFit && placed.length > 0) {
                if (FOCUS_FRANCE) {
                    const fr = lastPlaced.filter(inFrance);
                    // une seule station en France : on garde la vue France ; sinon on cadre les stations françaises
                    if (fr.length > 1) map.fitBounds(fr.map(n => [n.lat, n.lon]), { padding: [50, 50], maxZoom: 9 });
                    else map.fitBounds(FRANCE_BOUNDS, { padding: [10, 10] });
                } else {
                    map.fitBounds(lastPlaced.map(n => [n.lat, n.lon]), { padding: [40, 40], maxZoom: 9 });
                }
                firstFit = false;
            }

            const talking = entries.filter(([, n]) => n.is_talker).length;
            document.getElementById('mapInfo').innerHTML =
                `<strong>${placed.length}</strong> station(s) placée(s) • <strong>${talking}</strong> en PTT`;
            const missing = entries.length - placed.length;
            document.getElementById('noCoords').textContent = missing > 0 ? missing + ' station(s) sans coordonnées' : '';
        }

        async function loadStatus() {
            const errBox = document.getElementById('errorBox');
            try {
                const res = await fetch('api_status.php?_=' + Date.now(), { cache: 'no-store' });
                if (res.status === 401) { window.location.href = '../index.php'; return; }
                const data = await res.json();
                if (data.error) {
                    errBox.textContent = data.error;
                    errBox.style.display = 'block';
                } else {
                    errBox.style.display = 'none';
                }
                updateMap(data.nodes);
            } catch (e) {
                errBox.textContent = 'Impossible de contacter le serveur.';
                errBox.style.display = 'block';
            }
        }

        loadStatus();
        setInterval(loadStatus, 5000);
    </script>
</body>
</html>
