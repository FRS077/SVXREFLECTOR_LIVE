<?php
/**
 * fichier config.php
 * Configuration centrale du dashboard SvxReflector Live
 * Auteur : FRS077 Romuald pour RNFA
 *
 * Arborescence :
 *   index.php            (page principale – à la racine)
 *   config.php           (ce fichier – à la racine)
 *   include/             (toutes les autres pages et les API)
 *       info.php  stats.php  carte.php  mumble.php
 *       api_log.php  api_status.php  api_mumble.php  api_history.php  api_visitors.php
 *
 * true = affiché / activé      false = masqué / désactivé
 */

// ============================================================
// 1) GÉNÉRAL
// ============================================================
define('ENABLE_AUTH', true);              // true = mot de passe requis / false = accès libre
define('AUTH_USER', 'admin');             // identifiant de connexion
define('AUTH_PASS', 'rnfa*2026');         // mot de passe de connexion
define('AUTHOR_NAME', 'FRS077 Romuald pour RNFA');
define('LOG_TIMEZONE', 'Europe/Paris');   // fuseau dans lequel les serveurs écrivent leurs logs
define('TALK_MAX_SEC', 180);              // limite de parole en secondes (180 = 3 minutes)

// ============================================================
// 2) FICHIERS ET SERVICES
// ============================================================
define('LOG_FILE', '/var/log/svxreflector');                        // log du SvxReflector
define('STATUS_URL', 'http://127.0.0.1:8090/status');               // /status du SvxReflector
define('STATUS_TIMEOUT', 3);                                        // secondes
define('STATE_FILE', '/tmp/svxreflector_state.json');               // état du dashboard (compteurs du jour)
define('HISTORY_FILE', '/tmp/svxreflector_history.json');           // historique semaine / mois / année (mettre un dossier permanent pour survivre aux redémarrages)
define('VISITORS_FILE', '/tmp/svxreflector_visitors.json');         // visiteurs connectés
define('MUMBLE_LOG', '/var/log/mumble-server/mumble-server.log');   // log du serveur Mumble
define('MUMBLE_STATE', '/tmp/mumble_state.json');                   // état Mumble
define('GATEWAY_NAME', 'RNFA-GW');                                  // nom de la passerelle dans le log Mumble
define('MUMBLE_REFLECTOR_NODE', 'MUMBLE-GW');                       // nom de la même passerelle côté reflector (/status et log)

// Réglages de lecture / conservation
define('INITIAL_TAIL_LINES', 3000);       // lignes du log relues au premier chargement
define('MAX_TALK_EVENTS', 60);            // transmissions gardées en mémoire
define('MAX_CONN_EVENTS', 200);           // connexions / déconnexions gardées
define('KEEP_DAYS', 800);                 // jours d'historique conservés (stats)
define('MAX_HISTORY', 300);               // sessions Mumble gardées
define('INITIAL_MAX_BYTES', 3000000);     // log Mumble : taille relue au premier appel
define('VISITOR_TTL', 30);                // secondes sans signe de vie avant de ne plus compter un visiteur

// ============================================================
// 3) PAGE PRINCIPALE (index.php)
// ============================================================
define('SHOW_VISITORS', true);            // nombre de visiteurs connectés en temps réel
define('INDEX_SHOW_NAME_COL',  true);      // tableau « Transmissions en Direct » : colonne Prénom
define('INDEX_SHOW_PLACE_COL', true);      // tableau « Transmissions en Direct » : colonne Département / ville
define('INDEX_SHOW_DISCONNECTED', true);   // ligne « Dernières déconnexions » sous les stations actives
define('INDEX_DISCONNECTED_MAX', 10);      // nombre maximum de stations déconnectées affichées
define('INDEX_DISCONNECTED_HOURS', 12);    // ne montre que les déconnexions des X dernières heures (0 = sans limite)
define('FACEBOOK_URL', 'https://www.facebook.com/exemple'); // lien Facebook (vide = bouton masqué)
define('CONTACT_URL', '');                // lien contact : https://… ou mailto:adresse@exemple.fr (vide = bouton masqué)
define('INDEX_SHOW_ADMIN_LINK', true);  // bouton « Admin » (accès à include/admin.php) dans l'en-tête / false = masqué (la page reste accessible par son adresse)

