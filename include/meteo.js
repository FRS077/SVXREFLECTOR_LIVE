/**
 * meteo.js - petit encart météo discret (SvxReflector Live)
 * Température, icône, lever / coucher du soleil, selon la position du visiteur (ou le lieu par défaut de config.php).
 * Données : Open-Meteo (gratuit, sans clé). Réglages : section 10d de config.php.
 */
(function () {
    var C = window.WEATHER_CFG, pill = document.getElementById('weatherPill');
    if (!C || !pill) return;

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }
    function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }

    // Code météo Open-Meteo -> [icône Font Awesome, libellé]
    function info(code, day) {
        if (code === 0) return day ? ['fa-sun', 'Ciel dégagé'] : ['fa-moon', 'Nuit claire'];
        if (code === 1 || code === 2) return [day ? 'fa-cloud-sun' : 'fa-cloud-moon', 'Éclaircies'];
        if (code === 3) return ['fa-cloud', 'Couvert'];
        if (code === 45 || code === 48) return ['fa-smog', 'Brouillard'];
        if (code >= 51 && code <= 57) return ['fa-cloud-rain', 'Bruine'];
        if ((code >= 61 && code <= 67) || (code >= 80 && code <= 82)) return ['fa-cloud-showers-heavy', 'Pluie'];
        if ((code >= 71 && code <= 77) || code === 85 || code === 86) return ['fa-snowflake', 'Neige'];
        if (code >= 95) return ['fa-cloud-bolt', 'Orage'];
        return ['fa-cloud', 'Nuageux'];
    }

    function hhmm(iso) { var m = /T(\d{2}:\d{2})/.exec(iso || ''); return m ? m[1] : ''; }

    function draw(d, place) {
        var cur = d.current || {}, day = d.daily || {};
        var inf = info(cur.weather_code, cur.is_day !== 0);
        var parts = [];
        if (C.icon) parts.push('<i class="fa-solid ' + inf[0] + '"></i>');
        if (C.temp && cur.temperature_2m != null) parts.push('<b>' + Math.round(cur.temperature_2m) + '°C</b>');
        var rise = hhmm(day.sunrise && day.sunrise[0]), set = hhmm(day.sunset && day.sunset[0]);
        if (C.sun && rise && set) parts.push('<span class="w-sun"><i class="fa-solid fa-arrow-up"></i>' + rise + ' <i class="fa-solid fa-arrow-down"></i>' + set + '</span>');
        if (!parts.length) { pill.style.display = 'none'; return; }
        pill.innerHTML = parts.join('');
        pill.title = inf[1] + ' • ' + place + (rise ? ' • lever ' + rise + ' • coucher ' + set : '');
        pill.style.display = '';
    }

    function fetchWeather(lat, lon, place) {
        var key = 'svx_weather_' + lat.toFixed(2) + '_' + lon.toFixed(2);
        try {
            var c = JSON.parse(store(key) || 'null');
            if (c && Date.now() - c.t < C.refresh * 60000) { draw(c.d, place); return; }
        } catch (e) {}
        var url = 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lon +
            '&current=temperature_2m,weather_code,is_day&daily=sunrise,sunset&timezone=auto&forecast_days=1';
        fetch(url, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.current) return;
            store(key, JSON.stringify({ t: Date.now(), d: d }));
            draw(d, place);
        }).catch(function () { /* météo indisponible : l'encart reste masqué */ });
    }

    function start() {
        function dflt() { fetchWeather(C.lat, C.lon, C.name); }
        if (!C.ask || !navigator.geolocation) { dflt(); return; }
        // Position approximative uniquement dans le navigateur ; rien n'est envoyé à notre serveur.
        navigator.geolocation.getCurrentPosition(function (p) {
            fetchWeather(Math.round(p.coords.latitude * 100) / 100, Math.round(p.coords.longitude * 100) / 100, 'votre position');
        }, dflt, { timeout: 8000, maximumAge: 3600000 });
    }
    start();
    setInterval(start, C.refresh * 60000);
})();
