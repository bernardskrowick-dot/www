<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * dashboard.php - Logique PHP & Intégration des fichiers factorisés
 * THEME : MBS_DARK
 * SÉCURITÉ : Prepared Statements, typage strict, COALESCE et protection XSS
 */

// Utilisation des constantes de dossier et chargement des fonctions factorisées
require_once __DIR__ . '/header.php';
// Chargement des fichiers SQL et KPI factorisés depuis le dossier partagé du projet
require_once DIR_INCLUDES . 'sql/dashboard_queries.php';
require_once DIR_INCLUDES . 'sql/dashboard_achat_queries.php';
require_once DIR_INCLUDES . 'sql/dashboard_kpi.php';

// Récupération sécurisée des données de session
$user_role = $_SESSION['role'] ?? '';
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$dateJour = date('Y-m-d');
$aujourdhui = date('Y-m-d');

// Récupération sécurisée du jour de début de période de l'utilisateur connecté
$jourDebutPeriode = isset($_SESSION['jour_debut_periode']) ? (int) $_SESSION['jour_debut_periode'] : 1;

// Détermination du mois bancaire courant en fonction du jour de début de l'utilisateur
if (!isset($_GET['mois'])) {
    if ((int) date('d') > $jourDebutPeriode) {
        $mois = date('Y-m', strtotime('+1 month'));
    } else {
        $mois = date('Y-m');
    }
} else {
    $mois = $_GET['mois'];
    if (!preg_match('/^\d{4}-\d{2}$/', $mois)) {
        $mois = date('Y-m');
    }
}

/* ==========================================================================
   SECTION 1 : RÉCUPÉRATION DES DONNÉES DE LA PÉRIODE COURANTE (SQL)
   ========================================================================== */

$achats = getDashboardAchatsPeriode($pdo, $user_id, $mois, $jourDebutPeriode);
$versements = getDashboardVersementsPeriode($pdo, $user_id, $mois, $jourDebutPeriode);


/* ==========================================================================
   SECTION 2 : SYNTHÈSES GLOBALES & STATISTIQUES (SQL)
   ========================================================================== */

$totauxAchats = getDashboardTotauxAchats($pdo, $user_id);
$totalEcheances = (float) $totauxAchats['total_echeances'];
$totalPaye = (float) $totauxAchats['total_paye'];
$nbReste = (int) $totauxAchats['nb_reste'];
$resteTotal = $totalEcheances - $totalPaye;
$pourcentageGlobal = ($totalEcheances > 0) ? ($totalPaye / $totalEcheances) * 100 : 0;

$revenusSynthese = getDashboardRevenusSynthese($pdo, $user_id);
$totalRevenusPrevu = (float) $revenusSynthese['total_prevu'];
$totalRevenusRecu = (float) $revenusSynthese['total_recu'];
$totalRestantAPercevoir = (float) $revenusSynthese['total_restant_a_percevoir'];
$nbVersementsAttendus = (int) $revenusSynthese['nb_attendus'];

$tauxRecouvrement = ($totalRevenusPrevu > 0)
    ? round(($totalRevenusRecu / $totalRevenusPrevu) * 100, 2)
    : 0;

$pourcentageRevenus = ($totalRevenusPrevu > 0)
    ? ($totalRevenusRecu / $totalRevenusPrevu) * 100
    : 0;


/* ==========================================================================
   SECTION 3 : ACTIVITÉS, ALERTES & ÉVÉNEMENTS CLÉS (SQL)
   ========================================================================== */

$prochainesEcheances = getDashboardActiviteRecente($pdo, $user_id);
$totalRevenusReels = getDashboardSoldeRevenusReels($pdo, $user_id, $dateJour);
$alertesFinancieres = getDashboardAlertesFinancieres($pdo, $user_id, $dateJour);
$prochainRevenu = getDashboardProchainRevenu($pdo, $user_id, $dateJour);

// Analyse de la plus grosse dépense de la période
$plusGrosseDepense = getDashboardPlusGrosseDepense($pdo, $user_id, $mois, $jourDebutPeriode);

if ($plusGrosseDepense) {
    $nomPlusGrosseDepense = $plusGrosseDepense['nom_marchand'];
    $titrePlusGrosseDepense = $plusGrosseDepense['titre'];
    $montantPlusGrosseDepense = (float) $plusGrosseDepense['montant'];
    $datePlusGrosseDepense = $plusGrosseDepense['date_echeance'];
} else {
    $nomPlusGrosseDepense = 'Aucune dépense';
    $titrePlusGrosseDepense = '';
    $montantPlusGrosseDepense = 0;
    $datePlusGrosseDepense = null;
}

