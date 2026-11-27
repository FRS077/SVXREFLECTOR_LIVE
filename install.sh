#!/usr/bin/env bash
# =============================================================================
#  install.sh - Installation de SvxReflector Live (dashboard PHP)
#  Auteur : FRS077 Romuald pour RNFA  -  https://rnfa.fr
#  © 2026 Romuald (FRS077). Tous droits réservés.
#
#  Installe : serveur web (Apache ou Nginx) + PHP 8.x + extensions, copie le dashboard,
#  règle config.php (mots de passe, chemins), droits de lecture des logs, hôte virtuel, HTTPS (option).
#  Systèmes : Debian / Ubuntu / Raspberry Pi OS (apt).
#
#  Exemples :
#    sudo ./install.sh                                  (assistant interactif)
#    sudo ./install.sh --server apache --yes            (Apache, chemin /svxreflector, réponses par défaut)
#    sudo ./install.sh --server nginx --domain dash.exemple.fr --https --email moi@exemple.fr
#    sudo ./install.sh --update                         (met à jour le code, garde config.php et version.txt)
#    sudo ./install.sh --uninstall [--purge]
# =============================================================================
set -Eeuo pipefail

# ----------------------------- valeurs par défaut ----------------------------
SERVER=""                      # apache | nginx
WEB_ROOT="${SVX_WEB_ROOT:-/var/www/html}"   # le dashboard est toujours installé directement dans /var/www/html
INSTALL_DIR="$WEB_ROOT"
DOMAIN=""                      # vide = Apache : alias /svxreflector ; Nginx : écoute sur le port ci-dessous
PORT=""                        # Nginx : 8080 par défaut sans domaine, 80 avec domaine
LOG_FILE_PATH="/var/log/svxreflector"
STATUS_URL_VAL="http://127.0.0.1:8090/status"
MUMBLE_LOG_PATH="/var/log/mumble-server/mumble-server.log"
STATE_DIR="/var/lib/svxreflector-dashboard"
VISITOR_USER="admin"
VISITOR_PASS=""
ADMIN_PASS=""
EMAIL=""
WITH_HTTPS=0; ASSUME_YES=0; DO_UPDATE=0; DO_UNINSTALL=0; PURGE=0
NO_PACKAGES=0; NO_WEBSERVER=0; OPEN_FIREWALL=1
WEB_USER="www-data"
SRC_DIR=""
APP_NAME="svxreflector"

# ----------------------------- affichage -------------------------------------
if [ -t 1 ]; then C_G=$'\e[32m'; C_Y=$'\e[33m'; C_R=$'\e[31m'; C_B=$'\e[36m'; C_0=$'\e[0m'; else C_G=""; C_Y=""; C_R=""; C_B=""; C_0=""; fi
info() { echo "${C_B}[..]${C_0} $*"; }
ok()   { echo "${C_G}[OK]${C_0} $*"; }
warn() { echo "${C_Y}[!!]${C_0} $*" >&2; }
die()  { echo "${C_R}[ERREUR]${C_0} $*" >&2; exit 1; }
trap 'die "Échec à la ligne $LINENO (commande : $BASH_COMMAND)"' ERR

