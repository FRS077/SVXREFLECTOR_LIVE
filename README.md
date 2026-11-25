# SvxReflector Live

Tableau de bord web en direct pour un réflecteur **SvxLink** (réseau RNFA) : stations connectées, transmissions (PTT ON/OFF), statistiques, carte, passerelle Mumble, journal des événements et page d'administration.

> **© 2026 Romuald Botte. Tous droits réservés.**
> Ce site, son code source, son design et ses contenus sont protégés. Toute reproduction, distribution ou réutilisation sans autorisation écrite est interdite.

Conçu par **FRS077 Romuald** pour **RNFA**.

---

## ⚠️ Ce fichier contient des identifiants

Ne laissez **pas** ce `README.md` dans le dossier public du site (`/var/www/html`) : il serait téléchargeable par tout le monde.
Gardez-le sur votre ordinateur, ou supprimez-le du serveur après lecture.
Changez les mots de passe ci-dessous dès la première connexion.

---

## 1. Contenu du site

```
/var/www/html/
├── index.php            Dashboard principal
├── config.php           Tous les réglages (true / false, nombres, textes, liens)
├── version.txt          Numéro de version affiché dans les pieds de page
├── css/                 Tous les styles (index, stats, info, carte, mumble, log, admin, login)
│   └── dynamic.php      Styles dépendant de config.php (image de fond, menus, couleurs carte)
├── image/               Image de fond (fond-uhf-fibre.svg)
├── include/             Toutes les autres pages et les API
│   ├── info.php         Infos utilisateurs (fiches des stations)
│   ├── stats.php        Statistiques jour / semaine / mois / année
│   ├── carte.php        Carte (France, icônes par type de station)
│   ├── mumble.php       Page Mumble (connectés, PTT, historique, alertes)
│   ├── log.php          Journal des événements (IP masquées)
│   ├── admin.php        Administration de config.php
│   └── api_*.php        Données JSON utilisées par les pages
└── backup/              Sauvegardes automatiques de config.php (créé par vous, voir §4)
```

## 2. Identifiants

### Visiteurs (dashboard)

Réglés dans `config.php`, section 1 (modifiables aussi depuis la page admin) :

| Réglage | Valeur par défaut |
|---|---|
| `ENABLE_AUTH` | `true` (`false` = accès libre sans mot de passe) |
| `AUTH_USER` | `admin` |
| `AUTH_PASS` | `rnfa*2026` |

### Administrateur (`include/admin.php`)

Identifiants **différents** de ceux des visiteurs, section 11 de `config.php` :

| Réglage | Valeur |
|---|---|
| `ADMIN_USER` | `rnfa-admin` |
| mot de passe provisoire | `c4hJ7iUGiJ38` (à changer immédiatement) |

- Le mot de passe admin est stocké sous forme d'**empreinte chiffrée** (`ADMIN_PASS_HASH`), jamais en clair.
- Pour le changer : page admin → bloc « Mot de passe administrateur » (10 caractères minimum, différent de celui des visiteurs).
- Mot de passe oublié : générer une nouvelle empreinte sur le serveur, puis la coller dans `ADMIN_PASS_HASH` :
  ```
  php -r 'echo password_hash("NouveauMotDePasse", PASSWORD_DEFAULT), "\n";'
  ```
- Protections : blocage 15 minutes après 5 échecs, déconnexion après 30 minutes d'inactivité (`ADMIN_SESSION_MIN`), protection CSRF.
- `ADMIN_ENABLE = false` désactive entièrement la page (erreur 404).
- Utilisez **https** si la page est accessible depuis Internet.

## 3. Réglages généraux (`config.php`)

Chaque ligne est de la forme `define('NOM', valeur);` avec un commentaire. `true` = affiché / activé, `false` = masqué.