// Alerte « relais tombé »
define('ALERT_RELAY_DOWN', true);         // bandeau + bip quand un relais disparaît du /status
define('WATCH_STATIONS', ['RNFA-RE', 'MUMBLE-GW', 'DMR-GW']);   // indicatifs surveillés ; [] = toutes les stations déjà vues
define('RELAY_DOWN_AFTER_POLLS', 2);      // relevés consécutifs (toutes les 5 s) sans la station avant l'alerte

// ============================================================
// 4) STATISTIQUES (include/stats.php)
// ============================================================
define('STATS_SHOW_PERIOD_DAY',   true);  // onglet « Jour »
define('STATS_SHOW_PERIOD_WEEK',  true);  // onglet « Semaine »
define('STATS_SHOW_PERIOD_MONTH', true);  // onglet « Mois »
define('STATS_SHOW_PERIOD_YEAR',  true);  // onglet « Année »
define('STATS_DEFAULT_PERIOD', 'day');    // onglet ouvert au chargement : day / week / month / year

define('STATS_SHOW_KPIS',        true);   // les compteurs du haut
define('STATS_SHOW_RANKING',     true);   // classement des stations
define('STATS_SHOW_HOURLY',      true);   // activité par heure
define('STATS_SHOW_DAILY_CHART', true);   // activité par jour / par mois (semaine, mois, année)
define('STATS_SHOW_QUALITY',     true);   // qualité de liaison
define('STATS_MIN_PTT',         2);      // les PTT plus courts que N secondes ne sont pas comptés dans les statistiques (0 = tout compter)
define('STATS_SHOW_ANALYSIS',    true);   // « Analyse des temps de parole » (30 jours, records, évolution, export CSV)

// ============================================================
// 5) CARTE (include/carte.php)
// ============================================================
define('MAP_FOCUS_FRANCE',      true);    // true = ouverte sur la France métropolitaine ; false = cadre toutes les stations
define('MAP_MIN_ZOOM',          4);       // dézoom maximum (4 = Europe, 2 = monde)
define('MAP_SHOW_BUTTONS',      true);    // bouton « France »
define('MAP_SHOW_STATION_LIST', true);    // liste des stations + recherche (un clic zoome sur la station)
define('MAP_SHOW_SCALE',        true);    // échelle en km
define('MAP_SHOW_FULLSCREEN',   true);    // bouton plein écran
define('MAP_AUTO_ZOOM_PTT',     false);   // zoome automatiquement sur la station qui passe en PTT
define('MAP_ANTENNA_ICON',      true);    // true = icône antenne + ondes (station émettrice) / false = simples points
define('MAP_TYPE_ICONS',        true);    // true = icône selon l'indicatif : -RE relais, -GW passerelle, -M ou /M mobile (voiture), -P ou /P portable (piéton) / false = antenne pour tous
define('MAP_SHOW_LEGEND',       true);    // petite légende des icônes sur la carte
define('MAP_TYPE_COLORS',       true);    // true = couleur différente par type (en ligne) / false = tout en vert. Rouge = PTT dans tous les cas
define('MAP_COLOR_RELAY',       '#10b981'); // relais (-RE)        vert
define('MAP_COLOR_GATEWAY',     '#8b5cf6'); // passerelle (-GW)    violet
define('MAP_COLOR_MOBILE',      '#f59e0b'); // mobile (-M, /M)     orange
define('MAP_COLOR_PORTABLE',    '#ec4899'); // portable (-P, /P)   rose
define('MAP_COLOR_STATION',     '#38bdf8'); // station simple      bleu

