<?php
/* Version: v1.21.0 (Rev #16) - 2026-08-08 */

/**
 * dashboard.php - Tableau de bord avec jauge de progression circulaire
 * VERSION : Intégration Dynamique des Classes CSS de Statut (Bordures & Badges)
 * THEME : MBS_DARK
 * RÈGLES : CSRF, Constantes, Commentaires, Hachage (Session)
 */

// Utilisation des constantes de dossier (Règle stricto sensu)
require_once __DIR__ . '/header.php';
require_once DIR_INCLUDES . 'sql/dashboard_queries.php';
require_once DIR_INCLUDES . 'sql/dashboard_achat_queries.php';
require_once DIR_INCLUDES . 'sql/dashboard_kpi.php';

// Récupération sécurisée des données de session
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$aujourdhui = date('Y-m-d');

// ---------------------------
// Récupération des filtres
// ---------------------------
$filtreMarchand = !empty($_GET['marchand']) ? $_GET['marchand'] : '';
$filtreStatut = !empty($_GET['statut_filter']) ? $_GET['statut_filter'] : '';
$filtreCategorie = !empty($_GET['categorie']) ? (int) $_GET['categorie'] : '';
$dateDebut = !empty($_GET['date_debut']) ? $_GET['date_debut'] : '';
$dateFin = !empty($_GET['date_fin']) ? $_GET['date_fin'] : '';
$filtreAnnee = !empty($_GET['annee']) ? $_GET['annee'] : '';

// -------------------------------------------------------------------------
// APPEL DES FONCTIONS DU FICHIER DE REQUÊTES FACTORISÉ
// -------------------------------------------------------------------------

// 1. Récupération de la liste des achats filtrée
$achats = getDashboardAchatsFiltres(
    $pdo,
    $user_id,
    $filtreMarchand,
    $filtreCategorie,
    $dateDebut,
    $dateFin,
    $filtreAnnee
);

// 2. Filtrage selon le statut (Logique PHP)
if ($filtreStatut) {
    $achats = array_filter($achats, function ($achat) use ($filtreStatut, $aujourdhui) {
        $resteAPayer = $achat['total_echeances'] - $achat['total_paye'];
        if ($filtreStatut == 'solde' && $resteAPayer <= 0)
            return true;
        if ($filtreStatut == 'en_retard' && $achat['prochaine_echeance'] && $achat['prochaine_echeance'] < $aujourdhui)
            return true;
        if ($filtreStatut == 'a_venir' && $resteAPayer > 0 && (!$achat['prochaine_echeance'] || $achat['prochaine_echeance'] >= $aujourdhui))
            return true;
        return false;
    });
    // Réindexation du tableau après array_filter pour éviter les trous dans les clés
    $achats = array_values($achats);
}

// -------------------------------------------------------------------------
// APPEL DES KPIS CENTRALISÉS (Maintenant que $achats est rempli)
// -------------------------------------------------------------------------
$mois = $mois ?? date('Y-m');
$dateJour = $dateJour ?? date('Y-m-d');
$versements = $versements ?? [];

$dataKpi = computeDashboardData($pdo, (int)$user_id, (string)$mois, (string)$dateJour, $achats, $versements);
extract($dataKpi);

// 3. Totaux globaux
$totauxGlobaux = getDashboardAchatsTotaux(
    $pdo,
    $user_id,
    $filtreCategorie,
    $dateDebut,
    $dateFin,
    $filtreAnnee
);

$nbReste = $totauxGlobaux['nb_reste'] ?? 0;
$totalEcheances = $totauxGlobaux['total_echeances'] ?? 0;
$totalPaye = $totauxGlobaux['total_paye'] ?? 0;
$resteTotal = $totalEcheances - $totalPaye;
$pourcentageGlobal = ($totalEcheances > 0) ? ($totalPaye / $totalEcheances) * 100 : 0;

// 4. Échéances par mois
$echeancesParMois = getDashboardAchatsParMois(
    $pdo,
    $user_id,
    $filtreMarchand,
    $filtreCategorie,
    $dateDebut,
    $dateFin,
    $filtreAnnee
);

// --- CONFIGURATION PAGINATION CARDS ---
$parPage = 6;  // Nombre de cards par page
$pageCourante = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalAchatsFiltres = count($achats);
$totalPages = ceil($totalAchatsFiltres / $parPage);

// --- CONFIGURATION PAGINATION TABLEAU MOIS ---
$parPageMois = 5;  // Nombre de mois affichés par page dans le tableau
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($echeancesParMois);
$totalPagesMois = ceil($totalMois / $parPageMois);
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

    <div class="card bg-glass p-4 mb-4">
        <?php
        /** ==========================================================================
         *  Section Menu Appel page
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/menu_dashboard_achats.php'; ?>
    </div>
    <?php
    /** ==========================================================================
     *  Fin de Section menu achats
     *  ========================================================================== */

    /** ==========================================================================
     *  Section Progressions des paiements
     *  ========================================================================== */
    ?>
    <div class="row">
        <?php
        /** ==========================================================================
         *  Section : Avancement global du remboursement des paiements
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_avancement_global_paiement_large.php'; ?>
    </div>

    <hr class="hr-glass mt-4 mb-0" style="opacity:0.6;">

    <?php
    /** ==========================================================================
     *  Section Compteurs et KPI (Ligne 1 : 4 col)
     *  ========================================================================== */
    ?>
    <div class="row mt-4 mb-3 text-center">

        <?php
        /** ==========================================================================
         *  KPI 1 : ACHATS CONCERNÉS
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achats_concerner_compteur.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 2 : MONTANT RESTANT
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_montant_restant.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 3 : ÉCHÉANCES TOTALES
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_total_echeances_compteur.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 4 : ÉCHÉANCES RESTANTES
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_echeance_restante_compteur.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Section Compteurs et KPI (Ligne 2 : 3 col)
     *  ========================================================================== */
    ?>
    <div class="row mb-4 text-center">

        <?php
        /** ==========================================================================
         *  KPI 5 : TOTAL DÉJÀ PAYÉ
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_total_deja_payer.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 6 : MONTANT MOYEN PAR ACHAT
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_montant_moyen_par_achat.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 7 : PROCHAINE ÉCHÉANCE IMMINENTE
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/kpi_achat_prochaine_echeance.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Fin Section Compteurs Badges Achats
     *  ========================================================================== */

    /** ==========================================================================
     *  Section Filtres
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/filtres_dashboard_achat.php'; ?>

    <?php
    /** ==========================================================================
     *  Fin de Section Menu et filtres
     *  ========================================================================== */

    /** ==========================================================================
     *  Section Badges Achats
     *  ========================================================================== */

    require_once DIR_INCLUDES . 'components/badges_dashboard_achats.php'; ?>

    <?php
    /** ==========================================================================
     *  Fin de Section Badges Achats
     *  ========================================================================== */

    /** ==========================================================================
     *  Pagination des Badges Achats
     *  ========================================================================== */
    
    require_once DIR_INCLUDES . 'components/pagination_dashboard_achat.php'; ?>