| Section | Contenu |
|---|---|
| 1. Général | authentification visiteurs, nom de l'auteur, fuseau horaire des logs, limite de parole (`TALK_MAX_SEC`) |
| 2. Fichiers et services | `LOG_FILE`, `STATUS_URL`, fichiers d'état, log Mumble, noms des passerelles, durées de conservation |
| 3. Page principale | visiteurs en direct, colonnes Prénom / Ville, dernières déconnexions, liens Facebook / Contact, bouton Admin, alerte « relais tombé » |
| 4. Statistiques | onglets jour / semaine / mois / année et blocs affichés |
| 5. Carte | focus France, zoom, légende, icônes par type (-RE relais, -GW passerelle, -M mobile, -P portable) et couleurs |
| 6. Mumble | blocs affichés et alertes sonores (connexion, départ, passerelle) |
| 7. Version | lue dans `version.txt` |
| 8. Image de fond | `BACKGROUND_ENABLE`, `BACKGROUND_FILE` (thème sombre) |
| 9. Page Log | types d'événements, masquage des IP (`LOG_MASK_IP`, `LOG_MASK_OCTETS`), rafraîchissement |
| 10. Menus | menus déroulants « Liens » et « Réseaux » (une entrée sans adresse n'est pas affichée) |
| 11. Administration | identifiants admin, sauvegardes, session |

Deux façons de modifier les réglages :
1. **Page admin** (recommandé) : `https://votre-site/include/admin.php` ou bouton « Admin » du dashboard (`INDEX_SHOW_ADMIN_LINK`).
2. En éditant `config.php` à la main (FileZilla, éditeur…).

## 4. Installation / droits sur le serveur

Se connecter en **SSH** sur le serveur, puis :

```
cd /var/www/html
sudo mkdir -p backup
sudo chown debian:www-data config.php backup
sudo chmod 664 config.php
sudo chmod 775 backup
```

Pourquoi : la page admin doit pouvoir écrire dans `config.php` et créer ses sauvegardes dans `backup/`.
Avec `debian:www-data`, le compte `debian` (FileZilla / SFTP) garde la main et le serveur web (`www-data`) peut écrire grâce au groupe.

Autres points :
- Le serveur web doit pouvoir **lire** les logs (`/var/log/svxreflector`, `/var/log/mumble-server/mumble-server.log`).
- `STATUS_URL` pointe vers le `/status` du réflecteur (par défaut `http://127.0.0.1:8090/status`).
- Mettre `HISTORY_FILE` dans un dossier permanent (pas `/tmp`) pour conserver les statistiques après un redémarrage.
- PHP 7.4 ou plus, avec `mbstring`.
- En haut de la page admin, un encadré indique si l'écriture est autorisée (vert) ou bloquée (rouge, avec les commandes à taper).

## 5. Sauvegardes et retour arrière

- À chaque enregistrement depuis la page admin, une copie de `config.php` est créée dans `backup/` (`config_AAAAMMJJ_HHMMSS.php`). Les 15 dernières sont conservées (`ADMIN_BACKUP_KEEP`).
- Restauration : page admin → bloc « Sauvegardes de config.php » → choisir la date → Restaurer.
- Avant de remplacer des fichiers du site, gardez une copie de l'ancienne version.

## 6. Mise à jour

1. Copier les nouveaux fichiers sur le serveur (ne pas écraser un `config.php` modifié : ajouter seulement les nouvelles lignes).
2. Copier le dossier `css/` **avec** les pages (une page sans son `.css` s'affiche sans mise en forme).
3. Mettre à jour `version.txt` (première ligne = texte affiché dans les pieds de page).

## 7. Sécurité, en bref

- Pas de mot de passe admin en clair ; blocage après échecs ; CSRF ; durée de session limitée.
- Les adresses IP de la page Log sont masquées côté serveur (le navigateur ne reçoit jamais l'IP complète).
- Le dossier `backup/` est protégé par un `.htaccess` (Apache). Avec Nginx, interdire l'accès à `/backup/` dans la configuration du serveur.
- Les réglages sensibles (identifiants admin, chemins de fichiers) ne sont pas modifiables depuis le formulaire admin.

## 8. Licence

**© 2026 Romuald FRS077. Tous droits réservés.**
Ce site, son code source, son design et ses contenus sont protégés. Toute reproduction, distribution ou réutilisation sans autorisation écrite est interdite.
