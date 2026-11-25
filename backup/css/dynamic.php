<?php
/**
 * css/dynamic.php
 * Styles qui dépendent de config.php (vrai CSS envoyé au navigateur) :
 *   - image de fond (BACKGROUND_ENABLE / BACKGROUND_FILE)
 *   - menus déroulants Liens / Réseaux
 *   - couleurs des icônes de la carte (MAP_TYPE_COLORS, MAP_COLOR_*)
 * Le chemin de l'image est relatif à ce fichier (css/ -> ../image/...).
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: no-cache');

if (MAP_TYPE_COLORS && MAP_TYPE_ICONS) {
    $mapColors = ['relay' => MAP_COLOR_RELAY, 'gateway' => MAP_COLOR_GATEWAY, 'mobile' => MAP_COLOR_MOBILE, 'portable' => MAP_COLOR_PORTABLE, 'station' => MAP_COLOR_STATION];
    foreach ($mapColors as $t => $col) {
        if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $col)) continue;
        echo ".ant-icon.on.t-$t { color: $col; }\n";
    }
}
echo dashboard_menus_css(), "\n";
echo dashboard_background_css('../'), "\n";
