<?php

/**
 * =====================================================
 * HEADER DU THÈME : MBS_DARK
 * =====================================================
 * RÔLE : Gérer l'affichage de la structure <head> et
 * l'ouverture du layout principal pour le mode sombre.
 * -----------------------------------------------------
 */
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($titrePage) ?> - MBS DARK</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="<?= URL_THEME_ACTIF ?>css/bootstrap.min.css" rel="stylesheet">

    <link href="<?= URL_THEME_ACTIF ?>css/style-dark.css?v=<?= time() ?>" rel="stylesheet">

    <?php
    /* --- CHARGEMENT JS OPTIONNEL --- */
    // On vérifie si un script personnalisé existe pour ce thème
    $chemin_js_theme = DIR_THEME_ACTIF . 'js/custom.js';
    if (file_exists($chemin_js_theme)):
    ?>
        <script src="<?= URL_THEME_ACTIF ?>js/custom.js?v=<?= time() ?>" defer></script>
    <?php endif; ?>
</head>

<body class="mbs-dark-theme">

    <?php include 'navbar.php'; ?>

    <main class="main-content">