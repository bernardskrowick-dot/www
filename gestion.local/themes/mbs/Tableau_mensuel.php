<?php
/* Version: v1.19.1 (Rev #25) - 2026-08-06 */

/**
 * dashboard.php - Tableau de bord avec jauge de progression circulaire
 * VERSION : Intégration Dynamique des Classes CSS de Statut (Bordures & Badges) + Filtre Période Glissante
 * THEME : MBS_DARK
 * RÈGLES : CSRF, Constantes, Commentaires, Hachage (Session)
 */

// Utilisation des constantes de dossier (Règle stricto sensu)
require_once __DIR__ . '/header.php';

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
$afficherTout = isset($_GET['afficher_tout']) && $_GET['afficher_tout'] == '1';

// -----------------------------------------------------------
// Récupération dynamique du jour de début de période (session)
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
// Achats avec totaux (Sécurité : PDO + Prepared Statements)
// ---------------------------
$sql = "
    SELECT 
        a.*,
        c.nom AS nom_categorie,
        c.couleur AS couleur_categorie,
        SUM(e.montant) AS total_echeances,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
        COUNT(e.id) AS nb_total_echeances,
        SUM(CASE WHEN e.statut != 'payee' THEN 1 ELSE 0 END) AS nb_restantes,
        MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance
    FROM achats a
    LEFT JOIN echeances e ON a.id = e.achat_id
    LEFT JOIN categories c ON (a.categorie_id = c.id AND (c.user_id = a.user_id OR c.id = 1))
    WHERE a.user_id = :user_id
" . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
' . ($filtreCategorie ? ' AND a.categorie_id = :categorie' : '') . '
    GROUP BY a.id
    ORDER BY prochaine_echeance ASC
';

$params = ['user_id' => $user_id];
if ($filtreMarchand)
    $params['marchand'] = $filtreMarchand;
if ($filtreCategorie)
    $params['categorie'] = $filtreCategorie;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$achats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------
