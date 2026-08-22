<?php
/* Version: v1.21.0 (Rev #26) - 2026-08-08 */

/**
 * themes/xx/fonctions_theme.php
 * Logic spécifique au thème "Libre" (xx)
 * ---------------------------------------------------------
 * Ce fichier est chargé uniquement si l'utilisateur a choisi le thème 'xx'.
 */

// Exemple : Fonction de formatage spécifique à ce thème
// (Règle : On commente toujours le code)
function theme_xx_format_prix(float|int|string $montant)
{
    // Dans ce thème, on affiche les prix avec un symbole particulier ou une couleur
    return '<strong>' . number_format((float)$montant, 2, ',', ' ') . ' €</strong>';
}
/**
 * Note Sécurité :
 * Comme ce fichier est inclus dans config.php, il a accès à $pdo et à la session.
 * On peut donc y ajouter des vérifications de droits spécifiques si besoin.
 */

// On peut aussi définir des constantes de style propres à ce thème
if (!defined('THEME_COLOR_MAIN')) {
    define('THEME_COLOR_MAIN', '#4e73df');
}

function formatDateFr(string|null $date)
{
    if (!$date)
        return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d-m-Y') : '—';
}