usage() { sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; cat <<'U'

Options :
  --server apache|nginx     serveur web à installer / utiliser
  --domain NOM              nom de domaine (crée un hôte virtuel dédié)
  --port N                  port d'écoute (Nginx sans domaine : 8080 par défaut)
  --log FICHIER             log du SvxReflector (défaut /var/log/svxreflector)
  --status-url URL          /status du reflector (défaut http://127.0.0.1:8090/status)
  --mumble-log FICHIER      log du serveur Mumble (défaut /var/log/mumble-server/mumble-server.log)
  --state-dir DOSSIER       données d'état permanentes (défaut /var/lib/svxreflector-dashboard)
  --user NOM --pass MOT     identifiants visiteurs du dashboard (défaut admin / mot de passe généré)
  --admin-pass MOT          mot de passe de la page admin (défaut : généré)
  --https --email ADRESSE   certificat Let's Encrypt (domaine public obligatoire)
  --no-firewall             ne touche pas à ufw
  --no-packages             n'installe aucun paquet (serveur web et PHP déjà prêts)
  --no-webserver            copie et configure seulement le dashboard (pas d'hôte virtuel)
  --update                  mise à jour : remplace le code, garde config.php et version.txt
                            (le dashboard est toujours installé dans /var/www/html)
  --uninstall [--purge]     retire la configuration web (--purge : supprime aussi les fichiers)
  --yes                     aucune question, valeurs par défaut
  -h, --help                cette aide
U
}

# ----------------------------- arguments -------------------------------------
while [ $# -gt 0 ]; do
  case "$1" in
    --server) SERVER="${2:-}"; shift 2;;
    --domain) DOMAIN="${2:-}"; shift 2;;
    --port) PORT="${2:-}"; shift 2;;
    --log) LOG_FILE_PATH="${2:-}"; shift 2;;
    --status-url) STATUS_URL_VAL="${2:-}"; shift 2;;
    --mumble-log) MUMBLE_LOG_PATH="${2:-}"; shift 2;;
    --state-dir) STATE_DIR="${2:-}"; shift 2;;
    --user) VISITOR_USER="${2:-}"; shift 2;;
    --pass) VISITOR_PASS="${2:-}"; shift 2;;
    --admin-pass) ADMIN_PASS="${2:-}"; shift 2;;
    --email) EMAIL="${2:-}"; shift 2;;
    --https) WITH_HTTPS=1; shift;;
    --no-firewall) OPEN_FIREWALL=0; shift;;
    --no-packages) NO_PACKAGES=1; shift;;
    --no-webserver) NO_WEBSERVER=1; shift;;
    --update) DO_UPDATE=1; shift;;
    --uninstall) DO_UNINSTALL=1; shift;;
    --purge) PURGE=1; shift;;
    --yes|-y) ASSUME_YES=1; shift;;
    -h|--help) usage; exit 0;;
    *) die "Option inconnue : $1 (voir --help)";;
  esac
done

