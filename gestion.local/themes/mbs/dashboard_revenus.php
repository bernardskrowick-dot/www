<?php
/* Version: v1.21.0 (Rev #14) - 2026-08-08 */

/**
 * dashboard_revenus.php - Partie PHP / Traitement et initialisation
 * 
 * RÈGLES APPLIQUÉES :
 * - Sécurité : Requêtes préparées PDO avec gestion des sous-catégories.
 * - Constantes : Utilisation de __DIR__ et BASE_URL.
 * - Commentaires : Documentation claire et sections numérotées.
 */

// -------------------------------------------------------------------------
// 0. CHARGEMENT DES REQUÊTES EXTERNALISÉES
// -------------------------------------------------------------------------
require_once __DIR__ . '/header.php';
require_once DIR_INCLUDES . 'sql/dashboard_revenus_queries.php';

// Vérification de la session utilisateur
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$aujourdhui = date('Y-m-d');



// -------------------------------------------------------------------------
// 1. RÉCUPÉRATION ET VALIDATION DES FILTRES
// -------------------------------------------------------------------------
$filtreOrganisme = !empty($_GET['organisme']) ? trim($_GET['organisme']) : '';
$filtreStatut = !empty($_GET['statut_filter']) ? trim($_GET['statut_filter']) : '';
if (!empty($filtreStatut) && !in_array($filtreStatut, ['attendu', 'percu', 'en_retard'], true)) {
    $filtreStatut = '';
}

$categorieFilter = !empty($_GET['categorie_filter']) ? (int) $_GET['categorie_filter'] : 0;
$anneeFilter = !empty($_GET['annee_filter']) ? trim($_GET['annee_filter']) : '';

// -------------------------------------------------------------------------
// 2. RÉCUPÉRATION DES ANNÉES DISPONIBLES POUR LE FILTRE
// -------------------------------------------------------------------------
$anneesDisponibles = getDashboardRevenusAnneesDisponibles($pdo, $user_id);

// -------------------------------------------------------------------------
// 3. RÉCUPÉRATION ET FILTRAGE DES RESSOURCES
// -------------------------------------------------------------------------
$ressources = getDashboardRevenusFiltres(
    $pdo,
    $user_id,
    $filtreOrganisme,
    $filtreStatut,
    $categorieFilter,
    $anneeFilter,
    $aujourdhui
);

// -------------------------------------------------------------------------
// 4. CALCULS GLOBAUX ET INITIALISATION DES DONNÉES RESSOURCES
// -------------------------------------------------------------------------
$totalMontantPrevu = 0;
$totalMontantPercu = 0;

foreach ($ressources as &$ressource) {
    $ressource['total_prevu'] = (float) ($ressource['total_prevu'] ?? 0);
    $ressource['total_percu'] = (float) ($ressource['total_percu'] ?? 0);
    $ressource['nb_total_versements'] = (int) ($ressource['nb_total_versements'] ?? 0);
    $ressource['nb_percus'] = (int) ($ressource['nb_percus'] ?? 0);
    $ressource['nb_restants'] = (int) ($ressource['nb_restants'] ?? 0);

    $ressource['reste_a_percevoir'] = max(0, $ressource['total_prevu'] - $ressource['total_percu']);
    $ressource['pourcentage'] = ($ressource['total_prevu'] > 0) ? min(100, ($ressource['total_percu'] / $ressource['total_prevu']) * 100) : 0;

    $totalMontantPrevu += $ressource['total_prevu'];
    $totalMontantPercu += $ressource['total_percu'];
}
unset($ressource);

// -------------------------------------------------------------------------
// 5. CHARGEMENT ET TRAITEMENT DES REVENUS MENSUELS
// -------------------------------------------------------------------------
$rows = getDashboardRevenusParMois(
    $pdo,
    $user_id,
    $filtreOrganisme,
    $filtreStatut,
    $categorieFilter,
    $anneeFilter,
    $aujourdhui
);

$revenusParMois = [];
$totalRevenusPrevu = 0;
$totalRevenusRecu = 0;
$nbVersementsAttendus = 0;
$totalRevenusAttendus = 0;

foreach ($rows as $r) {
    $mois = $r['mois'];
    $revenusParMois[$mois] = [
        'percu' => (float) $r['revenu_percu'],
        'attendu' => (float) $r['revenu_attendu'],
        'total' => (float) $r['revenu_total_prevu'],
    ];

    $totalRevenusPrevu += (float) $r['revenu_total_prevu'];
    $totalRevenusRecu += (float) $r['revenu_percu'];
    $totalRevenusAttendus += (float) $r['revenu_attendu'];

    if ((float) $r['revenu_attendu'] > 0) {
        $nbVersementsAttendus++;
    }
}

// -------------------------------------------------------------------------
// 6. CALCULS DES KPI COMPLÉMENTAIRES
// -------------------------------------------------------------------------
$totalRessources = count($ressources);

$totalRestant = max(
    0,
    $totalRevenusPrevu - $totalRevenusRecu
);

$totalVersements = array_sum(
    array_map(
        fn($r) => $r['nb_total_versements'],
        $ressources
    )
);

// -------------------------------------------------------------------------
// 7. CALCULS DES KPI ET TITRE DYNAMIQUE DE CATÉGORIE
// -------------------------------------------------------------------------
$totalSourcesRevenus = count($ressources);
$totalVersementsRevenus = 0;
$totalRevenusAttendus = 0;

foreach ($ressources as $ressource) {
    $totalVersementsRevenus += (int) ($ressource['nb_total_versements'] ?? 0);

    $totalRevenusAttendus += max(
        0,
        ($ressource['total_prevu'] ?? 0) - ($ressource['total_percu'] ?? 0)
    );
}

