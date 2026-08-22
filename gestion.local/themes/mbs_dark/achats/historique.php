<?php
/**
 * historique_v2.php
 * Version refactorisée MBS_DARK
 *
 * Objectifs :
 * - Même fonctionnement que historique.php
 * - Même rendu visuel
 * - Code plus lisible
 * - Aucune modification des classes CSS
 * - Aucune modification de la logique métier
 */

// =====================================================
// 1. Sécurité (Vérification de l'authentification)
// =====================================================
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// =====================================================
// 2. Variables générales de contexte utilisateur
// =====================================================
$userId = $_SESSION['user_id'];
$aujourdhui = date('Y-m-d');

// =====================================================
// 3. Paramètres de pagination des achats
// =====================================================
$achatsParPage = 10;
$pageCourante = isset($_GET['page'])
    ? max(1, (int) $_GET['page'])
    : 1;
$offset = ($pageCourante - 1) * $achatsParPage;

// =====================================================
// 4. Récupération, nettoyage et sécurisation des filtres
// =====================================================
$search = isset($_GET['search'])
    ? mb_substr(trim($_GET['search']), 0, 100)
    : '';

$marchandFilter = isset($_GET['marchand_filter'])
    ? mb_substr(trim($_GET['marchand_filter']), 0, 100)
    : '';

$filtreCategorie = isset($_GET['categorie'])
    ? (int) $_GET['categorie']
    : 0;

$statutsFilter = isset($_GET['statuts_filter'])
    ? trim($_GET['statuts_filter'])
    : '';

$dateDebut = (
    !empty($_GET['date_debut']) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_debut'])
)
    ? $_GET['date_debut']
    : null;

$dateFin = (
    !empty($_GET['date_fin']) &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_fin'])
)
    ? $_GET['date_fin']
    : null;

// =====================================================
// 5. Construction dynamique du filtre SQL des statuts
// =====================================================
$statusWhere = '';
switch ($statutsFilter) {
    case 'solde':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) <= 0
        ";
        break;

    case 'en_retard':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) > 0
            AND
                MIN(
                    CASE
                        WHEN e.statut != 'payee'
                        THEN e.date_echeance
                    END
                ) < CURDATE()
        ";
        break;

    case 'a_venir':
        $statusWhere = "
            HAVING
                (SUM(e.montant)
                - SUM(CASE WHEN e.statut='payee'
                           THEN e.montant
                           ELSE 0 END)) > 0
            AND
                (
                    MIN(
                        CASE
                            WHEN e.statut != 'payee'
                            THEN e.date_echeance
                        END
                    ) >= CURDATE()
                    OR
                    MIN(
                        CASE
                            WHEN e.statut != 'payee'
                            THEN e.date_echeance
                        END
                    ) IS NULL
                )
        ";
        break;
}
// =====================================================
// 6. Fonction URL de pagination et calcul des bornes d'affichage
// =====================================================
function afficherPage(int $page)
{
    global $search, $marchandFilter, $filtreCategorie, $statutsFilter, $dateDebut, $dateFin;
    return '?p=historique.php' . '&page=' . $page . '&search=' . urlencode($search) . '&marchand_filter=' . urlencode($marchandFilter) . '&categorie=' . urlencode($filtreCategorie) . '&statuts_filter=' . urlencode($statutsFilter) . '&date_debut=' . urlencode($dateDebut ?? '') . '&date_fin=' . urlencode($dateFin ?? '');
}