[ "$(id -u)" -eq 0 ] || die "Lance ce script en root :  sudo ./install.sh"
[ -n "$SERVER" ] && case "$SERVER" in apache|nginx) ;; *) die "--server doit valoir apache ou nginx";; esac
[ -z "$DOMAIN" ] || [[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "--domain invalide : $DOMAIN"
[ -z "$PORT" ] || [[ "$PORT" =~ ^[0-9]{1,5}$ ]] || die "--port invalide : $PORT"

# dossier source = dossier du script (ou ./site à côté du script)
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if   [ -f "$HERE/index.php" ] && [ -f "$HERE/config.php" ]; then SRC_DIR="$HERE"
elif [ -f "$HERE/site/index.php" ] && [ -f "$HERE/site/config.php" ]; then SRC_DIR="$HERE/site"
fi

list_our_files() { ( cd "$SRC_DIR" && find . -type f ! -name install.sh ! -name '*.bak' ! -path './README.md' | sed 's#^\./##' | sort ); }

ask() {  # ask "Question" "défaut" -> écho de la réponse
  local q="$1" d="$2" r=""
  if [ "$ASSUME_YES" -eq 1 ] || [ ! -t 0 ]; then echo "$d"; return; fi
  read -r -p "$q [$d] : " r || true
  echo "${r:-$d}"
}
gen_pass() { tr -dc 'A-Za-z0-9' </dev/urandom | head -c 14 || true; }

# ----------------------------- désinstallation -------------------------------
detect_server() {
  if [ -n "$SERVER" ]; then return; fi
  if   [ -e "/etc/apache2/sites-available/$APP_NAME.conf" ] || [ -e "/etc/apache2/conf-available/$APP_NAME.conf" ]; then SERVER=apache
  elif [ -e "/etc/nginx/sites-available/$APP_NAME.conf" ]; then SERVER=nginx
  elif command -v apache2 >/dev/null 2>&1 && ! command -v nginx >/dev/null 2>&1; then SERVER=apache
  elif command -v nginx  >/dev/null 2>&1 && ! command -v apache2 >/dev/null 2>&1; then SERVER=nginx
  fi
}

if [ "$DO_UNINSTALL" -eq 1 ]; then
  info "Désinstallation de SvxReflector Live"
  if [ -e "/etc/apache2/sites-available/$APP_NAME.conf" ]; then a2dissite "$APP_NAME" >/dev/null 2>&1 || true; rm -f "/etc/apache2/sites-available/$APP_NAME.conf"; fi
  if [ -e "/etc/apache2/conf-available/$APP_NAME.conf" ]; then a2disconf "$APP_NAME" >/dev/null 2>&1 || true; rm -f "/etc/apache2/conf-available/$APP_NAME.conf"; fi
  command -v systemctl >/dev/null 2>&1 && systemctl reload apache2 >/dev/null 2>&1 || true
  if [ -e "/etc/nginx/sites-available/$APP_NAME.conf" ]; then rm -f "/etc/nginx/sites-enabled/$APP_NAME.conf" "/etc/nginx/sites-available/$APP_NAME.conf"; command -v systemctl >/dev/null 2>&1 && systemctl reload nginx >/dev/null 2>&1 || true; fi
  ok "Configuration web retirée."
  if [ "$PURGE" -eq 1 ]; then
    [ -n "$SRC_DIR" ] || die "--purge a besoin du dossier « site » à côté de install.sh (pour ne supprimer que les fichiers du dashboard et rien d'autre dans $INSTALL_DIR)."
    BK="/root/svxreflector-backup-$(date +%Y%m%d-%H%M%S)"; mkdir -p "$BK"
    cp -a "$INSTALL_DIR/config.php" "$INSTALL_DIR/version.txt" "$BK/" 2>/dev/null || true
    while IFS= read -r f; do rm -f "$INSTALL_DIR/$f"; done < <(list_our_files)
    rm -rf "$INSTALL_DIR/include/backups"; rm -f "$INSTALL_DIR"/include/backup-avant-maj-*.bak
    for d in include css image; do find "$INSTALL_DIR/$d" -depth -type d -empty -delete 2>/dev/null || true; done
    ok "Fichiers du dashboard supprimés (le reste de $INSTALL_DIR n'est pas touché ; config.php sauvegardé dans $BK)"
    rm -rf "$STATE_DIR"; ok "Données d'état supprimées ($STATE_DIR)"
  else
    info "Fichiers conservés dans $INSTALL_DIR (ajoute --purge pour les supprimer)."
  fi
  exit 0
fi

[ -n "$SRC_DIR" ] || die "Fichiers du dashboard introuvables : place install.sh à côté du dossier « site » (ou dans le dossier du dashboard)."
command -v php >/dev/null 2>&1 || [ "$NO_PACKAGES" -eq 0 ] || die "PHP absent et --no-packages demandé."

# ----------------------------- assistant -------------------------------------
IS_UPDATE=0
if [ "$DO_UPDATE" -eq 1 ] || { [ -f "$INSTALL_DIR/config.php" ] && grep -q "SvxReflector Live" "$INSTALL_DIR/config.php" 2>/dev/null; }; then
  IS_UPDATE=1
  [ "$DO_UPDATE" -eq 1 ] || warn "Une installation existe déjà dans $INSTALL_DIR : mise à jour (config.php et version.txt conservés)."
fi

echo
echo "${C_B}=== Installation de SvxReflector Live ===${C_0}"
if [ "$IS_UPDATE" -eq 0 ]; then
  detect_server
  [ -n "$SERVER" ] || SERVER="$(ask 'Serveur web (apache / nginx)' 'apache')"
  case "$SERVER" in apache|nginx) ;; *) die "Serveur web invalide : $SERVER";; esac
  if [ "$NO_WEBSERVER" -eq 0 ]; then
    [ -n "$DOMAIN" ] || DOMAIN="$(ask 'Nom de domaine (vide = aucun)' '')"
    [ -z "$DOMAIN" ] || [[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "Domaine invalide : $DOMAIN"
  fi
  LOG_FILE_PATH="$(ask 'Log du SvxReflector' "$LOG_FILE_PATH")"
  STATUS_URL_VAL="$(ask 'Adresse /status du reflector' "$STATUS_URL_VAL")"
  MUMBLE_LOG_PATH="$(ask 'Log du serveur Mumble' "$MUMBLE_LOG_PATH")"
  VISITOR_USER="$(ask 'Identifiant visiteurs du dashboard' "$VISITOR_USER")"
  [ -n "$VISITOR_PASS" ] || { GEN="$(gen_pass)"; VISITOR_PASS="$(ask 'Mot de passe visiteurs' "$GEN")"; }
  [ -n "$ADMIN_PASS" ]   || { GEN="$(gen_pass)"; ADMIN_PASS="$(ask 'Mot de passe de la page admin' "$GEN")"; }
  if [ "$WITH_HTTPS" -eq 1 ]; then
    [ -n "$DOMAIN" ] || die "--https demande un nom de domaine public (--domain)."
    [ -n "$EMAIL" ]  || EMAIL="$(ask 'Adresse e-mail (Let s Encrypt)' 'admin@'"$DOMAIN")"
  fi
fi
if [ -z "$PORT" ]; then if [ -n "$DOMAIN" ]; then PORT=80; else PORT=8080; fi; fi

# ----------------------------- paquets ---------------------------------------
if [ "$NO_PACKAGES" -eq 0 ]; then
  command -v apt-get >/dev/null 2>&1 || die "Système non supporté (apt-get absent). Installe à la main : $SERVER, PHP 8.x, php-curl, php-mbstring, php-xml, puis relance avec --no-packages."
  export DEBIAN_FRONTEND=noninteractive
  info "Mise à jour de la liste des paquets…"
  apt-get update -y >/dev/null
  PKGS=(ca-certificates curl unzip acl php php-cli php-curl php-mbstring php-xml php-zip)
  if [ "$IS_UPDATE" -eq 0 ] || [ -n "$SERVER" ]; then
    case "${SERVER:-apache}" in
      apache) PKGS+=(apache2 libapache2-mod-php);;
      nginx)  PKGS+=(nginx php-fpm);;
    esac
  fi
  [ "$WITH_HTTPS" -eq 1 ] && { PKGS+=(certbot); [ "$SERVER" = "apache" ] && PKGS+=(python3-certbot-apache) || PKGS+=(python3-certbot-nginx); }
  info "Installation : ${PKGS[*]}"
  apt-get install -y --no-install-recommends "${PKGS[@]}" >/dev/null
  ok "Paquets installés."
