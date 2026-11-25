# Installation de SvxReflector Live

Dashboard PHP pour SvxReflector – FRS077 Romuald pour RNFA – https://rnfa.fr
© 2026 Romuald (FRS077). Tous droits réservés.

---

## 1. Ce qu'il faut avant de commencer

| Élément | Détail |
|---|---|
| Système | Debian, Ubuntu ou Raspberry Pi OS (gestionnaire de paquets `apt`) |
| Accès | un compte root ou `sudo` (SSH) |
| SvxReflector | déjà installé, avec son log (`/var/log/svxreflector`) et son `/status` (`http://127.0.0.1:8090/status`) |
| PHP | 8.0 ou plus (installé par le script) |
| Mumble (option) | log `/var/log/mumble-server/mumble-server.log` |

Le dashboard est installé **directement dans `/var/www/html`** (adresse `http://IP-DU-SERVEUR/`).
Seuls ses propres fichiers sont écrits : les autres sites du dossier (ex. `freedmr_dashboard`) ne sont pas touchés.

---

## 2. Installation automatique (recommandé)

### Envoyer les fichiers sur le serveur

Depuis ton PC (PowerShell, Windows 10/11 ou Linux/macOS), ou avec FileZilla :

```bash
scp svx_dashboard.zip utilisateur@IP-DU-SERVEUR:/home/utilisateur/
```

### Lancer l'installation

```bash
ssh utilisateur@IP-DU-SERVEUR
sudo apt install -y unzip
unzip svx_dashboard.zip
cd ~            # le dossier qui contient « site » et « install.sh »
chmod +x install.sh
sudo ./install.sh
```

L'assistant demande : serveur web (Apache ou Nginx), nom de domaine (facultatif), chemin du log, adresse `/status`,
log Mumble, identifiant et mots de passe. Appuie sur Entrée pour garder la valeur proposée.

À la fin, le script affiche l'adresse et les mots de passe **(à noter tout de suite, ils ne sont plus affichés)**.

### Exemples

```bash
sudo ./install.sh --server apache --yes                         # Apache, réponses par défaut
sudo ./install.sh --server nginx --domain dash.exemple.fr       # Nginx avec un nom de domaine
sudo ./install.sh --server apache --domain dash.exemple.fr --https --email moi@exemple.fr   # + HTTPS Let's Encrypt
sudo ./install.sh --server apache --user admin --pass MonMotDePasse --admin-pass MonAdmin --yes
```

### Toutes les options

| Option | Rôle |
|---|---|
| `--server apache\|nginx` | serveur web à installer / utiliser |
| `--domain NOM` | crée un hôte virtuel pour ce nom de domaine |
| `--port N` | port d'écoute (Nginx sans domaine : 8080) |
| `--log FICHIER` | log du SvxReflector (défaut `/var/log/svxreflector`) |
| `--status-url URL` | `/status` du reflector (défaut `http://127.0.0.1:8090/status`) |
| `--mumble-log FICHIER` | log du serveur Mumble |
| `--state-dir DOSSIER` | données d'état permanentes (défaut `/var/lib/svxreflector-dashboard`) |
| `--user NOM` / `--pass MOT` | identifiants visiteurs du dashboard |
| `--admin-pass MOT` | mot de passe de la page admin (`include/admin.php`) |
| `--https --email ADRESSE` | certificat Let's Encrypt (domaine public obligatoire) |
| `--no-firewall` | ne touche pas à `ufw` |
| `--no-packages` | n'installe aucun paquet (serveur web et PHP déjà prêts) |
| `--no-webserver` | copie et configure seulement le dashboard |
| `--update` | mise à jour du code |
| `--uninstall [--purge]` | retire la configuration web (`--purge` : supprime aussi les fichiers du dashboard) |
| `--yes` | aucune question |

### Ce que fait le script

1. Installe le serveur web, PHP 8.x et ses extensions (curl, mbstring, xml, zip).
2. Copie le dashboard dans `/var/www/html` (en sauvegardant dans `/root/` un `index.php` ou `config.php` déjà présent).
3. Règle `config.php` : mots de passe, chemins des logs, dossier d'état permanent.
4. Donne au serveur web le droit de lire les logs (groupe ou ACL).
5. Crée la configuration Apache ou Nginx, ouvre le pare-feu `ufw` s'il est actif, active HTTPS si demandé.
6. Vérifie la syntaxe PHP de tous les fichiers.

---

## 3. Installation manuelle (sans le script)

```bash
sudo apt update
sudo apt install -y apache2 libapache2-mod-php php php-cli php-curl php-mbstring php-xml php-zip unzip acl
sudo unzip svx_dashboard.zip -d /tmp/svx
sudo cp -r /tmp/svx/site/* /var/www/html/
sudo mkdir -p /var/lib/svxreflector-dashboard
sudo chown -R www-data:www-data /var/www/html/{index.php,config.php,css,include,image} /var/lib/svxreflector-dashboard
sudo chmod 660 /var/www/html/config.php
sudo nano /var/www/html/config.php      # voir section 4
sudo usermod -aG adm www-data           # lecture des logs (adapter le groupe du fichier log)
sudo systemctl restart apache2
```

