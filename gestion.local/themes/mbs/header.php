<?php
/* Version: v1.16.2 (Rev #24) - 2026-08-05 */

/**
 * =====================================================
 * HEADER DU THÈME : MBS_WHITE
 * =====================================================
 * RÔLE : Gérer l'affichage de la structure <head> et
 * l'ouverture du layout principal pour le mode clair.
 * -----------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($titrePage, ENT_QUOTES, 'UTF-8') ?> - MBS WHITE</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="<?= htmlspecialchars(URL_THEME_ACTIF, ENT_QUOTES, 'UTF-8') ?>css/bootstrap.min.css" rel="stylesheet">

    <link href="<?= htmlspecialchars(URL_THEME_ACTIF, ENT_QUOTES, 'UTF-8') ?>css/style.css?v=<?= time() ?>"
        rel="stylesheet">

    <?php
    /* --- CHARGEMENT JS OPTIONNEL --- */
    // On vérifie si un script personnalisé existe pour ce thème
    $chemin_js_theme = DIR_THEME_ACTIF . 'js/custom.js';
    if (file_exists($chemin_js_theme)):
    ?>
        <script src="<?= htmlspecialchars(URL_THEME_ACTIF, ENT_QUOTES, 'UTF-8') ?>js/custom.js?v=<?= time() ?>" defer></script>
    <?php endif; ?>
</head>

<body class="mbs-white-theme">

    <?php include 'navbar.php'; ?>

    <main class="main-content">