fi

command -v php >/dev/null 2>&1 || die "PHP introuvable après installation."
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_MAJOR="${PHP_VER%%.*}"
if [ "$PHP_MAJOR" -lt 8 ]; then
  die "PHP $PHP_VER détecté : le dashboard demande PHP 8.0 ou plus. Debian 11 / Ubuntu 20.04 : ajoute le dépôt Sury (https://packages.sury.org/php/) ou passe à une version plus récente."
fi
for ext in curl mbstring json; do php -m | grep -qix "$ext" || die "Extension PHP manquante : php-$ext"; done
ok "PHP $PHP_VER avec les extensions requises."
id "$WEB_USER" >/dev/null 2>&1 || { WEB_USER="$(ps -eo user,comm 2>/dev/null | awk '$2 ~ /apache2|httpd|nginx|php-fpm/ && $1 != "root" {print $1; exit}')"; WEB_USER="${WEB_USER:-root}"; warn "Utilisateur web : $WEB_USER"; }

# ----------------------------- copie du dashboard ----------------------------
info "Copie du dashboard dans $INSTALL_DIR"
mkdir -p "$INSTALL_DIR"
TMP_BK=""
if [ "$IS_UPDATE" -eq 0 ]; then   # index.php / config.php d'un autre site à la racine : copiés dans /root avant d'être remplacés
  BKD="/root/svxreflector-fichiers-existants-$(date +%Y%m%d-%H%M%S)"
  for f in index.php config.php; do
    if [ -f "$INSTALL_DIR/$f" ]; then mkdir -p "$BKD"; cp -a "$INSTALL_DIR/$f" "$BKD/$f"; warn "$f existant sauvegardé dans $BKD (remplacé par celui du dashboard)"; fi
  done