Pour Nginx : remplacer `apache2 libapache2-mod-php` par `nginx php-fpm`, puis configurer un `server` avec
`fastcgi_pass unix:/run/php/php8.x-fpm.sock;`.

---

## 4. Réglages dans `config.php`

| Réglage | À faire |
|---|---|
| `AUTH_USER` / `AUTH_PASS` | identifiants des visiteurs (`ENABLE_AUTH` = `false` pour un accès libre) |
| `LOG_FILE` | log du SvxReflector |
| `STATUS_URL` | adresse `/status` du reflector |
| `MUMBLE_LOG`, `GATEWAY_NAME` | Mumble et nom de la passerelle |
| `STATE_FILE`, `HISTORY_FILE`, `VISITORS_FILE`, `MUMBLE_STATE` | dossier permanent (le script utilise `/var/lib/svxreflector-dashboard`) |
| `LOG_TIMEZONE` | fuseau du log (`Europe/Paris`) |
| Sections 3 à 10d | affichages `true` / `false` : stations, TG, météo, serveur, menus, fond d'écran… |
| `ADMIN_PASS_HASH` | mot de passe admin (à changer depuis `include/admin.php`, jamais en clair) |

Les réglages se modifient aussi depuis la page admin : `http://IP-DU-SERVEUR/include/admin.php`.

---

## 5. Vérifier que tout fonctionne

1. Ouvre `http://IP-DU-SERVEUR/` : page de connexion, puis dashboard.
2. Les stations doivent apparaître dans « Stations actives ». Sinon : `LOG_FILE` / `STATUS_URL` ou droits de lecture (section 7).
3. Pages à tester : Infos utilisateurs, Statistiques, Carte, Mumble, Talkgroupes, Serveur, Log.
4. La version est affichée en bas de chaque page (lue dans `version.txt`).

---

## 6. Mise à jour

Un petit point rouge clignote dans le pied de page de l'index quand une nouvelle version est publiée sur GitHub (vérification une fois par jour). La page **Nouveautés** détaille les changements.

```bash
unzip -o svx_dashboard.zip          # nouvelle version
sudo ./install.sh --update
```

Ton `config.php` et ton `version.txt` sont conservés (une sauvegarde de `config.php` est faite dans `include/`).
Les nouvelles options absentes de ton `config.php` prennent leurs valeurs par défaut (`include/defaults_tg.php`).

---

## 7. Dépannage

| Symptôme | Cause probable / solution |
|---|---|
| Page blanche | erreur PHP : `sudo tail -n 30 /var/log/apache2/error.log` (ou `/var/log/nginx/error.log`) ; vérifier que `include/footer.php` et `include/defaults_tg.php` sont bien présents |
| « Fichier log introuvable ou non lisible » | `sudo -u www-data cat /var/log/svxreflector \| head -1` ; sinon `sudo usermod -aG adm www-data` (ou le groupe du fichier : `ls -l /var/log/svxreflector`), puis `sudo systemctl restart apache2` |
| « Statut indisponible » | `curl http://127.0.0.1:8090/status` doit répondre ; corriger `STATUS_URL` |
| Admin : enregistrement impossible | `sudo chown www-data:www-data /var/www/html/config.php && sudo chmod 660 /var/www/html/config.php` |
| Historique perdu au redémarrage | utiliser un dossier permanent (`/var/lib/svxreflector-dashboard`) pour `HISTORY_FILE` et `STATE_FILE` |
| Heures décalées | régler `LOG_TIMEZONE` |
| Météo absente | le navigateur du visiteur doit pouvoir joindre `api.open-meteo.com` ; `WEATHER_ENABLE` = `true` |
| PHP trop ancien (< 8.0) | Debian 11 / Ubuntu 20.04 : ajouter le dépôt Sury ou passer à une version plus récente |

---

## 8. Désinstallation

```bash
sudo ./install.sh --uninstall            # retire seulement la configuration web
sudo ./install.sh --uninstall --purge    # supprime aussi les fichiers du dashboard et les données d'état
```

`--purge` ne supprime que les fichiers du dashboard (jamais le dossier `/var/www/html` en entier) et sauvegarde
`config.php` dans `/root/svxreflector-backup-…`.

---

## 9. Sécurité : à faire après l'installation

- Changer les mots de passe générés si besoin (`config.php` et page admin).
- Passer en HTTPS (`--https`) dès que le dashboard est accessible depuis Internet.
- Ne jamais laisser le mot de passe admin ou visiteur par défaut (`rnfa*2026`).
- Les adresses IP de la page Log sont masquées côté serveur (`LOG_MASK_IP`).
