<?php
/**
 * fichier nouveautes.php
 * Page Nouveautés : fonctionnalités du dashboard, historique des versions et notes de version GitHub
 * Auteur : FRS077 Romuald pour RNFA
 */
ini_set('display_errors', 0);
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/defaults_tg.php';

$isLogged = !ENABLE_AUTH || (!empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true);
if (!$isLogged || !NEWS_PAGE_ENABLE) { header('Location: ../index.php'); exit; }

$features = [
    ['fa-satellite-dish', 'Stations en direct', 'Stations actives, qui parle, durée de parole, dernière station connectée et dernières déconnexions.'],
    ['fa-hashtag', 'Talkgroupes', 'Les TG activés, les stations présentes sur chacun et celles restées hors du TG principal.'],
    ['fa-map-location-dot', 'Carte', 'Toutes les stations sur la carte, icônes par type (relais, passerelle, mobile, portable).'],
    ['fa-chart-column', 'Statistiques', 'Activité du jour, de la semaine, du mois et de l\'année, classement des stations.'],
    ['fa-headset', 'Mumble', 'Personnes connectées, passerelle et historique des connexions Mumble.'],
    ['fa-list-check', 'Log', 'Les événements utiles du réflecteur, adresses IP masquées.'],
    ['fa-server', 'Serveur', 'Processeur, température, mémoire, disque, réseau et temps de service.'],
    ['fa-cloud-sun', 'Météo', 'Température et lever / coucher du soleil selon ta position.'],
    ['fa-bell', 'Alertes', 'Bip sonore quand une station passe en PTT, alerte si un relais disparaît.'],
    ['fa-circle-half-stroke', 'Thème clair / sombre', 'Au choix, mémorisé sur tous les écrans du dashboard.'],
];
$changelog = is_readable(__DIR__ . '/changelog.php') ? (include __DIR__ . '/changelog.php') : [];
$esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveautés • SvxReflector Live</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        try {
            var t = localStorage.getItem('svxreflector_theme');
            if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="../css/nouveautes.css?v=<?= (int)@filemtime(__DIR__ . '/../css/nouveautes.css') ?>">
    <link rel="stylesheet" href="../css/dynamic.php?v=<?= (int)@filemtime(__DIR__ . '/../config.php') ?>">
</head>
<body>

    <header>
        <div class="logo-area">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <h1>NOUVEAUTÉS</h1>
                <small style="color: var(--text-muted);">Fonctionnalités et mises à jour</small>
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

    <div class="upd-card" id="updCard">
        <div class="upd-ic"><i class="fa-solid fa-circle-check" id="updIcon"></i></div>
        <div class="upd-main">
            <div class="upd-title" id="updTitle">Vérification des mises à jour…</div>
            <div class="upd-sub" id="updSub"></div>
        </div>
        <a class="btn-action" id="updLink" href="#" target="_blank" rel="noopener" style="display:none;"><i class="fa-brands fa-github"></i> <span>Voir sur GitHub</span></a>
    </div>

    <div class="card-panel" id="notesPanel" style="display:none;">
        <div class="panel-header"><h2><i class="fa-solid fa-note-sticky"></i> Notes de version <span id="notesVer"></span></h2></div>
        <div class="notes" id="notesBody"></div>
    </div>

    <div class="card-panel">
        <div class="panel-header"><h2><i class="fa-solid fa-star"></i> Fonctionnalités</h2></div>
        <div class="feat-grid">
            <?php foreach ($features as $f): ?>
            <div class="feat"><i class="fa-solid <?= $esc($f[0]) ?>"></i><div><b><?= $esc($f[1]) ?></b><span><?= $esc($f[2]) ?></span></div></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($changelog): ?>
    <div class="card-panel">
        <div class="panel-header"><h2><i class="fa-solid fa-clock-rotate-left"></i> Historique des versions</h2></div>
        <div class="chg">
            <?php foreach ($changelog as $c): ?>
            <div class="chg-row">
                <div class="chg-ver"><b>v<?= $esc($c[0]) ?></b><small><?= $esc(preg_replace('/^(\d{4})-(\d{2})-(\d{2})$/', '$3/$2/$1', $c[1])) ?></small></div>
                <ul><?php foreach ($c[2] as $line): ?><li><?= $esc($line) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endforeach; ?>
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
        document.getElementById('themeToggle').addEventListener('click', () => applyTheme(rootEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

        function fmtDate(ts) {
            try { return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(ts * 1000)); } catch (e) { return ''; }
        }
        // Notes GitHub (texte non fiable) : tout est inséré en texte, jamais en HTML
        function renderNotes(text) {
            const box = document.getElementById('notesBody');
            box.textContent = '';
            let ul = null;
            String(text).split(/\r?\n/).forEach(raw => {
                const line = raw.trim();
                if (!line) { ul = null; return; }
                const li = line.match(/^[-*+]\s+(.*)$/);
                if (li) {
                    if (!ul) { ul = document.createElement('ul'); box.appendChild(ul); }
                    const el = document.createElement('li'); el.textContent = li[1].replace(/[*_`]/g, ''); ul.appendChild(el);
                    return;
                }
                ul = null;
                const h = line.match(/^#{1,6}\s+(.*)$/);
                const el = document.createElement(h ? 'h3' : 'p');
                el.textContent = (h ? h[1] : line).replace(/[*_`]/g, '');
                box.appendChild(el);
            });
        }
        (async function () {
            const title = document.getElementById('updTitle'), sub = document.getElementById('updSub'), card = document.getElementById('updCard');
            try {
                const r = await fetch('api_update.php?_=' + Date.now(), { cache: 'no-store' });
                if (r.status === 401) { location.href = '../index.php'; return; }
                const d = await r.json();
                const link = document.getElementById('updLink');
                link.href = d.url; link.style.display = '';
                if (!d.enabled) { title.textContent = 'Vérification des mises à jour désactivée'; sub.textContent = 'Version installée : ' + (d.current || '—'); return; }
                if (d.newer) {
                    card.classList.add('is-new');
                    document.getElementById('updIcon').className = 'fa-solid fa-circle-arrow-up';
                    title.textContent = 'Nouvelle version disponible : ' + d.latest;
                    sub.textContent = 'Version installée : ' + d.current + (d.date ? ' • publiée le ' + d.date.split('-').reverse().join('/') : '');
                } else if (d.ok) {
                    title.textContent = 'Le dashboard est à jour';
                    sub.textContent = 'Version installée : ' + d.current + ' • vérifié le ' + fmtDate(d.checked);
                } else {
                    title.textContent = 'Vérification impossible pour le moment';
                    sub.textContent = 'Version installée : ' + (d.current || '—') + ' • GitHub n\'a pas répondu, nouvel essai plus tard.';
                }
                if (d.notes) {
                    document.getElementById('notesVer').textContent = d.latest ? '(' + d.latest + ')' : '';
                    renderNotes(d.notes);
                    document.getElementById('notesPanel').style.display = '';
                }
            } catch (e) { title.textContent = 'Vérification impossible pour le moment'; }
        })();
    </script>
</body>
</html>