$libellePlusGrosseDepense = !empty($titrePlusGrosseDepense) ? $titrePlusGrosseDepense : $nomPlusGrosseDepense;

if ($montantPlusGrosseDepense >= 1000) {
    $niveauDepense = '🔴 Exceptionnelle';
    $classeDepense = 'text-danger';
} elseif ($montantPlusGrosseDepense >= 500) {
    $niveauDepense = '🟠 Très importante';
    $classeDepense = 'text-warning';
} elseif ($montantPlusGrosseDepense >= 300) {
    $niveauDepense = '🟡 Importante';
    $classeDepense = 'text-warning';
} elseif ($montantPlusGrosseDepense >= 100) {
    $niveauDepense = '🔵 Modérée';
    $classeDepense = 'text-info';
} else {
    $niveauDepense = '🟢 Faible';
    $classeDepense = 'text-success';
}


/* ==========================================================================
   SECTION 4, 5 & 6 : CALCUL DU SOLDE HISTORIQUE, TRAITEMENTS & KPIs
   ========================================================================== */

// Appel de la fonction centralisée qui exécute la boucle historique et tous les KPI
$kpiData = computeDashboardData($pdo, $user_id, $mois, $dateJour, $achats, $versements);
extract($kpiData); // Restaure toutes les variables de calcul pour l'affichage HTML


/* ==========================================================================
   SECTION 7 : PROJECTIONS ANNUELLES (AU 31 DÉCEMBRE) (SQL)
   ========================================================================== */

$anneeEnCours = (int) date('Y', strtotime($dateJour));
$dateFinAnnee = $anneeEnCours . '-12-31';

$resteEncaisserAnnee = getDashboardResteEncaisserAnnee($pdo, $user_id, $dateFinAnnee);
$resteDepenserAnnee = getDashboardResteDepenserAnnee($pdo, $user_id, $dateFinAnnee);

$variationNetteAnnee = $resteEncaisserAnnee - $resteDepenserAnnee;
$atterrissageFinAnnee = $soldeReel + $variationNetteAnnee;
?>