// ============================================================
// 6) MUMBLE (include/mumble.php)
// ============================================================
define('MUMBLE_SHOW_PTT_BANNER',      true);   // bandeau « Émission via Mumble »
define('MUMBLE_SHOW_KPIS',            true);   // ligne de compteurs
define('MUMBLE_SHOW_KPI_ONLINE',      true);   //   « Connectés sur Mumble »
define('MUMBLE_SHOW_KPI_GATEWAY',     true);   //   « Passerelle »
define('MUMBLE_SHOW_KPI_TX',          true);   //   « Transmissions Mumble »
define('MUMBLE_SHOW_KPI_REJECTED',    true);   //   « Connexions refusées »
define('MUMBLE_SHOW_USERS',           true);   // bloc « Personnes connectées »
define('MUMBLE_SHOW_GATEWAY_CARD',    true);   //   afficher aussi la carte de la passerelle
define('MUMBLE_SHOW_USER_SINCE',      true);   //   ligne « Connecté à … »
define('MUMBLE_SHOW_USER_DURATION',   true);   //   ligne « Depuis … »
define('MUMBLE_SHOW_TRANSMISSIONS',   true);   // bloc « Dernières transmissions Mumble (PTT) »
define('MUMBLE_SHOW_HISTORY',         true);   // bloc « Historique connexions / départs »
define('MUMBLE_SHOW_GATEWAY_QUALITY', true);   // bloc « Qualité de la passerelle »
define('MUMBLE_SHOW_TODAY_STATS',     true);   // bloc « Aujourd'hui »
define('MUMBLE_SHOW_EXPORT_CSV',      true);   // bouton « Export CSV »
define('MUMBLE_SHOW_TAB_COUNT',       true);   // nombre de connectés dans l'onglet du navigateur

define('MUMBLE_ENABLE_CONNECT_ALERT',    true);  // bip + message quand une personne se connecte
define('MUMBLE_ENABLE_DISCONNECT_ALERT', true);  // bip + message quand une personne quitte
define('MUMBLE_ALERT_GATEWAY_DOWN',      true);  // message + bip quand la passerelle tombe

