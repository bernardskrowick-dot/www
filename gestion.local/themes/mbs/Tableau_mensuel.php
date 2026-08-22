<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * tableau_mensuel.php - Tableau de bord avec jauge de progression circulaire
 * VERSION : Intégration Dynamique des Classes CSS de Statut (Bordures & Badges) + Filtre Période Glissante
 * THEME : MBS_DARK
 * RÈGLES : CSRF, Constantes, Commentaires, Hachage (Session)
 */

// Utilisation des constantes de dossier (Règle stricto sensu)
require_once __DIR__ . '/header.php';
require_once DIR_INCLUDES . 'sql/tableau_mensuel_queries.php';

// Récupération sécurisée des données de session
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$aujourdhui = date('Y-m-d');

// ---------------------------
// 1. Récupération des filtres
// ---------------------------
$filtreMarchand = !empty($_GET['marchand']) ? $_GET['marchand'] : '';
$filtreStatut = !empty($_GET['statut_filter']) ? $_GET['statut_filter'] : '';
$filtreCategorie = !empty($_GET['categorie']) ? (int) $_GET['categorie'] : '';
$afficherTout = isset($_GET['afficher_tout']) && $_GET['afficher_tout'] == '1';

// -----------------------------------------------------------
// 2. Récupération dynamique du jour de début de période (session)
// -----------------------------------------------------------
$jourDebutPeriode = isset($_SESSION['jour_debut_periode']) ? (int) $_SESSION['jour_debut_periode'] : 1;

// -----------------------------------------------------------
// Calcul dynamique du mois bancaire courant selon le jour choisi
// -----------------------------------------------------------
if ((int) date('d') > $jourDebutPeriode) {
    $moisBancaireCourant = date('Y-m', strtotime('+1 month'));
} else {
    $moisBancaireCourant = date('Y-m');
}

// Mois - 1 (n-1) par rapport au mois bancaire courant
$moisMinGlissant = date('Y-m', strtotime($moisBancaireCourant . '-01 -1 month'));

// ---------------------------
// 3. Achats avec totaux (Sécurité : PDO + Prepared Statements)
// ---------------------------
$achats = getDashboardAchatsFiltres($pdo, $user_id, $filtreMarchand, $filtreCategorie);

// ---------------------------
// 4. Filtrage selon le statut (Logique PHP)
// ---------------------------
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
}

// ---------------------------
// 5. Totaux globaux
// ---------------------------
$totauxGlobaux = getDashboardTotauxGlobaux($pdo, $user_id);

$nbReste = $totauxGlobaux['nb_reste'] ?? 0;
$totalEcheances = $totauxGlobaux['total_echeances'] ?? 0;
$totalPaye = $totauxGlobaux['total_paye'] ?? 0;
$resteTotal = $totalEcheances - $totalPaye;

$pourcentageGlobal = ($totalEcheances > 0) ? ($totalPaye / $totalEcheances) * 100 : 0;

/* ******************************************** */
/* Premier relevé bancaire */
/* ******************************************** */

// =====================================================
// RÉCUPÉRATION EN BDD & MAPPING DES ANCIENNES VARIABLES
// =====================================================

$userData = getDashboardUserData($pdo, $_SESSION['user_id']);

// Mapping : Correspondance Ancien Nom = Nouvelle Valeur BDD
$jourDebutPeriode = (int) ($userData['jour_debut_periode'] ?? 1);
$premierePeriode = (string) ($userData['premiere_periode'] ?? '2026-06');
$soldeInitial = (float) ($userData['solde_initial'] ?? 0.0);

// ---------------------------
// 6. Échéances par mois (avec filtre glissant optionnel) → pour l'AFFICHAGE
// ---------------------------
$echeancesParMois = getDashboardEcheancesParMois(
    $pdo,
    $user_id,
    $jourDebutPeriode,
    $filtreMarchand,
    $afficherTout,
    $moisMinGlissant
);

// --- CONFIGURATION PAGINATION TABLEAU MOIS ---
$parPageMois = 7;
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($echeancesParMois);
$totalPagesMois = ceil($totalMois / $parPageMois);

// ---------------------------
// 7. REVENUS PAR MOIS
// ---------------------------
$rows = getDashboardRevenusParMois($pdo, $user_id, $jourDebutPeriode);

$revenusParMois = [];
$totalRevenusPrevu = 0;
$totalRevenusRecu = 0;
$nbVersementsAttendus = 0;

foreach ($rows as $r) {
    $mois = $r['mois'];

    $revenusParMois[$mois] = [
        'percu' => (float) $r['revenu_percu'],
        'attendu' => (float) $r['revenu_attendu'],
        'total' => (float) $r['revenu_total_prevu'],
    ];

    $totalRevenusPrevu += (float) $r['revenu_total_prevu'];
    $totalRevenusRecu += (float) $r['revenu_percu'];
    $nbVersementsAttendus += (float) $r['revenu_attendu'];
}

/* ******************************************** */
/* Calcul des soldes bancaires par période     */
/* (toujours sur l'historique COMPLET)        */
/* ******************************************** */

$echeancesCompletes = getDashboardEcheancesCompletes($pdo, $user_id, $jourDebutPeriode, $filtreMarchand);

// Calcul des soldes sur l'historique complet
$soldeCourant = $soldeInitial;
$soldesCalcules = [];

foreach ($echeancesCompletes as $row) {
    $keyMois = trim($row['mois']);

    $revenuMois = (
        ($revenusParMois[$keyMois]['percu'] ?? 0)
        + ($revenusParMois[$keyMois]['attendu'] ?? 0)
    );

    if ($row['mois'] == $premierePeriode) {
        $soldeCourant = $soldeInitial;
    } elseif ($row['mois'] > $premierePeriode) {
        $soldeCourant += $revenuMois - $row['total_mois'];
    } else {
        $soldeCourant = $revenuMois - $row['total_mois'];
    }

    $soldesCalcules[$keyMois] = $soldeCourant;
}

$totalPaye = $totalPaye ?? 0;
$totalEcheances = $totalEcheances ?? 0;

// SOLDE NET & THEORIQUE
$soldeNet = $totalRevenusRecu - $totalPaye;
$soldeTheorique = $totalRevenusPrevu - $totalEcheances;

// ETAT SANTE
$etatSante = 'stable';
if ($soldeNet < 0) {
    $etatSante = 'danger';
} elseif ($totalRevenusRecu > 0 && $soldeNet < ($totalRevenusRecu * 0.2)) {
    $etatSante = 'warning';
}

$pourcentageRevenus = ($totalRevenusPrevu > 0)
    ? ($totalRevenusRecu / $totalRevenusPrevu) * 100
    : 0;

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

    <hr class="hr-glass" style="opacity:0.6;">

    <?php
    /** ==========================================================================
     *  MENU ET ACTION
     *  ========================================================================== */
    require_once DIR_INCLUDES . 'components/tableau_mensuel_menu_actions.php';
    ?>

    <?php
    /** ==========================================================================
     *  Affichage du Tableau
     *  ========================================================================== */
    ?>

    <div id="table-mois" class="table-responsive">

        <?php require_once DIR_INCLUDES . 'components/tableau_mensuel_affichage_resultat.php'; ?>
        <?php

        /** ==========================================================================
         *  Pagination
         *  ========================================================================== */
        require_once DIR_INCLUDES . 'components/pagination_tableau_mensuel.php'; ?>
        
    </div>
</div>