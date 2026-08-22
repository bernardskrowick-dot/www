<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */
// =====================================================
// 1. CHEMINS SERVEUR (Pour PHP : include / require)
// =====================================================


if (!defined('DIR_ROOT')) {
    $rootPath = realpath(__DIR__ . '/../');
    define('DIR_ROOT', $rootPath . DIRECTORY_SEPARATOR);
}

if (!defined('DIR_INCLUDES')) define('DIR_INCLUDES', DIR_ROOT . 'includes' . DIRECTORY_SEPARATOR);
if (!defined('DIR_LOGIC')) define('DIR_LOGIC', DIR_INCLUDES); // Uniformisé proprement sur la base de DIR_INCLUDES
if (!defined('DIR_CSS')) define('DIR_CSS', DIR_ROOT . 'css' . DIRECTORY_SEPARATOR);
if (!defined('DIR_JS')) define('DIR_JS', DIR_ROOT . 'js' . DIRECTORY_SEPARATOR);
if (!defined('DIR_ADMIN')) define('DIR_ADMIN', DIR_ROOT . 'admin' . DIRECTORY_SEPARATOR);
if (!defined('DIR_ACHATS')) define('DIR_ACHATS', DIR_ROOT . 'achats' . DIRECTORY_SEPARATOR);
if (!defined('DIR_RESSOURCES')) define('DIR_RESSOURCES', DIR_ROOT . 'ressources' . DIRECTORY_SEPARATOR); // Ajouté par sécurité
if (!defined('DIR_THEMES')) define('DIR_THEMES', DIR_ROOT . 'themes' . DIRECTORY_SEPARATOR);

// =====================================================
// 2. URLS PUBLIQUES (Pour HTML : <a>, <link>, <script>)
// =====================================================
if (!defined('BASE_URL')) define('BASE_URL', '/'); // À adapter si sous-dossier (ex: '/MonProjet/')
if (!defined('URL_CSS')) define('URL_CSS', BASE_URL . 'css/');
if (!defined('URL_JS')) define('URL_JS', BASE_URL . 'js/');
if (!defined('URL_ADMIN')) define('URL_ADMIN', BASE_URL . 'admin/');
if (!defined('URL_INCLUDES')) define('URL_INCLUDES', BASE_URL . 'includes/');
if (!defined('URL_ACHATS')) define('URL_ACHATS', BASE_URL . 'achats/');
if (!defined('URL_RESSOURCES')) define('URL_RESSOURCES', BASE_URL . 'ressources/');
if (!defined('URL_THEMES')) define('URL_THEMES', BASE_URL . 'themes/');

// =====================================================
// 3. LOGIQUE DE THÈME DYNAMIQUE
// =====================================================

// S'assurer que la session est démarrée pour éviter les erreurs lors de la déconnexion
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Détection du thème (Priorité Session > Défaut 'mbs')
$theme_selectionne = $_SESSION['user_theme'] ?? 'mbs';

// Définition des chemins vers le thème actif
if (!defined('DIR_THEME_ACTIF')) define('DIR_THEME_ACTIF', DIR_THEMES . $theme_selectionne . '/');
if (!defined('URL_THEME_ACTIF')) define('URL_THEME_ACTIF', URL_THEMES . $theme_selectionne . '/');

/**
 * Inclusion de la logique spécifique au thème actif
 */
$fonctions_specifiques = DIR_THEME_ACTIF . 'fonctions/fonctions_theme.php';

if (file_exists($fonctions_specifiques)) {
    require_once $fonctions_specifiques;
}

// =====================================================
// 4. SÉCURITÉ DE SYNCHRONISATION (Ajouté ici)
// =====================================================
define('SYNC_SECRET_TOKEN', '3107c299311adfdbc146551d930066b3a8ef64c490549c55a29ec2f73004810d');

// Clé secrète pour le chiffrement du 2FA (à garder secrète sur le serveur)
define('ENCRYPTION_KEY', 'mets_ici_une_chaine_aleatoire_tres_longue_et_complexe_par_exemple_avec_des_caracteres_speciaux');