// ============================================================
// 7) VERSION (fichier version.txt à la racine, à côté de config.php)
// ============================================================
/** Retourne « • Version X » (texte sécurisé) pour les pieds de page, ou '' si version.txt est absent / vide. */
function dashboard_version_html(): string {
    $file = __DIR__ . '/version.txt';
    if (!is_readable($file)) { return ''; }
    $v = trim((string)strtok((string)file_get_contents($file), "\r\n"));
    $v = mb_substr($v, 0, 40);
    return $v === '' ? '' : ' • Version ' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// ============================================================
// 8) IMAGE DE FOND (thème sombre uniquement)
// ============================================================
define('BACKGROUND_ENABLE', true);                       // true = image de fond affichée / false = fond uni
define('BACKGROUND_FILE',   'image/fond-uhf-fibre.svg'); // chemin depuis la racine du site

// Retourne la règle CSS du fond. $prefix = '' pour index.php, '../' pour les pages de include/
function dashboard_background_css($prefix = '') {
    if (!BACKGROUND_ENABLE || BACKGROUND_FILE === '') return '';
    $url = htmlspecialchars($prefix . BACKGROUND_FILE, ENT_QUOTES, 'UTF-8');
    return '[data-theme="dark"] body { background-color: var(--bg-color); background-image: url("' . $url . '"); background-size: cover; background-position: center; background-attachment: fixed; background-repeat: no-repeat; }';
}

// ============================================================
// 9) PAGE LOG (include/log.php) - événements utiles du réflecteur
// ============================================================
define('LOG_PAGE_ENABLE',       true);   // true = page Log + bouton dans les menus / false = page désactivée
define('LOG_SHOW_LOGIN',        true);   // connexions des stations (indicatif, IP masquée, protocole)
define('LOG_SHOW_DISCONNECT',   true);   // déconnexions (avec la raison)
define('LOG_SHOW_ERRORS',       true);   // incidents : timeout, erreur de protocole, authentification refusée
define('LOG_SHOW_RESTART',      true);   // arrêts / redémarrages du réflecteur
define('LOG_SHOW_UDP_LOSS',     false);  // pertes de trames UDP (très bavard)
define('LOG_SHOW_TG_SELECT',    false);  // changements de TG (très bavard)
define('LOG_SHOW_IP',           true);   // colonne IP (false = colonne cachée)
define('LOG_MASK_IP',           true);   // true = IP masquée (ex. 90.45.x.x) / false = IP complète
define('LOG_MASK_OCTETS',       2);      // nombre d'octets masqués en partant de la fin (1 = 90.45.7.x / 2 = 90.45.x.x)
define('LOG_MAX_LINES',         200);    // nombre maximum de lignes affichées
define('LOG_READ_BYTES',        3000000);// taille lue en fin de fichier (octets)
define('LOG_REFRESH_SEC',       10);     // rafraîchissement automatique en secondes (0 = désactivé)

// ============================================================
// 10) MENUS DÉROULANTS : LIENS UTILES et RÉSEAUX SOCIAUX
//     Une entrée dont l'adresse est vide n'est pas affichée.
//     Si toutes les entrées d'un menu sont vides, le menu est masqué.
// ============================================================
define('MENU_LINKS_ENABLE',  true);        // true = menu « Liens » / false = masqué
define('MENU_LINKS_LABEL',   'Liens');     // texte du bouton
// Chaque ligne : ['Texte affiché', 'adresse https://…', 'icône Font Awesome']
$MENU_LINKS = [
    ['Site RNFA',          'https://www.exemple.fr', 'fa-solid fa-globe'],
    ['Forum / Discord',    'https://discord.gg/exemple', 'fa-solid fa-comments'],
    ['Liste des relais',   'https://www.exemple.fr/relais', 'fa-solid fa-tower-broadcast'],
    ['Documentation',      'https://www.exemple.fr/doc', 'fa-solid fa-book'],
];

define('MENU_SOCIAL_ENABLE', true);        // true = menu « Réseaux » / false = masqué
define('MENU_SOCIAL_LABEL',  'Réseaux');   // texte du bouton
// Facebook reprend FACEBOOK_URL (section 3). Les autres : adresse ou vide.
$MENU_SOCIAL = [
    ['Facebook',  FACEBOOK_URL, 'fa-brands fa-facebook'],
    ['YouTube',   'https://www.youtube.com/@exemple', 'fa-brands fa-youtube'],
    ['Instagram', 'https://www.instagram.com/exemple', 'fa-brands fa-instagram'],
    ['Telegram',  'https://t.me/exemple', 'fa-brands fa-telegram'],
    ['WhatsApp',  'https://chat.whatsapp.com/exemple', 'fa-brands fa-whatsapp'],
    ['X',         'https://x.com/exemple', 'fa-brands fa-x-twitter'],
];

// CSS des menus déroulants (utilise les variables de thème de chaque page)
function dashboard_menus_css() {
    return '
        .dd { position: relative; display: inline-block; }
        .dd > .btn-action .dd-caret { font-size: 0.65rem; opacity: 0.7; transition: transform 0.2s; }
        .dd.open > .btn-action { border-color: var(--accent-cyan); color: var(--accent-text); }
        .dd.open > .btn-action .dd-caret { transform: rotate(180deg); }
        .dd-menu { display: none; position: absolute; top: calc(100% + 6px); right: 0; z-index: 2000; min-width: 210px;
            background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.45); }
        .dd.open > .dd-menu { display: block; }
        .dd-menu a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 8px;
            color: var(--text-main); text-decoration: none; font-size: 0.86rem; white-space: nowrap; }
        .dd-menu a i { width: 18px; text-align: center; color: var(--accent-text); }
        .dd-menu a:hover { background: var(--surface-subtle); color: var(--accent-text); }
    ';
}