fi
if [ "$IS_UPDATE" -eq 1 ]; then
  TMP_BK="$(mktemp -d)"
  cp -a "$INSTALL_DIR/config.php" "$TMP_BK/config.php" 2>/dev/null || true
  cp -a "$INSTALL_DIR/version.txt" "$TMP_BK/version.txt" 2>/dev/null || true
  cp -a "$INSTALL_DIR/include/callsigns_masques.json" "$TMP_BK/callsigns_masques.json" 2>/dev/null || true
  BK="$INSTALL_DIR/include/backup-avant-maj-$(date +%Y%m%d-%H%M%S).config.php.bak"
  cp -a "$INSTALL_DIR/config.php" "$BK" 2>/dev/null && ok "Sauvegarde de ton config.php : $BK" || true
fi
# copie en excluant install.sh et README.md (un autre README.md éventuel dans /var/www/html n'est jamais écrasé)
( cd "$SRC_DIR" && tar --exclude='./install.sh' --exclude='./README.md' --exclude='*.bak' -cf - . ) | ( cd "$INSTALL_DIR" && tar -xf - )
if [ "$IS_UPDATE" -eq 1 ]; then
  [ -f "$TMP_BK/config.php" ]  && cp -a "$TMP_BK/config.php"  "$INSTALL_DIR/config.php"
  [ -f "$TMP_BK/version.txt" ] && cp -a "$TMP_BK/version.txt" "$INSTALL_DIR/version.txt"
  [ -f "$TMP_BK/callsigns_masques.json" ] && cp -a "$TMP_BK/callsigns_masques.json" "$INSTALL_DIR/include/callsigns_masques.json"
  rm -rf "$TMP_BK"
  ok "Code mis à jour. Ton config.php, ton version.txt et ta liste d'indicatifs masqués n'ont pas été touchés."
  warn "Nouvelles options éventuelles : consulte config.php du paquet (les valeurs par défaut s'appliquent sinon)."
fi

# ----------------------------- config.php ------------------------------------
HELPER="$(mktemp)"; trap 'rm -f "$HELPER"' EXIT
cat > "$HELPER" <<'PHPEOF'
<?php
// usage : php helper.php fichier CLE VALEUR TYPE(s|b|i)
[$_, $f, $k, $v, $t] = $argv;
$s = file_get_contents($f);
if ($t === 'b') { $rep = ($v === 'true') ? 'true' : 'false'; }
elseif ($t === 'i') { $rep = (string)(int)$v; }
else { $rep = "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'"; }
$n = 0;
$re = '/^(define\(\'' . preg_quote($k, '/') . '\',\s*)(\'(?:[^\'\\\\]|\\\\.)*\'|[^)]*?)(\);)/m';
$s = preg_replace_callback($re, fn($m) => $m[1] . $rep . $m[3], $s, 1, $n);
if ($n !== 1) { fwrite(STDERR, "clé absente de config.php : $k\n"); exit(2); }
file_put_contents($f, $s);
PHPEOF
set_cfg() { php "$HELPER" "$INSTALL_DIR/config.php" "$1" "$2" "${3:-s}" || warn "Réglage ignoré : $1"; }