// Libellé dynamique KPI Catégorie
$titreKpiCategorie = 'Total encaissé';

if ($categorieFilter > 0) {
    $stmtCategorie = $pdo->prepare('
        SELECT nom
        FROM categories_ressources
        WHERE id = :id
        LIMIT 1
    ');

    $stmtCategorie->execute([
        'id' => $categorieFilter
    ]);

    $categorieSelectionnee = $stmtCategorie->fetch(PDO::FETCH_ASSOC);

    if ($categorieSelectionnee) {
        $titreKpiCategorie = 'Total ' . $categorieSelectionnee['nom'];
    }
}

$totalPaye = 0;
$totalEcheances = 0;

$pourcentageRevenus = ($totalRevenusPrevu > 0)
    ? ($totalRevenusRecu / $totalRevenusPrevu) * 100
    : 0;

// -------------------------------------------------------------------------
// 6. CONFIGURATION DE LA PAGINATION
// -------------------------------------------------------------------------
$parPage = 6;
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalRessourcesFiltrees = count($ressources);
$totalPages = max(1, ceil($totalRessourcesFiltrees / $parPage));

// Extraction des 6 éléments de la page courante (laisse les KPI intègres)
$ressourcesAffichees = array_slice($ressources, ($pageCourante - 1) * $parPage, $parPage);

$parPageMois = 5;
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($revenusParMois);
$totalPagesMois = max(1, ceil($totalMois / $parPageMois));
?>

<?php
/** ==========================================================================
 *  Affichage Messages Flash
 *  ========================================================================== */
?>

<?php require_once DIR_INCLUDES . 'components/flash_messages.php'; ?>

<?php
/** ==========================================================================
 *  Conteneur principal unique pour tout le dashboard
 *  ========================================================================== */
?>
<div class="container mb-4 nav-dashboard-container">

    <?php
    /** ==========================================================================
     *  Section Menu Administrateur et Titre de la page
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/admin_header_nav.php';

    /** ==========================================================================
     *  Fin de Section Admin titre
     *  ========================================================================== */

    /** ==========================================================================
     *  Début d'affichage de la page
     *  ========================================================================== */
    ?>
    <hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">
    <!--******************************************* -->
    <!--** Section Menu Revenus Appel page     ** -->
    <!--******************************************* -->

    <div class="glass-card-nav mt-4 mb-4 align-items-center justify-content-center">

        <?php
        /** ==========================================================================
         *  Section Menu Appel page
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/menu_dashboard_revenus.php'; ?>
    </div>

    <hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">


    <?php
    /** ==========================================================================
     *  KPI REVENUS Compteurs Totaux
     *  ========================================================================== */
    ?>

    <div class="row mt-4 mb-4 text-center align-items-stretch">
        <?php
        /** ==========================================================================
         *  Montant prévu 
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_revenus_montant_prevu.php'; ?>

    </div>
    <?php
    /** ==========================================================================
     *  Total revenus perçus 
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/kpi_revenus_total_percus.php'; ?>

    <?php
    /** ==========================================================================
     *  Revenus à percevoir 
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/kpi_revenus_total_a_percevoir.php'; ?>

</div>
<?php
/** ==========================================================================
 *  Fin de Section KPI REVENUS Compteurs Totaux
 *  ========================================================================== */

/** ==========================================================================
 *  Section Compteurs Badges Revenus
 *  ========================================================================== */
?>

<div class="row mt-4 mb-4 text-center">
    <?php
    /** ==========================================================================
     *  Sources de revenus actifs compteur 
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/kpi_revenus_actifs_compteur.php'; ?>

    <?php
    /** ==========================================================================
     *  Total revenus encaissé
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/kpi_revenus_total_encaisser.php'; ?>

    <?php
    /** ==========================================================================
     *  Nombre de versement compteur
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/kpi_revenus_versements_compteur.php'; ?>

</div>

<?php
/** ==========================================================================
 *  Fin Section Badge Compteurs Revenus
 *  ========================================================================== */

/** ==========================================================================
 *  Section Progressions des revenus
 *  ========================================================================== */
?>

<div class="row">
    <?php require_once DIR_INCLUDES . 'components/kpi_avancement global_revenu_large.php'; ?>
</div>
<?php
/** ==========================================================================
 *  Fin Section Progression
 *  ========================================================================== */

/** ==========================================================================
 *  Section Filtres
 *  ========================================================================== */
?>
<hr class="hr-glass mt-4 mb-4" style="opacity:0.6;">

<div class="row align-items-center justify-content-center mt-4 mb-4">
    <?php
    /** ==========================================================================
     *  FORM SEARCH : Utilisation de col-12 pour occuper toute la largeur du container
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/filtres_dashboard_revenus.php'; ?>

</div>

<?php
/** ==========================================================================
 *  Fin de Section filtres
 *  ========================================================================== */
?>

<hr class="hr-glass mt-4 mb-0" style="opacity:0.6;">
<?php
/** ==========================================================================
 *  5. RENDU HTML / AFFICHAGE DES CARTES DE RESSOURCES
 *  ========================================================================== */
?>
<div class="row mt-4 mb-0">
    <?php require_once DIR_INCLUDES . 'components/badges_dashboard_revenus.php'; ?>
</div>
<?php
/** ==========================================================================
 *  Fin Section Badges Ressources
 *  ========================================================================== */
?>
<hr class="hr-glass mt-0 mb-4" style="opacity:0.6;">
<?php

        /** ==========================================================================
         *  Pagination
         *  ========================================================================== */

require_once DIR_INCLUDES . 'components/pagination_dashboard_revenus.php'; 

/** ==========================================================================
 *  Fin de Section Badges Revenus
 *  ========================================================================== */
?>