// =====================================================
// 7. Calcul des totaux globaux et indicateurs financiers
// =====================================================
$stmtTotal = $pdo->prepare("SELECT SUM(CASE WHEN :date_filter IS NULL THEN e.montant WHEN e.date_echeance BETWEEN :date_filter AND :date_filter_fin THEN e.montant ELSE 0 END) AS total_echeances, SUM(CASE WHEN e.statut='payee' THEN CASE WHEN :date_filter IS NULL THEN e.montant WHEN e.date_echeance BETWEEN :date_filter AND :date_filter_fin THEN e.montant ELSE 0 END ELSE 0 END) AS total_paye FROM echeances e JOIN achats a ON e.achat_id = a.id WHERE a.user_id = :user_id AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id)) AND (:date_filter IS NULL OR (a.date_depart BETWEEN :date_filter AND :date_filter_fin OR EXISTS (SELECT 1 FROM echeances e2 WHERE e2.achat_id = a.id AND e2.date_echeance BETWEEN :date_filter AND :date_filter_fin)))");
$stmtTotal->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmtTotal->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmtTotal->bindValue(':marchand_filter', $marchandFilter, PDO::PARAM_STR);
$stmtTotal->bindValue(':categorie', $filtreCategorie, PDO::PARAM_INT);
$stmtTotal->bindValue(':date_filter', $dateDebut, $dateDebut === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtTotal->bindValue(':date_filter_fin', $dateFin, $dateFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtTotal->execute();
$resultTotal = $stmtTotal->fetch(PDO::FETCH_ASSOC);
$totalGen = (float) ($resultTotal['total_echeances'] ?? 0);
$totalPayeGen = (float) ($resultTotal['total_paye'] ?? 0);
$pourcentageGlobal = ($totalGen > 0) ? ($totalPayeGen / $totalGen) * 100 : 0;

// =====================================================
// 8. KPI complémentaires (Prochaine échéance et retards)
// =====================================================
$stmtProchaine = $pdo->prepare("SELECT e.date_echeance, e.montant, a.titre, a.nom_marchand FROM echeances e JOIN achats a ON e.achat_id = a.id WHERE a.user_id = :user_id AND e.statut = 'en_attente' AND e.date_echeance >= CURDATE() AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id)) ORDER BY e.date_echeance ASC LIMIT 1");
$stmtProchaine->execute([':user_id' => $userId, ':search' => '%' . $search . '%', ':marchand_filter' => $marchandFilter, ':categorie' => $filtreCategorie]);
$prochaineEcheance = $stmtProchaine->fetch(PDO::FETCH_ASSOC);

$stmtRetards = $pdo->prepare("SELECT COUNT(e.id) AS nb_retards, SUM(e.montant) AS montant_retard FROM echeances e JOIN achats a ON e.achat_id = a.id WHERE a.user_id = :user_id AND e.statut = 'en_attente' AND e.date_echeance < CURDATE() AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id))");
$stmtRetards->execute([':user_id' => $userId, ':search' => '%' . $search . '%', ':marchand_filter' => $marchandFilter, ':categorie' => $filtreCategorie]);
$infosRetards = $stmtRetards->fetch(PDO::FETCH_ASSOC);
$nbRetards = (int) ($infosRetards['nb_retards'] ?? 0);
$montantRetard = (float) ($infosRetards['montant_retard'] ?? 0);

// =====================================================
// KPI : Nombre d'achats actifs
// =====================================================
$stmtAchatsActifs = $pdo->prepare("SELECT COUNT(DISTINCT a.id) FROM achats a JOIN echeances e ON e.achat_id = a.id WHERE a.user_id = :user_id AND e.statut = 'en_attente' AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id))");
$stmtAchatsActifs->execute([':user_id' => $userId, ':search' => '%' . $search . '%', ':marchand_filter' => $marchandFilter, ':categorie' => $filtreCategorie]);
$nbAchatsActifs = (int) $stmtAchatsActifs->fetchColumn();

// =====================================================
// 8. Nombre total d'achats filtrés (pagination)
// =====================================================
$stmtCount = $pdo->prepare("SELECT COUNT(*) AS total FROM (SELECT a.id FROM achats a LEFT JOIN echeances e ON e.achat_id = a.id WHERE a.user_id = :user_id AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id)) AND (:date_filter IS NULL OR (a.date_depart BETWEEN :date_filter AND :date_filter_fin OR EXISTS (SELECT 1 FROM echeances e2 WHERE e2.achat_id = a.id AND e2.date_echeance BETWEEN :date_filter AND :date_filter_fin))) GROUP BY a.id " . $statusWhere . ") AS achats_filtres");
$stmtCount->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmtCount->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmtCount->bindValue(':marchand_filter', $marchandFilter, PDO::PARAM_STR);
$stmtCount->bindValue(':categorie', $filtreCategorie, PDO::PARAM_INT);
$stmtCount->bindValue(':date_filter', $dateDebut, $dateDebut === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtCount->bindValue(':date_filter_fin', $dateFin, $dateFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmtCount->execute();
$totalAchats = (int) ($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$totalPages = ceil($totalAchats / $achatsParPage);

// Calcul propre des bornes pour le compteur d'achats (placé après le calcul de $totalAchats de l'étape 8)
$debutPlageAchats = ($totalAchats > 0) ? $offset + 1 : 0;
$finPlageAchats = min($offset + $achatsParPage, $totalAchats);

// =====================================================
// 9. Liste des achats de la page
// =====================================================
$sqlAchats = "SELECT a.*, SUM(e.montant) AS total_echeances, SUM(CASE WHEN e.statut='payee' THEN e.montant ELSE 0 END) AS total_paye, MIN(CASE WHEN e.statut != 'payee' THEN e.date_echeance END) AS prochaine_echeance FROM achats a LEFT JOIN echeances e ON e.achat_id = a.id AND (:date_filter IS NULL OR e.date_echeance BETWEEN :date_filter AND :date_filter_fin OR a.date_depart BETWEEN :date_filter AND :date_filter_fin) WHERE a.user_id = :user_id AND (:search = '' OR a.titre LIKE :search) AND (:marchand_filter = '' OR a.nom_marchand = :marchand_filter) AND (:categorie = 0 OR a.categorie_id = :categorie OR a.categorie_id IN (SELECT id FROM categories WHERE parent_id = :categorie AND user_id = :user_id)) AND (:date_filter IS NULL OR (a.date_depart BETWEEN :date_filter AND :date_filter_fin OR EXISTS (SELECT 1 FROM echeances e2 WHERE e2.achat_id = a.id AND e2.date_echeance BETWEEN :date_filter AND :date_filter_fin))) GROUP BY a.id " . $statusWhere . " ORDER BY a.date_depart DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sqlAchats);
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
$stmt->bindValue(':marchand_filter', $marchandFilter, PDO::PARAM_STR);
$stmt->bindValue(':categorie', $filtreCategorie, PDO::PARAM_INT);
$stmt->bindValue(':date_filter', $dateDebut, $dateDebut === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmt->bindValue(':date_filter_fin', $dateFin, $dateFin === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
$stmt->bindValue(':limit', $achatsParPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$achatsPage = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!is_array($achatsPage)) {
    $achatsPage = [];
}

// =====================================================
// 10. Liste des marchands pour le filtre
// =====================================================
$stmtM = $pdo->prepare('SELECT DISTINCT nom_marchand FROM achats WHERE user_id = :user_id ORDER BY nom_marchand');
$stmtM->execute([':user_id' => $userId]);
$marchands = $stmtM->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mb-4 nav-dashboard-container">
    <div class="glass-card-nav mt-4 mb-4">

        <div class="d-flex flex-wrap align-items-center mt-3">
        </div>

        <div class="p-3 pt-0">
            <?php
            if (file_exists(DIR_LOGIC . 'top-bar-title-page.php')) {
                include DIR_LOGIC . 'top-bar-title-page.php';
            }
            ?>
        </div>
        <hr class="hr-glass mt-4 mb-4">
        <!-- =====================================================
         Barre d'actions
    ====================================================== -->
        <div class="row g-3 align-items-center mb-4">
            <!-- Retour Dashboard -->
            <div class="col-12 col-md-4 d-flex justify-content-center justify-content-md-start">
                <a href="<?= BASE_URL ?>router.php?p=dashboard_achats.php"
                    class="btn btn-secondary shadow-sm w-100 w-md-auto d-flex align-items-center justify-content-center"
                    title="Retour au tableau de bord Achats" aria-label="Retour au tableau de bord Achats">
                    <span class="emoji fs-4">🛒</span>
                    Retour au Tableau de bord Achats
                </a>
            </div>
            <!-- Export CSV -->
            <div class="col-12 col-md-4 d-flex justify-content-center">
                <a href="<?= BASE_URL ?>router.php?p=export_csv.php&search=<?= urlencode($search) ?>&marchand_filter=<?= urlencode($marchandFilter) ?>&statuts_filter=<?= urlencode($statutsFilter) ?>&categorie=<?= urlencode($filtreCategorie) ?>&date_debut=<?= urlencode($dateDebut ?? '') ?>&date_fin=<?= urlencode($dateFin ?? '') ?>"
                    class="btn-create-dash shadow-sm text-decoration-none w-100 w-md-auto d-flex align-items-center justify-content-center"
                    style="min-height:38px;">
                    <span class="emoji fs-4">
                        💾
                    </span>
                    Exportation CSV
                </a>
            </div>
            <!-- Compteur -->
<div class="col-12 col-md-4">
    <span class="form-label glass-card-nav bg-glass-compteur badge-compteur text-muted align-items-center justify-content-center h-80">
        <span class="emoji fs-4">
            🧾
        </span>
        <?= $debutPlageAchats ?> - <?= $finPlageAchats ?> / <?= $totalAchats ?> achats
                    <span class="info-tooltip ms-1 text-info fs-4">
                        ⓘ
                        <span class="info-tooltip-text">
                            Indique la plage des achats affichés sur la page actuelle par rapport au nombre total d'achats (et leurs
                            226 échéances associées) correspondant aux critères sélectionnés.
                        </span>
                    </span>
                </span>
            </div>
        <hr class="hr-glass mt-4 mb-4">
        <!-- =====================================================
         KPI financiers principaux
         - Total engagé
         - Total réglé
         - Reste à payer
    ====================================================== -->
        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-accent-blue p-3 text-center card-kpi h-80">
                    <div class="text-accent-blue fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">💳</span> Total engagé
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant total des échéances liées aux achats correspondant aux filtres actifs.
                                Calcul : somme de toutes les échéances prises en compte dans la sélection actuelle.
                            </span>
                        </span>
                    </div>
                    <div class="fs-2 fw-800 text-white mt-2">
                        <?= number_format($totalGen, 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-accent-purple p-3 text-center card-kpi h-80">

                    <div class="text-accent-purple fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">✅</span> Total réglé
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant total des échéances déjà payées parmi les achats correspondant aux filtres
                                actifs.
                                Calcul : somme des échéances dont le statut est indiqué comme payée.
                            </span>
                        </span>
                    </div>
                    <div class="fs-2 fw-800 text-white mt-2">
                        <?= number_format($totalPayeGen, 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <?php
                // Calcul du montant restant dû
                $resteGlobal = $totalGen - $totalPayeGen;
                ?>
                <div class="form-label glass-card-nav border-danger p-3 text-center card-kpi h-80">
                    <div class="text-danger fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">💰</span> Reste à payer
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Montant restant dû après déduction des paiements déjà effectués.
                                Calcul : Total engagé moins Total réglé.
                            </span>
                        </span>
                    </div>
                    <div class="fs-2 fw-800 text-white mt-2">
                        <?= number_format($resteGlobal, 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>
        </div>
        <hr class="hr-glass mt-4 mb-4">
        <!-- =====================================================
     KPI complémentaires
     - Prochaine échéance
     - Échéances en retard
     - Achats actifs
====================================================== -->
        <div class="row g-3 mb-5">
            <!-- Prochaine échéance -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-accent-blue p-3 text-center card-kpi h-80">
                    <div class="text-accent-blue fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">📅</span> Prochaine échéance
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Affiche la prochaine échéance prévue parmi les achats correspondant aux filtres actifs.
                                Calcul : montant de la prochaine échéance avec sa date et les informations associées à
                                l'achat concerné.
                            </span>
                        </span>
                    </div>
                    <?php if ($prochaineEcheance): ?>
                        <div class="fs-2 fw-800 text-white mt-2">
                            <?= number_format($prochaineEcheance['montant'], 2, ',', ' ') ?> €
                        </div>
                        <div class="text-white-50 small mt-3">
                            <?= htmlspecialchars($prochaineEcheance['titre']) ?>
                            -
                            <?= htmlspecialchars($prochaineEcheance['nom_marchand']) ?>
                            -
                            <?= date('d/m/Y', strtotime($prochaineEcheance['date_echeance'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="fs-2 fw-800 text-white mt-2">
                            Aucune
                        </div>
                        <div class="text-white-50 small mt-3">
                            <?php if ($filtreCategorie || $marchandFilter || $search || $dateDebut || $dateFin): ?>
                                échéance correspondant aux filtres actifs
                            <?php else: ?>
                                échéance à venir
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Échéances en retard -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-danger p-3 text-center card-kpi h-80">
                    <div class="text-danger fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">⚠️</span> Échéances en retard
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Indique le nombre d'échéances dont la date prévue est dépassée.
                                Calcul : nombre d'échéances en retard et montant total restant associé.
                            </span>
                        </span>
                    </div>
                    <div class="fs-2 fw-800 text-white mt-2">
                        <?= $nbRetards ?>
                    </div>
                    <div class="text-white-50 small mt-3">
                        <?= number_format($montantRetard, 2, ',', ' ') ?> €
                    </div>
                </div>
            </div>
            <!-- Achats actifs -->
            <div class="col-md-4">
                <div class="form-label glass-card-nav border-accent-purple p-3 text-center card-kpi h-80">
                    <div class="text-accent-purple fw-bold uppercase-tracking fs-5">
                        <span class="emoji fs-4">🛒</span> Achats actifs
                        <span class="info-tooltip ms-1 text-info fs-4">
                            ⓘ
                            <span class="info-tooltip-text">
                                Nombre d'achats ayant encore des échéances à venir ou des paiements restant à effectuer.
                                Calcul : comptabilisation des achats associés à des échéances restantes.
                            </span>
                        </span>
                    </div>
                    <div class="fs-2 fw-800 text-white mt-2">
                        <?= $nbAchatsActifs ?>
                    </div>
                    <div class="text-white-50 small mt-3">
                        avec échéances restantes
                    </div>
                </div>
            </div>
        </div>
        <!-- =====================================================
         Suivi global du remboursement
         - Pourcentage payé
         - Barre de progression
    ====================================================== -->
        <div class="form-label glass-card-nav p-3 mb-4 border-0" style="border-radius:30px;">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="glass-header-small mb-0">
                    <span class="emoji" style="text-shadow:0 0 10px rgba(108,92,231,.5);">
                        🚀
                    </span>
                    Progression des remboursements
                    <span class="info-tooltip ms-1 text-info">
                        ⓘ
                        <span class="info-tooltip-text">
                            Indique l'avancement global du remboursement des achats suivis.
                            Calcul : montant total déjà réglé divisé par le montant total engagé, exprimé en
                            pourcentage.
                        </span>
                    </span>
                </h2>
                <span class="badge-neon badge-neon-purple badge-percent text-accent-purple fw-bold fs-5">
                    <?= number_format($pourcentageGlobal, 1, ',', ' ') ?> %
                </span>
            </div>
            <div class="progress progress-custom-dark">
                <div class="progress-bar progress-bar-neon bg-neon-purple" role="progressbar"
                    style="width:<?= $pourcentageGlobal ?>%" aria-valuenow="<?= $pourcentageGlobal ?>" aria-valuemin="0"
                    aria-valuemax="100">
                </div>
            </div>
        </div>

        <!-- =====================================================
     Formulaire des filtres
====================================================== -->
        <form method="GET" action="router.php" class="card glass-card-nav p-3 mb-4 border-0">
            <input type="hidden" name="p" value="historique.php">
            <div class="row g-3">
                <!-- Recherche -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">🔍</span> Rechercher
                    </label>
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary shadow-sm"
                        placeholder="Titre..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <!-- Marchand -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">🏪</span> Marchand
                    </label>
                    <select name="marchand_filter" class="form-select bg-dark text-white border-secondary shadow-sm">
                        <option value="">
                            Tous
                        </option>
                        <?php foreach ($marchands as $m): ?>
                            <option value="<?= htmlspecialchars($m['nom_marchand']) ?>"
                                <?= ($marchandFilter === $m['nom_marchand']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nom_marchand']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Catégorie -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📁</span> Catégorie
                    </label>
                    <select name="categorie" class="form-select bg-dark text-white border-secondary shadow-sm">
                        <option value="">
                            Toutes les catégories
                        </option>
                        <?php
                        $catStmt = $pdo->prepare('
                    SELECT id, nom, parent_id
                    FROM categories
                    WHERE user_id = ?
                    ORDER BY
                        COALESCE(parent_id, id),
                        parent_id IS NOT NULL,
                        nom ASC
                ');
                        $catStmt->execute([$user_id]);
                        foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $c):
                            $nomCategorie = !empty($c['parent_id'])
                                ? '└── 🏷️ ' . $c['nom']
                                : '📁 ' . $c['nom'];
                            ?>
                            <option value="<?= $c['id'] ?>" <?= ($filtreCategorie == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomCategorie) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Statut -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📌</span> Statut
                    </label>
                    <select name="statuts_filter" class="form-select bg-dark text-white border-secondary shadow-sm">
                        <option value="" <?= $statutsFilter == '' ? 'selected' : '' ?>>
                            Tous les statuts
                        </option>
                        <option value="a_venir" <?= $statutsFilter == 'a_venir' ? 'selected' : '' ?>>
                            📅 À venir
                        </option>
                        <option value="en_retard" <?= $statutsFilter == 'en_retard' ? 'selected' : '' ?>>
                            ⚠️ En retard
                        </option>
                        <option value="solde" <?= $statutsFilter == 'solde' ? 'selected' : '' ?>>
                            ✅ Soldé
                        </option>
                    </select>
                </div>
                <!-- Date début -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📅</span> Du
                    </label>
                    <input type="date" name="date_debut"
                        class="form-control bg-dark text-white border-secondary shadow-sm"
                        value="<?= htmlspecialchars($dateDebut ?? '') ?>">
                </div>
                <!-- Date fin -->
                <div class="col-md-2">
                    <label class="text-white small mb-1">
                        <span class="emoji fs-4">📅</span> Au
                    </label>
                    <input type="date" name="date_fin"
                        class="form-control bg-dark text-white border-secondary shadow-sm"
                        value="<?= htmlspecialchars($dateFin ?? '') ?>">
                </div>
            </div>
            <!-- Boutons -->
            <div class="row mt-3">
                <div class="col d-flex gap-2">
                    <button type="submit" class="btn-create-dash shadow-sm flex-fill justify-content-center">
                        <span class="emoji fs-4">🔍</span>
                        Filtrer
                    </button>
                    <a href="router.php?p=historique.php"
                        class="btn btn-secondary shadow-sm flex-fill d-flex align-items-center justify-content-center text-decoration-none">
                        <span class="emoji fs-4">🔄</span>
                        Effacer
                    </a>
                </div>
            </div>
        </form>
        <!-- =====================================================
         Tableau des badges achats
    ====================================================== -->
        <div class="table-responsive">
            <table class="table table-custom-dark align-middle">
                <thead>
                    <tr>
                        <th class="th-title text-start">
                            TITRE / MARCHAND
                        </th>
                        <th class="th-date text-center">
                            DATE
                        </th>
                        <th class="th-amount text-end">
                            TOTAL
                        </th>
                        <th class="th-amount text-end">
                            PAYÉ
                        </th>
                        <th class="th-progress text-center">
                            PROGRESSION
                        </th>
                        <th class="th-amount text-end">
                            RESTE
                        </th>
                        <th class="th-state text-center">
                            ÉTAT
                        </th>
                        <th class="th-date text-center">
                            PROCHAINE
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($achatsPage)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                Aucun achat trouvé.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($achatsPage as $achat): ?>
                            <?php
                            $totalEcheances = (float) ($achat['total_echeances'] ?? 0);
                            $totalPaye = (float) ($achat['total_paye'] ?? 0);
                            $reste = round(
                                $totalEcheances - $totalPaye,
                                2
                            );
                            $pct = ($totalEcheances > 0)
                                ? ($totalPaye / $totalEcheances) * 100
                                : 0;
                            $estEnRetard = (
                                $achat['prochaine_echeance'] &&
                                $achat['prochaine_echeance'] < $aujourdhui &&
                                $reste > 0
                            );
                            $classeBordure = ($reste <= 0)
                                ? 'border-solde'
                                : (
                                    $estEnRetard
                                    ? 'border-en-retard'
                                    : 'border-a-venir'
                                );
                            if ($reste <= 0) {
                                $textStatusClass = 'text-solde';
                            } elseif ($estEnRetard) {
                                $textStatusClass = 'text-retard';
                            } else {
                                $textStatusClass = 'text-a-venir';
                            }
                            ?>
                            <tr class="hover-glow">
                                <td class="td-title <?= $classeBordure ?>" data-label="Achat">
                                    <div class="d-flex flex-column align-items-start w-100">
                                        <div class="fw-bold text-white mb-1">
                                            <?= htmlspecialchars($achat['titre']) ?>
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="router.php?p=marchands.php&marchand_filter=<?= urlencode((string) $achat['nom_marchand']) ?>"
                                                class="badge-neon badge-marchand text-decoration-none">
                                                <span class="emoji fs-4">🏪</span>
                                                <?= htmlspecialchars($achat['nom_marchand']) ?>
                                            </a>
                                            <a href="router.php?p=echeancier.php&id=<?= $achat['id'] ?>"
                                                class="badge-neon badge-echeancier text-decoration-none">
                                                <span class="emoji fs-4">📅</span> Échéancier
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="td-date text-center" data-label="Date">
                                    <span class="text-muted">
                                        <?= date('d/m/Y', strtotime($achat['date_depart'])) ?>
                                    </span>
                                </td>
                                <td class="td-amount text-end fw-bold text-white" data-label="Total">
                                    <?= number_format($achat['total_echeances'], 2, ',', ' ') ?> €
                                </td>
                                <td class="td-amount text-end text-white" data-label="Payé">
                                    <?= number_format($achat['total_paye'], 2, ',', ' ') ?> €
                                </td>
                                <td class="td-progress text-center" data-label="Progression">
                                    <div class="progress progress-table">
                                        <div class="progress-bar bg-gradient-blue"
                                            style="width: <?= min(100, max(0, $pct)) ?>%">
                                        </div>
                                    </div>
                                    <div class="progress-label">
                                        <?= round($pct) ?>%
                                    </div>
                                </td>
                                <td class="td-amount text-end fw-bold <?= $textStatusClass ?>" data-label="Reste">
                                    <?php if ($estEnRetard): ?>
                                        <span class="emoji-warning">
                                            <span class="emoji fs-4">🚨</span>
                                        </span>
                                    <?php endif; ?>
                                    <?= number_format($reste, 2, ',', ' ') ?> €
                                </td>
                                <td class="td-state text-center" data-label="État">
                                    <?php if ($reste <= 0): ?>
                                        <span class="badge-neon badge-solde">
                                            SOLDÉ
                                        </span>
                                    <?php elseif ($estEnRetard): ?>
                                        <span class="badge-neon badge-en-retard">
                                            RETARD
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-neon badge-a-venir">
                                            EN COURS
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="td-date text-center <?= $textStatusClass ?>" data-label="Prochaine">
                                    <?= $achat['prochaine_echeance']
                                        ? date('d/m/Y', strtotime($achat['prochaine_echeance']))
                                        : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<!-- =====================================================
        Pagination
    ====================================================== -->
<?php if ($totalPages > 1): ?>
    <nav class="d-flex justify-content-center mt-4">
        <ul class="pagination pagination-sm">
            <!-- Première page -->
            <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= afficherPage(1) ?>" title="Première page"
                    aria-label="Aller à la première page">
                    «
                </a>
            </li>
            <!-- Page précédente -->
            <li class="page-item <?= ($pageCourante <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= afficherPage(max(1, $pageCourante - 1)) ?>" title="Page précédente"
                    aria-label="Aller à la page précédente">
                    ‹
                </a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= ($i == $pageCourante ? 'active' : '') ?>">
                    <a class="page-link" href="<?= afficherPage($i) ?>" title="Aller à la page <?= $i ?>"
                        aria-label="Aller à la page <?= $i ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor; ?>
            <!-- Page suivante -->
            <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= afficherPage(min($totalPages, $pageCourante + 1)) ?>" title="Page suivante"
                    aria-label="Aller à la page suivante">
                    ›
                </a>
            </li>
            <!-- Dernière page -->
            <li class="page-item <?= ($pageCourante >= $totalPages) ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= afficherPage($totalPages) ?>" title="Dernière page"
                    aria-label="Aller à la dernière page">
                    »
                </a>
            </li>
        </ul>
    </nav>
    <div class="text-center text-muted small mt-2">
        Page <?= $pageCourante ?> sur <?= $totalPages ?>
    </div>
<?php endif; ?>
</div>