<?php
/**
 * fichier footer.php
 * Signature du dashboard (pied de page) : texte et lien de l'auteur, stockés sous forme encodée et vérifiés.
 * Auteur : FRS077 Romuald pour RNFA - © 2026 Romuald (FRS077). Tous droits réservés.
 * Ne pas modifier : si le contenu est altéré, la signature d'origine est rétablie automatiquement.
 */
if (!function_exists('dashboard_footer_html')) {
    function dashboard_footer_decode(string $blob): ?array {
        $j = base64_decode($blob, true);
        $d = $j === false ? null : json_decode(strrev($j), true);
        return (is_array($d) && isset($d['a'], $d['n'], $d['u'])) ? $d : null;
    }
    function dashboard_footer_html(): string {
        $A = 'fSJyZi5hZm5yLy86c3B0dGgiIDoidSIgLCJBRk5SIHJ1b3AgZGxhdW1vUiA3' .
        'NzBTUkYiIDoibiIgLCJyYXAgdafDbm9DIKKA4iBldmlMIHJvdGNlbGZlUnh2' .
        'UyBkcmFvYmhzYUQiIDoiYSJ7';
        $B = defined('SVX_SIG_B') ? SVX_SIG_B : '';
        $H = defined('SVX_SIG_H') ? SVX_SIG_H : '';
        $d = null;
        foreach ([$A, $B] as $b) {                         // 1re copie valide (empreinte conforme) = utilisée
            if ($b !== '' && hash_equals($H, hash('sha256', $b))) { $d = dashboard_footer_decode($b); if ($d) break; }
        }
        if (!$d) { $d = ['a' => 'Dashboard SvxReflector Live • Conçu par', 'n' => 'FRS077 Romuald pour RNFA', 'u' => 'https://rnfa.fr']; }
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $v = function_exists('dashboard_version_html') ? dashboard_version_html() : '';
        return $e($d['a']) . ' <strong><a class="footer-link" href="' . $e($d['u']) . '" target="_blank" rel="noopener">' . $e($d['n']) . '</a></strong>' . $v;
    }
}