// Génère un menu déroulant à partir d'une liste ; vide si aucune adresse valide
function dashboard_dropdown_html($label, $icon, array $items) {
    $out = '';
    foreach ($items as $it) {
        $url = trim((string)($it[1] ?? ''));
        if ($url === '' || !preg_match('#^(https?://|mailto:|tel:)#i', $url)) continue;
        $out .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
              . (stripos($url, 'http') === 0 ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
              . '<i class="' . htmlspecialchars($it[2] ?? 'fa-solid fa-link', ENT_QUOTES, 'UTF-8') . '"></i>'
              . htmlspecialchars((string)$it[0], ENT_QUOTES, 'UTF-8') . '</a>';
    }
    if ($out === '') return '';
    return '<div class="dd"><button type="button" class="btn-action dd-toggle" aria-haspopup="true" aria-expanded="false">'
         . '<i class="' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i> <span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
         . '</span> <i class="fa-solid fa-chevron-down dd-caret"></i></button><div class="dd-menu">' . $out . '</div></div>';
}

// Les deux menus + le petit script d'ouverture/fermeture (à placer dans le menu d'en-tête)
function dashboard_menus_html() {
    global $MENU_LINKS, $MENU_SOCIAL;
    $h = '';
    if (MENU_LINKS_ENABLE)  $h .= dashboard_dropdown_html(MENU_LINKS_LABEL,  'fa-solid fa-link',  $MENU_LINKS);
    if (MENU_SOCIAL_ENABLE) $h .= dashboard_dropdown_html(MENU_SOCIAL_LABEL, 'fa-solid fa-share-nodes', $MENU_SOCIAL);
    if ($h === '') return '';
    return $h . '<script>(function(){
        var dds=document.querySelectorAll(".dd");
        function closeAll(except){dds.forEach(function(d){if(d!==except){d.classList.remove("open");var b=d.querySelector(".dd-toggle");if(b)b.setAttribute("aria-expanded","false");}});}
        dds.forEach(function(d){var b=d.querySelector(".dd-toggle");b.addEventListener("click",function(e){e.stopPropagation();var o=!d.classList.contains("open");closeAll(d);d.classList.toggle("open",o);b.setAttribute("aria-expanded",o?"true":"false");});});
        document.addEventListener("click",function(){closeAll(null);});
        document.addEventListener("keydown",function(e){if(e.key==="Escape")closeAll(null);});
    })();</script>';
}

// ============================================================
// 10b) TALKGROUPES (include/talkgroupe.php), DERNIÈRES CONNEXIONS, LIEN FreeDMR
// ============================================================
define('INDEX_SHOW_CONNECTED',  true);     // carte « Dernière station connectée » (avec l'heure) après « Temps de parole du jour »
define('FREEDMR_URL',   'http://freedmr.fr'); // lien FreeDMR à côté de Mumble (vide = bouton masqué)
define('FREEDMR_LABEL', 'FreeDMR');        // texte du bouton
define('TG_PAGE_ENABLE', true);            // true = page Talkgroupes + bouton / false = masquée
define('TG_LOCAL', 59);                    // TG local du réseau : les stations sur un autre TG sont mises en évidence
define('TG_REFRESH_SEC', 10);              // rafraîchissement de la page Talkgroupes (secondes)
define('TG_READ_BYTES', 3000000);          // taille lue en fin de log pour retrouver les TG activés
define('TG_SHOW_MONITORED', true);         // afficher aussi les stations qui écoutent un TG (sans y être)
define('TG_SHOW_SELECTED', true);          // afficher les stations qui ont activé ce TG dans le log
define('TG_NAME_DEFAULT', 'TG provisoire'); // nom affiché pour un TG absent de la liste ci-dessous
$TG_NAMES = [                              // noms des TG (numéro => nom)
    59  => 'TG principal',
    62  => 'TG de dégagement',
    100 => 'SSTV',
    101 => 'Packet APRS',
    102 => 'Test',
];