if [ "$IS_UPDATE" -eq 0 ]; then
  mkdir -p "$STATE_DIR"
  info "Réglage de config.php"
  set_cfg AUTH_USER "$VISITOR_USER"
  set_cfg AUTH_PASS "$VISITOR_PASS"
  set_cfg LOG_FILE "$LOG_FILE_PATH"
  set_cfg STATUS_URL "$STATUS_URL_VAL"
  set_cfg MUMBLE_LOG "$MUMBLE_LOG_PATH"
  set_cfg STATE_FILE   "$STATE_DIR/state.json"
  set_cfg HISTORY_FILE "$STATE_DIR/history.json"
  set_cfg VISITORS_FILE "$STATE_DIR/visitors.json"
  set_cfg MUMBLE_STATE "$STATE_DIR/mumble_state.json"
  ADMIN_HASH="$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' -- "$ADMIN_PASS")"
  set_cfg ADMIN_PASS_HASH "$ADMIN_HASH"
  ok "config.php réglé (mots de passe, chemins, dossier d'état permanent)."
fi

# ----------------------------- droits ----------------------------------------
# droits appliqués uniquement aux fichiers du dashboard (le reste de /var/www/html n'est pas modifié)
while IFS= read -r f; do
  chown "$WEB_USER:$WEB_USER" "$INSTALL_DIR/$f" 2>/dev/null || true; chmod 640 "$INSTALL_DIR/$f"
done < <(list_our_files)
for d in include css image; do
  [ -d "$INSTALL_DIR/$d" ] && { chown -R "$WEB_USER:$WEB_USER" "$INSTALL_DIR/$d" 2>/dev/null || true; find "$INSTALL_DIR/$d" -type d -exec chmod 750 {} \; ; }
done
[ -d "$STATE_DIR" ] && [ "$IS_UPDATE" -eq 0 ] && chown -R "$WEB_USER:$WEB_USER" "$STATE_DIR" 2>/dev/null || true
chmod 660 "$INSTALL_DIR/config.php"        # modifiable depuis la page admin
[ "$IS_UPDATE" -eq 0 ] && chmod 770 "$STATE_DIR" || true
ok "Droits appliqués (propriétaire : $WEB_USER)."

# ----------------------------- lecture des logs ------------------------------
grant_read() {   # grant_read FICHIER  : le serveur web doit pouvoir lire le log
  local f="$1" g
  [ -n "$f" ] || return 0
  if [ ! -e "$f" ]; then warn "Fichier absent (pas encore créé ?) : $f"; return 0; fi
  if su -s /bin/sh -c "test -r '$f'" "$WEB_USER" 2>/dev/null; then ok "Log lisible par $WEB_USER : $f"; return 0; fi
  g="$(stat -c %G "$f")"
  if [ -n "$g" ] && [ "$g" != "root" ] && [ "$g" != "UNKNOWN" ]; then usermod -aG "$g" "$WEB_USER" 2>/dev/null && info "$WEB_USER ajouté au groupe $g"; fi
  command -v setfacl >/dev/null 2>&1 && { setfacl -m "u:$WEB_USER:r" "$f" 2>/dev/null || true; setfacl -m "u:$WEB_USER:x" "$(dirname "$f")" 2>/dev/null || true; }
  if su -s /bin/sh -c "test -r '$f'" "$WEB_USER" 2>/dev/null; then ok "Log lisible par $WEB_USER : $f"; NEED_RESTART=1
  else warn "$f n'est pas lisible par $WEB_USER : chmod o+r '$f' ou ajoute $WEB_USER au bon groupe."; NEED_RESTART=1; fi
}
NEED_RESTART=0
if [ "$IS_UPDATE" -eq 0 ]; then grant_read "$LOG_FILE_PATH"; grant_read "$MUMBLE_LOG_PATH"; fi

