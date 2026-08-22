<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */

/**
 * =====================================================
 * HEADER SÉCURISÉ – GESTION ÉCHÉANCES
 * LOGIQUE : CHARGEMENT DYNAMIQUE (LOGIN VS THÈMES)
 * =====================================================
 */

// Déterminer si HTTPS est actif
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// 🔒 Démarrage sécurisé de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_secure' => $isHttps,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// Chargement des dépendances via require_once
require_once __DIR__ . '/config.php';
require_once DIR_INCLUDES . 'connexion.php';
require_once DIR_INCLUDES . 'fonctions.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Pages autorisées sans être complètement connecté
$pages_publiques = ['login.php', 'verify-2fa.php'];

// 🔒 CONTRÔLE D’ACCÈS
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id && !in_array($current_page, $pages_publiques)) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// Anti-cache
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// LOGIQUE DE TITRE
if (!isset($titrePage)) {
    $nomFichier = basename($current_page, '.php');
    $titre = mb_convert_case(str_replace('_', ' ', $nomFichier), MB_CASE_TITLE, "UTF-8");
    $titrePage = $titre;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titrePage, ENT_QUOTES, 'UTF-8') ?> - Gestion Échéances</title>

    <link href="<?= htmlspecialchars(URL_CSS, ENT_QUOTES, 'UTF-8') ?>bootstrap.min.css" rel="stylesheet">

    <?php if (in_array($current_page, $pages_publiques)): ?>
        <!-- Même style que la page de login pour verify-2fa -->
        <link href="<?= htmlspecialchars(URL_CSS, ENT_QUOTES, 'UTF-8') ?>style.css?v=<?= time() ?>" rel="stylesheet">
        <link rel="icon" href="/favicon.png" type="image/png">
        <?php else:
        $url_css_theme = URL_THEME_ACTIF . 'css/style.css';
        $chemin_css_theme = DIR_THEME_ACTIF . 'css/style.css';

        if (file_exists($chemin_css_theme)): ?>
            <link href="<?= htmlspecialchars($url_css_theme, ENT_QUOTES, 'UTF-8') ?>?v=<?= time() ?>" rel="stylesheet">
        <?php else: ?>
            <link href="<?= htmlspecialchars(URL_CSS, ENT_QUOTES, 'UTF-8') ?>style.css" rel="stylesheet">
    <?php endif;
    endif; ?>
</head>

<body class="<?= in_array($current_page, $pages_publiques) ? 'body-login' : 'body-app' ?>">
    <main class="main-content">