// Filtrage selon le statut (Logique PHP)
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
// Totaux globaux
// ---------------------------
$stmtTotaux = $pdo->prepare("
    SELECT 
        SUM(e.montant) AS total_echeances,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye,
        COUNT(CASE WHEN e.statut != 'payee' THEN 1 END) AS nb_reste
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
");
$stmtTotaux->execute(['user_id' => $user_id]);
$totauxGlobaux = $stmtTotaux->fetch(PDO::FETCH_ASSOC);

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

// 1. Requête SQL PDO pour extraire la configuration de l'utilisateur
$stmtUser = $pdo->prepare('
    SELECT solde_initial, premiere_periode, jour_debut_periode 
    FROM users 
    WHERE id = :id 
    LIMIT 1
');

$stmtUser->execute([
    'id' => $_SESSION['user_id']
]);

$userData = $stmtUser->fetch(PDO::FETCH_ASSOC);

// 2. Mapping : Correspondance Ancien Nom = Nouvelle Valeur BDD

// Ancien : $jourDebutPeriode = 10;
$jourDebutPeriode = (int) ($userData['jour_debut_periode'] ?? 1);

// Ancien : $premierePeriode = '2026-06';
$premierePeriode = (string) ($userData['premiere_periode'] ?? '2026-06');

// Ancien : $soldeInitial = 776.49;
$soldeInitial = (float) ($userData['solde_initial'] ?? 0.0);

// ---------------------------
// Échéances par mois (avec filtre glissant optionnel) → pour l'AFFICHAGE
// ---------------------------
$queryMois = "
    SELECT 
        DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                ELSE e.date_echeance
            END,
            '%Y-%m'
        ) AS mois,
        SUM(e.montant) AS total_mois,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
" . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    GROUP BY mois
    HAVING 1=1
' . (!$afficherTout ? ' AND mois >= :mois_min' : '') . '
    ORDER BY mois ASC
';

$paramsMois = [
    'user_id' => $user_id,
    'jour_debut' => $jourDebutPeriode
];

if (!$afficherTout) {
    $paramsMois['mois_min'] = $moisMinGlissant;
}

if ($filtreMarchand) {
    $paramsMois['marchand'] = $filtreMarchand;
}

$stmtMois = $pdo->prepare($queryMois);
$stmtMois->execute($paramsMois);
$echeancesParMois = $stmtMois->fetchAll(PDO::FETCH_ASSOC);

// --- CONFIGURATION PAGINATION TABLEAU MOIS ---
$parPageMois = 7;
$pageCouranteMois = isset($_GET['page_m']) ? max(1, (int) $_GET['page_m']) : 1;
$totalMois = count($echeancesParMois);
$totalPagesMois = ceil($totalMois / $parPageMois);

// ---------------------------
// REVENUS PAR MOIS
// ---------------------------
$stmtRevenusMois = $pdo->prepare("
    SELECT 
        DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(v.date_versement_prevue) >= :jour_debut
                THEN DATE_ADD(v.date_versement_prevue, INTERVAL 1 MONTH)
                ELSE v.date_versement_prevue
            END,
            '%Y-%m'
        ) AS mois,

        SUM(
            CASE 
                WHEN v.statut = 'percu'
                THEN COALESCE(v.montant_reel, v.montant_prevu)
                ELSE 0
            END
        ) AS revenu_percu,

        SUM(
            CASE 
                WHEN v.statut = 'attendu' 
                     OR v.statut IS NULL 
                     OR v.statut = ''
                THEN v.montant_prevu
                ELSE 0
            END
        ) AS revenu_attendu,

        SUM(v.montant_prevu) AS revenu_total_prevu

    FROM versements v
    INNER JOIN ressources r ON r.id = v.ressource_id
    WHERE r.user_id = :user_id
    GROUP BY mois
    ORDER BY mois ASC
");

$stmtRevenusMois->execute([
    'user_id' => $user_id,
    'jour_debut' => $jourDebutPeriode
]);
$rows = $stmtRevenusMois->fetchAll(PDO::FETCH_ASSOC);

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
/* (toujours sur l'historique COMPLET)         */
/* ******************************************** */

// Requête sans filtre de mois pour avoir l'historique complet
$queryMoisComplet = "
    SELECT 
        DATE_FORMAT(
            CASE
                WHEN :jour_debut > 1 AND DAY(e.date_echeance) >= :jour_debut
                THEN DATE_ADD(e.date_echeance, INTERVAL 1 MONTH)
                ELSE e.date_echeance
            END,
            '%Y-%m'
        ) AS mois,
        SUM(e.montant) AS total_mois,
        SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS paye_mois
    FROM echeances e
    INNER JOIN achats a ON e.achat_id = a.id
    WHERE a.user_id = :user_id
" . ($filtreMarchand ? ' AND a.nom_marchand = :marchand' : '') . '
    GROUP BY mois
    ORDER BY mois ASC
';

$paramsComplet = [
    'user_id' => $user_id,
    'jour_debut' => $jourDebutPeriode
];
if ($filtreMarchand) {
    $paramsComplet['marchand'] = $filtreMarchand;
}

$stmtComplet = $pdo->prepare($queryMoisComplet);
$stmtComplet->execute($paramsComplet);
$echeancesCompletes = $stmtComplet->fetchAll(PDO::FETCH_ASSOC);

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
<!------  Début d'affichage de la page  ---->
<div id="flash-container" class="container mt-3" style="position: relative; z-index: 9999;">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success bg-success text-white border-0 shadow-lg p-3 rounded alert-dismissible fade show position-relative"
            role="alert">
            <div class="d-flex align-items-center">
                <span class="fs-4 me-2">✅</span>
                <div class="pe-5">
                    <strong>Succès !</strong><br>
                    <?= $_SESSION['flash_success']; ?>
                </div>
            </div> <button type="button" data-bs-dismiss="alert" class="btn-flash-close">
                ❌ FERMER
            </button>
        </div> <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger bg-danger text-white border-0 shadow-lg p-3 rounded" role="alert">
            <div class="d-flex align-items-center">
                <span class="fs-4 me-2">⚠️</span>
                <div>
                    <strong>Erreur !</strong><br>
                    <?= $_SESSION['flash_error']; ?>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>
</div>

<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-0">

        <div class="d-flex flex-wrap align-items-center mt-3">

        </div>
        <div class="p-3 pt-0">
            <?php
            $titreSurcharge = 'Tableau mensuel';


            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>
    </div>

    <hr class="hr-glass" style="opacity:0.6;">

    <div class="mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="<?= BASE_URL ?>index.php<?= $filtreMarchand ? '?marchand=' . urlencode($filtreMarchand) : '' ?>"
            class="btn btn-secondary shadow-sm" title="Retour au tableau de bord"
            aria-label="Retour au tableau de bord">
            <span class="emoji">🏠</span> Retour au Tableau de bord
        </a>

        <!-- BOUTON BASCULE : VUE GLISSANTE / HISTORIQUE COMPLET -->
        <div>
            <?php if ($afficherTout): ?>
                <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php<?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
                    class="btn-modifier-neon shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
                    style="height: 38px;">
                    👁️ Masquer l'historique ancien (Vue courant/futur)
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&afficher_tout=1<?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
                    class="btn-create-dash shadow-sm px-3 text-nowrap text-decoration-none d-inline-flex align-items-center justify-content-center"
                    style="height: 38px;">
                    📜 Afficher tout l'historique
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card glass-card-nav mb-4" style="border: none;">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="glass-header-small mb-0">
                    <span class="emoji">📅</span> Échéances par mois
                </h2>
                <span class="small fw-bold text-uppercase" style="letter-spacing: 1px;">
                    <?= count($echeancesParMois) ?> Mois affichés
                </span>
            </div>
        </div>
    </div>
    <!-- Affichage Tableau -->
    <div id="table-mois" class="table-responsive">
        <?php if (count($echeancesParMois) > 0): ?>
            <table class="table table-custom-dark">
                <thead>
                    <tr>
                        <th class="fw-bold text-center" style="width: 150px; padding: 10px 5px !important;">Mois</th>
                        <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Total Prévu</th>
                        <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Déjà Payé</th>
                        <th class="fw-bold text-center" style="width: 110px; padding: 10px 5px !important;">Reste à payer
                        </th>
                        <th class="fw-bold text-center">Revenu du mois</th>
                        <th class="fw-bold text-center">Solde</th>
                        <th class="fw-bold text-center" style="width: 150px; padding: 10px 5px !important;">Statut actuel
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $compteurMois = 0;

                    foreach ($echeancesParMois as $row):
                        $compteurMois++;

                        // Pagination
                        $debutMois = ($pageCouranteMois - 1) * $parPageMois;
                        $finMois = $pageCouranteMois * $parPageMois;
                        if ($compteurMois <= $debutMois || $compteurMois > $finMois)
                            continue;

                        $restant = $row['total_mois'] - $row['paye_mois'];
                        $finDuMois = date('Y-m-t', strtotime($row['mois'] . '-01'));
                        $keyMois = trim($row['mois']);

                        $revenuMois = (
                            ($revenusParMois[$keyMois]['percu'] ?? 0)
                            + ($revenusParMois[$keyMois]['attendu'] ?? 0)
                        );

                        // ← Solde pré-calculé sur l'historique complet
                        $soldeMois = $soldesCalcules[$keyMois] ?? $soldeInitial;

                        if ($restant <= 0) {
                            $statut = '✅ Soldé';
                            $badgeClass = 'badge-solde';
                        } elseif ($finDuMois < $aujourdhui && $restant > 0) {
                            $statut = '⚠️ En retard';
                            $badgeClass = 'badge-en-retard';
                        } else {
                            $statut = '📅 À venir';
                            $badgeClass = 'badge-a-venir';
                        }
                        $periode = periodeBancaire($row['mois'], $jourDebutPeriode);
                    ?>
                        <tr>
                            <td data-label="Mois" class="text-center fw-bold">
                                <a href="<?= BASE_URL ?>router.php?p=echeances_mois.php&mois=<?= $row['mois'] ?><?= $filtreMarchand ? '&marchand=' . urlencode($filtreMarchand) : '' ?>"
                                    class="text-info text-decoration-none">
                                    <?= $periode ?>
                                </a>
                            </td>
                            <td data-label="Total Prévu" class="text-center text-white">
                                <?= number_format($row['total_mois'], 2) ?> €
                            </td>
                            <td data-label="Déjà Payé" class="text-center text-success opacity-75">
                                <?= number_format($row['paye_mois'], 2) ?> €
                            </td>
                            <td data-label="Reste" class="text-center <?= $restant > 0 ? 'text-warning' : 'text-white-50' ?>">
                                <?= number_format($restant, 2) ?> €
                            </td>
                            <td data-label="Revenu du mois" class="text-center text-info">
                                <?= number_format($revenuMois, 2) ?> €
                            </td>
                            <td data-label="Solde" class="text-center <?= $soldeMois >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($soldeMois, 2) ?> €
                            </td>
                            <td data-label="Statut" class="text-center">
                                <span class="badge-status <?= $badgeClass ?>">
                                    <?= htmlspecialchars($statut) ?>
                                    <?php if ($badgeClass === 'badge-en-retard'): ?>
                                        <span class="pulse-dot"></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>


            <?php if ($totalPagesMois > 1): ?>
                <div class="d-flex justify-content-center pb-3">
                    <ul class="pagination pagination-minimal">
                        <li class="page-item <?= ($pageCouranteMois <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link"
                                href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $pageCouranteMois - 1 ?>">
                                &lsaquo;
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPagesMois; $i++): ?>
                            <li class="page-item <?= ($i == $pageCouranteMois ? 'active' : '') ?>">
                                <a class="page-link"
                                    href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $i ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?= ($pageCouranteMois >= $totalPagesMois) ? 'disabled' : '' ?>">
                            <a class="page-link"
                                href="<?= BASE_URL ?>router.php?p=Tableau_mensuel.php&marchand=<?= urlencode($filtreMarchand) ?>&statut_filter=<?= $filtreStatut ?>&afficher_tout=<?= $afficherTout ? 1 : 0 ?>&page_m=<?= $pageCouranteMois + 1 ?>">
                                &rsaquo;
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center p-5 opacity-50">
                <div class="emoji mb-3" style="font-size: 3rem;">🌙</div>
                <p class="dashboard-subtitle" style="border:0; margin-bottom: 0;">
                    Aucune échéance à afficher pour le moment
                </p>
                <small class="text-white-50">Vos futures dépenses apparaîtront ici.</small>
            </div>
        <?php endif; ?>

    </div>
</div>