# ----------------------------- serveur web -----------------------------------
URL_SHOWN=""
reload_srv() { command -v systemctl >/dev/null 2>&1 && systemctl "$1" "$2" >/dev/null 2>&1 || service "$2" "$1" >/dev/null 2>&1 || true; }

if [ "$NO_WEBSERVER" -eq 0 ] && [ "$IS_UPDATE" -eq 0 ]; then
  HOSTIP="$(hostname -I 2>/dev/null | awk '{print $1}')"; HOSTIP="${HOSTIP:-IP-du-serveur}"
  if [ "$SERVER" = "apache" ]; then
    command -v a2enmod >/dev/null 2>&1 || die "Apache introuvable (a2enmod absent)."
    # module PHP actif (mpm_prefork requis par libapache2-mod-php)
    a2enmod rewrite headers >/dev/null 2>&1 || true
    PHPMOD="php${PHP_VER}"; [ -e "/etc/apache2/mods-available/$PHPMOD.load" ] && a2enmod "$PHPMOD" >/dev/null 2>&1 || true
    # protections et page d'accueil du dashboard (le reste de /var/www/html n'est pas modifié)
    cat > "/etc/apache2/conf-available/$APP_NAME.conf" <<APC
<Directory ${INSTALL_DIR}>
    DirectoryIndex index.php index.html
</Directory>
<Directory ${INSTALL_DIR}/include/backups>
    Require all denied
</Directory>
<FilesMatch "^(README\.md|install\.sh|.*\.bak)\$">
    Require all denied
</FilesMatch>
APC
    a2enconf "$APP_NAME" >/dev/null
    URL_SHOWN="http://${DOMAIN:-$HOSTIP}/"
    if [ -n "$DOMAIN" ]; then
      [ "$PORT" = "80" ] || grep -qs "^Listen $PORT\$" /etc/apache2/ports.conf || echo "Listen $PORT" >> /etc/apache2/ports.conf
      cat > "/etc/apache2/sites-available/$APP_NAME.conf" <<APC
<VirtualHost *:${PORT}>
    ServerName ${DOMAIN}
    DocumentRoot ${INSTALL_DIR}
    ErrorLog \${APACHE_LOG_DIR}/${APP_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APP_NAME}_access.log combined
</VirtualHost>
APC
      a2ensite "$APP_NAME" >/dev/null
      URL_SHOWN="http://${DOMAIN}$([ "$PORT" = "80" ] || echo ":$PORT")/"
    fi
    apache2ctl configtest >/dev/null 2>&1 || { apache2ctl configtest; die "Configuration Apache invalide."; }
    reload_srv enable apache2; reload_srv restart apache2
    ok "Apache configuré et redémarré."
  else
    FPM_SOCK="$(ls /run/php/php${PHP_VER}-fpm.sock 2>/dev/null || ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"
    [ -n "$FPM_SOCK" ] || { reload_srv start "php${PHP_VER}-fpm"; sleep 1; FPM_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)"; }
    [ -n "$FPM_SOCK" ] || die "Socket PHP-FPM introuvable (service php${PHP_VER}-fpm démarré ?)."
    mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled
    cat > "/etc/nginx/sites-available/$APP_NAME.conf" <<NGC
