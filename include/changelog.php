<?php
/**
 * fichier changelog.php
 * Historique des versions affiché sur la page Nouveautés (une ligne par nouveauté, sans détail technique).
 * À compléter à chaque mise à jour : la plus récente en premier.
 */
return [
    ['1.0.29', '', ['Statistiques : analyse des temps de parole sur 30 jours (records, évolution par station, total par jour)', 'Cartes du jour avec le meilleur indicatif et la variation par rapport à hier', 'Les PTT de moins de 2 secondes ne sont plus comptés dans les statistiques', 'Indicatifs masqués (balises) : liste modifiable depuis l\'administration']],
    ['1.0.28', '2026-11-25', ['Boutons de l\'en-tête réorganisables (bouton ✥), date, météo et Déconnexion restent fixes', 'Dernière personne à avoir parlé : badge PTT retiré']],
    ['1.0.27', '2026-11-25', ['Page « Nouveautés » : fonctionnalités et historique des versions', 'Point rouge discret dans le pied de page quand une nouvelle version est publiée sur GitHub']],
    ['1.0.26', '2026-11-25', ['Installation automatique : install.sh (Apache ou Nginx, PHP 8.x), dans /var/www/html']],
    ['1.0.23', '2026-11-25', ['Signature du pied de page protégée']],
    ['1.0.22', '2026-11-25', ['Météo discrète dans l\'en-tête : température, lever / coucher du soleil, icône', 'Noms des TG : 59 principal, 62 dégagement, 100 SSTV, 101 Packet APRS, 102 Test']],
    ['1.0.19', '2026-11-25', ['Pied de page identique sur toutes les pages, avec lien vers rnfa.fr', 'Carte « Dernière station connectée »']],
    ['1.0.18', '2026-11-25', ['Page Serveur : processeur, température, mémoire, disque, réseau, temps de service', 'Ligne « Serveur » sur la page principale']],
    ['1.0.17', '2026-11-25', ['Page Talkgroupes : stations présentes sur chaque TG, alerte hors TG principal', 'Bouton FreeDMR à côté de Mumble']],
    ['1.0.10', '2026-11-23', ['Version validée : stations actives, transmissions, carte, statistiques, Mumble, log, administration']],
];