// ============================================================
// 10c) SERVEUR (include/serveur.php) : CPU, température, mémoire, disque, réseau, uptime
// ============================================================
define('INDEX_SHOW_SERVER',  true);    // ligne « Serveur » (CPU, température, mémoire, disque, uptime) sur la page principale
define('SERVER_PAGE_ENABLE', true);    // true = page Serveur + bouton / false = page désactivée
define('SERVER_SHOW_HOST',   true);    // nom de la machine affiché (false = masqué)
define('SERVER_SHOW_CPU',    true);    // charge du processeur
define('SERVER_SHOW_TEMP',   true);    // température (si le matériel la fournit)
define('SERVER_SHOW_RAM',    true);    // mémoire (et swap)
define('SERVER_SHOW_DISK',   true);    // espace disque
define('SERVER_SHOW_NET',    true);    // débit réseau
define('SERVER_SHOW_UPTIME', true);    // durée depuis le démarrage (jours, heures)
define('SERVER_SHOW_SYSTEM', true);    // système, noyau, PHP
define('SERVER_DISK_PATHS',  ['/']);   // disques à surveiller (ex. ['/', '/var/log'])
define('SERVER_TEMP_WARN',   60);      // °C : orange au-dessus
define('SERVER_TEMP_CRIT',   75);      // °C : rouge au-dessus
define('SERVER_PCT_WARN',    75);      // % : orange (CPU, mémoire, disque)
define('SERVER_PCT_CRIT',    90);      // % : rouge
define('SERVER_REFRESH_SEC', 3);       // rafraîchissement de la page Serveur en secondes

// ============================================================
// 10d) MÉTÉO DISCRÈTE (index.php) : température, lever / coucher du soleil, petite icône
// ============================================================
define('WEATHER_ENABLE',       true);      // true = petit encart météo dans l'en-tête / false = masqué
define('WEATHER_ASK_LOCATION', true);      // true = utilise la position du visiteur (le navigateur demande l'autorisation) / false = toujours le lieu par défaut
define('WEATHER_SHOW_ICON',    true);      // icône soleil / nuage / pluie / neige
define('WEATHER_SHOW_TEMP',    true);      // température
define('WEATHER_SHOW_SUN',     true);      // lever et coucher du soleil
define('WEATHER_DEFAULT_NAME', 'Lorgies'); // lieu utilisé si le visiteur refuse la localisation
define('WEATHER_DEFAULT_LAT',  50.5469);   // latitude du lieu par défaut
define('WEATHER_DEFAULT_LON',  2.8133);    // longitude du lieu par défaut
define('WEATHER_REFRESH_MIN',  15);        // rafraîchissement en minutes

// ============================================================
// 10e) NOUVEAUTÉS ET MISES À JOUR (include/nouveautes.php)
// ============================================================
define('NAV_REORDER_ENABLE',  true);      // bouton ✥ pour réorganiser les boutons de l'en-tête (index.php) ; date, météo et Déconnexion restent fixes
define('NEWS_PAGE_ENABLE',     true);      // page « Nouveautés » (fonctionnalités + historique des versions) et son bouton
define('UPDATE_CHECK_ENABLE',  true);      // vérifie une fois par jour sur GitHub s'il existe une nouvelle version (point rouge discret dans le pied de page)
define('UPDATE_REPO',          'FRS077/SVXREFLECTOR_LIVE'); // dépôt GitHub contrôlé
define('UPDATE_CHECK_HOURS',   24);        // délai entre deux vérifications (heures)

// ============================================================
// 10f) INDICATIFS MASQUÉS (balises, passerelles…)
// ============================================================
define('HIDE_CALLSIGNS_ENABLE', true);     // true = les indicatifs de include/callsigns_masques.json sont masqués dans « Transmissions en Direct » et les Statistiques / false = tout est affiché (liste modifiable dans la page admin)

// ============================================================
// 11) ADMINISTRATION (include/admin.php) - identifiants DIFFÉRENTS de ceux du dashboard
//     Ces lignes ne sont pas modifiables depuis la page admin, sauf le mot de passe (formulaire dédié).
// ============================================================
define('ADMIN_ENABLE', true);                 // true = page admin accessible / false = page désactivée (erreur 404)
define('ADMIN_USER', 'rnfa-admin');           // identifiant administrateur
define('ADMIN_PASS_HASH', '$2y$10$nKvbflifNGBN6lxfsjmnlO3hNO60iMVbPtDNBPtJlpsmGifmq4mY.'); // mot de passe administrateur (empreinte chiffrée, jamais en clair)
define('ADMIN_EDIT_PATHS', false);            // true = les chemins de fichiers/services sont modifiables dans la page admin
define('ADMIN_SESSION_MIN', 30);              // minutes d'inactivité avant déconnexion automatique
define('ADMIN_BACKUP_KEEP', 15);              // nombre de sauvegardes de config.php conservées