server {
    listen ${PORT};
    listen [::]:${PORT};
    server_name ${DOMAIN:-_};
    root ${INSTALL_DIR};
    index index.php index.html;
    charset utf-8;

    location / { try_files \$uri \$uri/ =404; }
    location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:${FPM_SOCK}; }
    location ~* ^/(README\.md|install\.sh|.*\.bak)\$ { deny all; }
    location ^~ /include/backups/ { deny all; }
    location ~ /\. { deny all; }
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options SAMEORIGIN;
}
NGC
    ln -sf "/etc/nginx/sites-available/$APP_NAME.conf" "/etc/nginx/sites-enabled/$APP_NAME.conf"
    nginx -t >/dev/null 2>&1 || { nginx -t; die "Configuration Nginx invalide."; }
    reload_srv enable nginx; reload_srv enable "php${PHP_VER}-fpm"; reload_srv restart "php${PHP_VER}-fpm"; reload_srv restart nginx
    PORT_TXT=""; [ "$PORT" = "80" ] || PORT_TXT=":$PORT"
    URL_SHOWN="http://${DOMAIN:-$HOSTIP}${PORT_TXT}/"
    ok "Nginx + PHP-FPM configurés et redémarrés."
  fi

  if [ "$OPEN_FIREWALL" -eq 1 ] && command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow "${PORT}/tcp" >/dev/null 2>&1 && ok "Pare-feu ufw : port ${PORT} ouvert." || true
    [ "$WITH_HTTPS" -eq 1 ] && ufw allow 443/tcp >/dev/null 2>&1 || true
  fi

  if [ "$WITH_HTTPS" -eq 1 ]; then
    info "Certificat Let's Encrypt pour $DOMAIN"
    if certbot --"$SERVER" -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect >/dev/null 2>&1; then
      URL_SHOWN="https://$DOMAIN/"; ok "HTTPS activé."
    else
      warn "Certbot a échoué (le domaine pointe-t-il bien vers ce serveur, ports 80/443 ouverts ?). Relance :  certbot --$SERVER -d $DOMAIN"
    fi
  fi
else
  [ "$IS_UPDATE" -eq 1 ] && { command -v systemctl >/dev/null 2>&1 && { systemctl reload apache2 >/dev/null 2>&1 || true; systemctl reload nginx >/dev/null 2>&1 || true; }; }
fi
[ "$NEED_RESTART" -eq 1 ] && [ "$NO_WEBSERVER" -eq 0 ] && { reload_srv restart apache2; reload_srv restart nginx; reload_srv restart "php${PHP_VER}-fpm"; } 2>/dev/null || true

# ----------------------------- contrôle final --------------------------------
info "Contrôle de la syntaxe PHP…"
BAD=0
while IFS= read -r f; do case "$f" in *.php) php -l "$INSTALL_DIR/$f" >/dev/null 2>&1 || { warn "Erreur de syntaxe : $f"; BAD=1; };; esac; done < <(list_our_files)
[ "$BAD" -eq 0 ] && ok "Tous les fichiers PHP sont valides."

echo
echo "${C_G}=============================================================${C_0}"
echo "${C_G}  SvxReflector Live : installation terminée${C_0}"
echo "${C_G}=============================================================${C_0}"
[ -n "$URL_SHOWN" ] && echo "  Adresse         : $URL_SHOWN"
echo "  Dossier         : $INSTALL_DIR"
[ "$IS_UPDATE" -eq 0 ] && echo "  Données d'état  : $STATE_DIR"
echo "  PHP             : $PHP_VER   Serveur web : ${SERVER:-—}   Utilisateur : $WEB_USER"
if [ "$IS_UPDATE" -eq 0 ]; then
  echo "  Visiteurs       : $VISITOR_USER / $VISITOR_PASS"
  echo "  Admin           : rnfa-admin / $ADMIN_PASS   (page include/admin.php)"
  echo
  echo "  ${C_Y}Note ces mots de passe maintenant, ils ne seront plus affichés.${C_0}"
  echo "  Modifie-les ensuite dans config.php ou via la page admin."
fi
[ -n "$SERVER" ] && echo "  Logs web        : /var/log/$([ "$SERVER" = apache ] && echo apache2 || echo nginx)/"
echo "  Mise à jour     : sudo ./install.sh --update"
echo "  Désinstallation : sudo ./install.sh --uninstall [--purge]"
echo
