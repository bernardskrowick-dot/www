<?php
/* Version: v1.5.1 (Rev #14) - 2026-08-01 */

/**
 * =====================================================
 * ROUTER.PHP – VERSION CLEAN 3.1
 * =====================================================
 * RÔLE :
 * - Routing dynamique des pages (Thèmes vs Racine)
 * - Injection globale Header / Topbar / Footer
 * =====================================================
 */
require_once __DIR__ . '/init.php';
ob_start();
/* -----------------------------
   1. ANALYSE DE LA PAGE DEMANDÉE
----------------------------- */
$p = $_GET['p'] ?? 'dashboard.php';

// Nettoyage de l'extension pour traiter proprement le chemin
$slug = str_replace('.php', '', $p);
$fileNameOnly = basename($slug) . '.php';

/* -----------------------------
   2. TITRE DE LA PAGE
----------------------------- */
$pageName = str_replace('.php', '', $fileNameOnly);

if (function_exists('getPageDetails')) {
    $details = getPageDetails($pageName);
    $titrePage = $details['emoji'] . ' ' . $details['text'];
} else {
    $titrePage = ucwords(str_replace('_', ' ', $pageName));
}

/* -----------------------------
   3. HEADER THÈME
----------------------------- */
$theme_header = DIR_THEME_ACTIF . 'header.php';
if (file_exists($theme_header)) {
    include $theme_header;
}

/* -----------------------------
   4. TOP BAR (Optionnel global)
----------------------------- */
$topbar_path = DIR_THEME_ACTIF . 'includes/top-bar-title-page.php';
if (!file_exists($topbar_path)) {
    $topbar_path = __DIR__ . '/includes/top-bar-title-page.php';
}

/* -----------------------------
   5. ARBORESCENCE ET RESOLUTION DES CHEMINS
----------------------------- */
$paths = [
    // A. Recherche directe si un sous-dossier est fourni dans $_GET['p'] (ex: p=achats/echeancier)
    DIR_THEME_ACTIF . $slug . '.php',

    // B. Thème : Modules
    DIR_THEME_ACTIF . 'achats/' . $fileNameOnly,
    DIR_THEME_ACTIF . 'ressources/' . $fileNameOnly,
    DIR_THEME_ACTIF . 'profil/' . $fileNameOnly,
    DIR_THEME_ACTIF . 'includes/' . $fileNameOnly,
    DIR_THEME_ACTIF . 'vendor/' . $fileNameOnly,
    DIR_THEME_ACTIF . $fileNameOnly,

    // C. Fallback : Structure Racine (Hors-Thème)
    __DIR__ . '/admin/' . $fileNameOnly,
    __DIR__ . '/profil/' . $fileNameOnly,
    __DIR__ . '/includes/' . $fileNameOnly,
    __DIR__ . '/vendor/' . $fileNameOnly,
    __DIR__ . '/' . $fileNameOnly,
];

$found = false;

/* -----------------------------
   6. CHARGEMENT DE LA PAGE
----------------------------- */
foreach ($paths as $path) {
    if (file_exists($path)) {
        include $path;
        $found = true;
        break;
    }
}

/* -----------------------------
   7. PAGE 404
----------------------------- */
if (!$found) {
    echo "<div class='container mt-5 text-white'>";
    echo "<div class='alert alert-warning bg-dark border-warning'>";
    echo '❌ Page introuvable : <strong>' . htmlspecialchars($fileNameOnly) . '</strong>';
    echo '</div>';
    echo "<a href='router.php?p=dashboard.php' class='btn btn-secondary'>🏠 Retour</a>";
    echo '</div>';
}

/* -----------------------------
   8. FOOTER THÈME
----------------------------- */
$theme_footer = DIR_THEME_ACTIF . 'footer.php';
if (file_exists($theme_footer)) {
    require_once $theme_footer;
}
ob_end_flush();