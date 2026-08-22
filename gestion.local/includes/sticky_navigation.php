<?php
/* Version: v1.5.1 (Rev #14) - 2026-08-01 */

/**
 * =====================================================
 * COMPOSANT : NAVIGATION STICKY FLOTTANTE
 * =====================================================
 * RÔLE : Barre de raccourcis dynamique vers les tableaux
 * de bord principaux (Dashboard, Achats, Revenus, Mensuel).
 * -----------------------------------------------------
 */

// 1. Position selon la session utilisateur (défaut : 'right')
$stickyPosition = $_SESSION['user_sticky_pos'] ?? 'right';
$stickyClass = ($stickyPosition === 'left') ? 'sticky-nav-left' : 'sticky-nav-right';

// 2. Détection de la page courante (s'assure que .php est présent pour la comparaison)
$currentPage = $_GET['p'] ?? 'dashboard.php';
if (substr($currentPage, -4) !== '.php') {
    $currentPage .= '.php';
}
?>

<?php
/** ==========================================================================
 *  Barre de navigation sticky flottante
 *  ========================================================================== */
?>
<div class="sticky-dashboard-nav <?= htmlspecialchars($stickyClass, ENT_QUOTES, 'UTF-8') ?>">
    <div class="sticky-nav-container">
        <?php
        /** ==========================================================================
         *  Lien Dashboard Principal
         *  ========================================================================== */
        ?>

        <a href="<?= BASE_URL ?>router.php?p=dashboard.php"
            class="sticky-nav-item <?= ($currentPage === 'dashboard.php' || $currentPage === 'index.php') ? 'active' : '' ?>"
            title="Dashboard Principal">
            <span class="sticky-icon">🏠</span>
            <span class="sticky-badge">Général</span>
        </a>
        <?php
        /** ==========================================================================
         *  Lien Dashboard Achats 
         *  ========================================================================== */
        ?>

        <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
            class="sticky-nav-item <?= ($currentPage === 'dashboard_achats.php') ? 'active' : '' ?>"
            title="Dashboard Achats">
            <span class="sticky-icon">🛒</span>
            <span class="sticky-badge">Achats</span>
        </a>
        <?php
        /** ==========================================================================
         *  Lien Dashboard Revenus
         *  ========================================================================== */
        ?>
      
        <a href="<?= BASE_URL ?>router.php?p=dashboard_revenus.php"
            class="sticky-nav-item <?= ($currentPage === 'dashboard_revenus.php') ? 'active' : '' ?>"
            title="Dashboard Revenus">
            <span class="sticky-icon">💰</span>
            <span class="sticky-badge">Revenus</span>
        </a>
        <?php
        /** ==========================================================================
         *  Lien Tableau Mensuel
         *  ========================================================================== */
        ?>
      
        <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php"
            class="sticky-nav-item <?= ($currentPage === 'Tableau_mensuel.php') ? 'active' : '' ?>"
            title="Tableau Mensuel">
            <span class="sticky-icon">📅</span>
            <span class="sticky-badge">Mensuel</span>
        </a>

    </div>
</div>