<?php
/** ==========================================================================
 *  Fin des requêtes SQL
 *  ========================================================================== */

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
    ?>
    <?php require_once DIR_INCLUDES . 'components/admin_header_nav.php'; ?>

    <?php
    /** ==========================================================================
     *  Début d'affichage de la page
     *  ========================================================================== */

    /** ==========================================================================
     *  SECTION 1 : REVENUS & PROJECTIONS
     *  ========================================================================== */
    ?>
    <div class="row mb-3 mt-3 g-3">
        <?php
        /** ==========================================================================
         *  13. TAUX D'ÉPARGNE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi-taux_epargne.php'; ?>

        <?php
        /** ==========================================================================
         *  14. BUDGET JOURNALIER DISPONIBLE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_budget_journalier_disponible.php'; ?>

        <?php
        /** ==========================================================================
         *  15. ALERTE DÉCOUVERT DATE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_alerte_decouvert_date.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  LIGNE KPI : PROJECTION ANNUELLE
     *  ========================================================================== */
    ?>
    <div class="row mb-0 mt-0 g-3 text-center">
        <?php
        /** ==========================================================================
         *  KPI 1 : Reste à encaisser (Année)
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_reste_encaisser.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 2 : Reste à dépenser (Année)
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_Reste_dépenser.php'; ?>

        <?php
        /** ==========================================================================
         *  KPI 3 : Atterrissage Estimé au 31 Décembre
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi-atterrissage_estimer.php'; ?>
    </div>

    <div class="row mb-5 mt-0 g-3 text-center">
        <?php
        /** ==========================================================================
         *  1. TOTAL REVENUS
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_total_revenu.php'; ?>

        <?php
        /** ==========================================================================
         *  2. REVENUS À PERCEVOIR
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_revenus_percevoir.php'; ?>

        <?php
        /** ==========================================================================
         *  3. PROCHAIN REVENU
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_prochain_revenu.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Séparateur 1 HR GLASS
     *  ========================================================================== */
    ?>
    <div class="row">
        <div class="col-12">
            <hr class="hr-glass mt-5 mb-4" style="opacity: 0.6;">
        </div>
    </div>

    <?php
    /** ==========================================================================
     *  SECTION 2 : DÉPENSES (3 KPIs)
     *  ========================================================================== */
    ?>
    <div class="row mb-3 mt-0 g-3">
        <?php
        /** ==========================================================================
         *  4. TOTAL DÉPENSES
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_total_depenses.php'; ?>

        <?php
        /** ==========================================================================
         *  5. RESTE À PAYER
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_reste_payer.php'; ?>

        <?php
        /** ==========================================================================
         *  6. PLUS GROSSE DÉPENSE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_plus_grosse_depense.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Séparateur 2 HR GLASS
     *  ========================================================================== */
    ?>
    <div class="row">
        <div class="col-12">
            <hr class="hr-glass mt-5 mb-4" style="opacity: 0.6;">
        </div>
    </div>

    <?php
    /** ==========================================================================
     *  SECTION 3 : ANALYSE & SOLDE
     *  ========================================================================== */
    ?>
    <div class="row mb-3 mt-0 g-3">
        <?php
        /** ==========================================================================
         *  7. RESTE À VIVRE RÉEL
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_reste_vivre.php'; ?>

        <?php
        /** ==========================================================================
         *  8. TAUX DE COUVERTURE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_taux_couverture.php'; ?>

        <?php
        /** ==========================================================================
         *  9. POINT BAS DE TRÉSORERIE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_point_bas_tresorerie.php'; ?>
    </div>

    <div class="row mb-3 mt-0 g-3">
        <?php
        /** ==========================================================================
         *  10. SOLDE RÉEL DU JOUR
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_solde_reel_jour.php'; ?>

        <?php
        /** ==========================================================================
         *  11. JOURS RESTANTS AVANT RELEVÉ
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_jour_restant_avant_releve.php'; ?>

        <?php
        /** ==========================================================================
         *  12. TENDANCE DU SOLDE
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_tendance_solde.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Séparateur 3 HR GLASS
     *  ========================================================================== */
    ?>
    <div class="row">
        <div class="col-12">
            <hr class="hr-glass my-4" style="opacity: 0.6;">
        </div>
    </div>

    <?php
    /** ==========================================================================
     *  SECTION ACTIVITÉ ET ALERTES
     *  ========================================================================== */
    ?>
    <div class="row mb-3 mt-0 g-3">
        <!-- 1. ACTIVITÉ RÉCENTE -->
        <?php require_once DIR_INCLUDES . 'components/kpi_activite_recente.php'; ?>

        <!-- 2. ALERTES FINANCIÈRES -->
        <?php require_once DIR_INCLUDES . 'components/kpi_alerte_financiere.php'; ?>
    </div>

    <!-- Séparateur 4 -->
    <div class="row">
        <div class="col-12">
            <hr class="hr-glass my-4" style="opacity: 0.6;">
        </div>
    </div>

    <?php
    /** ==========================================================================
     *  SECTION ACCÈS RAPIDES
     *  ========================================================================== */
    ?>
    <div class="row mb-3 mt-0 g-4">
        <?php
        /** ==========================================================================
         *  1. CARTE MENU TABLEAU DE BORD DES REVENUS
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/carte_menu_dashboard_revenu.php'; ?>

        <?php
        /** ==========================================================================
         *  2. CARTE MENU TABLEAU MENSUEL
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/carte_menu_dashboard_mensuel.php'; ?>

        <?php
        /** ==========================================================================
         *  3. CARTE MENU TABLEAU DE BORD DES ACHATS
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/carte_menu_dashboard_achat.php'; ?>
    </div>

    <?php
    /** ==========================================================================
     *  Séparateur 5 HR GLASS
     *  ========================================================================== */
    ?>
    <div class="row">
        <div class="col-12">
            <hr class="hr-glass my-4" style="opacity: 0.6;">
        </div>
    </div>

    <?php
    /** ==========================================================================
     *  SECTION PROGRESSIONS (REVENUS & PAIEMENTS)
     *  ========================================================================== */
    ?>
    <div class="row mb-0 mt-0 g-3">
        <?php
        /** ==========================================================================
         *  1. AVANCEMENT GLOBAL DES REVENUS
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_avancement global_revenu.php'; ?>


        <?php
        /** ==========================================================================
         *  2. AVANCEMENT GLOBAL DES PAIEMENTS
         *  ========================================================================== */
        ?>
        <?php require_once DIR_INCLUDES . 'components/kpi_avancement_global_paiement.php'; ?>
    </div>